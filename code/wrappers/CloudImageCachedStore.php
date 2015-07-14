<?php
/**
 * Stores information about size, dimensions, and state of formatted (cached)
 * images in a separate table so they don't show up in the assets manager
 * in the CMS, but can still be used in redundant environments and across
 * cache clears.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 02.05.2014
 * @package cloudassets
 * @subpackage wrappers
 */

/**
 * Class CloudImageCachedStore
 */
class CloudImageCachedStore extends DataObject
{
	public static $db = array(
		'Filename'      => 'Varchar(255)',
		'CloudStatus'   => "Enum('Local,Live,Error','Local')",
		'CloudSize'     => 'Int',
		'CloudMetaJson' => 'Text',      // saves any bucket or file-type specific information
	);

	public static $has_one = array(
		'Source'        => 'CloudImage',
	);

	public static $indexes = array(
		'Filename'      => true,
	);


	/**
	 * Constructs an image record that can be used with this meta data
	 * @return CloudImageCached
	 */
	public function getCloudImageCached() {
		$cached = new CloudImageCached($this->Filename);
		$cached->Title = $this->Title;
		$cached->ParentID = $this->ParentID;
		$cached->setStoreRecord($this);
		return $cached;
	}
}