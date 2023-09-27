<?php
declare(strict_types=1);

/*
 *  Alarm field handler
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

class fldAlarm extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'Alarm';

	// Optional parameter 	X-RECUR=				true if the appointment is recurring

    const SUB_TAG          	= [
        'VCALENDAR/%s/VALARM/ACTION'        => 'Action',
        'VCALENDAR/%s/VALARM/REPEAT'        => 'fldRepeat',
        'VCALENDAR/%s/VALARM/TRIGGER'       => 'fldTrigger',
        'VCALENDAR/%s/VALARM/ATTACH'        => 'fldAttach',
        'VCALENDAR/%s/VALARM/SUMMARY'       => 'fldSummary',
        'VCALENDAR/%s/VALARM/DESCRIPTION'	=> 'fldBody',
        'VCALENDAR/%s/VALARM/DURATION'      => 'fldDuration',
        'VCALENDAR/%s/VALARM/ATTENDEE'      => 'fldAttendee',
        'VCALENDAR/%s/VALARM/EMAIL'		    => fldMailOther::TAG,
    ];

    /*
     action     = "ACTION" actionparam ":" actionvalue CRLF

     actionparam        = *(";" xparam)

     actionvalue        = "AUDIO" / "DISPLAY" / "EMAIL" / "PROCEDURE"
                        / iana-token / x-name
     */
    const RFCC_PARM        	= [
		// description see fldHandler:check()
        'text'             	=> [
	       'VALUE'          => [ 1, 'text ' ],
		  '[ANY]'           => [ 0 ],
	    ],
    ];
    const RFCC_ACTION      	= [
        'VCALENDAR/%s/DALARM'   		=> 'DISPLAY',
        'VCALENDAR/%s/MALARM'           => 'EMAIL',
        'VCALENDAR/%s/AALARM'           => 'AUDIO',
    ];

    // application/activesync.calendar+xml
    // application/activesync.mail+xml
    const ASC_SUB			= [
        'Action'        	=> 'Action',					// internal field
        'Reminder'   		=> 'fldTrigger',
    ];
    // application/activesync.task+xml
    const AST_SUB			= [
        'Action'        	=> 'Action',					// internal field
        'ReminderTime'		=> 'fldTrigger',
	//  'ReminderSet'		// Handled in fldTrigger
    ];

   /**
     * 	Singleton instance of object
     * 	@var fldAlarm
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldAlarm {

		if (!self::$_obj) {

            self::$_obj = new self();
			// clear tag deletion status
			unset(parent::$Deleted[self::TAG]);
		}

		return self::$_obj;
	}

    /**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
     *	@param 	- true = Provide status information only (if available)
	 */
	public function getInfo(XML &$xml, bool $status): void {

		$xml->addVar('Opt', sprintf('&lt;%s&gt; field handler', self::TAG));
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
	    $chk   = null;
	    $cnf = Config::getInstance();

	    switch ($typ) {
		case 'text/calendar':
	    case 'text/x-vcalendar':
		    // get time zone
		    $p = $int->savePos();
			if (!($tzid = $int->getVar(fldTimezone::TAG)))
		    	$tzid = 'UTC';
			$int->restorePos($p);

			$in   = false;
	        $tags = explode('/', $xpath);
			$sub  = [];
			foreach (self::SUB_TAG as $k => $v)
				$sub[sprintf($k, $tags[1])] = $v;
			$act = [];
			foreach (self::RFCC_ACTION as $k => $v)
				$act[sprintf($k, $tags[1])] = $v;

            foreach ($ext as $rec) {

                if ($ver == 1.0 && (!$cnf->getVar(Config::DBG_SCRIPT) || ($cnf->getVar(Config::DBG_SCRIPT) == 'MIME01' ||
                	$cnf->getVar(Config::DBG_SCRIPT) == 'MIME02' || $cnf->getVar(Config::DBG_SCRIPT) == 'MIME04')) ) {

                    // filter out record for us...
                    if (!isset($act[$rec['T']]))
                        continue;

                    parent::delTag($int, $ipath);
                    $xml = new XML();

    				// P0: run time
    	   			// P1: duration
		      		// P2: repeat count
		      		// P3: DALARM: Message to display
				    // P3: PALARM: procedure name
	       			// P3: AALARM: audio content
			     	// P3: MALARM: Email addreess and note
                    $parm = explode(';', $rec['D']);

                    $ip = $int->savePos();

    				$int->addVar(self::TAG);
                    $int->addVar($sub[$xpath], $act[$rec['T']]);
	       			$p = $int->savePos();

                    // convert given date in relation to start date
                    $parm[0] = Util::unxTime($parm[0], $tzid);
                    if ($int->getVar(fldStartTime::TAG) === null) {

                        // inject missing start date
                        $int->restorePos($ip);
                        $int->addVar(fldStartTime::TAG, $parm[0]);
                    }
                    $int->restorePos($p);
                    self::_swap($typ, $ver, $xpath, $sub, 'TRIGGER', gmdate(Config::UTC_TIME, intval($parm[0])), $int);

    				if (isset($parm[1]) && $parm[1])
						self::_swap($typ, $ver, $xpath, $sub, 'DURATION', $parm[1], $int);
    				if (isset($parm[2]) && $parm[2])
						self::_swap($typ, $ver, $xpath, $sub, 'REPEAT', $parm[2], $int);

       				switch($rec['T']) {
       				case 'VCALENDAR/VEVENT/AALARM':
   				    case 'VCALENDAR/VTODO/AALARM':
					    if (isset($parm[3]) && $parm[3])
							self::_swap($typ, $ver, $xpath, $sub, 'ATTACH', base64_encode($parm[3]), $int);
					    break;

       				case 'VCALENDAR/VEVENT/DALARM':
					case 'VCALENDAR/VTODO/DALARM':
					    if (isset($parm[3]) && $parm[3])
							self::_swap($typ, $ver, $xpath, $sub, 'DESCRIPTION', $parm[3], $int);
   				        break;

       				case 'VCALENDAR/VEVENT/MALARM':
   				    case 'VCALENDAR/VTODO/MALARM':
					    if (isset($parm[3]) && $parm[3])
					    	$int->addVar(fldMailOther::TAG, $parm[3]);
   				    	if (isset($parm[4]) && $parm[4])
							self::_swap($typ, $ver, $xpath, $sub, 'DESCRIPTION', $parm[4], $int);
   				        break;
       				}
       				$int->restorePos($ip);
       				$rc = true;
       				continue;
                }
                if (strpos($rec['T'], 'VALARM/BEGIN') !== false) {

                    $rc = true;
                    $in = true;
                    parent::delTag($int, $ipath);
                    $ip = $int->savePos();
                    $int->addVar(self::TAG);
                    $ia = $int->savePos();
                    continue;
                }
                if ($in && strpos($rec['T'], 'VALARM/END') !== false) {

                    $in = false;
                    $int->restorePos($ia);
                    if ($int->getVar('Action') === null)
                    	$int->addVar('Action', 'DISPLAY');
                    $int->restorePos($ip);
                    continue;
                }
                if (!$in)
                    continue;

                switch ($rec['T']) {
                case 'VCALENDAR/VEVENT/VALARM/ACTION':
                case 'VCALENDAR/VTODO/VALARM/ACTION':
                    if (strpos('AUDIO DISPLAY EMAIL', $rec['D']) === false) {
                        Msg::InfoMsg('['.$rec['D'].'] "'.$rec['D'].'" not "AUDIO DISPLAY EMAIL" - dropping record');
                        break;
                    }
                    // check parameter
                    parent::check($rec, self::RFCC_PARM['text']);
                    $int->addVar($sub[$rec['T']], $rec['D']);
                    break;

                case 'VCALENDAR/VEVENT/VALARM/EMAIL':
                case 'VCALENDAR/VTODO/VALARM/EMAIL':
                    $int->addVar(fldMailOther::TAG, $rec['D']);
                    break;

                default:
                	if (isset($sub[$rec['T']])) {
                		$xml = new XML();
                    	$xml->loadXML('<syncgw/>');
                    	$xml->getVar('syncgw');
                    	$class = 'syncgw\\document\\field\\'.$sub[$rec['T']];
                    	$field = $class::getInstance();
                        $field->import($typ, $ver, $rec['T'], [ $rec ], $ipath, $xml);
                        $xml->xpath('//'.$class::TAG);
						while ($xml->getItem() !== null)
	                        $int->append($xml, false);
                    }
                    break;
                }
    	    }
            break;

        case 'application/activesync.mail+xml':
        case 'application/activesync.task+xml':
            $chk = [ '', self::AST_SUB ];

        case 'application/activesync.calendar+xml':
        	if (!$chk)
        		$chk = [ '16.0', self::ASC_SUB ];

			if (!$ext->xpath($xpath, false))
        		break;

	        self::delTag($int, $ipath, $chk[0]);

			$ip = $int->savePos();
			$int->addVar(self::TAG);
			$ip1 = $int->savePos();

	        $xp = $ext->savePos();
    	    foreach ($chk[1] as $key => $class) {

        		if (substr($class, 0, 3) == 'fld') {

        	       	$class = 'syncgw\\document\\field\\'.$class;
        	       	$field = $class::getInstance();
					$field->import($typ, $ver, $xpath, $ext, $ipath.'/', $int);
				} else
					$int->addVar($key, 'DISPLAY');
    	        $ext->restorePos($xp);
	        }
    	    $int->restorePos($ip1);
    	    if ($int->getVar($field::TAG, false) === null) {

    	        $int->delVar(self::TAG, false);
    	        $rc = false;
    	    } else
    	        $rc = true;
    	    $int->restorePos($ip);

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

		// get time zone
		$p = $int->savePos();
		if (!($tzid = $int->getVar(fldTimezone::TAG)))
	    	$tzid = 'UTC';
		$int->restorePos($p);
		$fmt = $tzid != 'UTC' || $ver == 1.0 ? Config::STD_TIME : Config::UTC_TIME;
        $chk = null;

		if (!$int->xpath($ipath.self::TAG, false))
			return $rc;

		switch ($typ) {
		case 'text/calendar':
	    case 'text/x-vcalendar':
            $recs = [];
			$sub  = [];
			$val  = self::SUB_TAG;
			foreach ($val as $k => $v)
				$sub[sprintf($k, $tags[0])] = $v;
			$act  = [];
	        $val  = self::RFCC_ACTION;
			foreach ($val as $k => $v)
				$act[sprintf($k, $tags[0])] = $v;

            while ($int->getItem() !== null) {

                if ($ver != 1.0)
                    $recs[] = [ 'T' => 'BEGIN', 'P'=> [], 'D' => 'VALARM' ];
                $parm = [ 0 => 0, 1 => '', 2 => '', 3 => '', 4 => '', 5 => 'DALARM' ];
                foreach ($sub as $key => $class) {

	                $p = $int->savePos();
                	if ($ver == '1.0' && $class == 'fldDuration')
                		$class = fldDuration::TAG;
                    if (substr($class, 0, 3) == 'fld') {

                        $class = 'syncgw\\document\\field\\'.$class;
                        $field = $class::getInstance();
                        if ($c = $field->export($typ, $ver, '', $int, $key, $ext)) {

                            foreach ($c as $r) {

                                if ($ver == 1.0) {

                                    if (strpos($key, 'REPEAT') !== false)
                                        $parm[2] = $r['D'];
                                	elseif (strpos($key, 'TRIGGER') !== false)
                                        $parm[1] = $r;
                                	elseif (strpos($key, 'ATTACH') !== false)
                                        $parm[3] = isset($r['P']['ENCODING']) ? base64_decode($r['D']) : $r['D'];
                                    elseif (strpos($key, 'DESCRIPTION') !== false)
                                        $parm[4] = $r['D'];
                                } else
                                    $recs[] = $r;
                           }
                        }
                    } elseif (($val = $int->getVar($class, false)) !== null) {

                        if ($ver == 1.0) {

                            if (strpos($key, 'ACTION') !== false) {

                                $a = array_flip($act);
                                if (isset($a[$val])) {
                                	$t 		 = explode('/', $a[$val]);
                                    $parm[5] = array_pop($t);
                                }
                            } elseif (strpos($key, 'EMAIL') !== false)
                             	$parm[3] = $val;
                            elseif (strpos($key, 'DURATION') !== false)
                            	$parm[0] = $val;
                        } else {

							$tags = explode('/', $key);
							$tag  = array_pop($tags);
                            $recs[] = [ 'T' => $tag, 'P' => $int->getAttr(), 'D' => $val ];
                        }
                    }
                    $int->restorePos($p);
                }
                if ($ver == 1.0) {

         			// P0: run time
        	   		// P1: duration
		        	// P2: repeat count
			     	// P3: PALARM: procedure name
    	       		// P3: AALARM: audio content
	   		        // P3: MALARM: Email addreess
    		  	    // P4: Temp. Note
    	   		    // P5: Temp. Alarm type
                    $r  = [ 'T' => $parm[5], 'P' => [], 'D' => gmdate($fmt, intval($int->getVar(fldStartTime::TAG))).';' ];
                    if (isset($parm[0]))
                   		$r['D'] .= Util::cnvDuration(false, strval($parm[0]));
                    $r['D'] .=  ';'.$parm[2].($parm[3] ? ';'.$parm[3] : '').($parm[4] ? ';'.$parm[4] : '');
                    $recs[] = $r;
                } else
                    $recs[] = [ 'T' => 'END', 'P'=> [], 'D' => 'VALARM' ];
            }
            if (count($recs))
                $rc = $recs;
            break;

        case 'application/activesync.mail+xml':
        case 'application/activesync.task+xml':
        	$chk = $xpath == 'Reminder' ? self::ASC_SUB : self::AST_SUB;

        case 'application/activesync.calendar+xml':
        	if (!$chk)
        		$chk = self::ASC_SUB;

       		while ($int->getItem() !== null) {

	            $ip = $int->savePos();
				foreach ($chk as $key => $class) {

					if (substr($class, 0, 3) == 'fld') {
	                	$class = 'syncgw\\document\\field\\'.$class;
	                	$field = $class::getInstance();
                    	$field->export($typ, $ver, '', $int, $key ? $key : $xpath, $ext);
					}
					$int->restorePos($ip);
	            }
				$int->restorePos($ip);
	            $rc = true;
       		}
       		break;

        default:
            break;
	    }

	    return $rc;
	}

	/**
	 *  Swap data
	 *
	 *	@param  - MIME type
	 *	@param  - MIME ver
	 *  @param  - Orginal tag name
	 *  @param  - self::SUB_TAG
	 *  @param  - New sub Tag
	 *  @param  - Value to import
	 *  @param  - Internal record
	 */
	private function _swap(string $typ, float $ver, string $fulltag, array $sub, string $tag, string $val, XML &$int): void {

	    $t   = explode('/', $fulltag);
    	array_pop($t);
        $t[]   = $tag;
		$t     = implode('/', $t);
		$class = 'syncgw\\document\\field\\'.$sub[$t];
	    $field = $class::getInstance();
	    $xml = new XML();
		$xml->loadXML('<syncgw/>');
    	$xml->getVar('syncgw');
   		// RELATED will only survive for durations!
    	$field->import($typ, $ver, $t, [[ 'T' => $t, 'P' => $tag == 'DURATION' ? [ 'RELATED' => 'START'] : [], 'D' => $val ]], 'Data/',  $xml);
		$xml->xpath('//'.$class::TAG);
    	while ($xml->getItem() !== null)
           	$int->append($xml, false);
	}

}
