<?php
declare(strict_types=1);

/*
 *  Exception field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Config;
use syncgw\lib\Msg;
use syncgw\lib\Util;
use syncgw\lib\XML;

class fldExceptions extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'Exceptions';
	const SUB_TAG 			= [
							 	'Exception',
								'Delete',
								'InstanceId',
	];

	/*
	 exdate     = "EXDATE" exdtparam ":" exdtval *("," exdtval) CRLF

     exdtparam  = *(

                ; the following are optional,
                ; but MUST NOT occur more than once

                (";" "VALUE" "=" ("DATE-TIME" / "DATE")) /

                (";" tzidparam) /

                ; the following is optional,
                ; and MAY occur more than once

                (";" xparam)

                )

     exdtval    = date-time / date
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

	const ASC_SUB 			= [
	// 		'AllDayEvent 					handled by fldEndTime
			'AppointmentReplyTime'		=> [ 14.0, 	16.1, 	'fldAppointmentReply',		],
		    'Attachments'				=> [ 16.0,	16.1,	'fldAttach',				],
			'Attendees'					=> [ 14.0,	16.1,	'fldAttendee',				],
			'Body'						=> [ 2.5,	16.1,	'fldBody',					],
			'BusyStatus'				=> [ 2.5,	16.1,	'fldBusyStatus',			],
			'Categories'				=> [ 2.5,	16.1,	'fldCategories',			],
			'Deleted'					=> [ 2.5, 	16.1,	self::SUB_TAG[1],			],
	// 		'DtStamp'						ignored
			'EndTime'					=> [ 2.5, 	16.1,	'fldEndTime',				],
			'ExceptionStartTime'		=> [ 2.5, 	14.1,	'fldStartTimeException',	],
			'InstanceId'				=> [ 2.5, 	16.1,	self::SUB_TAG[2],			],
			'Location'					=> [ 2.5, 	16.1,	'fldLocation',				],
			'MeetingStatus'				=> [ 2.5, 	16.1,	'fldMeetingStatus',			],
			'NativeBodyType'			=> [ 12.0,	16.1,	'fldBodyType',				],
			'OnlineMeetingConfLink'		=> [ 14.1,	16.1,	'fldConference',			],
			'OnlineMeetingExternalLink'	=> [ 14.1,	16.1,	'fldConferenceExt',			],
			'Reminder'					=> [ 2.5, 	16.1,	'fldAlarm',					],
			'ResponseType'				=> [ 14.0,	16.1,	'fldRType',					],
			'Sensitivity'				=> [ 2.5, 	16.1,	'fldClass',					],
			'StartTime'					=> [ 2.5, 	16.1,	'fldStartTime',				],
			'Subject'					=> [ 2.5, 	16.1,	'fldSummary',				],
			'UID'						=> [ 2.5,	2.5,	'fldUid',					],
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldExceptions
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldExceptions {

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
				// time zone set?
				if (isset($rec['P']['TZID'])) {

					if ($tzid = Util::getTZName($rec['P']['TZID'])) {

						$int->updVar(fldTimezone::TAG, $tzid);
						unset($rec['P']['TZID']);
					} else
						// delete unknown time zone
						unset($rec['P']['TZID']);
				}
				$ip = $int->savePos();
				parent::delTag($int, $ipath);
				if ($int->getVar(self::TAG) === null)
					$int->addVar(self::TAG);
				$ip1 = $int->savePos();
   				// other exceptions were handled by MIME_Handler::Handler()
				foreach (explode(',', $rec['D']) as $val) {

					$int->addVar(self::SUB_TAG[0]);
					// check type
					$var = 'text';
					$p   = date_parse($val);
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
						$val = 0;
						break;
					}
					// check parameter
					parent::check($rec, self::RFCC_PARM[$var]);
					if ($var != 'date-time')
						$rec['P']['VALUE'] = $var;
					// store time
					$int->addVar(self::SUB_TAG[2], Util::unxTime($val, $tzid), false, $rec['P']);
					$int->addVar(self::SUB_TAG[1], null);
					$rc = true;
					$int->restorePos($ip1);
				}
				$int->restorePos($ip);
			}
			break;

		case 'application/activesync.calendar+xml':

	   		if (!$ext->xpath($xpath.'/Exception', false))
	   			break;

			parent::delTag($int, $ipath, '16.0');
			$ip = $int->savePos();
			$int->addVar(self::TAG);
			$ip1 = $int->savePos();
			while ($ext->getItem() !== null) {

				$int->addVar(self::SUB_TAG[0]);
				$xp = $ext->savePos();
		        foreach (self::ASC_SUB as $key => $parm) {

		        	$xp1 = $ext->savePos();
		        	$val = $ext->getVar($key, false);
		        	$ext->restorePos($xp1);
		        	if (substr($parm[2], 0, 3) == 'fld') {

        		       	$class = 'syncgw\\document\\field\\'.$parm[2];
	               		$field = $class::getInstance();
	               		if ($field->import($typ, $ver, $key, $ext, $ipath.'/Exception/', $int))
							$rc = true;
		        	} elseif ($val) {

						if ($key == 'InstanceId')
							$int->addVar($parm[2], Util::unxTime($val));
						else {

			            	$int->addVar($parm[2]);
			            	$int->setParent();
						}
						$rc = true;
		        	}
            		$ext->restorePos($xp);
				}
				$int->restorePos($ip1);
			}
			$int->restorePos($ip);
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

		if (!$int->xpath($ipath.self::TAG.'/'.self::SUB_TAG[0], false))
			return $rc;

		switch ($typ) {
		case 'text/calendar':
		case 'text/x-vcalendar':
			$p = $int->savePos();
			if (!($tzid = $int->getVar(fldTimezone::TAG)))
				$tzid = 'UTC';
			$int->restorePos($p);

			$a = $int->getAttr();
			$t = isset($a['VALUE']) ? $a['VALUE'] : '';
			if ($ver != 1.0) {

				if ($tzid && $tzid != 'UTC')
	   				$a['TZID'] = $tzid;
				if (isset($a['VALUE']))
					$a['VALUE'] = strtoupper($a['VALUE']);
			} else
  				unset($a['VALUE']);
   			$d   = new \DateTime('', new \DateTimeZone($tzid));
   	   		$val = '';
  			while ($int->getItem() !== null) {

   				$p = $int->savePos();
   				if ($int->getVar(self::SUB_TAG[1]) !== null) {

		   			// we use start time, since according to RFC it is always the whole day
		   			$int->restorePos($p);
		   			// get <InstanceId>
		   			if (!($v = $int->getVar(self::SUB_TAG[2], false))) {

		   				// fall back to <StartTimeException>
		   				$int->restorePos($p);
		   				if (!($v = $int->getVar(fldStartTimeException::TAG, false))) {

		   					// fall back to <StartTime>
		   					$int->restorePos($p);
		   					if (!($v = $int->getVar(fldStartTime::TAG, false)))
		   						break;
		   				}
		   			}
		   			$d->setTimestamp(intval($v));
					$val .= ($val ? ($ver == 1.0 ? ';' : ',') : '').$d->format($t == 'date' ? Config::STD_DATE : Config::UTC_TIME);
   				}
   				// other exceptions were handled by MIME_Handler::Handler()
   				$int->restorePos($p);
	   		}
        	if ($val) {
        		if (!$rc)
        			$rc = [];
	   			$rc[] = [ 'T' => $tag, 'P' => $a, 'D' => $val ];
        	}
	   		break;

		case 'application/activesync.calendar+xml':
        	$p   = $ext->savePos();
			$ext->addVar($tag, null, false, $ext->setCP(XML::AS_CALENDAR));
			if (!class_exists($class = 'syncgw\\activesync\\masHandler'))
				break;

			$ver = $class::getInstance()->callParm('BinVer');

			while ($int->getItem() !== null) {

        		$ip = $int->savePos();
        		$xp = $ext->savePos();
 	        	$ext->addVar('Exception', null, false, $ext->setCP(XML::AS_CALENDAR));
	        	foreach (self::ASC_SUB as $key => $parm) {

	        		// check version
	        		if ($ver < $parm[0] || $ver > $parm[1])
	        			continue;
	        		// check class
					if (substr($parm[2], 0, 3) == 'fld') {

	                	$class = 'syncgw\\document\\field\\'.$parm[2];
	               		$field = $class::getInstance();
                 	  	$field->export($typ, $ver, '', $int, self::TAG.'/'.$key, $ext);
					} elseif ($key == 'InstanceId') {

						// we must use xpath to ensure proper <InstanceId> is found
						$int->xpath($parm[2], false);
						$val = gmdate(Config::UTC_TIME, intval($int->getItem()));
						if ($ver < 16.0)
							$ext->addVar('StartTime', $val, false, $ext->setCP(XML::AS_CALENDAR));
						else
							$ext->addVar($key, $val, false, $ext->setCP(XML::AS_BASE));
					// <Delete>
					} elseif ($int->xpath($parm[2], false))
						$ext->addVar($key, '1', false, $ext->setCP(XML::AS_CALENDAR));
					$int->restorePos($ip);
	            }
	            $ext->restorePos($xp);
	            $rc = true;
       		}
			$ext->restorePos($p);
       		break;

		default:
			break;
		}

		return $rc;
	}

}
