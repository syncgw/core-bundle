<?php
declare(strict_types=1);

/*
 *  Timezone id field handler
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

class fldTimezone extends fldHandler {

	const TAG 				= 'Timezone';

	/*
	 TZ-param = "VALUE=" ("text" / "uri" / "utc-offset")
     TZ-value = text / URI / utc-offset
       ; Value and parameter MUST match.

     TZ-param =/ altid-param / pid-param / pref-param / type-param
               / mediatype-param / any-param
	 */
	const RFCA_PARM			= [
		// description see fldHandler:check()
	    'text'			 	=> [
		  'VALUE'			=> [ 1, 'text ' ],
		  'PID'			  	=> [ 0 ],
		  'PREF'			=> [ 2, '1-100' ],
		  'ALTID'			=> [ 0 ],
		  'TYPE'			=> [ 1, ' work home x- ' ],
		  '[ANY]'			=> [ 0 ],
		],
		'uri'			  	=> [
		  'VALUE'			=> [ 1, 'uri ' ],
		  'PID'			  	=> [ 0 ],
		  'PREF'			=> [ 2, '1-100' ],
		  'ALTID'			=> [ 0 ],
		  'TYPE'			=> [ 1, ' work home x- ' ],
		  'MEDIATYPE'		=> [ 7 ],
		  '[ANY]'			=> [ 0 ],
		],
		'utc-offset'	   	=> [
		  'VALUE'			=> [ 1, 'utc-offset ' ],
		  'PID'			  	=> [ 0 ],
		  'PREF'			=> [ 2, '1-100' ],
		  'ALTID'			=> [ 0 ],
		  'TYPE'			=> [ 1, ' work home x- ' ],
		  '[ANY]'			=> [ 0 ],
		],
	];

	/*
	   tzid       = "TZID" tzidpropparam ":" [tzidprefix] text CRLF

       tzidpropparam      = *(";" other-param)

       ;tzidprefix        = "/"
       ; Defined previously. Just listed here for reader convenience.
	 */
	const RFCC_PARM			= [
		// description see fldHandler:check()
	    'text'			 	=> [
		  'VALUE'			=> [ 1, 'text' ],
		  '[ANY]'			=> [ 0 ],
		],
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldTimezone
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldTimezone {

		if (!self::$_obj)
            self::$_obj = new self();

		// clear tag deletion status
		unset(parent::$Deleted[self::TAG]);

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
		case 'text/vcard':
		case 'text/x-vcard':

			foreach ($ext as $rec) {

				if ($rec['T'] != $xpath)
					continue;
				$var = 'text';
				$p = parse_url($rec['D']);
				if (isset($p['scheme']))
					$var = 'uri';
				else {

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
				}
				if (strpos('text uri utc-offset', $var) === false) {

					Msg::InfoMsg('['.$rec['D'].'] "'.$rec['D'].'" wrong type "'.$var.'" - dropping record');
					continue;
				}
				// check parameter
				parent::check($rec, self::RFCA_PARM[$var]);
				parent::delTag($int, $ipath);
				if (!$p['error_count']) {

					if (isset($p['tz_id']))
						$rec['D'] = Util::getTZName($p['tz_id']);
					if (isset($p['zone']))
						$rec['D'] = Util::getTZName($p['zone'].'/0');
					unset($rec['P']['VALUE']);
					$int->addVar(self::TAG, $rec['D'], false, $rec['P']);
					$rc = true;
				}
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
				$p = date_parse($rec['D']);
				if (!$p['error_count']) {

					if (isset($p['tz_id']))
						$rec['D'] = Util::getTZName($p['tz_id']);
					if (isset($p['zone']))
						$rec['D'] = Util::getTZName($p['zone'].'/0');
					$int->addVar(self::TAG, $rec['D'], false, $rec['P']);
					$rc = true;
				}
	  		}
			break;

		case 'application/activesync.calendar+xml':
		case 'application/activesync.mail+xml':
			if ($ext->xpath($xpath, false))
				parent::delTag($int, $ipath, $typ == 'application/activesync.calendar+xml' ? '16.0' : '');

			while (($val = $ext->getItem()) !== null) {

				if (!strlen($val))
					continue;
				//
				// https://github.com/horde/ActiveSync/blob/master/lib/Horde/ActiveSync/Timezone.php
				// http://msdn.microsoft.com/en-us/library/ms725481%28VS.85%29.aspx
		   		//
				// typedef struct TIME_ZONE_INFORMATION {
				// 	LONG 		Bias;				// The offset from UTC, in minutes
		   		// 	WCHAR 		StandardName[32];	// It contains an optional description for standard time
				// 	SYSTEMTIME 	StandardDate;		// It contains the date and time when the transition from DST to standard time occurs
		   		// 	LONG 		StandardBias;		// It contains the number of minutes to add to the value of the Bias field during standard time
				// 	WCHAR 		DaylightName[32];	// It contains an optional description for DST
		   		// 	SYSTEMTIME 	DaylightDate;		// It contains the date and time when the transition from standard time to DST occurs.
				// 	LONG 		DaylightBias;		// It contains the number of minutes to add to the value of the Bias field during DST
				// };
		   		//
				// http://msdn.microsoft.com/en-us/library/ms724950%28VS.85%29.aspx
		   		//
				// typedef struct _SYSTEMTIME {
		   		// 	WORD wYear;
				// 	WORD wMonth;
		   		// 	WORD wDayOfWeek;
				// 	WORD wDay;
		   		// 	WORD wHour;
				// 	WORD wMinute;
		   		// 	WORD wSecond;
				// 	WORD wMilliseconds;
		   		// } SYSTEMTIME, *PSYSTEMTIME;
				//

				// -------------------------------------------------------------------------------------------------------------------------
				//
				// PLEASE NOTE:
				//
				// ActiveSync provides all time stamps as UTC (this is "user intent time"). This time zone
				// setting is only used "viewer local time", which means this information is used to display
				// the time in proper time zone on client device, not more! As such we do not heavily analyse
				// the provided time zone information - we only try to find the basic time zone used for viewing.
				//
				// -------------------------------------------------------------------------------------------------------------------------

				$f = 'l'.	'utcOffset/'.

		   			 'Z64'.	'stdName/'.
		   				'v'.'stdYear/'.
		   			 	'v'.'stdMonth/'.
		   			 	'v'.'stdDayOfWeek/'.
			   			'v'.'stdDay/'.
			   			'v'.'stdHour/'.
			   			'v'.'stdMin/'.
			   			'v'.'stdSec/'.
			   			'v'.'stdMS/'.
			   		 	'l'.'stdOffset/'.

			   		 'Z64'.	'dstName/'.
		   				'v'.'dstYear/'.
		   			 	'v'.'dstMonth/'.
		   			 	'v'.'dstDayOfWeek/'.
			   			'v'.'dstDay/'.
			   			'v'.'dstHour/'.
			   			'v'.'dstMin/'.
			   			'v'.'dstSec/'.
			   			'v'.'dstMS/'.
			   		 	'l'.'dstOffset';
				$tz = unpack($f, base64_decode($val));
				// Msg::InfoMsg(base64_decode($rec['D']), 'TimeZone field', 0, 10240);
				if (!self::_isLittleEndian()) {

		   			$tz['utcOffset'] = self::_chbo($tz['utcOffset']);
		   			$tz['stdOffset'] = self::_chbo($tz['stdOffset']);
		   			$tz['dstOffset'] = self::_chbo($tz['dstOffset']);
			  		$tz['stdName']   = iconv('UTF-16BE', 'UTF-8', trim($tz['stdName']));
					$tz['dstName'] 	 = iconv('UTF-16BE', 'UTF-8', trim($tz['dstName']));
				} else {

					$tz['stdName']   = trim($tz['stdName']);
					$tz['dstName']   = trim($tz['dstName']);
				}
				Msg::InfoMsg($tz, 'Time zone buffer');
				$val = '';
				if (strlen($tz['stdName']))
			  		$val = Util::getTZName($tz['stdName']);
				if (!$val)
					$val = Util::getTZName($tz['utcOffset'] * -60 .'/'. ($tz['utcOffset'] + $tz['dstOffset']) * -60);
				if ($val)
					$int->addVar(self::TAG, $val);
				else
					Msg::InfoMsg('Not a valid time zone');
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

		if (!$int->xpath($ipath.self::TAG, false))
			return $rc;

		switch ($typ) {
		case 'text/vcard':
		case 'text/x-vcard':
			$recs = [];
			while (($val = $int->getItem()) !== null) {

				$d 		= new \DateTime('now', new \DateTimeZone($val));
				$recs[] = [ 'T' => $tag, 'P' => $int->getAttr(), 'D' => $d->format('O') ];
			}
			if (count($recs))
				$rc = $recs;
			break;

		case 'text/calendar':
		case 'text/x-vcalendar':
			$recs = [];
			while (($val = $int->getItem()) !== null) {

				if ($val == 'UTC') {

					$a = $int->getAttr();
					// $a['VALUE'] = 'TEXT';
					$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => $val ];
					continue;
				}

				// create time zone object

				// find earliest and latest time in record
				$st = $et = 0;
				foreach ([ fldStartTime::TAG, fldEndTime::TAG, fldDueDate::TAG,
						   fldRecurrence::TAG.'/Until', fldRecurrenceDate::TAG ] as $tag) {

				   	$p = $int->savePos();
				   	$int->xpath('//'.$ipath.$tag);
				   	while (($v = $int->getItem()) !== null) {

				   		if ($tag == fldRecurrenceDate::TAG) {

			   				if ($int->getAttr('VALUE') == 'period') {

			   					list ($s, $e) = explode('/', $v);
				   				// save start time
				  				if ($s < $st || !$st)
					   				$st = $s;
					  			// or end time
					   			if ($s > $et)
					   				$et = $s;
					   			$v = $e;
			   				}
				   		}
			   			// save start time
			  			if ($v < $st || !$st)
		   					$st = $v;
						// or end time
						if ($v > $et)
		   					$et = $v;
				   	}
					$int->restorePos($p);
				}

				// get transitions
				$trans = Util::getTransitions($val, intval($st), intval($et));

				// create DST data
		   		$d = new \DateTime(date('Y', intval($st)).'-01-01', new \DateTimeZone($val));

				if ($ver == 1.0) {

		   			$recs[] = [ 'T' => 'TZ', 'P' => [], 'D' => $d->format('O') ];

					if (!count($trans['DAYLIGHT']))
	   			  		$recs [] = [ 'T' => 'DAYLIGHT', 'P' => [], 'D' => 'false' ];
			  		else {

						// build time zone records
			  			for ($i=0; isset($trans['DAYLIGHT'][$i]); $i++) {

							$recs[] = [ 'T' => 'DAYLIGHT', 'P' => [], 'D' => 'true;'.
									// DST offset
									sprintf('%+03d', $trans['DAYLIGHT'][$i]['offset'] / 3600).sprintf('%02d',
													 abs($trans['DAYLIGHT'][$i]['offset'] % 3600)).';'.
	   								// start DST
									str_replace([ '-', ':' ], [ '', '' ], substr($trans['DAYLIGHT'][$i]['time'], 0, 19)).';'.
									// end DST
									str_replace([ '-', ':' ], [ '', '' ], substr($trans['STANDARD'][$i]['time'], 0, 19)).';'.
									// STD abbrevation
									$trans['STANDARD'][$i]['abbr'].';'.
									// DST abbrevation
									$trans['DAYLIGHT'][$i]['abbr'] ];
						}
		   			}
				} else {

		   			$recs[] = [ 'T' => 'BEGIN', 'P' => [], 'D' => 'VTIMEZONE' ];
				 	$recs[] = [ 'T' => 'TZID',  'P' => [], 'D' => $val ];

					// offset from
					$h = $m = 0;
		   			sscanf(date('O', intval($st)), '%3d%2d', $h, $m);
					$o = $h * 3600 + $m * 60;

		   			// build time zone records
					foreach ([ 'STANDARD', 'DAYLIGHT'] as $k) {

						if (!count($trans[$k]))
							continue;

				 		// no DST available?
						if ($k == 'STANDARD' && !count($trans['DAYLIGHT']))
							break;

						$recs[] = [ 'T' => 'BEGIN', 'P' => [], 'D' => $k ];
		   				$s	    = 0;

						for ($i=0; isset($trans[$k][$i]); $i++) {

							if ($s) {

								$recs[] = [ 'T' => 'RDATE', 'P' => [], 'D' => gmdate(Config::STD_TIME, intval($trans[$k][$i]['ts'])) ];
								continue;
		   					}

		   					$t	    = $o + $trans[$k][$i]['offset'];
		   					$recs[] = [ 'T' => 'TZOFFSETFROM', 'P' => [], 'D' => sprintf('%+03d', $t / 3600).sprintf('%02d', abs($t % 3600)) ];
							$recs[] = [ 'T' => 'TZOFFSETTO', 'P' => [], 'D' => sprintf('%+03d',
										 $trans[$k][$i]['offset'] / 3600).sprintf('%02d', abs(intval($trans[$k][$i]['offset'])) % 3600) ];
							$recs[] = [ 'T' => 'TZNAME', 'P' => [], 'D' => $trans[$k][$i]['abbr'] ];
							$recs[] = [ 'T' => 'DTSTART', 'P' => [], 'D' => gmdate(Config::UTC_TIME, intval($trans[$k][$i]['ts'])) ];

							$s = 1;

						}
						$recs[] = [ 'T' => 'END', 'P' => [], 'D' => $k ];
					}
					$recs[] = [ 'T' => 'END', 'P' => [], 'D' => 'VTIMEZONE' ];
				}
			}
			if (count($recs))
				$rc = $recs;
			break;


		case 'application/activesync.mail+xml':
			$cp = XML::AS_MAIL;

		case 'application/activesync.calendar+xml':

			if (!$cp)
				$cp = XML::AS_CALENDAR;

			while ($val = $int->getItem()) {

				Msg::InfoMsg('Exporting time zone "'.$val.'"');

		   		$tz = [
	   				'utcOffset' 		=> 0,

		   			'stdName'			=> '',
				 		'stdYear'		=> 0,
		   				'stdMonth'  	=> 0,
			   			'stdDayOfWeek'	=> 0,
			   			'stdDay' 		=> 0,
			   			'stdHour' 		=> 0,
			   			'stdMin' 		=> 0,
			   			'stdSec' 		=> 0,
			   			'stdMS' 		=> 0,
			   			'stdOffset'		=> 0,

		   			'dstName'			=> '',
				 		'dstYear'		=> 0,
		   				'dstMonth'  	=> 0,
			   			'dstDayOfWeek'	=> 0,
			   			'dstDay' 		=> 0,
			   			'dstHour' 		=> 0,
			   			'dstMin' 		=> 0,
			   			'dstSec' 		=> 0,
			   			'dstMS' 		=> 0,
		   				'dstOffset'		=> 0,
		  		];

	   			// get new time zone object
	   			$trans = Util::getTransitions($val, gmmktime(0, 0, 0, 1, 1, intval(date('Y'))),
	   												gmmktime(0, 0, 0, 12, 31, intval(date('Y'))));
				// we take only the fist two entries found
				foreach ([ 'STANDARD', 'DAYLIGHT'] as $k) {

					if (!count($trans[$k]))
						continue;

					if ($k == 'STANDARD') {

						$tz['utcOffset'] = $trans[$k][0]['offset'] / -60;
						$t = 'std';
					} else {

						$tz['dstOffset'] = $trans[$k][0]['offset'] / -60 - $tz['utcOffset'];
						$t = 'dst';
					}

					$tz[$t.'Year']  	= gmdate('Y', intval($trans[$k][0]['ts']));
		  			$tz[$t.'Month'] 	= gmdate('n', intval($trans[$k][0]['ts']));
				  	$tz[$t.'DayOfWeek'] = gmdate('w', intval($trans[$k][0]['ts']));
		   		   	$tz[$t.'Day']		= gmdate('W', intval($trans[$k][0]['ts'])) -
		   		   					  	  gmdate('W', strtotime(date('Y-m-01', intval($trans[$k][0]['ts'])))) + 1;
			   		// check if week is last week in month
			   		if (gmdate('t', intval($trans[$k][0]['ts'])) < $tz[$t.'Day'] * 7 + 7)
			   			$tz[$t.'Day'] = 5;
			   		$tz[$t.'Hour'] 	= gmdate('G', intval($trans[$k][0]['ts'] + $trans[$k][0]['offset']));
			   		$tz[$t.'Min'] 	= intval(gmdate('i', intval($trans[$k][0]['ts'])));
			   		$tz[$t.'Sec'] 	= intval(gmdate('s', intval($trans[$k][0]['ts'])));
			   		$tz[$t.'Name'] 	= $trans[$k][0]['abbr'];
	   			}

		   		if (!self::_isLittleEndian()) {

		  			$tz['utcOffset'] = self::_chbo($tz['utcOffset']);
		   			$tz['stdOffset'] = self::_chbo($tz['stdOffset']);
		   			$tz['dstOffset'] = self::_chbo($tz['dstOffset']);
		   		}

		   		Msg::InfoMsg($tz, 'Time zone buffer');
		   		$val = pack('la64vvvvvvvvla64vvvvvvvvl',
			  			 	  $tz['utcOffset'], $tz['stdName'],
			   		   		  $tz['stdYear'], $tz['stdMonth'], $tz['stdDayOfWeek'], $tz['stdDay'],
		   					  $tz['stdHour'], $tz['stdMin'], $tz['stdSec'], $tz['stdMS'],
					   		  $tz['stdOffset'],
		   					  $tz['dstName'],
			   		   		  $tz['dstYear'], $tz['dstMonth'], $tz['dstDayOfWeek'], $tz['dstDay'],
		   					  $tz['dstHour'], $tz['dstMin'], $tz['dstSec'], $tz['dstMS'],
							  $tz['dstOffset']);
				// required for 2.5
				$ext->addVar($tag, base64_encode($val), false, $ext->setCP($cp));
				$rc	= true;
			}
			break;

		default:
			break;
		}

		return $rc;
	}

	/**
	 * Determine if the current machine is little endian.
	 *
	 * @return - true = If endianness is little endian, otherwise false.
	*/
	private function _isLittleEndian(): bool {

		$testint = 0x00FF;
		$p = pack('S', $testint);

		return ($testint === current(unpack('v', $p)));
	}

	/**
	 * Change the byte order of a number. Used to allow big endian machines to
	 * decode the timezone blobs, which are encoded in little endian order.
	 *
	 * @param  - The number to reverse.
	 * @return - The number, in the reverse byte order.
	*/
	private function _chbo(int $num): int {

		$u = unpack('l', strrev(pack('l', $num)));

		return $u[1];
	}

}
