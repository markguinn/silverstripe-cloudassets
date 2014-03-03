<?php
/**
 * Wraps a special case that really only occurs when we try to
 * generate a thumbnail from a missing image (remote and local).
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 03.03.2014
 * @package cloudassets
 * @subpackage wrappers
 */
class CloudImageMissing extends Image
{
	/** @var CloudImage */
	protected $source;

	/** @var string - e.g. 1x1 */
	protected $dimensions;


	/**
	 * Create a new cached image.
	 *
	 */
	public function __construct($source=null, $args=null) {
		parent::__construct(array(), false);
		$this->ID = -1;
		$this->Filename = CloudAssets::config()->missing_image;
		$this->source = $source;
		if (empty($args)) {
			$this->dimensions = '100x100';
		} else {
			switch ($args[0]) {
				case 'SetWidth';
				case 'SetHeight':
					$this->dimensions = $args[1] . 'x' . $args[1];
					break;

				case 'SetSize';
				case 'SetRatioSize';
				case 'ResizedImage';
				case 'CroppedImage';
				case 'PaddedImage':
					$this->dimensions = $args[1] . 'x' . $args[2];
					break;

				default:
					$this->dimensions = '100x100';
			}
		}
	}


	/**
	 * @return String
	 */
	public function getRelativePath() {
		return $this->getField('Filename');
	}


	/**
	 * Prevent creating new tables for the cached record
	 *
	 * @return false
	 */
	public function requireTable() {
		return false;
	}


	/**
	 * Prevent writing the cached image to the database, but write the store record instead
	 */
	public function write($showDebug = false, $forceInsert = false, $forceWrite = false, $writeComponents = false) {
		throw new Exception("{$this->ClassName} can not be written back to the database.");
	}


	/**
	 * Prevent a delete
	 */
	public function delete() {
		throw new Exception("{$this->ClassName} can not be written back to the database.");
	}


	/**
	 * @param string $dim - 'string' or 0 (width) or 1 (height)
	 * @return int|string
	 */
	function getDimensions($dim = "string") {
		$val = $this->dimensions;
		if ($dim === 'string') return $val;
		$val = explode('x', $val);
		return $val[$dim];
	}
}