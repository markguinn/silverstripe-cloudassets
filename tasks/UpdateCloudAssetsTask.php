<?php
/**
 * Simply calls updateCloudStatus on every file in the db.
 * Allows you to get a new server or development environment synced up easily.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 01.14.2014
 * @package cloudassets
 * @subpackage tasks
 */
class UpdateCloudAssetsTask extends BuildTask
{
	protected $title = 'Cloud Assets: Update All Files';
	protected $description = 'Simply calls updateCloudStatus on every file in the db. Allows you to get a new server or development environment synced up easily.';


	public function run($request) {
		$buckets = Config::inst()->get('CloudAssets', 'map');

		foreach ($buckets as $basePath => $cfg) {
			echo "processing $basePath...\n";
			$files = File::get()->filter('Filename:StartsWith', ltrim($basePath, '/'));
			foreach ($files as $f) {
				echo " - {$f->Filename}: {$f->CloudStatus} - placeholder={$f->containsPlaceholder()}\n";
				$f->updateCloudStatus();
				$f->createLocalIfNeeded();
			}
		}

		echo "done\n\n";
	}

}