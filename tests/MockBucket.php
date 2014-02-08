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
	public $uploadContents = array();

	/**
	 * @param File $f
	 */
	public function put(File $f) {
		$this->uploads[] = $f->Filename;
		$this->uploadContents[ $f->Filename ] = file_get_contents($f->getFullPath());
	}


	/**
	 * @param File|string $f
	 */
	public function delete($f) {
		$this->deletes[] = is_object($f) ? $f->Filename : $f;
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
		return isset($this->uploadContents[$f->Filename]) ? $this->uploadContents[$f->Filename] : null;
	}


	/**
	 * @param string|File $f
	 * @return bool
	 */
	public function wasDeleted($f) {
		return in_array(is_object($f) ? $f->Filename : $f, $this->deletes);
	}


	/**
	 * @param string|File $f
	 * @return bool
	 */
	public function wasUploaded($f) {
		return in_array(is_object($f) ? $f->Filename : $f, $this->uploads);
	}


	/**
	 * @param string|File $newName
	 * @param string $oldName
	 * @return bool
	 */
	public function wasRenamed($newName, $oldName) {
		if (is_object($newName)) $newName = $newName->Filename;
		return $newName == $this->renames[$oldName];
	}


	/**
	 * Wipes upload/delete/etc log
	 */
	public function clearActivityLog() {
		$this->uploads = array();
		$this->deletes = array();
		$this->renames = array();
		$this->uploadContents = array();
	}
}