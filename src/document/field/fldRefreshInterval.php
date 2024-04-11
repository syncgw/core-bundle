<?php
declare(strict_types=1);

/*
 *  Refresh field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Msg;
use syncgw\lib\Util;
use syncgw\lib\XML;

class fldRefreshInterval extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 					= 'RefreshInterval';

	/*
	   refresh      = "REFRESH-INTERVAL" refreshparam
                    ":" dur-value CRLF
                    ;consisting of a positive duration of time.

       refreshparam = *(
                   ;
                   ; The following is REQUIRED,
                   ; but MUST NOT occur more than once.
                   ;
                   (";" "VALUE" "=" "DURATION") /
                   ;
                   ; The following is OPTIONAL,
                   ; and MAY occur more than once.
                   ;
                   (";" other-param)
                   ;
                   )
	 */
	const RFCC_PARM			= [
		// description see fldHandler:check()
	    'duration'			=> [
		  'VALUE'			=> [ 1, 'duration ' ],
		  '[ANY]'			=> [ 0 ],
		],
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldRefreshInterval
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldRefreshInterval {

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
				$var = 'text';
				if (($val = Util::cnvDuration(true, $rec['D'])) !== null)
					$var = 'duration';
				if ($var != 'duration') {

					Msg::InfoMsg('['.$rec['D'].'] "'.$rec['D'].'" wrong type "'.$var.'" - dropping record');
					continue;
				}
				// check parameter
				parent::check($rec, self::RFCC_PARM[$var]);
				parent::delTag($int, $ipath);
				$rec['P']['VALUE'] = $var;
				$int->addVar(self::TAG, $val, false, $rec['P']);
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
				if ($ver == 1.0)
					unset($a['VALUE']);
				else
					$a['VALUE'] = strtoupper($a['VALUE']);
	   			$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => Util::cnvDuration(false, $val) ];
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
