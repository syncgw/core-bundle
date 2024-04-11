<?php
declare(strict_types=1);

/*
 *  DateRecurrenceId field handler
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

class fldRecurrenceId extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'RecurrenceId';

	/*
	 recurid    = "RECURRENCE-ID" ridparam ":" ridval CRLF

     ridparam   = *(

                ; the following are optional,
                ; but MUST NOT occur more than once

                (";" "VALUE" "=" ("DATE-TIME" / "DATE)) /
                (";" tzidparam) / (";" rangeparam) /
                ; the following is optional,
                ; and MAY occur more than once

                (";" xparam)

                )

     ridval     = date-time / date
     ;Value MUST match value type
	 */
	const RFCC_PARM			= [
		// description see fldHandler:check()
	    'date-time'			=> [
		  'VALUE'			=> [ 1, 'date-time ' ],
		  'TZID'			=> [ 0 ],
		  '[ANY]'			=> [ 0 ],
		],
		'date'			 	=> [
		  'VALUE'			=> [ 1, 'date ' ],
		  'TZID'			=> [ 0 ],
		  '[ANY]'			=> [ 0 ],
		],
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldRecurrenceId
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldRecurrenceId {

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

			foreach ($ext as $rec) {

				if ($rec['T'] != $xpath)
					continue;
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
				if (strpos('date-time date', $var) === false) {

					Msg::InfoMsg('['.$rec['D'].'] "'.$rec['D'].'" wrong type "'.$var.'" - dropping record');
					continue;
				}
				// check parameter
				parent::check($rec, self::RFCC_PARM[$var]);
				parent::delTag($int, $ipath);
				// time zone set?
				if (isset($rec['P']['TZID']))
					if ($tzid = Util::getTZName($rec['P']['TZID'])) {

						$int->updVar(fldTimezone::TAG, $tzid);
						unset($rec['P']['TZID']);
					} else
						// delete unknown time zone
						unset($rec['P']['TZID']);
				$rec['P']['VALUE'] = $var;
				$int->addVar(self::TAG, Util::unxTime($rec['D'], $tzid), false, $rec['P']);
				$rc = true;
			}
			break;

		case 'application/activesync.mail+xml':
			if ($ext->xpath($xpath, false))
				parent::delTag($int, $ipath);

			while (($val = $ext->getItem()) !== null) {

				$int->addVar(self::TAG, Util::unxTime($val));
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
			$p = $int->savePos();
			if (!($tzid = $int->getVar(fldTimezone::TAG)))
				$tzid = 'UTC';
			$d = new \DateTime('', new \DateTimeZone($tzid));
			$int->restorePos($p);

			$recs = [];
			while (($val = $int->getItem()) !== null) {

				$a = $int->getAttr();
				if ($ver != 1.0 && $tzid && $tzid != 'UTC')
   				   $a['TZID'] = $tzid;
				$d->setTimestamp(intval($val));
	   			$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => $d->format($a['VALUE'] == 'date' ? Config::STD_DATE : Config::UTC_TIME) ];
			}
			if (count($recs))
				$rc = $recs;
			break;

		case 'application/activesync.mail+xml':
			while (($val = $int->getItem()) !== null) {

				$ext->addVar($tag, gmdate(Config::masTIME, intval($val)), false, $ext->setCP(XML::AS_MAIL));
				$rc	= true;
			}
			break;

		default:
			break;
		}

		return $rc;
	}

}
