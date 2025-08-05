<?php
declare(strict_types=1);

/*
 *  Date created field handler
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

class fldCreated extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 			 = 'Created';

	/*
	 REV-param = "VALUE=timestamp" / any-param
     REV-value = timestamp
	 */

    /**
     * 	Singleton instance of object
     * 	@var fldCreated
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldCreated {

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
		case 'application/activesync.doclib+xml':
			foreach (explode(',', $xpath) as $t) {

				if ($ext->xpath($t, false))
					parent::delTag($int, $ipath);

				while (($val = $ext->getItem()) !== null && $val) {

					$int->addVar(self::TAG, Util::unxTime($val));
					$rc = true;
				}
				if ($rc)
					break;
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
		$ip   = $int->savePos();
		$val  = gmdate(Config::UTC_TIME, intval($int->getVar(self::TAG)));
		$int->restorePos($ip);
		$cp   = null;

		switch ($typ) {
		case 'text/x-vnote':
		case 'text/vcard':
		case 'text/x-vcard':
			$rc = [[ 'T' => $tag, 'P' => [], 'D' => $val ]];
			break;

		case 'text/calendar':
		case 'text/x-vcalendar':
			$tags    = explode(',', $xpath);
			$tags[0] = explode('/', $tags[0]);
			$tags[1] = explode('/', $tags[1]);
			$rc = [[ 'T' => strpos($tag, 'VEVENT') && $ver == 1.0 ? array_pop($tags[1]) : array_pop($tags[0]),
					 'P' => [], 'D' => $val ]];
			break;

		case 'application/activesync.doclib+xml':
			if (!class_exists($class = 'syncgw\\activesync\\masHandler') ||
				$class::getInstance()->callParm('BinVer') < 12.0)
				break;
			$cp = XML::AS_DocLib;

		case 'application/activesync.mail+xml':
			if (!$cp)
				$cp = XML::AS_MAIL;

			if (!$int->xpath($ipath.self::TAG, false))
				return $rc;
			while (($val = $int->getItem()) !== null) {

				$ext->addVar($tag, gmdate(Config::masTIME, intval($val)), false, $ext->setCP($cp));
				$rc = true;
			}
			break;

		default:
			break;
		}

		return $rc;
	}

}
