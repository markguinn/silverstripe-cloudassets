<?php
/**
 * 
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 01.10.2014
 * @package cloudassets
 */
class CloudAssetsTest extends SapphireTest
{
	protected static $fixture_file = 'CloudAssets.yml';

	function testMap() {
		$bucket = CloudAssets::inst()->map('assets/FileTest-folder1/File1.txt');
		$this->assertTrue($bucket instanceof MockBucket);
		$this->assertEquals('http://testcdn.com/', $bucket->getBaseURL());
	}


	function testWrap() {
		$f1 = $this->objFromFixture('File', 'file1-folder1');
		$f2 = $this->objFromFixture('File', 'asdf');
		$this->assertTrue($f1->hasExtension('CloudFileExtension'));

		// NOTE: we're having to call updateCloudStatus here in the tests
		// because the files weren't present when the objects were created
		// due to the order of setup in tests. Turns out it's handy for
		// testing because we can test before and after states.
		$f1->updateCloudStatus();
		$this->assertEquals('CloudFile', $f1->ClassName);

		$f2->updateCloudStatus();
		$this->assertEquals('File', $f2->ClassName);
	}


	function testLinks() {
		CloudAssets::inst()->updateAllFiles();

		$f1 = $this->objFromFixture('File', 'file1-folder1');
		$this->assertEquals('http://testcdn.com/File1.txt', $f1->Link());
		$this->assertEquals('http://testcdn.com/File1.txt', $f1->RelativeLink());
		$this->assertEquals('http://testcdn.com/File1.txt', $f1->getURL());
		$this->assertEquals('http://testcdn.com/File1.txt', $f1->getAbsoluteURL());
		// there may be more methods we need to test here?

		$f2 = $this->objFromFixture('File', 'asdf');
		$this->assertEquals('/assets/FileTest.txt', $f2->Link());
		$this->assertEquals('assets/FileTest.txt', $f2->RelativeLink());
		$this->assertEquals('/assets/FileTest.txt', $f2->getURL());
		$this->assertEquals(Director::absoluteBaseURL() . 'assets/FileTest.txt', $f2->getAbsoluteURL());
	}


	function testUpload() {
		$f1 = $this->objFromFixture('File', 'file1-folder1');
		$this->assertEquals(1000, filesize($f1->getFullPath()), 'should initially contain 1000 bytes');

		$f1 = $f1->updateCloudStatus();
		$placeholder = Config::inst()->get('CloudAssets', 'file_placeholder');
		$this->assertEquals($placeholder, file_get_contents($f1->getFullPath()), 'should contain the placeholder after updating status');

		$bucket = CloudAssets::inst()->map($f1);
		$this->assertTrue(in_array($f1, $bucket->uploads), 'mock bucket should have recorded an upload');
	}


	function testDelete() {
		CloudAssets::inst()->updateAllFiles();
		$f1 = $this->objFromFixture('File', 'file1-folder1');
		$f1->delete();
		$this->assertFalse(file_exists($f1->getFullPath()), 'local file should not exist');
		$this->assertTrue(in_array($f1, $f1->getCloudBucket()->deletes), 'remote file should have been deleted');
	}


	function testFileSize() {
		CloudAssets::inst()->updateAllFiles();
		$f1 = $this->objFromFixture('File', 'file1-folder1');
		$placeholder = Config::inst()->get('CloudAssets', 'file_placeholder');
		$this->assertEquals($placeholder, file_get_contents($f1->getFullPath()), 'should contain the placeholder');
		$this->assertEquals(1000, $f1->getAbsoluteSize(), 'should still report the cloud size');
		$this->assertEquals('1000 bytes', $f1->getSize(), 'formatted size should work too');
	}


	function testRename() {
		CloudAssets::inst()->updateAllFiles();
		$f1 = $this->objFromFixture('File', 'file1-folder1');
		$oldPath = $f1->getFullPath();
		$oldURL = $f1->getCloudURL();
		$oldName = $f1->getFilename();

		$f1->Name = 'Newname1.txt';
		$f1->write();
		$this->assertFalse(file_exists($oldPath));
		$newPath = $f1->getFullPath();
		$newURL = $f1->getCloudURL();
		$newName = $f1->getFilename();

		$this->assertFalse(file_exists($oldPath));
		$this->assertTrue(file_exists($newPath));
		$this->assertNotEquals($oldPath, $newPath);
		$this->assertNotEquals($oldURL, $newURL);
		$this->assertEquals($newName, $f1->getCloudBucket()->renames[$oldName]);
	}


	function testCloudMetaData() {
		CloudAssets::inst()->updateAllFiles();
		$f1 = $this->objFromFixture('File', 'file1-folder1');

		$f1->setCloudMeta(array('abc' => '123'));
		$data = $f1->getCloudMeta();
		$this->assertTrue(is_array($data),          'should be an array');
		$this->assertEquals(1, count($data),        'should have one index');
		$this->assertEquals('123', $data['abc'],    'should have the correct key/val');

		$f1->setCloudMeta('def', 456);
		$data = $f1->getCloudMeta();
		$this->assertEquals(2, count($data),        'should have two indexes');
		$this->assertEquals('123', $data['abc'],    'should have the original key/val');
		$this->assertEquals('456', $data['def'],    'should have the new key/val');
	}


	function testFormattedImage() {
		CloudAssets::inst()->updateAllFiles();

		$img = $this->objFromFixture('Image', 'png');
		$this->assertTrue($img instanceof CloudImage);
		$this->assertEquals(20, $img->getWidth());
		$this->assertEquals(20, $img->getHeight());
		$this->assertEquals('http://testcdn.com/test-png32.png', $img->Link());

		$countBefore = File::get()->count();
		$resized = $img->SetWidth(10);
		$countAfter = File::get()->count();
		$this->assertEquals(10, $resized->getWidth());
		$this->assertEquals(10, $resized->getHeight());
		$this->assertEquals('http://testcdn.com/' . $resized->getCloudBucket()->getRelativeLinkFor($resized), $resized->Link());
		$this->assertEquals($countBefore, $countAfter, 'SetWidth should not create a database record');
		$bucket = $resized->getCloudBucket();
		$this->assertTrue(in_array($resized, $bucket->uploads), 'mock bucket should have recorded an upload');

		// deleting the image should also delete the resize
		$img->delete();
		$this->assertTrue(in_array($resized->Filename, $bucket->deletes), 'mock bucket should have recorded a delete');
	}


	function testChangeParentIdShouldRenameInCloud() {
		CloudAssets::inst()->updateAllFiles();
		$sub2       = $this->objFromFixture('Folder', 'folder1-subfolder2');
		$file       = $this->objFromFixture('File', 'file2-folder1');
		$oldName    = $file->getFilename();

		$this->assertEquals('http://testcdn.com/FileTest-folder1-subfolder1/File2.txt', $file->Link());

		$file->ParentID = $sub2->ID;
		$file->write();
		$newName    = $file->getFilename();

		$this->assertEquals('http://testcdn.com/FileTest-folder1-subfolder2/File2.txt', $file->Link());
		$this->assertEquals($newName, $file->getCloudBucket()->renames[$oldName]);
	}


	function testChangeFolderNameShouldRenameAllFiles() {
		CloudAssets::inst()->updateAllFiles();
		$sub1       = $this->objFromFixture('Folder', 'folder1-subfolder1');
		$file       = $this->objFromFixture('File', 'file2-folder1');

		$oldName    = $file->getFilename();
		$this->assertEquals('http://testcdn.com/FileTest-folder1-subfolder1/File2.txt', $file->Link());

		$sub1->Name = 'crazytown';
		$sub1->write();

		DataObject::flush_and_destroy_cache();
		$file       = File::get()->byID($file->ID); // reload to pick up changes
		$newName    = $file->getFilename();

		$this->assertEquals('http://testcdn.com/crazytown/File2.txt', $file->Link());
		$this->assertEquals($newName, $file->getCloudBucket()->renames[$oldName]);
	}


	function testLocalCopyShouldBeCreatedIfNotPresent() {
		// GIVEN: the local file is not present but DB and Cloud versions are
		CloudAssets::inst()->updateAllFiles();
		$f1 = $this->objFromFixture('File', 'file1-folder1');
		unlink($f1->getFullPath());

		// local file should be restored when exists() is called
		$this->assertTrue($f1->exists()); // NOTE: this is the necessary call
		$this->assertFileExists($f1->getFullPath());
		$this->assertTrue($f1->containsPlaceholder());
	}


	function testMetadataRestoredOnCachedImageIfNotPresent() {
		CloudAssets::inst()->updateAllFiles();
		$img = $this->objFromFixture('Image', 'png');
		$resized = $img->SetWidth(10);
		$this->assertEquals(10, $resized->getWidth());
		$this->assertEquals(10, $resized->getHeight());

		// this should wipe out the cloudmeta for the above
		$cache = SS_Cache::factory('CloudImage');
		$cache->clean();

		// this should generate a fresh cached image object (with no stored meta data)
		$resized2 = $img->SetWidth(10);

		// this call should then require it to fetch the cloud version to restore the meta data
		$this->assertEquals(10, $resized2->getWidth());
		$this->assertEquals(10, $resized2->getHeight());
	}


	function testUploadsDisabled() {
		Config::inst()->update('CloudAssets', 'uploads_disabled', true);

		// this is needed to undo the upload done during setup
		$f1 = $this->objFromFixture('File', 'file1-folder1');
		if ($f1->CloudStatus != 'Local') {
			$f1->CloudStatus = 'Local';
			$f1->downloadFromCloud();
			$f1->write();
		}

		CloudAssets::inst()->updateAllFiles();
		DataObject::flush_and_destroy_cache();
		$f1 = File::get()->byID($f1->ID);
		$this->assertEquals('Local', $f1->CloudStatus);
		$this->assertEquals(0, count($f1->getCloudBucket()->uploads));
	}


	// TODO: local copy mode


	/////////////////////////////////////////////////////////////////////////////////////////////////////////////


	public function setUpOnce() {
		parent::setUpOnce();
		Config::inst()->remove('CloudAssets', 'map');
		Config::inst()->update('CloudAssets', 'map', array(
			'assets/FileTest-folder1'   => array(
				'BaseURL'   => 'http://testcdn.com/',
				'Type'      => 'MockBucket',
			),
		));
	}

	public function setUp() {
		parent::setUp();
		Config::inst()->update('CloudAssets', 'disabled', false);
		Config::inst()->update('CloudAssets', 'uploads_disabled', false);

		if(!file_exists(ASSETS_PATH)) mkdir(ASSETS_PATH);

		/* Create a test folders for each of the fixture references */
		$folderIDs = $this->allFixtureIDs('Folder');
		foreach($folderIDs as $folderID) {
			$folder = DataObject::get_by_id('Folder', $folderID);
			if(!file_exists(BASE_PATH."/$folder->Filename")) mkdir(BASE_PATH."/$folder->Filename");
		}

		/* Create a test files for each of the fixture references */
		$fileIDs = $this->allFixtureIDs('File');
		foreach($fileIDs as $fileID) {
			if ($fileID == 'png') continue;
			$file = DataObject::get_by_id('File', $fileID);
			$fh = fopen(BASE_PATH."/$file->Filename", "w");
			fwrite($fh, str_repeat('x',1000));
			fclose($fh);
		}

		// Conditional fixture creation in case the 'cms' module is installed
		if(class_exists('ErrorPage')) {
			$page = new ErrorPage(array(
				'Title' => 'Page not Found',
				'ErrorCode' => 404
			));
			$page->write();
			$page->publish('Stage', 'Live');
		}

		$src  = dirname(__FILE__) . '/test-png32.png';
		$dest = ASSETS_PATH . '/FileTest-folder1/test-png32.png';
		$f = copy($src, $dest);
		if (!$f) die('unable to copy $src to $dest');

		CloudAssets::inst()->clearBucketCache();
	}

	public function tearDown() {
		parent::tearDown();

		/* Remove the test files that we've created */
		$fileIDs = $this->allFixtureIDs('File');
		foreach($fileIDs as $fileID) {
			$file = DataObject::get_by_id('File', $fileID);
			if($file && file_exists(BASE_PATH."/$file->Filename")) unlink(BASE_PATH."/$file->Filename");
		}

		/* Remove the test folders that we've crated */
		$folderIDs = $this->allFixtureIDs('Folder');
		foreach($folderIDs as $folderID) {
			$folder = DataObject::get_by_id('Folder', $folderID);
			if($folder && file_exists(BASE_PATH."/$folder->Filename")) {
				Filesystem::removeFolder(BASE_PATH."/$folder->Filename");
			}
		}

		// Remove left over folders and any files that may exist
		if(file_exists('../assets/FileTest')) Filesystem::removeFolder('../assets/FileTest');
		if(file_exists('../assets/FileTest-subfolder')) Filesystem::removeFolder('../assets/FileTest-subfolder');
		if(file_exists('../assets/FileTest.txt')) unlink('../assets/FileTest.txt');

		if (file_exists("../assets/FileTest-folder-renamed1")) {
			Filesystem::removeFolder("../assets/FileTest-folder-renamed1");
		}
		if (file_exists("../assets/FileTest-folder-renamed2")) {
			Filesystem::removeFolder("../assets/FileTest-folder-renamed2");
		}
		if (file_exists("../assets/FileTest-folder-renamed3")) {
			Filesystem::removeFolder("../assets/FileTest-folder-renamed3");
		}
	}
}