<?php
declare(strict_types=1);

/*
 *  Last modified field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Config;
use syncgw\lib\XML;

class fldLastMod extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 			= 'LastMod';

	/*
	 last-mod   = "LAST-MODIFIED" lstparam ":" date-time CRLF

     lstparam   = *(";" xparam)
	 */
	const RFCC_PARM	  	= [
		// description see fldHandler:check()
	    'date-time'			=> [
		  'VALUE'			=> [ 1, 'date-time ' ],
		  '[ANY]'			=> [ 0 ],
		],
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldLastMod
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldLastMod {

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

		return false;
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
		$ip   = $int->savePos();
		$val  = gmdate(Config::UTC_TIME, intval($int->getVar(self::TAG)));
		$int->restorePos($ip);

		switch ($typ) {
		case 'text/x-vnote':
		case 'text/calendar':
		case 'text/x-vcalendar':
			$rc = [[ 'T' => $tag, 'P' => [], 'D' => $val ]];
			break;

		case 'application/activesync.note+xml':
			if (!class_exists($class = 'syncgw\\activesync\\masHandler') ||
				$class::getInstance()->callParm('BinVer') < 14.0)
				break;
			$ext->addVar($tag, $val, false, $ext->setCP(XML::AS_NOTE));
  		  	$rc = true;
			break;

		case 'application/activesync.doclib+xml':
			if (!class_exists($class = 'syncgw\\activesync\\masHandler') ||
				$class::getInstance()->callParm('BinVer') < 12.0)
				break;
			$ext->addVar($tag, $val, false, $ext->setCP(XML::AS_DocLib));
  		  	$rc = true;
			break;

		default:
			break;
		}

		return $rc;
	}

}
