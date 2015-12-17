<?php
/**
 * Interface for extending File or one of it's subclasses with cloud
 * support. I'm not sure this is needed or not. It does allow you to
 * easily check if a file is wrapped ($f implements CloudAssetInterface)
 * but doesn't do much more.
 *
 * To create a new wrapper just extend the class you want to wrap, implement
 * this interface and copy (uncommented of course) the code below.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 01.10.2014
 * @package cloudassets
 */
interface CloudAssetInterface
{
    //	public function Link() {
//		$this->createLocalIfNeeded();
//		return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::Link();
//	}
//
//	public function RelativeLink() {
//		$this->createLocalIfNeeded();
//		return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::RelativeLink();
//	}
//
//	public function getURL() {
//		$this->createLocalIfNeeded();
//		return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::getURL();
//	}
//
//	public function getAbsoluteURL() {
//		$this->createLocalIfNeeded();
//		return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::getAbsoluteURL();
//	}
//
//	public function getAbsoluteSize() {
//		$this->createLocalIfNeeded();
//		return $this->CloudStatus == 'Live' ? $this->CloudSize : parent::getAbsoluteSize();
//	}
//
//	public function exists() {
//		$this->createLocalIfNeeded();
//		return parent::exists();
//	}
}
