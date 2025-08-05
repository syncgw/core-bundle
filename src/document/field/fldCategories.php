<?php
declare(strict_types=1);

/*
 *  Categories text field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Msg;
use syncgw\lib\Util;
use syncgw\lib\XML;

class fldCategories extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'Category';

	/*
	 CATEGORIES-param = "VALUE=text" / pid-param / pref-param
                      / type-param / altid-param / any-param
     CATEGORIES-value = text-list
	 */
	const RFCA_PARM			= [
		// description see fldHandler:check()
	    'text'			 	=> [
		  'VALUE'			=> [ 1, 'text ' ],
		  'PID'			  	=> [ 0 ],
		  'PREF'			=> [ 2, '1-100' ],
		  'TYPE'			=> [ 1, ' work home x- ' ],
		  'ALTID'			=> [ 0 ],
		  '[ANY]'			=> [ 0 ],
		],
	];

	/*
	   categories = "CATEGORIES" catparam ":" text *("," text)
                    CRLF

       catparam   = *(
                  ;
                  ; The following is OPTIONAL,
                  ; but MUST NOT occur more than once.
                  ;
                  (";" languageparam ) /
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
		  'VALUE'			=> [ 1, 'text ' ],
		  'LANGUAGE'		=> [ 6 ],
		  '[ANY]'			=> [ 0 ],
		],
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldCategories
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldCategories {

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
			foreach ($ext as $rec) {

				if (strpos($xpath, $rec['T'].',') === false)
					continue;
				foreach (Util::unfoldStr($rec['D'], ',', 0) as $val) {

					parent::delTag($int, $ipath);
					$int->addVar(self::TAG, $val, false, $rec['P']);
					$rec['P'] = [];
					$rc	      = true;
				}
			}
			break;

		case 'text/vcard':
		case 'text/x-vcard':
		    foreach ($ext as $rec) {

				if (strpos($xpath, $rec['T'].',') === false)
					continue;
				// check parameter
				parent::check($rec, self::RFCA_PARM['text']);
				foreach (Util::unfoldStr(str_replace("\,", "\#", $rec['D']), ',', 0) as $val) {

					parent::delTag($int, $ipath);
					unset($rec['P']['VALUE']);
					$val = parent::rfc6350(str_replace('\#', ',', $val));
					$int->addVar(self::TAG, $val, false, $rec['P']);
					$rec['P'] = [];
					$rc	      = true;
				}
			}
			break;

		case 'text/calendar':
		case 'text/x-vcalendar':
			foreach ($ext as $rec) {

				if (strpos($xpath, $rec['T'].',') === false)
					continue;
				// check parameter
				parent::check($rec, self::RFCC_PARM['text']);
				foreach (Util::unfoldStr(str_replace("\,", "\#", $rec['D']), ',', 0) as $val) {

					parent::delTag($int, $ipath);
					unset($rec['P']['VALUE']);
					$val = parent::rfc5545(str_replace('\#', ',', $val));
					$int->addVar(self::TAG, $val, false, $rec['P']);
					$rec['P'] = [];
					$rc	      = true;
				}
			}
			break;

		case 'application/activesync.note+xml':
		case 'application/activesync.contact+xml':
		case 'application/activesync.calendar+xml':
			if (!$mver)
				$mver = '16.0';

		case 'application/activesync.task+xml':
		case 'application/activesync.mail+xml':
			if ($ext->xpath($xpath.'/Category', false))
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
		$tag  = str_replace(',', '', $tag);

		if (!$int->xpath($ipath.self::TAG, false))
			return $rc;

		switch ($typ) {
		case 'text/x-vnote':
			$val = [];
			$a   = null;
			while (($v = $int->getItem()) !== null) {

				if (!$a)
					$a = $int->getAttr();
				$val[] = $v;
			}
			if (count($val))
				$rc = [[ 'T' => $tag, 'P' => $a, 'D' => Util::foldStr($val, ',') ]];
			break;

		case 'text/vcard':
		case 'text/x-vcard':
			$val = [];
			$a   = null;
			while (($v = $int->getItem()) !== null) {

				if (!$a)
					$a = $int->getAttr();
				$val[] = parent::rfc6350($v, false);
			}
			$tags = explode(',', $xpath);
			if (count($val)) {

				$tags[0] = explode('/', $tags[0]);
				$tags[1] = explode('/', $tags[1]);
				$rc = [[ 'T' => $ver != 1.0 ? array_pop($tags[0]) : array_pop($tags[1]),
						 'P' => $a, 'D' => Util::foldStr($val, ',') ]];
			}
			break;

		case 'text/calendar':
		case 'text/x-vcalendar':
			$val = [];
			$a   = null;
			while (($v = $int->getItem()) !== null) {

				if (!$a)
					$a = $int->getAttr();
				$val[] = parent::rfc5545($v, false);
			}
			$tags = explode(',', $xpath);
			if (count($val)) {

				$tags[0] = explode('/', $tags[0]);
				$tags[1] = explode('/', $tags[1]);
				$rc = [[ 'T' => $ver != 1.0 ? array_pop($tags[0]) : array_pop($tags[1]),
						 'P' => $a, 'D' => Util::foldStr($val, ',') ]];
			}
			break;

		case 'application/activesync.note+xml':
			if (!class_exists($class = 'syncgw\\activesync\\masHandler') ||
				$class::getInstance()->callParm('BinVer') < 14.0)
				break;
			$cp = XML::AS_NOTE;

	   case 'application/activesync.contact+xml':
			if (!$cp)
				$cp = XML::AS_CONTACT;

		case 'application/activesync.calendar+xml':
			if (!$cp)
				$cp = XML::AS_CALENDAR;

		case 'application/activesync.task+xml':
			if (!$cp)
				$cp = XML::AS_TASK;

		case 'application/activesync.mail+xml':
			if (!$cp) {
				if (!class_exists($class = 'syncgw\\activesync\\masHandler') ||
					$class::getInstance()->callParm('BinVer') < 14.0)
					break;
				$cp = XML::AS_MAIL;
			}

			$n  = 0;
			$xp = null;
			while (($val = $int->getItem()) !== null) {

				if (!$rc) {

					$xp = $ext->savePos();
		   		  	$ext->addVar('Categories', null, false, $ext->setCP($cp));
		   		  	$rc = true;
				}
				$ext->addVar(self::TAG, $val);
				// check maximum number of categries
				if (++$n == 300) {

					Msg::InfoMsg('['.$ipath.'] max. number reached');
					break;
		   		}
			}
			if ($xp)
				$ext->restorePos($xp);
			break;

		default:
			break;
		}

		return $rc;
	}

}
