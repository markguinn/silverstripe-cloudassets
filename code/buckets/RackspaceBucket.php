<?php
/**
 * Bucket/container driver for rackspace
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 01.10.2014
 * @package cloudassets
 * @subpackage buckets
 */
use OpenCloud\Rackspace;

class RackspaceBucket extends CloudBucket
{
	const CONTAINER   = 'Container';
	const REGION      = 'Region';
	const USERNAME    = 'Username';
	const API_KEY     = 'ApiKey';
	const SERVICE_NET = 'ServiceNet';
	const FORCE_DL    = 'ForceDownload';

	/** @var \OpenCloud\ObjectStore\Resource\Container */
	protected $container;

	/** @var string */
	protected $containerName;


	/**
	 * @param string $path
	 * @param array  $cfg
	 * @throws Exception
	 */
	public function __construct($path, array $cfg=array()) {
		parent::__construct($path, $cfg);
		if (empty($cfg[self::CONTAINER])) throw new Exception('RackspaceBucket: missing configuration key - ' . self::CONTAINER);
		if (empty($cfg[self::REGION]))    throw new Exception('RackspaceBucket: missing configuration key - ' . self::REGION);
		if (empty($cfg[self::USERNAME]))  throw new Exception('RackspaceBucket: missing configuration key - ' . self::USERNAME);
		if (empty($cfg[self::API_KEY]))   throw new Exception('RackspaceBucket: missing configuration key - ' . self::API_KEY);
		$this->containerName = $this->config[self::CONTAINER];
	}


	/**
	 * @return \OpenCloud\ObjectStore\Resource\Container
	 */
	protected function getContainer() {
		if (!isset($this->container)) {
			$client = new Rackspace(Rackspace::US_IDENTITY_ENDPOINT, array(
				'username'      => $this->config[self::USERNAME],
				'apiKey'        => $this->config[self::API_KEY],
			));

			$service = $client->objectStoreService('cloudFiles', $this->config[self::REGION],
				empty($this->config[self::SERVICE_NET]) ? 'publicURL' : 'internalURL');

			$this->container = $service->getContainer($this->containerName);
		}

		return $this->container;
	}


	/**
	 * @param File $f
	 * @throws Exception
	 */
	public function put(File $f) {
		$fp = fopen($f->getFullPath(), 'r');
		if (!$fp) throw new Exception("Unable to open file: " . $f->getFilename());

		$headers = array();
		if (!empty($this->config[self::FORCE_DL])) {
			$headers['Content-Disposition'] = 'attachment; filename=' . ($f->hasMethod('getFriendlyName') ? $f->getFriendlyName() : $f->Name);
		}

		$this->getContainer()->uploadObject($this->getRelativeLinkFor($f), $fp, $headers);
	}


	/**
	 * @param File|string $f
	 */
	public function delete($f) {
		$obj = $this->getFileObjectFor($f);
		$obj->delete();
	}

	/**
	 * @param File $f
	 * @param string $beforeName - contents of the Filename property (i.e. relative to site root)
	 * @param string $afterName - contents of the Filename property (i.e. relative to site root)
	 */
	public function rename(File $f, $beforeName, $afterName) {
		$obj = $this->getFileObjectFor( $this->getRelativeLinkFor($beforeName) );
		$obj->copy($this->containerName . '/' . $this->getRelativeLinkFor($afterName));
		$obj->delete();
	}


	/**
	 * @param File $f
	 * @return string
	 */
	public function getContents(File $f) {
		$obj = $this->getContainer()->getObject($this->getRelativeLinkFor($f));
		return $obj->getContent();
	}


	/**
	 * This version just returns a normal link. I'm assuming most
	 * buckets will implement this but I want it to be optional.
	 * NOTE: I'm not sure how reliably this is working.
	 *
	 * @param File|string $f
	 * @param int $expires [optional] - Expiration time in seconds
	 * @return string
	 */
	public function getTemporaryLinkFor($f, $expires=3600) {
		$obj = $this->getFileObjectFor( $this->getRelativeLinkFor($f) );
		return $obj->getTemporaryUrl($expires, 'GET');
	}


	/**
	 * @param $f - File object or filename
	 * @return bool
	 * @throws Exception|Guzzle\Http\Exception\ClientErrorResponseException
	 */
	public function checkExists($f) {
		try {
			$obj = $this->getFileObjectFor($f);
			return true;
		} catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
			if ($e->getResponse()->getStatusCode() == 404) {
				return false;
			} else {
				throw $e;
			}
		}
	}


	/**
	 * @param $f - File object or filename
	 * @return int - if file doesn't exist, returns -1
	 * @throws Exception|Guzzle\Http\Exception\ClientErrorResponseException
	 */
	public function getFileSize($f) {
		try {
			$obj = $this->getFileObjectFor($f);
			return $obj->getContentLength();
		} catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
			if ($e->getResponse()->getStatusCode() == 404) {
				return -1;
			} else {
				throw $e;
			}
		}
	}


	/**
	 * @param File|string $f
	 * @return \OpenCloud\ObjectStore\Resource\DataObject
	 */
	protected function getFileObjectFor($f) {
		return $this->getContainer()->getPartialObject($this->getRelativeLinkFor($f));
	}
}
