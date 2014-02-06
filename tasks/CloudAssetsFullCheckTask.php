<?php
/**
 * Does a full status check on every file AND thumbnail/formatted/generated file.
 * Checks the following cases:
 *
 * 1. Local file doesn't exist                          -> downloads from cloud
 * 2. Local file is a placeholder but KeepLocal is true -> downloads from cloud
 * 3. Remote file is a placeholder but local in tact    -> clears status and re-uploads
 * 4. Meta data is missing                              -> restores meta
 * 5. Both remote and local are missing or corrupt      -> deletes if thumbnail, flags if not (writes to missing_files.csv)
 * 6. Local needs to be wrapped or uploaded             -> uploads as normal (via updateCloudStatus)
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 02.06.2014
 * @package cloudassets
 * @subpackage tasks
 */
class CloudAssetsFullCheckTask extends BuildTask
{
	protected $title = 'Cloud Assets: Full Health Check';
	protected $description = 'Does a full status check on every file AND thumbnail/formatted/generated file.';

	protected $missing; // file handle


	/**
	 * @param $request
	 */
	public function run($request) {
		$buckets     = Config::inst()->get('CloudAssets', 'map');

		foreach ($buckets as $basePath => $cfg) {
			echo "Processing $basePath...\n";
			$baseQuery = File::get()->filter('Filename:StartsWith', ltrim($basePath, '/'));
			$total     = $baseQuery->count();
			$query     = $baseQuery->dataQuery()->query()->execute();
			foreach ($query as $i => $row) {
				if (isset($_GET['start']) && $i < $_GET['start']) continue;
				$class = $row['ClassName'];
				$file  = new $class($row);
				echo "$i/$total: " . $file->Filename;

				$this->processFile($file);

//				if ($file instanceof CloudImage) {
//					foreach ($file->DerivedImages() as $thumbStore) {
//						$thumb = $thumbStore->getCloudImageCached();
//						echo "\n   DERIVED: " . $thumb->Filename;
//						$this->processFile($thumb);
//					}
//				}

				echo "\n";
			}
		}

		echo "done\n\n";
	}


	/**
	 * @param File $file
	 */
	protected function processFile(File $file) {
		$placeholder = Config::inst()->get('CloudAssets', 'file_placeholder');

		// 6. Local needs to be wrapped or uploaded             -> uploads as normal (via updateCloudStatus)
		$file->updateCloudStatus();

		// 1. Local file doesn't exist                          -> downloads from cloud
		// 2. Local file is a placeholder but KeepLocal is true -> downloads from cloud
		$file->createLocalIfNeeded();

		$bucket = $file->getCloudBucket();
		if ($bucket && $bucket->hasMethod('getFileSize')) {
			$size = $bucket->getFileSize($file);

			// if the size matches the placeholder, go ahead and check the actual contents
			if ($size === strlen($placeholder)) {
				echo " Remote placeholder!";
				$content = $bucket->getContents($file);
				if ($content === $placeholder) $size = -1;
			}

			if ($size < 0) {
				if ($file->isLocalMissing()) {
					// 5. Both remote and local are missing or corrupt      -> deletes if thumbnail, flags if not (writes to missing_files.csv)
					if ($file instanceof CloudImageCached) {
						echo " Corrupted thumbnail deleted";
						$file->delete();
						return;
					} else {
						if (!isset($this->missing)) $this->missing = fopen(BASE_PATH . '/missing_files.csv', 'a');
						fputcsv($this->missing, array(date('Y-m-d H:i:s'), $file->ID, $file->Filename));
						echo " Corrupted in both locations!!!";
					}
				} else {
					// 3. Remote file is a placeholder but local in tact    -> clears status and re-uploads
					$file->CloudStatus = 'Local';
					$file->updateCloudStatus();
				}
			}
		}

		// 4. Meta data is missing                              -> restores meta
		if ($file instanceof CloudImage && !$file->getCloudMeta('Dimensions')) {
			echo " Missing metadata!";
			if ($file->isLocalMissing()) $file->downloadFromCloud();
			$file->setCloudMeta('Dimensions', $file->getDimensions('live'));
			$file->write();
		}
	}

}