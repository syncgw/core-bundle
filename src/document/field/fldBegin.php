<?php
declare(strict_types=1);

/*
 *  Begin field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Config;
use syncgw\lib\XML;

class fldBegin extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 			 = 'Begin';

   	/**
     * 	Singleton instance of object
     * 	@var fldBegin
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldBegin {

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

		return true;
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
		$cnf  = Config::getInstance();
		$tags = explode('/', $xpath);

		switch ($typ) {
		case 'text/x-vnote':
		case 'text/x-vnote':
			$rc = [[ 'T' => $tags[1],		'P' => [], 'D' => $tags[0] ],
				   [ 'T' => 'VERSION', 		'P' => [], 'D' => sprintf('%.1F', $ver) ],
		   		   [ 'T' => 'X-PRODID', 	'P' => [], 'D' => '-//Florian Daeumling//NONSGML syncgw '.
		   		   				($cnf->getVar(Config::DBG_SCRIPT) ? 'x.x.x' : $cnf->getVar(Config::VERSION)) ]];
			break;

		case 'text/vcard':
		case 'text/x-vcard':
			$rc = [[ 'T' => $tags[1],		'P' => [], 'D' => $tags[0] ],
				   [ 'T' => 'VERSION', 		'P' => [], 'D' => sprintf('%.1F', $ver) ],
		   		   [ 'T' => 'PRODID', 		'P' => [], 'D' => '-//Florian Daeumling//NONSGML syncgw '.
		   		   				($cnf->getVar(Config::DBG_SCRIPT) ? 'x.x.x' : $cnf->getVar(Config::VERSION)) ]];
			break;

		case 'text/calendar':
		case 'text/x-vcalendar':
			if ($tags[1] != 'BEGIN')
				$rc = [[ 'T' => 'BEGIN',	'P' => [], 'D' => $tags[1] ]];
			else
				$rc = [[ 'T' => 'BEGIN',	'P' => [], 'D' => $tags[0] ],
					   [ 'T' => 'VERSION', 	'P' => [], 'D' => sprintf('%.1F', $ver) ],
	   		   		   [ 'T' => 'PRODID', 	'P' => [], 'D' => '-//Florian Daeumling//NONSGML syncgw '.
		   		   				($cnf->getVar(Config::DBG_SCRIPT) ? 'x.x.x' : $cnf->getVar(Config::VERSION)) ]];
	   		break;

		default:
			break;
		}

		return $rc;
	}

}
