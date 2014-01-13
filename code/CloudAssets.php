<?php
/**
 * Central interface for cloud assets module.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 01.10.2014
 * @package cloudassets
 */
class CloudAssets extends Object
{
	/** @var array */
	private static $map = array(
		//'assets/folder/path' => array(
		//  'Type'      => 'RackspaceBucket',
		//  'BaseURL'   => 'http://cdnurl.com/',
		//  'Container' => 'container-name',
		//  'UserID'    => 'username',
		//  'ApiKey'    => 'key',
		//  'LocalCopy' => true,
		//);
	);

	/** @var array - add to this if you have other file subclasses floating around */
	private static $wrappers = array(
		'File'          => 'CloudFile',
		'Image'         => 'CloudImage',
		'CloudImage_Cached' => 'CloudImage_Cached', // this is awkward but prevents it from trying to transform Image_Cached
	);

	/** @var string - placeholder string used for local files */
	private static $file_placeholder = 'CloudFile';

	/** @var array - only keep one instance of each bucket */
	protected $bucketCache = array();


	/**
	 * @return CloudAssets
	 */
	public static function inst() {
		return Injector::inst()->get('CloudAssets');
	}


	/**
	 * @param string|File $filename
	 * @return CloudBucket
	 */
	public function map($filename) {
		if (is_object($filename)) $filename = $filename->getFilename();
		$maps = Config::inst()->get('CloudAssets', 'map');

		foreach ($maps as $path => $cfg) {
			if (empty($cfg[ CloudBucket::TYPE ])) continue;
			if (strpos($filename, $path) === 0) {
				if (!isset($this->bucketCache[$path])) {
					$this->bucketCache[$path] = Injector::inst()->create($cfg[CloudBucket::TYPE], $path, $cfg);
				}

				return $this->bucketCache[$path];
			}
		}

		return null;
	}


	/**
	 * Updates the cloud status of all files (used in tests and cron job)
	 */
	public function updateAllFiles() {
		foreach (File::get() as $f) {
			$f->updateCloudStatus();
		}
	}


	/**
	 * @param string $className
	 * @return string
	 */
	public function getWrapperClass($className) {
		$wrappers = Config::inst()->get('CloudAssets', 'wrappers');
		// Needs to be wrapped
		if (isset($wrappers[$className])) return $wrappers[$className];
		// Already wrapped
		if (in_array($className, $wrappers, true)) return $className;
		// Can't be wrapped
		return null;
	}

}