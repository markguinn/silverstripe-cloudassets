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
	/** @var string $localPath - local path being replaced (e.g. assets/Uploads) */
	protected $localPath;

	/** @var string $baseURL - CDN url */
	protected $baseURL;


	/**
	 * @param File $f
	 */
	abstract public function put(File $f);


	/**
	 * @param File $f
	 */
	abstract public function delete(File $f);


	/**
	 * @param File $f
	 * @param string $beforeName - contents of the Filename property (i.e. relative to site root)
	 * @param string $afterName - contents of the Filename property (i.e. relative to site root)
	 */
	abstract public function rename(File $f, $beforeName, $afterName);


	/**
	 * @param string $path
	 * @param array  $cfg
	 */
	public function __construct($path, array $cfg=array()) {
		$this->localPath = $path;
		$this->baseURL   = empty($cfg['BaseURL']) ? (Director::baseURL() . $path) : $cfg['BaseURL'];
		if (substr($this->localPath, -1) != '/') $this->localPath .= '/';
		if (substr($this->baseURL, -1) != '/') $this->baseURL .= '/';
	}


	/**
	 * @return string
	 */
	public function getBaseURL() {
		return $this->baseURL;
	}


	/**
	 * @param File $f
	 * @return string
	 */
	public function getLinkFor(File $f) {
		return $this->baseURL . str_replace($this->localPath, '', $f->getFilename());
	}

}