<?php
declare(strict_types=1);

/*
 *  Importance field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\XML;

class fldImportance extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
    const TAG 				= 'Importance';
	// 						1 - Highest
	//						2 - High
	//						3 - Normal
    // 						4 - Low
    //						5 - Lowest

    // 0 (zero) Low importance
	// 1 Normal importance
	// 2 High importance
    const masIMP 			= [
    	1 => '2',	2 => '2',
    	3 => '1',
    	4 => '0', 	5 => '0',
    ];

   	/**
     * 	Singleton instance of object
     * 	@var fldImportance
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldImportance {

		if (!self::$_obj) {

            self::$_obj = new self();
			// clear tag deletion status
			unset(parent::$Deleted[self::TAG]);
		}

		return self::$_obj;
	}

  	/**
	 * 	Import field
	 *
	 *  @param  - MIME type
	 *  @param  - MIME version
	 *	@param  - External path
	 *  @param  - [[ 'T' => Tag; 'P' => [ Parm => Val ]; 'D' => Data ]] or external document
	 *  @param  - Internal path
	 * 	@param 	- Internal document
	 *  @return - true = Ok; false = Skipped
	 */
	public function import(string $typ, float $ver, string $xpath, $ext, string $ipath, XML &$int): bool {

		$rc    = false;
		$ipath .= self::TAG;

		switch ($typ) {
		case 'application/activesync.mail+xml':
			if ($ext->xpath($xpath, false))
				parent::delTag($int, $ipath);
			$stat = array_flip(self::masIMP);
			while (($val = $ext->getItem()) !== null) {

				$int->addVar(self::TAG, strval($stat[$val]));
				$rc = true;
			}
			break;

		default:
			break;
		}

		return $rc;
	}

	/**
	 * 	Export field
	 *
	 *  @param  - MIME type
	 *  @param  - MIME version
 	 *	@param  - Internal path
	 * 	@param 	- Internal document
	 *  @param  - External path
	 *  @param  - External document
	 *  @return - [[ 'T' => Tag; 'P' => [ Parm => Val ]; 'D' => Data ]] or false=Not found
	 */
	public function export(string $typ, float $ver, string $ipath, XML &$int, string $xpath, ?XML $ext = null) {

		$rc   = false;
		$tags = explode('/', $xpath);
		$tag  = array_pop($tags);

		if (!$int->xpath($ipath.self::TAG, false))
			return $rc;

		switch ($typ) {
		case 'application/activesync.mail+xml':
			while (($val = $int->getItem()) !== null) {

				$ext->addVar($tag, self::masIMP[$val], false, $ext->setCP(XML::AS_MAIL));
				$rc	= true;
			}
			break;

		default:
			break;
		}

		return $rc;
	}

}
