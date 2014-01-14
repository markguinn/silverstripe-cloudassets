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
class CloudImage_Cached extends CloudImage
{
	private $cloudMeta;

	/**
	 * Create a new cached image.
	 * @param string $filename The filename of the image.
	 * @param boolean $isSingleton This this to true if this is a singleton() object, a stub for calling methods.
	 *                             Singletons don't have their defaults set.
	 */
	public function __construct($filename = null, $isSingleton = false) {
		parent::__construct(array(), $isSingleton);
		$this->ID = -1;
		$this->Filename = $filename;

		// this covers the case where the image already exists in the cloud from a previous call
		if (file_exists($this->getFullPath()) && $this->containsPlaceholder()) $this->CloudStatus = 'Live';
	}

	public function getRelativePath() {
		return $this->getField('Filename');
	}

	/**
	 * Prevent creating new tables for the cached record
	 *
	 * @return false
	 */
	public function requireTable() {
		return false;
	}

	/**
	 * Prevent writing the cached image to the database
	 *
	 * @throws Exception
	 */
	public function write($showDebug = false, $forceInsert = false, $forceWrite = false, $writeComponents = false) {
		//throw new Exception("{$this->ClassName} can not be written back to the database.");
		// NOTE: we need to fail silently on writes because writing is part of the cloud upload process
	}


	/**
	 * @param string $key [optional] - if not present returns the whole array
	 * @return array
	 */
	public function getCloudMeta($key = null) {
		$cache = SS_Cache::factory('CloudImage');
		if (!isset($this->cloudMeta)) {
			$data = $cache->load($this->cloudCacheKey());
			$this->cloudMeta = ($data === false) ? array() : unserialize($data);
		}

		if (!empty($key)) {
			return isset($this->cloudMeta[$key]) ? $this->cloudMeta[$key] : null;
		} else {
			return $this->cloudMeta;
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

		$cache = SS_Cache::factory('CloudImage');
		$cache->save(serialize($data), $this->cloudCacheKey());

		$this->cloudMeta = $data;
		return $this->owner;
	}


	/**
	 * @return string
	 */
	private function cloudCacheKey() {
		return md5($this->Filename);
	}
}