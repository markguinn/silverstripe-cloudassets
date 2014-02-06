<?php
/**
 * If there are any duplicate file records (2 records pointing to the same file)
 * This task will sync the cloud state between them. If any of them has a live
 * status, all of them will.
 *
 * NOTE: this scenario shouldn't ever happen but there are lots of ways that it
 * CAN happen (I've had this crop up on two different projects) and when it does
 * it causes strange artifacts like thumbnails disappearing.
 *
 * This is a quick fix for sites with duplicate records. I'd suggest writing a
 * script to actually combine duplicates as a long-term solution but that would
 * have to be site-specific.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 02.06.2014
 * @package cloudassets
 * @subpackage tasks
 */
class CloudAssetsSyncDupsTask extends BuildTask
{
	protected $title = "Cloud Assets: Sync Duplicate File Status";
	protected $description = "If there are any duplicate file records (2 records pointing to the same file) this task will sync the cloud state between them. If any of them has a live status, all of them will.";

	public function run($request) {
		$results = DB::query(<<<SQL
			SELECT "Filename", count("ID") as "Num",
				group_concat("ID" order by "ID" separator ',') as "IDs",
				group_concat("CloudStatus" order by "ID" separator ',') as "CloudStatus",
				group_concat("CloudSize" order by "ID" separator ',') as "CloudSize",
				group_concat("CloudMetaJson" order by "ID" separator '\n') as "CloudMetaJson"
			FROM "File"
			GROUP BY "Filename"
			HAVING "Num" > 1
SQL
		);

		$fixed = 0;
		foreach ($results as $row) {
			$status = explode(',', $row['CloudStatus']);
			$size   = explode(',', $row['CloudSize']);
			$meta   = explode("\n", $row['CloudMetaJson']);
			$pos    = array_search('Live', $status);
			if ($pos !== false && (in_array('Local', $status) || in_array('Error', $status))) {
				$myMeta = '';
				foreach ($meta as $m) {
					if (!empty($m)) {
						$myMeta = $m;
						break;
					}
				}

				$fixed++;
				DB::query(sprintf(<<<SQL
					UPDATE "File"
					SET "CloudStatus" = 'Live',
						"CloudSize" = '%d',
						"CloudMetaJson" = '%s'
					WHERE "ID" in (%s)
SQL
				, (int)$size[$pos], Convert::raw2sql($myMeta), $row['IDs']));
			}
		}

		echo "Fixed: $fixed\n\n";
	}
}