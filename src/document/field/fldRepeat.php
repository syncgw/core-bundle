<?php
declare(strict_types=1);

/*
 *  Repeat field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Msg;
use syncgw\lib\XML;

class fldRepeat extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'Repeat';

	/*
	 repeatcnt  = "REPEAT" repparam ":" integer CRLF
     ;Default is "0", zero.

     repparam   = *(";" xparam)
 	 */
	const RFCC_PARM			= [
		// description see fldHandler:check()
	    'integer'		  	=> [
		  'VALUE'			=> [ 1, 'integer ' ],
		  '[ANY]'			=> [ 0 ],
		],
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldRepeat
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldRepeat {

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
		case 'text/calendar':
		case 'text/x-vcalendar':
			foreach ($ext as $rec) {

				if ($rec['T'] != $xpath)
					continue;
				if (!is_numeric($rec['D'])) {

					Msg::InfoMsg('['.$rec['D'].'] "'.$rec['D'].'" not "integer" - dropping record');
					break;
				}
				parent::check($rec, self::RFCC_PARM['integer']);
				parent::delTag($int, $ipath);
				unset($rec['P']['VALUE']);
				$int->addVar(self::TAG, $rec['D'], false, $rec['P']);
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

		if (!$int->xpath($ipath.self::TAG, false) )
			return $rc;

		switch ($typ) {
		case 'text/calendar':
		case 'text/x-vcalendar':
			$recs = [];
			while (($val = $int->getItem()) !== null) {

				$a = $int->getAttr();
				// $a['VALUE'] = 'INTEGER';
				$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => $val ];
			}
			if (count($recs))
				$rc = $recs;
			break;

		default:
			break;
		}

		return $rc;
	}

}
