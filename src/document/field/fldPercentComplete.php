<?php
declare(strict_types=1);

/*
 *  Percent complete field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Msg;
use syncgw\lib\XML;

class fldPercentComplete extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'PercentComplete';

	/*
	 percent = "PERCENT-COMPLETE" pctparam ":" integer CRLF

     pctparam   = *(";" xparam)
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
     * 	@var fldPercentComplete
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldPercentComplete {

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
				// check type
				if (!is_numeric($rec['D'])) {

					Msg::InfoMsg('['.$rec['T'].'] ['.$rec['D'].'] not "integer" - dropping record');
					continue;
				}
				if ($rec['D'] < 0 || $rec['D'] > 100) {

					Msg::InfoMsg('['.$rec['T'].'] value "'.$rec['D'].'" not between "0-100" - dropping record');
					continue;
				}
				// check parameter
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

		if (!$int->xpath($ipath.self::TAG, false))
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
