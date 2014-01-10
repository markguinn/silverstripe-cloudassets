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
	);

	private $inAfterWrite = false;


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
			$bucket->rename($this->owner, $pathBefore, $pathAfter);
		}
	}


	/**
	 * Update cloud status any time the file is written
	 */
	public function onAfterWrite() {
		if (!$this->inAfterWrite) {
			$this->inAfterWrite = true;
			$this->updateCloudStatus();
			$this->inAfterWrite = false;
		}
	}


	/**
	 * Delete the file from the cloud (if it was ever there)
	 */
	public function onAfterDelete() {
		$bucket = CloudAssets::inst()->map($this->owner->getFilename());
		if ($bucket) $bucket->delete($this->owner);
	}


	/**
	 * Performs two functions:
	 * 1. Wraps this object in CloudFile (etc) by changing the classname if it should be and is not
	 * 2. Uploads the file to the cloud storage if it doesn't contain the placeholder
	 *
	 * @return File
	 */
	public function updateCloudStatus() {
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
				} else {
					$wrapped = $this->owner;
				}

				// does this file need to be uploaded to storage
				if ($this->canBeInCloud() && !$this->containsPlaceholder()) {
					try {
						$bucket->put($this->owner);

						$wrapped->CloudStatus = 'Live';
						$wrapped->CloudSize   = filesize($this->owner->getFullPath());
						$wrapped->write();

						file_put_contents($this->owner->getFullPath(), Config::inst()->get('CloudAssets', 'file_placeholder'));
					} catch(Exception $e) {
						$wrapped->CloudStatus = 'Error';
						$wrapped->write();

						if (Director::isDev()) {
							Debug::log("Failed bucket upload: " . $e->getMessage() . " for " . $this->owner->getFullPath());
						} else {
							// Fail silently for now. This will cause the local copy to be served.
						}
					}
				}

				return $wrapped;
			}
		}

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
}