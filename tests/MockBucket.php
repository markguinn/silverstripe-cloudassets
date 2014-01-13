<?php
/**
 * Stubs out all the cloud calls for testing.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 01.10.2014
 * @package cloudassets
 * @subpackage buckets
 */
class MockBucket extends CloudBucket
{
	public $uploads = array();
	public $deletes = array();
	public $renames = array();
	private $uploadContents = array();

	/**
	 * @param File $f
	 */
	public function put(File $f) {
		$this->uploads[] = $f;
		$this->uploadContents[ $f->getFilename() ] = file_get_contents($f->getFullPath());
	}


	/**
	 * @param File|string $f
	 */
	public function delete($f) {
		$this->deletes[] = $f;
	}


	/**
	 * @param File $f
	 * @param string $beforeName - contents of the Filename property (i.e. relative to site root)
	 * @param string $afterName - contents of the Filename property (i.e. relative to site root)
	 */
	public function rename(File $f, $beforeName, $afterName) {
		$this->renames[$beforeName] = $afterName;
	}


	/**
	 * @param File $f
	 * @return string
	 */
	public function getContents(File $f) {
		return isset($this->uploadContents[$f->getFilename()]) ? $this->uploadContents[$f->getFilename()] : null;
	}
}