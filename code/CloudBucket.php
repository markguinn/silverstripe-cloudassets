<?php
/**
 * Base class for all bucket drivers
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 01.10.2014
 * @package cloudassets
 */
abstract class CloudBucket extends Object
{
	const BASE_URL   = 'BaseURL';
	const SECURE_URL = 'SecureURL';
	const LOCAL_COPY = 'LocalCopy';
	const TYPE       = 'Type';

	/** @var string $localPath - local path being replaced (e.g. assets/Uploads) */
	protected $localPath;

	/** @var array $baseURL - CDN url(s) */
	protected $baseURL;

	/** @var int $baseUrlIndex - last index sent if more than one base */
	protected $baseUrlIndex = 0;

	/** @var  array $secureURL - CDN url(s) for https (optional) */
	protected $secureURL;

	/** @var int $secureUrlIndex - last index sent if more than one base */
	protected $secureUrlIndex = 0;

	/** @var  array $config */
	protected $config;


	/**
	 * @param File $f
	 */
	abstract public function put(File $f);


	/**
	 * NOTE: This method must handle string filenames as well
	 * for the purpose of deleting cached resampled images.
	 * @param File|string $f
	 */
	abstract public function delete($f);


	/**
	 * @param File $f
	 * @param string $beforeName - contents of the Filename property (i.e. relative to site root)
	 * @param string $afterName - contents of the Filename property (i.e. relative to site root)
	 */
	abstract public function rename(File $f, $beforeName, $afterName);


	/**
	 * @param File $f
	 * @return string
	 */
	abstract public function getContents(File $f);


	/**
	 * @param string $path
	 * @param array  $cfg
	 */
	public function __construct($path, array $cfg=array()) {
		$this->config    = $cfg;
		$this->localPath = $path;
		$this->baseURL   = empty($cfg[self::BASE_URL]) ? array(Director::baseURL() . $path) : $cfg[self::BASE_URL];
		$this->baseURL   = $this->scrubBasePath( $this->baseURL );
		$this->secureURL = empty($cfg[self::SECURE_URL]) ? array() : $cfg[self::SECURE_URL];
		$this->secureURL = $this->scrubBasePath( $this->secureURL );
		if (substr($this->localPath, -1) != '/') $this->localPath .= '/';
	}


	/**
	 * @param string|array $paths
	 * @return array
	 */
	protected function scrubBasePath($paths) {
		if (!is_array($paths)) $paths = is_string($paths) ? array($paths) : array();

		foreach ($paths as &$p) {
			if (strlen($p) > 0 && substr($p, -1) != '/') $p .= '/';
		}

		return $paths;
	}


	/**
	 * @return string
	 */
	public function getBaseURL() {
		return $this->roundRobinGet('baseURL');
	}


	/**
	 * @return string
	 */
	public function getSecureURL() {
		return $this->roundRobinGet('secureURL');
	}


	/**
	 * Given an array property, returns the next element
	 * from it and increments an index field
	 * @param string $field
	 * @return string
	 */
	protected function roundRobinGet($field) {
		if (empty($this->$field) || !is_array($this->$field)) return '';
		$val = $this->$field;
		$idx = $field . 'Index';
		if (!isset($this->$idx) || $this->$idx >= count($val)) $this->$idx = 0;
		return $val[ $this->$idx++ ];
	}


	/**
	 * @param File|string $f - the string should be the Filename field of a File
	 * @return string
	 */
	public function getLinkFor($f) {
		$ssl   = Director::is_https() && !empty($this->secureURL);
		$field = $ssl ? 'secureURL' : 'baseURL';
		$base  = null;

		if (count($this->$field) > 1 && is_object($f)) {
			// If there are multiple urls, use cloud meta to remember
			// which one we used so the url stays the same for any
			// given image, allowing the image to still be cached
			$base = $f->getCloudMeta($field);
			if (!$base) {
				$base = $this->roundRobinGet($field);
				$f->setCloudMeta($field, $base);
				$f->write();
			}
		} else {
			// If there's only one, don't touch meta data
			$base = $this->roundRobinGet($field);
		}

		return $base . $this->getRelativeLinkFor($f);
	}


	/**
	 * This version just returns a normal link. I'm assuming most
	 * buckets will implement this but I want it to be optional.
	 * @param File|string $f
	 * @param int $expires [optional] - Expiration time in seconds
	 * @return string
	 */
	public function getTemporaryLinkFor($f, $expires=3600) {
		return $this->getLinkFor($f);
	}


	/**
	 * Returns the full path and filename, relative to the BaseURL
	 * @param File|string $f
	 * @return string
	 */
	public function getRelativeLinkFor($f) {
		$fn = is_object($f) ? $f->getFilename() : $f;
		return trim(str_replace($this->localPath, '', $fn), '/');
	}


	/**
	 * @return bool
	 */
	public function isLocalCopyEnabled() {
		return !empty($this->config[self::LOCAL_COPY]);
	}
}