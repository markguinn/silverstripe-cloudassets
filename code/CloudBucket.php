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

	/** @var string $baseURL - CDN url */
	protected $baseURL;

	/** @var  string $secureURL - CDN url for https (optional) */
	protected $secureURL;

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
		$this->baseURL   = empty($cfg[self::BASE_URL]) ? (Director::baseURL() . $path) : $cfg[self::BASE_URL];
		$this->secureURL = empty($cfg[self::SECURE_URL]) ? '' : $cfg[self::SECURE_URL];
		if (substr($this->localPath, -1) != '/') $this->localPath .= '/';
		if (substr($this->baseURL, -1) != '/') $this->baseURL .= '/';
		if (!empty($this->secureURL) && substr($this->secureURL, -1) != '/') $this->secureURL .= '/';
	}


	/**
	 * @return string
	 */
	public function getBaseURL() {
		return $this->baseURL;
	}


	/**
	 * @return string
	 */
	public function getSecureURL() {
		return $this->secureURL;
	}


	/**
	 * @param File|string $f - the string should be the Filename field of a File
	 * @return string
	 */
	public function getLinkFor($f) {
		$base = Director::is_https() && !empty($this->secureURL) ? $this->secureURL : $this->baseURL;
		return $base . $this->getRelativeLinkFor($f);
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