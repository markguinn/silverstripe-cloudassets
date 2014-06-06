<?php
/**
 * Subclass of Upload controller to return wrapped files.
 * This fixes a bug where thumbnail creation was failing
 * immediately after initial upload because UploadFile still
 * had a reference to the original Image object.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 06.05.2014
 * @package cloudassets
 */
class CloudAwareUpload extends Upload
{
	/**
	 * Get file-object, either generated from {load()},
	 * or manually set.
	 *
	 * @return File
	 */
	public function getFile() {
		if ($this->file && get_class($this->file) != $this->file->ClassName) {
			// If the class is Image and the ClassName says it should be a CloudImage, just reload it
			$this->file = DataObject::get($this->file->ClassName)->byID($this->file->ID);
		}

		return $this->file;
	}
}