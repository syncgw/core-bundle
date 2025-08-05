<?php
declare(strict_types=1);

/*
 *  Exception start date field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Config;
use syncgw\lib\Util;
use syncgw\lib\XML;

class fldStartTimeException extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'StartTimeException';

   	/**
     * 	Singleton instance of object
     * 	@var fldStartTimeException
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldStartTimeException {

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
		case 'application/activesync.calendar+xml':
			$xp = $ext->savePos();
	   		$ext->restorePos($xp);
			if ($ext->xpath($xpath, false))
				parent::delTag($int, $ipath, '16.0');

			while (($val = $ext->getItem()) !== null && $val)
				$int->addVar(self::TAG, Util::unxTime($val));
			$rc = true;
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
		case 'application/activesync.calendar+xml':
			if (!class_exists($class = 'syncgw\\activesync\\masHandler') ||
				$class::getInstance()->callParm('BinVer') < 14.1)
				break;

			// try to load exception start time
			if (!($val = $int->getItem()) !== null) {

				// check for <InstanceId>
				if (!$int->xpath($ipath.fldExceptions::SUB_TAG[2], false))
					// fall back to <StartTime>
					if ($int->xpath($ipath.fldStartTime::TAG, false))
						$val = $int->getItem();
				else
					$val = $int->getItem();
			}
			if ($val) {

				$ext->addVar($tag, gmdate(Config::UTC_TIME, intval($val)), false, $ext->setCP(XML::AS_CALENDAR));
				$rc = true;
			}
			break;

		default:
			break;
		}

		return $rc;
	}

}
