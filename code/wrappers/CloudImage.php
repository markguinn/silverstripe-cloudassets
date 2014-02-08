<?php
/**
 * Wraps the Image object. We have to replace a few more methods
 * with Image because things like formatting and dimensions don't
 * work the same with a placeholder file.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 01.10.2014
 * @package cloudassets
 * @subpackage wrappers
 */
class CloudImage extends Image implements CloudAssetInterface
{
	private static $has_many = array(
		'DerivedImages' => 'CloudImageCachedStore',
	);


	public function Link() {
		$this->createLocalIfNeeded();
		return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::Link();
	}

	public function RelativeLink() {
		$this->createLocalIfNeeded();
		return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::RelativeLink();
	}

	public function getURL() {
		$this->createLocalIfNeeded();
		return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::getURL();
	}

	public function getAbsoluteURL() {
		$this->createLocalIfNeeded();
		return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::getAbsoluteURL();
	}

	public function getAbsoluteSize() {
		$this->createLocalIfNeeded();
		return $this->CloudStatus == 'Live' ? $this->CloudSize : parent::getAbsoluteSize();
	}

	public function exists() {
		$this->createLocalIfNeeded();
		return parent::exists();
	}


	/**
	 * Save the dimensions before we potentially wipe out the file
	 */
	function onBeforeCloudPut() {
		$this->setCloudMeta('Dimensions', $this->getDimensions('live'));
	}


	/**
	 * @param string $dim - 'string' or 0 (width) or 1 (height)
	 * @return int|string
	 */
	function getDimensions($dim = "string") {
		// give an option to get the real dimensions
		if ($dim === 'live') return parent::getDimensions();
		if ($this->CloudStatus != 'Live') return parent::getDimensions($dim);

		// otherwise we need to resort to stored dimensions because the
		// file may be in the cloud and the local may be a placeholder
		$val = $this->getCloudMeta('Dimensions');
		if (empty($val)) {
			$this->downloadFromCloud();
			$val = parent::getDimensions('string');
			$this->convertToPlaceholder();
		}

		if ($dim === 'string') return $val;

		$val = explode('x', $val);
		return $val[$dim];
	}


	/**
	 * Return an image object representing the image in the given format.
	 * This image will be generated using generateFormattedImage().
	 * The generated image is cached, to flush the cache append ?flush=1 to your URL.
	 *
	 * Just pass the correct number of parameters expected by the working function
	 *
	 * @param string $format The name of the format.
	 * @return CloudImageCached|null
	 */
	public function getFormattedImage($format) {
		$args = func_get_args();

		if($this->ID && $this->Filename && Director::fileExists($this->Filename)) {
			$cacheFile = call_user_func_array(array($this, "cacheFilename"), $args);
			$cachePath = Director::baseFolder()."/".$cacheFile;

			$stored = CloudImageCachedStore::get()->filter('Filename', $cacheFile)->first();
			if ($stored && !$stored->exists()) $stored = null;

			// If ?flush is present, always wipe existing data and start clean
			if (isset($_GET['flush'])) {
				// There was a bug here caused by the fact that GDBackend tries
				// to read the size off the cached image, which would be a placeholder
				// in certain cases.
				// I'm not 100% sure what the correct behaviour is here. For now
				// we'll destroy the existing image, causing it to be re-uploaded
				// every time. That seems safer if a little bit wasteful.
				if (file_exists($cachePath)) unlink($cachePath);

				// delete the existing meta if it existed
				if ($stored) {
					$stored->delete();
					$stored = null;
				}
			}

			// start building out the record
			$cached = new CloudImageCached($cacheFile);
			$cached->Title = $this->Title;
			$cached->ParentID = $this->ParentID;

			// Is there a meta record for this formatted file?
			if ($stored) {
				// Has it been successfully uploaded to the cloud?
				// If so, we can just send this puppy on
				// If not, is there a local file that's present and correct?
				// If not, we need to wipe the meta and anything local and regenerate
				if ($stored->CloudStatus !== 'Live' && $cached->isLocalMissing()) {
					$stored->delete();
					$stored = null;
					if (file_exists($cachePath)) unlink($cachePath);
				} else {
					$cached->setStoreRecord($stored);
				}
			}

			// If there is no meta record (or an invalid one), is there a local file or placeholder?
			if (!$stored) {
				// if the local exists as a placeholder, we need to check if the cloud version is valid
				if (file_exists($cachePath) && $cached->containsPlaceholder()) {
					try {
						$cached->downloadFromCloud();
					} catch (Exception $e) {
						// We want to fail silently here if there is any trouble
						// because we can always regenerate the thumbnail
					}
				}

				// If we don't have a valid local version at this point...
				if ($cached->isLocalMissing()) {
					// delete whatever might have been there
					if (file_exists($cachePath)) unlink($cachePath);

					// Regenerate the formatted image
					if ($this->CloudStatus === 'Live' && $this->isLocalMissing()) {
						$this->downloadFromCloud();
						call_user_func_array(array($this, "generateFormattedImage"), $args);
						$this->convertToPlaceholder();
					} else {
						call_user_func_array(array($this, "generateFormattedImage"), $args);
					}
				}

				// If we now have a valid image, generate a stored meta record for it
				if (file_exists($cachePath)) {
					$stored = new CloudImageCachedStore();
					$stored->Filename = $cacheFile;
					$stored->SourceID = $this->ID;
					$stored->write();
					// all the other fields will get set when the cloud status is updated
					$cached->setStoreRecord($stored);
				}
			}

			// upload to cloud if needed
			$cached->updateCloudStatus();

			return $cached;
		}
	}


	/**
	 * Checks if the local file is an image that can be used and not
	 * a placeholder or a corrupted file.
	 *
	 * @return bool
	 */
	public function isLocalValid() {
		$path = $this->getFullPath();
		if (!file_exists($path)) return false;
		return (getimagesize($path) !== false);
	}


	/**
	 * @return int The number of formatted images deleted
	 */
	public function deleteFormattedImages() {
		foreach ($this->DerivedImages() as $store) {
			$img = $store->getCloudImageCached();
			$img->delete();
		}

		// The above should have covered everything but this will clean up any
		// loose ends that were maybe created before wrapping or fell through a crack
		parent::deleteFormattedImages();
	}

}