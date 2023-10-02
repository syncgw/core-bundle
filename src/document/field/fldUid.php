<?php
declare(strict_types=1);

/*
 *  Uid field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Msg;
use syncgw\lib\Config;
use syncgw\lib\XML;

class fldUid extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'UID';

	/*
	 UID-param = UID-uri-param / UID-text-param
     UID-value = UID-uri-value / UID-text-value
       ; Value and parameter MUST match.

     UID-uri-param = "VALUE=uri"
     UID-uri-value = URI

     UID-text-param = "VALUE=text"
     UID-text-value = text

     UID-param =/ any-param
	 */
	const RFCA_PARM			= [
		// description see fldHandler:check()
	    'uri'			  	=> [
		  'VALUE'			=> [ 1, 'uri ' ],
		],
		'text'			 	=> [
		  'VALUE'			=> [ 1, 'text ' ],
		],
	];

	/*
	uid        = "UID" uidparam ":" text CRLF

    uidparam   = *(";" other-param)
	 */
	const RFCC_PARM			= [
		// description see fldHandler:check()
	    'text'			 	=> [
		  'VALUE'			=> [ 1, 'text ' ],
		  '[ANY]'			=> [ 0 ],
		],
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldUid
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldUid {

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
		case 'text/x-vnote':
			foreach ($ext as $rec) {

				if ($rec['T'] != $xpath)
					continue;
				parent::delTag($int, $ipath);
				$int->addVar(self::TAG, $rec['D'], false, $rec['P']);
				$rc = true;
			}
			break;

		case 'text/vcard':
		case 'text/x-vcard':
			foreach ($ext as $rec) {

				if ($rec['T'] != $xpath)
					continue;
				// defaults to type text
				$var = 'text';
				$p = parse_url($rec['D']);
				if (isset($p['scheme']))
					$var = 'uri';
				// check parameter
				parent::check($rec, self::RFCA_PARM[$var]);
				parent::delTag($int, $ipath);
				$rec['P']['VALUE'] = $var;
				$int->addVar(self::TAG, $var == 'text' ? parent::rfc6350($rec['D']) : $rec['D'], false, $rec['P']);
				$rc = true;
			}
			break;

		case 'text/calendar':
		case 'text/x-vcalendar':
			foreach ($ext as $rec) {

				if (strpos($xpath, $rec['T']) === false)
					continue;
				// check parameter
				parent::check($rec, self::RFCC_PARM['text']);
				parent::delTag($int, $ipath);
				unset($rec['P']['VALUE']);
				$int->addVar(self::TAG, parent::rfc5545($rec['D']), false, $rec['P']);
				$rc = true;
			}
			break;

		case 'application/activesync.mail+xml':
		case 'application/activesync.calendar+xml':
	   		if ($ext->xpath($xpath, false))
				parent::delTag($int, $ipath, $typ == 'application/activesync.calendar+xml' ? '16.0' : '');

			while (($val = $ext->getItem()) !== null) {

				if (strlen($val)) {

					$int->addVar(self::TAG, $val);
					$rc = true;
				}
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

		$int->xpath($ipath.self::TAG, false);

		switch ($typ) {
		case 'text/x-vnote':
			$recs = [];
			while (($val = $int->getItem()) !== null) {

				$a = $int->getAttr();
				$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => $val ];
			}
			if (count($recs))
				$rc = $recs;
			else {

				Msg::InfoMsg(' ['.self::TAG.'] adding missing value');
				$cnf = Config::getInstance();
				if ($cnf->getVar(Config::DBG_SCRIPT) || $cnf->getVar(Config::DBG_LEVEL) == Config::DBG_TRACE)
					$int->addVar(self::TAG, $val = '5c9264179f3d9');
				else
					$int->addVar(self::TAG, $val = uniqid());
				$rc = [[ 'T' => $tag, 'P' => [], 'D' => $val ]];
			}
			break;

		case 'text/vcard':
		case 'text/x-vcard':
			$recs = [];
			while (($val = $int->getItem()) !== null) {

				$a = $int->getAttr();
				$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => (isset($a['VALUE']) && $a['VALUE'] == 'text') ?
				            parent::rfc6350($val, false) : $val ];
			}
			if (count($recs))
				$rc = $recs;
			break;

		case 'text/calendar':
		case 'text/x-vcalendar':
			$tags = explode(',', $xpath);
			$tags = $tags[$ver == 1.0 && isset($tags[1]) ? 1 : 0];
			$tags = explode('/', $tags);
			$tag  = array_pop($tags);
			$recs = [];
			while (($val = $int->getItem()) !== null) {

				$a = $int->getAttr();
				$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => parent::rfc5545($val, false) ];
			}
			if (count($recs))
				$rc = $recs;
			break;

		case 'application/activesync.mail+xml':
			if (!class_exists($class = 'syncgw\\activesync\\masHandler') ||
				$class::getInstance()->callParm('BinVer') < 12.0)
				break;

		case 'application/activesync.calendar+xml':
			if (isset($tags[0]) && $tags[0] == fldExceptions::TAG) {

				if (!class_exists($class = 'syncgw\\activesync\\masHandler') ||
					$class::getInstance()->callParm('BinVer') > 2.5)
					break;
			}

			while (($val = $int->getItem()) !== null) {

				$ext->addVar($tag, $val, false, $ext->setCP(XML::AS_CALENDAR));
				$rc	= true;
			}
			break;

		default:
			break;
		}

		return $rc;
	}

}
