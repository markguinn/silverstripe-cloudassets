<?php
/**
 * Wraps Image_Cached. This one we have to be a little more
 * careful with because we don't keep a database record.
 *
 * NOTE: An Image_Cached can never actually be converted to
 * one of these because it's not in the db. It must be created
 * as this class (see CloudImage::getFormattedImage).
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 01.13.2014
 * @package cloudassets
 * @subpackage wrappers
 */
class CloudImageCached extends CloudImage
{
    /** @var CloudImageCachedStore */
    protected $storeRecord;

    /**
     * Create a new cached image.
     * @param string $filename The filename of the image.
     * @param boolean $isSingleton This this to true if this is a singleton() object, a stub for calling methods.
     *                             Singletons don't have their defaults set.
     */
    public function __construct($filename = null, $isSingleton = false)
    {
        parent::__construct(array(), $isSingleton);
        $this->ID = -1;
        $this->Filename = $filename;

        // this covers the case where the image already exists in the cloud from a previous call
        if (file_exists($this->getFullPath()) && $this->containsPlaceholder()) {
            $this->CloudStatus = 'Live';
        }
    }


    /**
     * @return String
     */
    public function getRelativePath()
    {
        return $this->getField('Filename');
    }


    /**
     * Prevent creating new tables for the cached record
     *
     * @return false
     */
    public function requireTable()
    {
        return false;
    }


    /**
     * Prevent writing the cached image to the database, but write the store record instead
     */
    public function write($showDebug = false, $forceInsert = false, $forceWrite = false, $writeComponents = false)
    {
        //throw new Exception("{$this->ClassName} can not be written back to the database.");
        // NOTE: we need to fail silently on writes because writing is part of the cloud upload process
        if ($this->storeRecord) {
            $this->storeRecord->write($showDebug, $forceInsert, $forceWrite, $writeComponents);
        }
    }


    /**
     * Simulates a delete
     */
    public function delete()
    {
        $this->brokenOnDelete = true;
        $this->onBeforeDelete();
        if ($this->brokenOnDelete) {
            user_error("$this->class has a broken onBeforeDelete() function."
            . " Make sure that you call parent::onBeforeDelete().", E_USER_ERROR);
        }

        $path = $this->getFullPath();
        if (file_exists($path)) {
            unlink($path);
        }
        if ($this->storeRecord) {
            $this->storeRecord->delete();
        }

        $this->flushCache();
        $this->onAfterDelete();
    }


    /**
     * @param CloudImageCachedStore $store
     * @return $this
     */
    public function setStoreRecord(CloudImageCachedStore $store)
    {
        $this->storeRecord    = $store;
        $this->CloudStatus   = $store->CloudStatus;
        $this->CloudSize     = $store->CloudSize;
        $this->CloudMetaJson = $store->CloudMetaJson;
        return $this;
    }


    /**
     * @return CloudImageCachedStore
     */
    public function getStoreRecord()
    {
        return $this->storeRecord;
    }


    /**
     * @param $val
     */
    public function setCloudMetaJson($val)
    {
        $this->setField('CloudMetaJson', $val);
        if ($this->storeRecord) {
            $this->storeRecord->CloudMetaJson = $val;
            //$this->storeRecord->write();
        }
    }


    /**
     * @param $val
     */
    public function setCloudStatus($val)
    {
        $this->setField('CloudStatus', $val);
        if ($this->storeRecord) {
            $this->storeRecord->CloudStatus = $val;
            //$this->storeRecord->write();
        }
    }


    /**
     * @param $val
     */
    public function setCloudSize($val)
    {
        $this->setField('CloudSize', $val);
        if ($this->storeRecord) {
            $this->storeRecord->CloudSize = $val;
            //$this->storeRecord->write();
        }
    }
}
