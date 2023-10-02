<?php
declare(strict_types=1);

/*
 *  Recurrence field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Config;
use syncgw\lib\Msg;
use syncgw\lib\Util;
use syncgw\lib\XML;
use syncgw\lib\DataStore;

class fldRecurrence extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'Recurrence';

	const RFC_SUB		  	= [
		'FREQ'				=> 'Frequency',			// SECONDLY, MINUTELY, HOURLY, DAILY, WEEKLY, MONTHLY, YEARLY
		'X-START'			=> fldStartTime::TAG,	// recurrence start date
		'UNTIL'			  	=> fldEndTime::TAG,	    // recurrence end date
		'COUNT'				=> 'Count',				// 1-n; 1=default
		'INTERVAL'		 	=> 'Interval',			// 1-n; 1=default

		'BYSECOND'		   	=> 'Second',			// [0-60[,]] (absolute)
		'BYMINUTE'		   	=> 'Minute',			// [0-60[,]] (absolute)
		'BYHOUR'			=> 'Hour',				// [0-23[,]] (absolute)
		'BYMONTH'			=> 'Month',				// [1-12[,]] (absolute)

		'BYYEARDAY'		  	=> 'YearDay',			// [[+/-]1-366[,]] (relativ)
		'BYSETPOS'	   		=> 'YearDayPos',		// [[+/-]1-366[,]] (relativ)
		'BYDAY'		   		=> 'DayPos',			// [[+/-][1-53]SU-SA[,]] (relative)
		'BYMONTHDAY'		=> 'MonthDay',			// [[+/-]1-31; -1=last day of month[,]] (relative)
		'BYWEEKNO'		 	=> 'YearWeek',			// [[+/-]1-53; -1=last week in year[,]] (relative)

		'WKST'				=> 'WeekStart',			// SU-SA
	];

	/*
	 rrule      = "RRULE" rrulparam ":" recur CRLF

     rrulparam  = *(";" xparam)
	 */
	const RFCC_PARM			= [
		// description see fldHandler:check()
	    'recur'				=> [
		  'VALUE'			=> [ 1, 'recur ' ],
		  'RANGE'			=> [ 1, ' thisandfuture ' ],
		  '[ANY]'			=> [ 0 ],
		],
	];
	// Day translation table (v1.0)
	const RFCC_DAY		 	= [
		'SU'			   	=> 0,
		'MO'			   	=> 1,
		'TU'			   	=> 2,
		'WE'			   	=> 3,
		'TH'			   	=> 4,
		'FR'			   	=> 5,
		'SA'			   	=> 6,
		'X-LASTDAY'			=> 0,
	];
	const RFCC_TYP		 	= [
		'H'					=> 'HOURLY',
		'D'					=> 'DAILY',
		'W'					=> 'WEEKLY',
		'M'					=> 'MONTHLY',
		'Y'					=> 'YEARLY',
	];

	// ActiveSync sub tags
	const ASC_SUB		  	= [
		'CalendarType'	 	=> [ 14.0, 	XML::AS_CALENDAR,	'fldCalendarType',	],	// specifies the calendar system used by the recurrence
		'DayOfMonth'		=> [ 2.5,	XML::AS_CALENDAR,	'MonthDay',			],	// [[+/-]1-31; -1=last day of month[,]] (relative)
		'DayOfWeek'		   	=> [ 2.5, 	XML::AS_CALENDAR,	'DayPos',			],	// [[+/-][1-53]SU-SA[,]] (relative)
		'FirstDayOfWeek'	=> [ 14.1,	XML::AS_CALENDAR,	'WeekStart',		],	// SU-SA
		'Interval'		 	=> [ 2.5, 	XML::AS_CALENDAR,	'Interval',			],	// 1-n; 1=default
		'IsLeapMonth'	  	=> [ 14.0,	XML::AS_CALENDAR,	'fldIsLeap',	    ],	// specifies whether the recurrence of the appointment takes place on the embolismic (leap) month
		'MonthOfYear'		=> [ 2.5,	XML::AS_CALENDAR,	'Month',			],	// [1-12[,]] (absolute)
		'Occurrences'		=> [ 2.5, 	XML::AS_CALENDAR,	'Count',			],	// 1-n; 1=default
		'Type'				=> [ 2.5, 	XML::AS_CALENDAR,	'Frequency',		],	// SECONDLY, MINUTELY, HOURLY, DAILY, WEEKLY, MONTHLY, YEARLY
		'Until'		  		=> [ 2.5, 	XML::AS_CALENDAR,	'fldEndTime',	    ], 	// recurrence end date
		'WeekOfMonth'		=> [ 2.5, 	XML::AS_CALENDAR,	'WeekOfMonth',		], 	// [[+/-]1-53; -1=last week in year[,]] (relative)
	];
	const AST_SUB		  	= [
		'CalendarType'	 	=> [ 14.0, 	XML::AS_TASK,		'fldCalendarType',],	// specifies the calendar system used by the recurrence
		'DayOfMonth'		=> [ 2.5,	XML::AS_TASK,		'MonthDay',			],	// [[+/-]1-31; -1=last day of month[,]] (relative)
		'DeadOccur'			=> [ 2.5,	XML::AS_TASK,		'Dead',				],	// specifies whether the task is an instance of a recurring task
		'DayOfWeek'		   	=> [ 2.5, 	XML::AS_TASK,		'DayPos',			],	// [[+/-][1-53]SU-SA[,]] (relative)
		'FirstDayOfWeek'	=> [ 14.1,	XML::AS_TASK,		'WeekStart',		],	// SU-SA
		'Interval'		 	=> [ 2.5, 	XML::AS_TASK,		'Interval',			],	// 1-n; 1=default
		'IsLeapMonth'	  	=> [ 14.0,	XML::AS_TASK,		'fldIsLeap',	    ],	// specifies whether the recurrence of the appointment takes place on the embolismic (leap) month
		'MonthOfYear'		=> [ 2.5,	XML::AS_TASK,		'Month',			],	// [1-12[,]] (absolute)
		'Occurrences'		=> [ 2.5, 	XML::AS_TASK,		'Count',			],	// 1-n; 1=default
		'Regenerate'		=> [ 2.5, 	XML::AS_TASK,		'Regenerate',		],	// specifies whether this task item regenerates after it is completed
		'Start'				=> [ 2.5,	XML::AS_TASK,		'fldStartTime', 	], 	// recurrence start date
		'Type'				=> [ 2.5, 	XML::AS_TASK,		'Frequency',		],	// SECONDLY, MINUTELY, HOURLY, DAILY, WEEKLY, MONTHLY, YEARLY
		'Until'		  		=> [ 2.5, 	XML::AS_TASK,		'fldEndTime',	    ], 	// recurrence end date
		'WeekOfMonth'		=> [ 2.5, 	XML::AS_TASK,		'WeekOfMonth',		], 	// [[+/-]1-53; -1=last week in year[,]] (relative)
	];
	const ASM_SUB		  	= [
		'CalendarType'	 	=> [ 14.0, 	XML::AS_MAIL2,		'fldCalendarType',],	// specifies the calendar system used by the recurrence
		'DayOfMonth'		=> [ 2.5,	XML::AS_MAIL,		'MonthDay',			],	// [[+/-]1-31; -1=last day of month[,]] (relative)
		'DayOfWeek'		   	=> [ 2.5, 	XML::AS_MAIL,		'DayPos',			],	// [[+/-][1-53]SU-SA[,]] (relative)
		'FirstDayOfWeek'	=> [ 14.1,	XML::AS_MAIL2,		'WeekStart',		],	// SU-SA
		'Interval'		 	=> [ 2.5, 	XML::AS_MAIL,		'Interval',			],	// 1-n; 1=default
		'IsLeapMonth'	  	=> [ 14.0,	XML::AS_MAIL2,		'fldIsLeap',	    ],	// specifies whether the recurrence of the appointment takes place on the embolismic (leap) month
		'MonthOfYear'		=> [ 2.5,	XML::AS_MAIL,		'Month',			],	// [1-12[,]] (absolute)
		'Occurrences'		=> [ 2.5, 	XML::AS_MAIL,		'Count',			],	// 1-n; 1=default
		'Type'				=> [ 2.5, 	XML::AS_MAIL,		'Frequency',		],	// SECONDLY, MINUTELY, HOURLY, DAILY, WEEKLY, MONTHLY, YEARLY
		'WeekOfMonth'		=> [ 2.5, 	XML::AS_MAIL,		'WeekOfMonth',		], 	// [[+/-]1-53; -1=last week in year[,]] (relative)
	];

	// Parameter		   	Frequency translation table
	const AS_FREQ		  	= [
		'0'					=> 'DAILY',		  		// Recurs daily
		'1'					=> 'WEEKLY',		 	// Recurs weekly
		'2'					=> 'MONTHLY',			// Recurs monthly
		'3'					=> 'MONTHLY',			// Recurs monthly on the nth day
		'5'					=> 'YEARLY',		 	// Recurs yearly
		'6'					=> 'YEARLY',		 	// Recurs yearly on the nth day
	];
	// Parameter		   	Week day translation table
	const AS_WDAY		  	= [
		0x01			   	=> 'SU',
		0x02			   	=> 'MO',
		0x04			   	=> 'TU',
		0x08			   	=> 'WE',
		0x10			   	=> 'TH',
		0x20			   	=> 'FR',
	//  62				 	=> 'MO,TU,WE,TH,FR',
		0x40			   	=> 'SA',
	//  65				 	=> 'SU,SA',
		0x80			   	=> 'X-LASTDAY',
	];
	// Parameter		   	Day translation table
	const AS_DAY		   	= [
		0				  	=> 'SU',
		1				  	=> 'MO',
		2				  	=> 'TU',
		3				  	=> 'WE',
		4				  	=> 'TH',
		5				  	=> 'FR',
		6				  	=> 'SA',
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldRecurrence
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldRecurrence {

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
		$map   = null;

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

				// check parameter
				parent::check($rec, self::RFCC_PARM['recur']);
				parent::delTag($int, $ipath);
				if (($ver == '2.0' && $rec['D'][0] != 'F') || ($ver != '2.0' && $rec['D'][0] == 'F')) {

					Msg::InfoMsg('Processing skipped by intention');
					$rc = true;
				 	continue;
				}

				$ip = $int->savePos();
   				$int->addVar(self::TAG);

   				// the easy way...
				if ($ver == 2.0) {

					foreach (explode(';', $rec['D']) as $v) {

						list($key, $val) = explode('=', $v);
						foreach (self::RFC_SUB as $k => $tag) {

							if ($key == $k) {

								if ($k == 'UNTIL' || $k == 'X-START')
									$val = Util::unxTime($val, $tzid);
								$int->addVar($tag, $val);
							}
						}
					}
					$rc = true;
					$int->restorePos($ip);
					continue;
				}

				// reformat v1.0

				// we do not support "early termination"
				$parm = explode(' ', str_replace('$', '', $rec['D']));
				// scan through parameter
				$int->addVar(self::RFC_SUB['FREQ'], self::RFCC_TYP[$freq = $parm[0][0]]);

				$parm[0] = substr($parm[0], 1);
				if ($freq == 'Y') {

	   				if ($parm[0][0] == 'D') {

			  			$key = 'BYYEARDAY';
						$parm[0] = substr($parm[0], 1);
	   				} elseif ($parm[0][0] == 'M') {

						$key = 'BYMONTH';
		   				$parm[0] = substr($parm[0], 1);
	   				} elseif ($parm[0][0] == 'P') {

	   					$key = 'BYSETPOS';
		   				$parm[0] = substr($parm[0], 1);
	   				}
	   			} elseif ($freq == 'M') {

	   				if ($parm[0][0] == 'D') {

			  			$key = 'BYDAY';
						$parm[0] = substr($parm[0], 1);
	   				} elseif ($parm[0][0] == 'M') {

						$key = 'BYMONTH';
		   				$parm[0] = substr($parm[0], 1);
	   				} elseif ($parm[0][0] == 'P') {

	   					$key = 'BYDAY';
		   				$parm[0] = substr($parm[0], 1);
	   				}
	   			} elseif ($freq == 'H') {

	   				if ($parm[0][0] == 'M') {

	   					$key = 'BYMINUTE';
		   				$parm[0] = substr($parm[0], 1);
	   				}
	   			} else
	   				$key = 'BYDAY';

				if ($parm[0] && $parm[0] > 1)
					$int->addVar(self::RFC_SUB['INTERVAL'], $parm[0]);

				$val  = '';
				$pref = '';
				for ($i=1; isset($parm[$i]); $i++) {

					// special hack for last day
					if ($parm[$i] == 'LD')
						$parm[$i] = '1-';
					if (($v = substr($parm[$i], -1)) == '-' || $v == '+')
						$parm[$i] = $v.substr($parm[$i], 0, -1);
					if (isset(self::RFCC_DAY[$parm[$i]])) {

						$val .= ($val ? ',' : '').$pref.$parm[$i];
						$pref = '';
					} elseif (is_numeric($parm[$i]))
						$pref .= ($pref ? ',' : '').$parm[$i];
				}

				if ($freq == 'M' && !$val) {

					$key = 'BYMONTHDAY';
					$val = $pref;
				}
				if ($freq == 'Y' && !$val)
					$val = $pref;

				if ($val)
					$int->addVar(self::RFC_SUB[$key], $val);

				// duration specified?
				if ($parm[--$i][0] == '#') {

					if (($val = substr($parm[$i], 1)) > 0)
						$int->addVar(self::RFC_SUB['COUNT'], $val);
				} else
					$int->addVar(self::RFC_SUB['UNTIL'], Util::unxTime($parm[$i], $tzid));
				$int->restorePos($ip);
				$rc = true;
	  		}
			break;

        case 'application/activesync.calendar+xml':
			$map = self::ASC_SUB;

        case 'application/activesync.task+xml':
			if (!$map)
				$map = self::AST_SUB;

        case 'application/activesync.mail+xml':
			if (!$map)
				$map = self::ASM_SUB;

        	if ($ext->xpath($xpath, false))
				parent::delTag($int, $ipath, $typ == 'application/activesync.calendar+xml' ? '16.0' : '');

			$ip = $int->savePos();
			$int->addVar(self::TAG);
			$ip1 = $int->savePos();

			while ($ext->getItem() !== null) {

				$xp = $ext->savePos();
	    	    foreach ($map as $key => $parm) {

	        		if (substr($parm[2], 0, 3) == 'fld') {

	        	       	$class = 'syncgw\\document\\field\\'.$parm[2];
	               		$field = $class::getInstance();
						if ($field->import($typ, $ver, $key, $ext, $ipath.'/', $int))
							$rc = true;
	        		} elseif (($val = $ext->getVar($key, false)) !== null) {

	        			switch ($parm[2]) {
	        			case 'Frequency':
	        				$val = self::AS_FREQ[$val];
	        				break;

	        			case 'WeekOfMonth':
							// last week of month
							if ($val == 5)
		 						$val = '-1';
		 					break;

	        			case 'DayPos':
							$v = '';
							foreach (self::AS_WDAY as $bit => $d) {

								if ($val & $bit)
									$v .= ($v ? ',' : '').$d;
							}
							$val = $v;
							break;

						case 'WeekStart':
			 				$val = self::AS_DAY[$val];

						case 'Dead':
						case 'Regenerate':
		   					// 0 false (do not regenerate)
							// 1 true (regenerate)
							// The default value of the DeadOccur element is 0
							if ($val == '0')
								$val = '';

						// case 'MonthDay':
						// case 'Month:
		 				// case 'Interval':
	        			// case 'Count':
						default:
	       					break;
	        			}
	        			if (strlen($val)) {
			 				$int->addVar($parm[2], strval($val));
	        				$rc = true;
	        			}
	        		}
		    		$int->restorePos($ip1);
	        		$ext->restorePos($xp);
	    	    }
			}
	    	$int->restorePos($ip);
			if (!$rc)
				$int->delVar(self::TAG, false);
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
		$map  = null;

		if (!$int->xpath($ipath.self::TAG, false))
			return $rc;

		switch ($typ) {
		case 'text/calendar':
		case 'text/x-vcalendar':
			$p = $int->savePos();
			if (!($tzid = $int->getVar(fldTimezone::TAG)))
				$tzid = 'UTC';
			$int->restorePos($p);
			$d    = new \DateTime('', new \DateTimeZone($tzid));
			$fmt  = $tzid != 'UTC' ? Config::STD_TIME : Config::UTC_TIME;

			$recs = [];
			while ($int->getItem() !== null) {

				// the easy way...
				if ($ver == 2.0) {

					$val = '';
					$p = $int->savePos();
					foreach (self::RFC_SUB as $key => $t) {

						if ($v = $int->getVar($t, false)) {

							if ($key == 'UNTIL' || $key == 'X-START') {

						  		$d->setTimestamp(intval($v));
								$v = $d->format($fmt);
							} elseif ($key == 'BYDAY')
								$v = str_replace('X-LASTDAY', '', $v);
							$val .= ($val ? ';' : '').$key.'='.$v;
						}
						$int->restorePos($p);
					}
					$recs[] = [ 'T' => $tag, 'P' => [], 'D' => $val ];
					continue;
				}

				$parm = [];
				$val  = '';
				$p	  = $int->savePos();
				foreach (self::RFC_SUB as $key => $t) {

					if (!($v = $int->getVar($t, false)))
						$v = '';
					$parm[$key] = $v;
					$int->restorePos($p);
				}

		 		// convert dates
				foreach ($parm as $k => $v) {

					if (isset($v['UNTIL'])) {

						$d->setTimestamp(intval($parm['UNTIL']));
	   					$parm[$k]['UNTIL'] = $d->format($fmt);
 					}
				}

				switch ($parm['FREQ']) {
				case 'SECONDLY':
					$val .= 'S'.(!$parm['INTERVAL'] ? '1' : $parm['INTERVAL']).' '.
				  			($parm['UNTIL'] ? $parm['UNTIL'] : ($parm['COUNT'] ? '#'.$parm['COUNT'] : '#0')).' ';
		   			break;

				case 'MINUTELY':
					$val .= 'M'.(!$parm['INTERVAL'] ? '1' : $parm['INTERVAL']).' '.
				  			($parm['UNTIL'] ? $parm['UNTIL'] : ($parm['COUNT'] ? '#'.$parm['COUNT'] : '#0')).' ';
		   			break;

	   			case 'HOURLY':
   		   			$val .= 'M60 '.($parm['UNTIL'] ? $parm['UNTIL'] : ($parm['COUNT'] ? '#'.$parm['COUNT'] : '#0')).' ';
   				 	break;

   	   			case 'DAILY':
   		   			$val .= 'D'.(!$parm['INTERVAL'] ? '1' : $parm['INTERVAL']).' ';
					if ($parm['BYHOUR'])
   	   					$val .= str_replace(',', '00 ', $parm['BYHOUR'].',');
   		  			$val .= ($parm['UNTIL'] ? gmdate($fmt, intval($parm['UNTIL'])) : ($parm['COUNT'] ? '#'.$parm['COUNT'] : '#0')).' ';
   				 	break;

   	   			case 'WEEKLY':
   		   			$val .= 'W'.(!$parm['INTERVAL'] ? '1' : $parm['INTERVAL']).' ';
   				 	if ($parm['BYDAY'])
						$val .= str_replace(',', ' ', $parm['BYDAY']).' ';
   	   				$val .= ($parm['UNTIL'] ? gmdate($fmt, intval($parm['UNTIL'])) : ($parm['COUNT'] ? '#'.$parm['COUNT'] : '#0')).' ';
   		   			break;

				case 'MONTHLY':
   		   			if ($parm['BYDAY'])
   				 		$val .= 'MP';
					else
   	   					$val .= 'MD';
					$val .= (!$parm['INTERVAL'] ? '1' : $parm['INTERVAL']).' ';
					foreach ([ 'BYDAY', 'BYMONTHDAY' ] as $t) {

	   					$days = explode(',', $parm[$t]);
   			  			foreach ($days as $v) {

   							// extract occurance
   							$sign = '';
   			  				if (strlen($v)) {

   			  					if ($v == '-1')
   			  						$v = 'LD';
   			  					if ($v[0] == '-') {

   			  						$sign = '-';
	   	   							$v = substr($v, 1);
   			  					} elseif ($v[0] == '+') {

   			  						$sign = '+';
   			  						$v = substr($v, 1);
   			  					}
   			  					$occ = '';
   				 				while (isset($v[0]) && is_numeric($c = $v[0])) {

	   								$occ .= $c;
   							   		$v	= substr($v, 1);
	  							}
	   							$val .= (strlen($occ.$sign) ? $occ.$sign.' ' : '').($v ? $v.' ' : '');
   			  				}
   			  			}
					}
   		   			$val .= ($parm['UNTIL'] ? gmdate($fmt, intval($parm['UNTIL'])) : ($parm['COUNT'] ? '#'.$parm['COUNT'] : '#0')).' ';
	   				break;

   		   		case 'YEARLY':
   				 	$p = '';
	   				if ($parm['BYMONTH']) {

   						$val .= 'YM';
   		   				$p	= str_replace(',', ' ', $parm['BYMONTH']);
	   				} elseif ($parm['BYYEARDAY']) {

   						$val .= 'YD';
		   				$p	= str_replace(',', ' ', $parm['BYYEARDAY']);
					} else
   						$val .= 'YM';
   					$val .= ($parm['INTERVAL'] ? $parm['INTERVAL'].' ' : '1 ').($p ? $p.' ' : '').
   		   					($parm['UNTIL'] ? gmdate($fmt, intval($parm['UNTIL'])) : ($parm['COUNT'] ? '#'.$parm['COUNT'].' ' : '#0 ')).' ';
   				 	break;

	   			default:
   					break;
   				}
   				$recs[] = [ 'T' => $tag, 'P' => $int->getAttr(), 'D' => trim($val) ];
			}
			if (count($recs))
				$rc = $recs;
			break;

        case 'application/activesync.calendar+xml':
        	$map = self::ASC_SUB;
        	$cp  = XML::AS_CALENDAR;

		case 'application/activesync.task+xml':
			if (!$map) {
				$map = self::AST_SUB;
        		$cp  = XML::AS_TASK;
			}

        case 'application/activesync.mail+xml':
        	if (!$map) {
        		$map = self::ASM_SUB;
        		$cp  = XML::AS_MAIL;
        	}

			if (!class_exists($class = 'syncgw\\activesync\\masHandler'))
				break;

			$ver = $class::getInstance()->callParm('BinVer');
	        $xp  = $ext->savePos();

			if ($typ == 'application/activesync.mail+xml')
				$ext->addVar('Recurrences', null, false, $ext->setCP($cp));

			while ($int->getItem() !== null) {

	            $ip = $int->savePos();
				$ext->addVar('Recurrence', null, false, $ext->setCP($cp));
	            foreach ($map as $key => $parm) {

	            	// check version
	            	if ($ver < $parm[0])
	            		continue;
	            	// check for class
					if (substr($parm[2], 0, 3) == 'fld') {

						$class = 'syncgw\\document\\field\\'.$parm[2];
	               		$field = $class::getInstance();
                    	$field->export($typ, $ver, '', $int, $key ? $key : $xpath, $ext);
					} elseif (($val = $int->getVar($parm[2], false)) !== null) {

						switch ($parm[2]) {
						case 'Frequency':
							if (($val = array_search($val, self::AS_FREQ)) > 1) {

								if ($int->getVar('Day', false) !== null)
									$val++;
								$int->restorePos($ip);
								if ($int->getVar('DayPos', false) !== null)
									$val++;
							}
							$val = strval($val);
							break;

						case 'WeekOfMonth':
							// we take only first value
							$val = explode(',', $val);
							if ($val[0] == '-1')
						 		$val = '5';
			  				else
			  					$val = $val[0];
							break;

						case 'DayPos':
			   				$v = 0;
							foreach (explode (',', $val) as $d)
								$v |= array_search($d, self::AS_WDAY);
		   					$val = strval($v);
							break;

						case 'MonthDay':
							// we take only first value
							$val = explode(',', $val);
							if ($val[0] == '-1')
								$val = '127';
					   		else
				  				$val = $val[0];
							break;

						case 'WeekStart':
					   		$val = strval(array_search($val, self::AS_DAY));
							break;

						case 'Dead':
						case 'Regenerate':

						// case 'Month':
						// case 'Interval':
						// case 'Count':
						default:
							break;
						}
						if (strlen($val))
							$ext->addVar($key, strval($val), false, $ext->setCP($parm[1]));
					}
					$int->restorePos($ip);
		            $rc = true;
	            }
       		}
       		$ext->restorePos($xp);
       		break;

		default:
			break;
		}

		return $rc;
	}

	/**
	 * 	Regenerate task or calendar document
	 *
	 *	@parm 	- Handler id
	 * 	@parm 	- XML document
	 * 	@parm 	- Unix time stamp to catch (0= now)
	 * 	@return - Regernerated document; null = Could not regenerate
	 */
	static public function regenerate(int $hid, XML $doc, int $catch = 0): ?XML {

		$xml = new XML($doc);

		// is task completed?
		if ($hid & DataStore::TASK) {

			if ($xml->getVar(fldCompleted::TAG) ||
				// recurrence given?
				$xml->getVar(self::TAG) === null &&
				// regeneration allowed?
				!$xml->getVar(self::AST_SUB['Regenerate'][2]))
				return null;

			// check for counter
			if ($cnt = $xml->getVar(self::AST_SUB['Occurrences'][2])) {

				$xml->setVal(--$cnt);
				return $xml;
			}

			// frequency
			$freq = $xml->getVar(self::AST_SUB['Type'][2]);
			// get due (end) date
			$tme = $xml->getVar(fldDueDate::TAG);

		} elseif ($hid & DataStore::CALENDAR) {

			// frequency
			$freq = $xml->getVar(self::ASC_SUB['Type'][2]);
			// get start date
			$tme = $xml->getVar(fldStartTime::TAG);
		}

		// regeneration only allowed for special frequencies
		$reg = false;
		foreach (self::AS_FREQ as $unused => $day)
			if ($freq == $day) {

				$reg = true;
				break;
			}
		$unused; // disable Eclipse warning
		if (!$reg)
			return null;

		// limited by "UNTIL"?
		$xml->getVar(self::TAG);
		$p = $xml->savePos();

		$until = $xml->getVar(fldEndTime::TAG, false);
		if ($until && $tme >= $until)
			return null;

		// get exception dates
		$exd = [];
		if ($xml->getVar(fldExceptions::TAG) !== null) {

			$xml->xpath('//'.fldExceptions::SUB_TAG[2]);
			// we assume for excluded dates this is 00:00
			while (($t = $xml->getItem())) {

				$xml->setParent();
				if ($xml->getVar(fldExceptions::SUB_TAG[1]) !== null)
					$exd[gmdate('Ymd', intval(Util::mkTZOffset($t, true)))] = 1;
			}
		}

		// remove completed flags
		if ($hid & DataStore::TASK) {

			$xml->delVar(fldCompleted::TAG);
			$xml->delVar(fldStatus::TAG);
		}

		$chk = new \DateTime($catch ? gmdate(Config::UTC_TIME, $catch) : 'now', new \DateTimeZone('UTC'));
		$ofs = new \DateTime('now', new \DateTimeZone('UTC'));
		$ofs->setTimestamp(intval($tme));

		// get interval
		$xml->restorePos($p);
		if (!($intv = $xml->getVar(self::ASC_SUB['Interval'][2], false)))
			$intv = 1;

		// prolongate date
		do {

			switch ($freq) {
			case 'DAILY':
				while ($ofs->getTimestamp() - $chk->getTimestamp() < 0)
					$ofs->modify('+'.$intv.' day');
				break;

			case 'WEEKLY':
				while ($ofs->getTimestamp() - $chk->getTimestamp() < 0)
					$ofs->modify('+'.$intv.' week');
				break;

			case 'MONTHLY':
				while ($ofs->getTimestamp() - $chk->getTimestamp() < 0)
					$ofs->modify('+'.$intv.' month');
				break;

			case 'YEARLY':
				while ($ofs->getTimestamp() - $chk->getTimestamp() < 0)
					$ofs->modify('+'.$intv.' year');
				break;
			}

			if ($ofs->getTimestamp() - $chk->getTimestamp() >= 0 && isset($exd[$ofs->format('Ymd')])) {

				// are we checking dynamic time?
				if ($catch && $chk->getTimestamp() < time()) {

					switch ($freq) {
					case 'DAILY':
						$chk->modify('+'.$intv.' day');
						break;

					case 'WEEKLY':
						$chk->modify('+'.$intv.' week');
						break;

					case 'MONTHLY':
						$chk->modify('+'.$intv.' month');
						break;

					case 'YEARLY':
						$chk->modify('+'.$intv.' year');
						break;
					}

				} else
					return null;
			}

		} while (isset($exd[$ofs->format('Ymd')]));

		// set new due date/time
		if ($hid & DataStore::TASK)
			$xml->updVar(fldDueDate::TAG, strval($ofs->getTimestamp()));
		elseif ($hid & DataStore::CALENDAR) {

			$dur = $xml->getVar(fldEndTime::TAG) - $xml->getVar(fldStartTime::TAG);
			$xml->updVar(fldStartTime::TAG, strval($ofs->getTimestamp()));
			$xml->updVar(fldEndTime::TAG, strval($ofs->getTimestamp() + $dur));
		}

		$gid = $xml->getVar('GUID');
		$xml->setTop();
		Msg::InfoMsg($xml, 'Record regenerated from '.$tme.' ['.$gid.']');

		return $xml;
	}

}
