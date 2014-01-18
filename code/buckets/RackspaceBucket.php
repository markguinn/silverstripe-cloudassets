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

		$client = new Rackspace(Rackspace::US_IDENTITY_ENDPOINT, array(
			'username'      => $cfg[self::USERNAME],
			'apiKey'        => $cfg[self::API_KEY],
		));

		$service = $client->objectStoreService('cloudFiles', $cfg[self::REGION],
			empty($cfg[self::SERVICE_NET]) ? 'publicURL' : 'internalURL');

		$this->containerName = $cfg[self::CONTAINER];
		$this->container = $service->getContainer($this->containerName);
	}


	/**
	 * @param File $f
	 * @throws Exception
	 */
	public function put(File $f) {
		$fp = fopen($f->getFullPath(), 'r');
		if (!$fp) throw new Exception("Unable to open file: " . $f->getFilename());
		$this->container->uploadObject($this->getRelativeLinkFor($f), $fp, array(
			'FileID'    => $f->ID,
		));
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
		$obj->copy($this->config[self::CONTAINER] . '/' . $this->getRelativeLinkFor($afterName));
		$obj->delete();
	}


	/**
	 * @param File $f
	 * @return string
	 */
	public function getContents(File $f) {
		$obj = $this->container->getObject($this->getRelativeLinkFor($f));
		return $obj->getContent();
	}


	/**
	 * @param File|string $f
	 * @return \OpenCloud\ObjectStore\Resource\DataObject
	 */
	protected function getFileObjectFor($f) {
		return $this->container->getPartialObject($this->getRelativeLinkFor($f));
	}
}
