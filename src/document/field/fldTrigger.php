<?php
declare(strict_types=1);

/*
 *  Trigger date status field handler
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

class fldTrigger extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'Trigger';

	/*
	 trigger    = "TRIGGER" (trigrel / trigabs)

     trigrel    = *(

                ; the following are optional,
                ; but MUST NOT occur more than once

                  (";" "VALUE" "=" "DURATION") /
                  (";" trigrelparam) /

                ; the following is optional,
                ; and MAY occur more than once

                  (";" xparam)
                  ) ":"  dur-value

     trigabs    = 1*(

                ; the following is REQUIRED,
                ; but MUST NOT occur more than once

                  (";" "VALUE" "=" "DATE-TIME") /

                ; the following is optional,
                ; and MAY occur more than once

                  (";" xparam)

                  ) ":" date-time
	 */
	const RFCC_PARM			= [
		// description see fldHandler:check()
	    'duration'		 	=> [
		  'VALUE'			=> [ 1, 'duration ' ],
		  'RELATED'		  	=> [ 1, 'start end ' ],
		  '[ANY]'			=> [ 0 ],
		],
		'date-time'			=> [
		  'VALUE'			=> [ 1, 'date-time ' ],
		  'TZID'			=> [ 0 ],
		  '[ANY]'			=> [ 0 ],
		],
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldTrigger
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldTrigger {

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
				if (Util::cnvDuration(true, $rec['D']) !== null)
					$var = 'duration';
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
				if ($var != 'duration' && $var != 'date-time') {

					Msg::InfoMsg('['.$rec['D'].'] "'.$rec['D'].'" wrong type "'.$var.'" - dropping record');
					break;
				}
				// check parameter
				parent::check($rec, self::RFCC_PARM[$var]);
				parent::delTag($int, $ipath);
				$a = $int->getAttr();
				if ($var == 'duration' && !isset($a['RELATED']))
					$a['RELATED'] = 'start';
			  	$a['VALUE'] = $var;
				$int->addVar(self::TAG, $var == 'duration' ? Util::cnvDuration(true, $rec['D']) : Util::unxTime($rec['D']), false, $a);
				$rc = true;
			}
			break;

        case 'application/activesync.task+xml':
	   		if ($ext->xpath($xpath, false))
				parent::delTag($int, $ipath);

			while (($val = $ext->getItem()) !== null) {

				if ($val) {

					$int->addVar(self::TAG, Util::unxTime($val), false, [ 'VALUE' => 'date-time' ]);
					$rc = true;
				}
			}
        	break;

		case 'application/activesync.mail+xml':
	   		if ($ext->xpath($xpath, false))
				parent::delTag($int, $ipath);

			while (($val = $ext->getItem()) !== null) {

				if ($val) {

					if ($xpath == 'Flag/ReminderTime')
						$int->addVar(self::TAG, Util::unxTime($val), false, [ 'VALUE' => 'date-time' ]);
					else
						$int->addVar(self::TAG, $val, false, [ 'VALUE' => 'duration', 'RELATED' => 'start' ]);
					$rc = true;
				}
			}
        	break;

        case 'application/activesync.calendar+xml':
	   		if ($ext->xpath($xpath, false))
				parent::delTag($int, $ipath, '16.0');

			while (($val = $ext->getItem()) !== null) {

				if ($val) {

					$int->addVar(self::TAG, strval($val * -60), false, [ 'VALUE' => 'duration', 'RELATED' => 'start' ]);
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
  				elseif (isset($a['VALUE']))
   					$a['VALUE'] = strtoupper($a['VALUE']);
  				if (isset($a['RELATED']))
   					$a['RELATED'] = strtoupper($a['RELATED']);
   				if (isset($a['VALUE']) && $a['VALUE'] == 'DURATION')
		   			$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => Util::cnvDuration(false, $val) ];
	   			else
	   				$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => gmdate(Config::UTC_TIME, intval($val)) ];
	   		}
			if (count($recs))
				$rc = $recs;
			break;

        case 'application/activesync.task+xml':
        	while (($val = $int->getItem()) !== null) {

            	$a = $int->getAttr();
            	if (isset($a['VALUE']) && $a['VALUE'] == 'duration') {

            		$p = $int->savePos();
            		$val += $int->getVar(fldDueDate::TAG);
            		$int->restorePos($p);
            	}
	            $ext->addVar($tag, gmdate(Config::masTIME, intval($val)), false, $ext->setCP(XML::AS_TASK));
                $rc = true;
            }
            if ($rc)
	            $ext->addVar('ReminderSet', '1');
            break;

		case 'application/activesync.calendar+xml':
            while (($val = $int->getItem()) !== null) {

            	$a = $int->getAttr();
            	if (!isset($a['RELATED']))
            		$a['RELATED'] = 'START';
            	$start = $int->getVar(fldStartTime::TAG);
            	$end   = $int->getVar(fldEndTime::TAG);
            	if ($a['VALUE'] != 'duration') {

	            	if ($a['RELATED'] == 'END')
    	        		$val -= $end;
	            	else
	            		$val -= $start;
            	} elseif ($a['RELATED'] == 'END')
            		$val += $start - $end;
            	if ($val)
            		$val /= -60;
            	$ext->addVar($tag, strval($val), false, $ext->setCP(XML::AS_CALENDAR));
           		$rc = true;
            }
            break;

        case 'application/activesync.mail+xml':
        	while (($val = $int->getItem()) !== null) {

            	$a = $int->getAttr();
            	if (isset($a['VALUE']) && $a['VALUE'] == 'duration')
	                $ext->addVar($tag, $val, false, $ext->setCP(XML::AS_TASK));
	            else
	                $ext->addVar($tag, gmdate(Config::masTIME, intval($val)), false, $ext->setCP(XML::AS_TASK));
                $rc = true;
            }
            break;

		 default:
			break;
		}

		return $rc;
	}

}
