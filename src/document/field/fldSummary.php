<?php
declare(strict_types=1);

/*
 *  Summary text field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\XML;

class fldSummary extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'Summary';

	/*
	   summary    = "SUMMARY" summparam ":" text CRLF

       summparam  = *(
                  ;
                  ; The following are OPTIONAL,
                  ; but MUST NOT occur more than once.
                  ;
                  (";" altrepparam) / (";" languageparam) /
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
	    'text'			 	=> [
		  'ALTREP'		   	=> [ 5 ],
		  'LANGUAGE'		=> [ 6 ],
		  '[ANY]'			=> [ 0 ],
		],
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldSummary
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldSummary {

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
		$mver  = '';

		switch ($typ) {
		case 'text/x-vnote':
		case 'text/plain':
			foreach ($ext as $rec) {

				if ($rec['T'] != $xpath)
					continue;
				parent::delTag($int, $ipath);
   				$int->addVar(self::TAG, $rec['D'], false, $rec['P']);
				$rc = true;
			}
			break;

		case 'text/calendar':
		case 'text/x-vcalendar':
			foreach ($ext as $rec) {

				if ($rec['T'] != $xpath)
					continue;
				// check parameter
				parent::check($rec, self::RFCC_PARM['text']);
				parent::delTag($int, $ipath);
				$int->addVar(self::TAG, parent::rfc5545($rec['D']), false, $rec['P']);
				$rc = true;
	  		}
			break;

		case 'application/activesync.calendar+xml':
		case 'application/activesync.task+xml':
			$mver = '16.0';

		case 'application/activesync.note+xml':
		case 'application/activesync.mail+xml':
			if ($ext->xpath($xpath, false))
				parent::delTag($int, $ipath, $mver);

			while (($val = $ext->getItem()) !== null) {

				$int->addVar(self::TAG, $val);
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
		$cp   = null;
		$tags = explode('/', $xpath);
		$tag  = array_pop($tags);
		if (!class_exists($class = 'syncgw\\activesync\\masHandler'))
			return $rc;
		$ver = $class::getInstance()->callParm('BinVer');

		if (!$int->xpath($ipath.self::TAG, false))
			return $rc;

		switch ($typ) {
		case 'text/x-vnote':
		case 'text/plain':
			$recs = [];
			while (($val = $int->getItem()) !== null) {

				$a = $int->getAttr();
				$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => $val ];
			}
			if (count($recs))
				$rc = $recs;
			break;

		case 'text/calendar':
		case 'text/x-vcalendar':
			$recs = [];
			while (($val = $int->getItem()) !== null) {

				$a = $int->getAttr();
				$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => parent::rfc5545($val, false) ];
			}
			if (count($recs))
				$rc = $recs;
			break;

		case 'application/activesync.note+xml':
			if ($ver < 14.0)
				break;

			$cp = XML::AS_NOTE;

		case 'application/activesync.calendar+xml':
			if (!$cp)
				$cp = XML::AS_CALENDAR;

		case 'application/activesync.task+xml':
			if (!$cp)
				$cp = XML::AS_TASK;

		case 'application/activesync.mail+xml':
			if (!$cp)
				$cp = XML::AS_MAIL;

			if (!$cp) {

				if (isset($tags[0]) && $tags[0] == fldFlag::TAG && $ver < 12.0)
					break;

				$cp = XML::AS_MAIL;
			}

			while (($val = $int->getItem()) !== null) {

				$ext->addVar($tag, $val, false, $ext->setCP($cp));
				$rc	= true;
			}
			break;

		default:
			break;
		}

		return $rc;
	}

}
