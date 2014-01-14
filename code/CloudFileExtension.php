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
		if ($bucket) {
			if ($this->owner->hasMethod('onBeforeCloudDelete')) $this->owner->onBeforeCloudDelete();
			$bucket->delete($this->owner);
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
					$this->owner->ClassName = $wrapClass;
					$this->owner->write();
					$wrapped = DataObject::get($wrapClass)->byID($this->owner->ID);
					if ($wrapped->hasMethod('onAfterCloudWrap')) $wrapped->onAfterCloudWrap();
				} else {
					$wrapped = $this->owner;
				}

				// does this file need to be uploaded to storage
				if ($wrapped->canBeInCloud() && !$wrapped->containsPlaceholder()) {
					try {
						if ($wrapped->hasMethod('onBeforeCloudPut')) $wrapped->onBeforeCloudPut();
						$bucket->put($wrapped);

						$wrapped->CloudStatus = 'Live';
						$wrapped->CloudSize   = filesize($this->owner->getFullPath());
						$wrapped->write();

						$wrapped->convertToPlaceholder();
						if ($wrapped->hasMethod('onAfterCloudPut')) $wrapped->onAfterCloudPut();
					} catch(Exception $e) {
						$wrapped->CloudStatus = 'Error';
						$wrapped->write();

						if (Director::isDev()) {
							Debug::log("Failed bucket upload: " . $e->getMessage() . " for " . $wrapped->getFullPath());
						} else {
							// Fail silently for now. This will cause the local copy to be served.
						}
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
		return (filesize($path) == strlen($placeholder) && file_get_contents($path) == $placeholder);
	}


	/**
	 * Wipes out the contents of this file and replaces with placeholder text
	 */
	public function convertToPlaceholder() {
		file_put_contents($this->owner->getFullPath(), Config::inst()->get('CloudAssets', 'file_placeholder'));
		return $this->owner;
	}


	/**
	 * @return CloudBucket
	 */
	public function getCloudBucket() {
		return CloudAssets::inst()->map($this->owner);
	}


	/**
	 * @return string
	 */
	public function getCloudURL() {
		$bucket = $this->getCloudBucket();
		return $bucket ? $bucket->getLinkFor($this->owner) : '';
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
			$contents = $bucket->getContents($this->owner);
			file_put_contents($this->owner->getFullPath(), $contents);
		}
	}


	/**
	 * If the file is present in the database and the cloud but not
	 * locally, create a placeholder for it. This can happen in a lot
	 * of cases such as load balanced servers and local development.
	 */
	public function createLocalIfNeeded() {
		if ($this->owner->CloudStatus === 'Live' && !file_exists($this->owner->getFullPath())) {
			$this->convertToPlaceholder();
		}
	}
}