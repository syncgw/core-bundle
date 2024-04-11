<?php
declare(strict_types=1);

/*
 *  Completion date field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Config;
use syncgw\lib\Msg;
use syncgw\lib\Util;
use syncgw\lib\XML;

class fldCompleted extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'Completed';

	/*
	 completed  = "COMPLETED" compparam ":" date-time CRLF
     compparam  = *(";" xparam)
	 */
	const RFCC_PARM			= [
		// description see fldHandler:check()
	    'date-time'			=> [
		  'VALUE'			=> [ 1, 'date-time ' ],
		  'TZID'			=> [ 0 ],
		  '[ANY]'			=> [ 0 ],
		],
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldCompleted
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldCompleted {

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
			// get time zone
			$p = $int->savePos();
			if (!($tzid = $int->getVar(fldTimezone::TAG)))
				$tzid = 'UTC';
			$int->restorePos($p);

			$del = [];
			foreach ($ext as $rec) {

				if ($rec['T'] != $xpath)
					continue;
				// check type
				$var = 'text';
				$p = date_parse($rec['D']);
				if (!$p['warning_count'] && !$p['error_count']) {

					if ($p['year'] !== false && $p['hour'] !== false)
						$var = 'date-time';
					elseif ($p['year'] !== false)
						$var = 'date';
					elseif ($p['hour'] !== false)
						$var = 'timestamp';
					elseif (isset($p['zone']) || isset($p['tz_id']))
						$var = 'utc-offset';
					else
						$var = 'date-and-or-time';
				}
				if ($var != 'date-time') {

					Msg::InfoMsg('['.$rec['D'].'] "'.$rec['D'].'" wrong type "'.$var.'" - dropping record');
					continue;
				}
				// check parameter
				parent::check($rec, self::RFCC_PARM[$var]);
				if (!isset($del[$ipath])) {

					parent::delTag($int, $ipath);
					$del[$ipath] = 1;
				}
				// time zone set?
				if (isset($rec['P']['TZID'])) {

					if ($tzid = Util::getTZName($rec['P']['TZID'])) {

						$int->updVar(fldTimezone::TAG, $tzid);
						unset($rec['P']['TZID']);
					} else
						// delete unknown time zone
						unset($rec['P']['TZID']);
				}
				$int->addVar(self::TAG, Util::unxTime($rec['D'], $tzid), false, $rec['P']);
				$rc = true;
			}
			break;

		case 'application/activesync.mail+xml':
		case 'application/activesync.task+xml':
			foreach (explode(',', $xpath) as $xpath) {

				if ($ext->xpath($xpath, false))
					parent::delTag($int, $ipath);
				if (($val = $ext->getItem()) !== null && $val) {

					$int->addVar(self::TAG, Util::unxTime($val));
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
		$cp   = null;

		if (!$int->xpath($ipath.self::TAG, false))
			return $rc;

		switch ($typ) {
		case 'text/calendar':
		case 'text/x-vcalendar':
			$p = $int->savePos();
			if (!($tzid = $int->getVar(fldTimezone::TAG)))
				$tzid = 'UTC';
			$int->restorePos($p);

			$recs = [];
	   		while (($val = $int->getItem()) !== null) {

		   		$d = new \DateTime('', new \DateTimeZone($tzid));
				$d->setTimestamp(intval($val));
	   			$a = $int->getAttr();
	   			if ($ver != 1.0 && $tzid && $tzid != 'UTC')
   				   $a['TZID'] = $tzid;
	   			$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => $d->format(isset($a['VALUE']) && $a['VALUE'] == 'date' ?
	   						Config::STD_DATE : Config::UTC_TIME) ];
	   		}
			if (count($recs))
				$rc = $recs;
			break;

		case 'application/activesync.mail+xml':
			if (!class_exists($class = 'syncgw\\activesync\\masHandler') ||
				$class::getInstance()->callParm('BinVer') < 12.0)
				break;
			$cp = XML::AS_MAIL;

		case 'application/activesync.task+xml':
			if (!$cp)
				$cp = XML::AS_TASK;
			if (($val = $int->getItem()) !== null) {

				$ext->addVar($tag, gmdate(Config::masTIME, intval($val)), false, $ext->setCP($cp));
				$rc	= true;
			}
			break;

		default:
			break;
		}

		return $rc;
	}

}
