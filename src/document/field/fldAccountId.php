<?php
declare(strict_types=1);

/*
 *  Account id field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\XML;

class fldAccountId extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 		 	 = 'AccountId';

   	/**
     * 	Singleton instance of object
     * 	@var fldAccountId
     */
    static private $_obj = NULL;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldAccountId {

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
	 *  @return - TRUE = Ok; FALSE = Skipped
	 */
	public function import(string $typ, float $ver, string $xpath, $ext, string $ipath, XML &$int): bool {

		$rc    = FALSE;
		$ipath .= self::TAG;

		switch ($typ) {
		case 'application/activesync.mail+xml':
	   		if ($ext->xpath($xpath, FALSE))
				parent::delTag($int, $ipath);

			while (($val = $ext->getItem()) !== NULL) {

				$int->addVar(self::TAG, $val);
				$rc = TRUE;
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
	 *  @return - [[ 'T' => Tag; 'P' => [ Parm => Val ]; 'D' => Data ]] or FALSE=Not found
	 */
	public function export(string $typ, float $ver, string $ipath, XML &$int, string $xpath, ?XML $ext = NULL) {

		$rc   = FALSE;
		$tags = explode('/', $xpath);
		$tag  = array_pop($tags);

		if (!$int->xpath($ipath.self::TAG, FALSE))
			return $rc;

		switch ($typ) {
		case 'application/activesync.mail+xml':
			if (!class_exists($class = 'syncgw\\activesync\\masHandler') ||
				$class::getInstance()->callParm('BinVer') < 14.1)
				break;

			if (($val = $int->getItem()) === FALSE)
				$val = uniqid('sgw');
			$ext->addVar($tag, $val, FALSE, $ext->setCP(XML::AS_MAIL2));
			$rc	= TRUE;
			break;

		default:
			break;
		}

		return $rc;
	}

}
