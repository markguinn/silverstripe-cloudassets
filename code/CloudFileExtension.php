<?php
/**
 * 
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 01.10.2014
 * @package cloudassets
 */
class CloudFileExtension extends DataExtension
{
	private static $db = array(
		'CloudStatus'   => "Enum('Local,Live,Error','Local')",
		'CloudSize'     => 'Int',
		'CloudMetaJson' => 'Text',      // saves any bucket or file-type specific information
	);


	private $inUpdate = false;


	/**
	 * Handle renames
	 */
	public function onBeforeWrite() {
		$bucket = CloudAssets::inst()->map($this->owner->getFilename());
		if ($bucket) {
			if(!$this->owner->isChanged('Filename')) return;

			$changedFields = $this->owner->getChangedFields();
			$pathBefore = $changedFields['Filename']['before'];
			$pathAfter = $changedFields['Filename']['after'];

			// If the file or folder didn't exist before, don't rename - its created
			if(!$pathBefore) return;

			// Tell the remote to rename the file (or delete and recreate or whatever)
			if ($this->owner->hasMethod('onBeforeCloudRename')) $this->owner->onAfterCloudRename($pathBefore, $pathAfter);
			CloudAssets::inst()->getLogger()->info("CloudAssets: Renaming $pathBefore to $pathAfter");
			$bucket->rename($this->owner, $pathBefore, $pathAfter);
			if ($this->owner->hasMethod('onAfterCloudRename')) $this->owner->onAfterCloudRename($pathBefore, $pathAfter);
		}
	}


	/**
	 * Update cloud status any time the file is written
	 */
	public function onAfterWrite() {
		$this->updateCloudStatus();
	}


	/**
	 * Delete the file from the cloud (if it was ever there)
	 */
	public function onAfterDelete() {
		$bucket = CloudAssets::inst()->map($this->owner->getFilename());
		if ($bucket && !Config::inst()->get('CloudAssets', 'uploads_disabled')) {
			if ($this->owner->hasMethod('onBeforeCloudDelete')) $this->owner->onBeforeCloudDelete();

			try {
				CloudAssets::inst()->getLogger()->info("CloudAssets: deleting {$this->owner->getFilename()}");
				$bucket->delete($this->owner);
			} catch(Exception $e) {
				CloudAssets::inst()->getLogger()->errror("CloudAssets: Failed bucket delete: " . $e->getMessage() . " for " . $this->owner->getFullPath());
			}

			if ($this->owner->hasMethod('onAfterCloudDelete')) $this->owner->onAfterCloudDelete();
		}
	}


	/**
	 * Performs two functions:
	 * 1. Wraps this object in CloudFile (etc) by changing the classname if it should be and is not
	 * 2. Uploads the file to the cloud storage if it doesn't contain the placeholder
	 *
	 * @return File
	 */
	public function updateCloudStatus() {
		if ($this->inUpdate) return;
		$this->inUpdate = true;
		$cloud  = CloudAssets::inst();

		// does this file fall under a cloud bucket?
		$bucket = $cloud->map($this->owner->getFilename());
		if ($bucket) {
			// does this file need to be wrapped?
			$wrapClass = $cloud->getWrapperClass($this->owner->ClassName);
			if (!empty($wrapClass)) {
				if ($wrapClass != $this->owner->ClassName) {
					$cloud->getLogger()->debug("CloudAssets: wrapping {$this->owner->ClassName} to $wrapClass. ID={$this->owner->ID}");
					$this->owner->ClassName = $wrapClass;
					$this->owner->write();
					$wrapped = DataObject::get($wrapClass)->byID($this->owner->ID);
					if ($wrapped->hasMethod('onAfterCloudWrap')) $wrapped->onAfterCloudWrap();
				} else {
					$wrapped = $this->owner;
				}

				// does this file need to be uploaded to storage?
				if ($wrapped->canBeInCloud() && $wrapped->isCloudPutNeeded() && !Config::inst()->get('CloudAssets', 'uploads_disabled')) {
					try {
						if ($wrapped->hasMethod('onBeforeCloudPut')) $wrapped->onBeforeCloudPut();
						$cloud->getLogger()->debug("CloudAssets: uploading file ".$wrapped->getFilename());
						$bucket->put($wrapped);

						$wrapped->setCloudMeta('LastPut', time());
						$wrapped->CloudStatus = 'Live';
						$wrapped->CloudSize   = filesize($this->owner->getFullPath());
						$wrapped->write();

						$wrapped->convertToPlaceholder();
						if ($wrapped->hasMethod('onAfterCloudPut')) $wrapped->onAfterCloudPut();
					} catch(Exception $e) {
						$wrapped->CloudStatus = 'Error';
						$wrapped->write();
						$cloud->getLogger()->error("CloudAssets: Failed bucket upload: " . $e->getMessage() . " for " . $wrapped->getFullPath());
						// Fail silently for now. This will cause the local copy to be served.
					}
				} elseif ($wrapped->CloudStatus !== 'Live' && $wrapped->containsPlaceholder()) {
					// If this is a duplicate file, update the status
					// This shouldn't happen ever and won't happen often but when it does this will be helpful
					$dup = File::get()->filter(array(
						'Filename'      => $wrapped->Filename,
						'CloudStatus'   => 'Live',
					))->first();

					if ($dup && $dup->exists()) {
						$cloud->getLogger()->warn("CloudAssets: fixing status for duplicate file: {$wrapped->ID} and {$dup->ID}");
						$wrapped->CloudStatus   = $dup->CloudStatus;
						$wrapped->CloudSize     = $dup->CloudSize;
						$wrapped->CloudMetaJson = $dup->CloudMetaJson;
						$wrapped->write();
					}
				}

				$this->inUpdate = false;
				return $wrapped;
			}
		}

		$this->inUpdate = false;
		return $this->owner;
	}


	/**
	 * @return bool
	 */
	public function canBeInCloud() {
		if ($this->owner instanceof Folder) return false;
		if (!file_exists($this->owner->getFullPath())) return false;
		return true;
	}


	/**
	 * @return bool
	 */
	public function containsPlaceholder() {
		$placeholder = Config::inst()->get('CloudAssets', 'file_placeholder');
		$path = $this->owner->getFullPath();

		// check the size first to avoid reading crazy huge files into memory
		return (file_exists($path) && filesize($path) == strlen($placeholder) && file_get_contents($path) == $placeholder);
	}


	/**
	 * Wipes out the contents of this file and replaces with placeholder text
	 */
	public function convertToPlaceholder() {
		$bucket = $this->getCloudBucket();
		if ($bucket && !$bucket->isLocalCopyEnabled()) {
			$path = $this->owner->getFullPath();
			CloudAssets::inst()->getLogger()->debug("CloudAssets: converting $path to placeholder");
			Filesystem::makeFolder(dirname($path));
			file_put_contents($path, Config::inst()->get('CloudAssets', 'file_placeholder'));
		}

		return $this->owner;
	}


	/**
	 * @return CloudBucket
	 */
	public function getCloudBucket() {
		return CloudAssets::inst()->map($this->owner);
	}


	/**
	 * @param int $linkType [optional] - see CloudBucket::LINK_XXX constants
	 * @return string
	 */
	public function getCloudURL($linkType = CloudBucket::LINK_SMART) {
		$bucket = $this->getCloudBucket();
		return $bucket ? $bucket->getLinkFor($this->owner, $linkType) : '';
	}


	/**
	 * @param string $key [optional] - if not present returns the whole array
	 * @return array
	 */
	public function getCloudMeta($key = null) {
		$data = json_decode($this->owner->CloudMetaJson, true);
		if (empty($data) || !is_array($data)) $data = array();

		if (!empty($key)) {
			return isset($data[$key]) ? $data[$key] : null;
		} else {
			return $data;
		}
	}


	/**
	 * @param string|array $key - passing an array as the first argument replaces the meta data entirely
	 * @param mixed        $val
	 * @return File - chainable
	 */
	public function setCloudMeta($key, $val = null) {
		if (is_array($key)) {
			$data = $key;
		} else {
			$data = $this->getCloudMeta();
			$data[$key] = $val;
		}

		$this->owner->CloudMetaJson = json_encode($data);
		return $this->owner;
	}


	/**
	 * If this file is stored in the cloud, downloads the cloud
	 * copy and replaces whatever is local.
	 */
	public function downloadFromCloud() {
		if ($this->owner->CloudStatus === 'Live') {
			$bucket   = $this->owner->getCloudBucket();
			if ($bucket) {
				$contents = $bucket->getContents($this->owner);
				$path     = $this->owner->getFullPath();
				Filesystem::makeFolder(dirname($path));
				CloudAssets::inst()->getLogger()->debug("CloudAssets: downloading $path from cloud (size=".strlen($contents).")");
				// if there was an error and we overwrote the local file with empty or null, it could delete the remote
				// file as well. Better to err on the side of not writing locally when we should than that.
				if (!empty($contents)) file_put_contents($path, $contents);
			}
		}
	}


	/**
	 * If the file is present in the database and the cloud but not
	 * locally, create a placeholder for it. This can happen in a lot
	 * of cases such as load balanced servers and local development.
	 */
	public function createLocalIfNeeded() {
		if ($this->owner->CloudStatus === 'Live') {
			if ($this->getCloudBucket()->isLocalCopyEnabled()) {
				if (!file_exists($this->owner->getFullPath()) || $this->containsPlaceholder()) {
					try {
						$this->downloadFromCloud();
					} catch(Exception $e) {
						// I'm not sure what the correct behaviour is here
						// Pretty sure it'd be better to have a broken image
						// link than a 500 error though.
						CloudAssets::inst()->getLogger()->error("CloudAssets: Failed bucket download: " . $e->getMessage() . " for " . $this->owner->getFullPath());
					}
				}
			} else {
				if (!file_exists($this->owner->getFullPath())) $this->convertToPlaceholder();
			}
		}
	}


	/**
	 * @return bool
	 */
	public function isCloudPutNeeded() {
		// we never want to upload the placeholder
		if ($this->containsPlaceholder()) return false;

		// we never want to upload an empty file
		$path = $this->owner->getFullPath();
		if (!file_exists($path)) return false;

		// we always want to upload if it's the first time
		$lastPut = $this->getCloudMeta('LastPut');
		if (!$lastPut) return true;

		// additionally, we want to upload if the file has been changed or replaced
		$mtime = filemtime($path);
		if ($mtime > $lastPut) return true;

		return false;
	}


	/**
	 * Returns true if the local file is not available
	 * @return bool
	 */
	public function isLocalMissing() {
		return !file_exists($this->owner->getFullPath()) || $this->containsPlaceholder();
	}

}