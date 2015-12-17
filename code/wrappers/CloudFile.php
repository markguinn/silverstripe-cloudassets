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
    public function Link()
    {
        $this->createLocalIfNeeded();
        return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::Link();
    }

    public function RelativeLink()
    {
        $this->createLocalIfNeeded();
        return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::RelativeLink();
    }

    public function getURL()
    {
        $this->createLocalIfNeeded();
        return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::getURL();
    }

    public function getAbsoluteURL()
    {
        $this->createLocalIfNeeded();
        return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::getAbsoluteURL();
    }

    public function getAbsoluteSize()
    {
        $this->createLocalIfNeeded();
        return $this->CloudStatus == 'Live' ? $this->CloudSize : parent::getAbsoluteSize();
    }

    public function exists()
    {
        $this->createLocalIfNeeded();
        return parent::exists();
    }
}
