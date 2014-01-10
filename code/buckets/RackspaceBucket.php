<?php
/**
 * Bucket/container driver for rackspace
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 01.10.2014
 * @package cloudassets
 * @subpackage buckets
 */
class RackspaceBucket extends CloudBucket
{
	/**
	 * @param File $f
	 */
	public function put(File $f) {
	}


	/**
	 * @param File $f
	 */
	public function delete(File $f) {
	}

	/**
	 * @param File $f
	 * @param string $beforeName - contents of the Filename property (i.e. relative to site root)
	 * @param string $afterName - contents of the Filename property (i.e. relative to site root)
	 */
	public function rename(File $f, $beforeName, $afterName) {

	}
}