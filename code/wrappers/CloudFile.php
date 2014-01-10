<?php
/**
 * Wraps the file object. I hate having to do it this way but I
 * can't think of a better way. Extensions aren't able to override
 * core class methods (such as Link).
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 01.10.2014
 * @package cloudassets
 * @subpackage wrappers
 */
class CloudFile extends File implements CloudAssetInterface
{
	function Link() {
		return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::Link();
	}

	function RelativeLink() {
		return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::RelativeLink();
	}

	function getURL() {
		return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::getURL();
	}

	function getAbsoluteURL() {
		return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::getAbsoluteURL();
	}

	function getAbsoluteSize() {
		return $this->CloudStatus == 'Live' ? $this->CloudSize : parent::getAbsoluteSize();
	}
}