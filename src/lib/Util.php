<?php
declare(strict_types=1);

/*
 * 	Utility functions class
 *
 *	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\lib;

use DateTime;

class Util {

	// picture formats
	const PIC_FMT     = [
			'jpeg'	=> 'imagejpeg',
			'jpg'	=> 'imagejpeg',
			'gd2'	=> 'imagegd2',
			'gd'	=> 'imagegd',
			'gif'	=> 'imagegif',
			'png'	=> 'imagepng',
			'wmbp'	=> 'imagewbmp',
			'xbm'	=> 'imagexbm',
	];

	// HID() parameter
	const HID_TAB    = 0;											// get internal table names
	const HID_ENAME  = 1;											// get external names
	const HID_CNAME  = 2;											// get handler class name
	const HID_PREF   = 3;											// get short names (GUID prefix)

 	/**
	 * 	Get unused file name in tmp. directory
	 *
	 * 	@param	- Optional file extension (defaults to "tmp")
	 * 	@return	- Normalized full file name
	 */
	static function getTmpFile(string $ext = 'tmp'): string {

		$dir = Config::getInstance()->getVar(Config::TMP_DIR);

		// create unique file name
		do {

			$name = $dir.uniqid().'.'.$ext;
		} while (file_exists($name));

		return $name;
	}

	/**
	 *  Replace any non a-z, A-Z and 0-9 character with "-" in file name
	 *
	 * 	@param	- File name
	 * 	@return	- Converted name
	 */
	static function normFileName(string $name): string {

		if ($p = strrpos($name, '.')) {

			$ext = substr($name, $p);
			$nam = substr($name, 0, $p - 1);
		} else {

			$nam = $name;
			$ext = '';
		}

   		$nam = preg_replace('|[^a-zA-Z0-9]+|', '-', $nam);

   		return $nam.$ext;
	}

	/**
	 *  Convert MIME type to file name extension
	 *
	 *  @param  - MIME Type
	 *  @return - File name extension or null if not found
	 */
	static function getFileExt(string $mime): ?string {
        static $_mime = null;

		// load mime mapping table
	    if (!$_mime) {

            $_mime = new XML();
            $_mime->loadFile(Config::getInstance()->getVar(Config::ROOT).'core-bundle/assets/mime_types.xml');
	    }

	    $_mime->xpath('//Name[text()="'.strtolower($mime).'"]/..');
	    if ($_mime->getItem() === false)
	         return null;

	    return '.'.$_mime->getVar('Ext', false);
	}

	/**
	 * 	Delete directory (and content)
	 *
	 * 	@param 	- Direcory path
	 */
	static function rmDir(string $dir): bool {
	    static $_lvl = 0;
	    static $_err;

	    if (substr($dir, -1) != '/')
	    	$dir .= '/';

		if (!file_exists($dir) || !is_dir($dir) || !($h = opendir($dir))) {

		    Msg::WarnMsg('Error deleting file "'.$dir.'"');
		    return false;
		}

		if (!$_lvl)
		    $_err = false;

		while($file = readdir($h)) {

			if ($file != '.' && $file != '..') {
			    if (!is_dir($dir.$file)) {

					if (!unlink($dir.$file))
					    $_err = true;
			    } else {

			        $_lvl++;
			        if (!self::rmDir($dir.$file.'/')) {

			            closedir($h);
            		    Msg::WarnMsg('Error deleting file "'.$dir.'"');
			            return false;
			        }
			    }
			}
		}
		closedir($h);
		if (!$_err) {

			// ignore '.' and '..'
			if (count(scandir($dir)) == 2)
		    	rmdir($dir);
		}

		return true;
	}

	/**
	 * 	Cleanup debug directory
	 *
	 * 	@param 	- File name pattern
	 */
    static public function CleanDir(string $file): void {

		array_map('unlink', glob(Config::getInstance()->getVar(Config::DBG_DIR).'/'.$file));
    }

   	/**
     * 	Save content to file
     *
     * 	@param	- File name skeleton (e.g. "Raw%d.xml")
     * 	@param	- Data to store
     *  @param  - true = Whole XML; false = From current position
     * 	@return	- Debug file name or null on error
     */
    static public function Save(string $fnam, $data, bool $top = true): ?string {

    	$d = Config::getInstance()->getVar(Config::DBG_DIR);

    	if (strpos($fnam, '%') !== false) {

        	$n = 1;
        	do {

        		$name = $d.sprintf($fnam, $n);
        		if ($n++ > 999)
        		    break;
        	} while (file_exists($name));
        	if ($n > 999)
        	    return null;
    	} else
    	    $name = $d.$fnam;

        if (is_array($data)) {

            ob_start();
       		print_r($data);
       		$data = ob_get_contents();
       		ob_end_clean();
            $data = str_replace("\r", '', $data);
        }
        // XML object?
        elseif (is_object($data))
            $data = $data->saveXML($top, true);

    	file_put_contents($name, $data);

    	if (!Config::getInstance()->getVar(Config::DBG_SCRIPT))
    		Log::getInstance()->logMsg(Log::INFO, 10001, 'Debug data saved to "'.$name.'"');
    	else
            Msg::InfoMsg('Data saved to "'.$name.'"');

        return $name;
    }

    /**
	 * 	Fold array to string
	 *
	 * 	@param 	- Input array
	 * 	@param	- Seperator character
	 *	@return - Output string
	 */
	static function foldStr(array $recs, string $sep): string {

		$str = '';
		foreach ($recs as $r) {

		    if (is_null($r))
		        $r = '';
   			$str .= strval($r).$sep;
		}

		return strlen($str) ? substr($str, 0, -1) : $str;
	}

	/**
	 * 	Unfold string
	 *
	 * 	@param 	- Input string
	 * 	@param	- Seperator string
	 * 	@param	- # of parameter or 0 for any (Default)
	 * 	@return - Output array
	 */
	static function unfoldStr(string $str, string $sep, int $cnt = 0): array {

		$str = str_replace('\\'.$sep, '\x01', $str);
		$out = [];
		$n = 0;
		foreach (explode($sep, $str) as $v) {

			$v = str_replace('\x01', '\\'.$sep, $v);
			$out[] = $v ? $v : '';
			$n++;
		}
		while ($cnt && $n++ < $cnt)
			$out[] = '';

		return $out;
	}

	/**
	 *  Sleep for a while
	 *
	 *  @param  - > 0 = seconds; else < 1 second
	 */
	static function Sleep(int $sec = 0): void {

	    if (!$sec)
    	    // get value < 1 second
	        usleep(rand(500000, 1000000));
	    else
	        sleep($sec);
	}

	/**
	 * 	Get datastore handler array
	 *
	 * 	@param	- Data typ to obtain<fieldset>
	 * 			  Util::HID_TAB    Internal table names<br>
	 * 			  Util::HID_ENAME  External names<br>
	 * 			  Util::HID_PREF   Short name (GUID prefix)<br>
	 * 			  Util::HID_CNAME  Handler class name
	 * 	@param	- Bit map to decode (defaults to DataStore::ALL)
	 * 	@param	- true=All defined; false=All Config::ENABLED (default)
	 * 	@return	- [ Config => name ] or name (if one bit is only set in $map)
	 */
	static public function HID(int $mod, int $map = DataStore::ALL, bool $all = false) {
	    static $_hd = null;

	    // remove internal / external bit
    	$map &= ~(DataStore::EXT);

	    if (!$_hd)
	        $_hd = [
				// Datastore               Util::HID_TAB   		Util::HID_ENAME   	 	  	Util::HID_CNAME					Util::HID_PREF
				DataStore::SESSION  	   => [ 'Session',	    'Session',	     	 	'syncgw\\lib\\Session',		    'S', ],
				DataStore::TRACE    	   => [ 'Trace',		'Trace',	     	  	'syncgw\\lib\\Trace',			'X', ],
				DataStore::DEVICE   	   => [ 'Device',		'Device',	     	  	'syncgw\\lib\\Device',		    'D', ],
				DataStore::USER		       => [ 'User',		    'User',		     	  	'syncgw\\lib\\User',			'U', ],
                DataStore::ATTACHMENT      => [ 'Attachments',  'Attachments',    	  	'syncgw\\lib\\Attachment',      'Z', ],

				DataStore::CONTACT  	   => [ 'Contact',	    'Contact',	     	  	'syncgw\\document\\docContact',	'A', ],
				DataStore::CALENDAR 	   => [ 'Calendar',	    'Calendar',	     	  	'syncgw\\document\\docCalendar','C', ],
				DataStore::TASK    	       => [ 'Task',		    'Task',		     	  	'syncgw\\document\\docTask',	'T', ],
				DataStore::NOTE     	   => [ 'Note',		    'Note',		     	  	'syncgw\\document\\docNote',	'N', ],
				DataStore::MAIL		  	   => [ 'Mail',			'Mail',	       	 	  	'syncgw\\document\\docMail',	'M', ],
				DataStore::SMS		  	   => [ 'SMS',			'SMS',	       	 	  	'syncgw\\document\\docSMS',		'F', ],
				DataStore::DOCLIB	  	   => [ 'DocLib',		'DocLib',	     	  	'syncgw\\document\\docDoc',		'L', ],
				DataStore::GAL		  	   => [ 'GAL',			'Global Address Book',	'syncgw\\document\\docGAL',		'A', ],
            ];

		// count # of bits to check
		$b = 0;
		for ($n=$map; $n; $n >>= 1) {

			if ($n & 1)
				$b++;
		}

		$rc = [];

		// get enabled data stores only
		if (!$all) {

		    $cnf = Config::getInstance();
			$map &= ($cnf->getVar(Config::ENABLED)|DataStore::SYSTEM);
		}

		// swap data
  		foreach ($_hd as $k => $v) {

			if ($v[$mod] && $map & $k)
    			$rc[$k] = $v[$mod];
		}

		return $b == 1 ? array_pop($rc) : $rc;
	}

	/**
	 *  Compare array - based on http://code.iamkate.com/php/diff-implementation/
	 *
	 *  @param  - [ lines ]
	 *  @param  - [ lines ]
	 *  @param  - [ exclusions ]
	 *  @return - [ # of differences (in lower case) ] [ output string ]
	 */
	static function diffArray(array $arr1, array $arr2, ?array $ex = null): array {

	    // change counter
	    $cnt = 0;
	    // output buffer
	    $out = '';

	    // check array type
	    if (!isset($arr1[0])) {

	        $tab = [];
	        foreach ($arr1 as $k => $v)
	            $tab[] = $k.': '.$v;
	        $arr1 = $tab;
	    }
	    if (!isset($arr2[0])) {

	        $tab = [];
	        foreach ($arr2 as $k => $v)
	            $tab[] = $k.': '.$v;
	        $arr2 = $tab;
	    }

	    // check exlusion
	    if (is_null($ex))
	        $ex = [];

        // initialise the sequences and comparison start and end positions
        $pos = 0;

        $e1 = count($arr1) - 1;
        $e2 = count($arr2) - 1;

        // skip any common prefix
        while ($pos <= $e1 && $pos <= $e2 && $arr1[$pos] == $arr2[$pos])
            $pos++;

        // skip any common suffix
        while ($e1 >= $pos && $e2 >= $pos && $arr1[$e1] == $arr2[$e2]) {

            $e1--;
            $e2--;
        }

        // determine the lengths to be compared
        $l1 = $e1 - $pos + 1;
        $l2 = $e2 - $pos + 1;

        // initialise the table
        $tab = [ array_fill (0, $l2 + 1, 0) ];

        // loop over the rows
        for ($i1=1; $i1 <= $l1; $i1++) {

            // create the new row
            $tab[$i1] = [ 0 ];

            // loop over the columns
            for ($i2=1; $i2 <= $l2; $i2++) {

                if ($arr1[$i1 + $pos - 1] == $arr2[$i2 + $pos -1])
                    $tab[$i1][$i2] = $tab[$i1 - 1][$i2 - 1] + 1;
                else
                    $tab[$i1][$i2] = max($tab[$i1 - 1][$i2], $tab[$i1][$i2 - 1]);
            }
        }

        // partial differences
        $diff = [];

        // initialise the indices
        $i1 = count($tab) - 1;
        $i2 = count($tab[0]) - 1;

        // loop until there are no items remaining in either sequence
        while ($i1 > 0 || $i2 > 0) {

            // check what has happened to the items at these indices

            // on exclusion list?
            $line = strtolower(isset($arr1[$i1 + $pos - 1]) ? $arr1[$i1 + $pos - 1] : '');

			$f    = 0;
	        foreach ($ex as $tag) {

    	       if (strpos($line, $tag) !== false) {
	               $f++;
	               break;
    	       }
            }
            $line = strtolower(isset($arr2[$i2 + $pos - 1]) ? $arr2[$i2 + $pos - 1] : '');
	        foreach ($ex as $tag) {

    	       if (strpos($line, $tag) !== false) {

	               $f++;
	               break;
    	       }
            }

            if ($f == 2) {

                $c = strpos($arr1[$i1 + $pos - 1], '<!--') !== false ? Config::CSS_INFO : Config::CSS_CODE;
                $diff[] = '<code style="'.$c.'">'.XML::cnvStr('X '.$arr1[$i1 + $pos - 1]).'</code><br>';
                if ($i1 > 0)
	                $i1--;
                $i2--;

            } elseif ($i1 > 0 && $i2 > 0 && $arr1[$i1 + $pos - 1] == $arr2[$i2 + $pos - 1]) {

                // update the diff and the indices
                $c = strpos($arr1[$i1 + $pos - 1], '<!--') !== false ? Config::CSS_INFO : Config::CSS_CODE;
                $diff[] = '<code style="'.$c.'">'.XML::cnvStr('= '.$arr1[$i1 + $pos - 1]).'</code><br>';
                if ($i1 > 0)
	                $i1--;
                $i2--;

            } elseif ($i2 > 0 && $tab[$i1][$i2] == $tab[$i1][$i2 - 1]) {

                // update the diff and the indices
                $diff[] = '<code style="'.Config::CSS_WARN.'">'.XML::cnvStr('+ '.$arr2[$i2 + $pos - 1]).'</code><br>';
                $i2--;
                $cnt++;

            } else {

                // update the diff and the indices
                $diff[] = '<code style="'.Config::CSS_WARN.'">'.XML::cnvStr('- '.$arr1[$i1 + $pos - 1 ]).'</code><br>';
                $cnt++;
                if ($i1 > 0)
	                $i1--;

            }
        }

#        if (Config::getInstance()->getVar(Config::DBG_SCRIPT))
#	        return [ $cnt, '' ];

        // generate the full diff

        for ($i=0; $i < $pos; $i++) {

            $c = strpos($arr1[$i], '<!--') !== false ? Config::CSS_INFO : Config::CSS_CODE;
            $out .= '<code style="'.$c.'">'.XML::cnvStr('= '.$arr1[$i]).'</code><br>';
        }

        while (count($diff) > 0)
        	$out .= array_pop($diff);

        for ($i=$e1+1; $i < count($arr1); $i++) {

        	$c = strpos($arr1[$i], '<!--') !== false ? Config::CSS_INFO : Config::CSS_CODE;
            $out .= '<code style="'.$c.'">'.XML::cnvStr('= '.$arr1[$i]).'</code><br>';
        }

        return [ $cnt, $out ];
	}

	/**
	 * 	Convert date / time string to UTC UNIX time stamp
	 *
	 *  @param	- Date / time string
	 *  @param	- Optional time zone ID (or '' = default)
	 *  @return	- UNIX time stamp as string or null
	 */
	static function unxTime(string $str, ?string $tzid = null): ?string {

		if (!strlen($str))
			return null;

		if (($l = strlen($str)) == 8)
			$str .= 'T000000Z';
		elseif ($l == 10)
		    $str .= ' 00:00:00';

		// check for time zone conversion
		if ($tzid) {

			try {

			    $t = new \DateTime($str, new \DateTimeZone($tzid));
			} catch(\Exception $e) {
				$t = '';
			}
			if ($t) {

				$t->setTimezone(new \DateTimeZone('UTC'));
				return $t->format('U');
			}
		}

		if (($v = strtotime($str)) === false) {

			// try to reformat string
			// 2022 Jan 14 09:29:22
			if (($v = DateTime::createFromFormat("Y M d H:i:s", $str)) !== false)
				$v = $v->getTimestamp();
			else {

				// special hack for non existing date
				if ($str == '0000-00-00 00:00:00')
					$t = '19700101T000000Z';
				else {

					$t = '20380101T000000Z';
					if (Config::getInstance()->getVar(Config::DBG_SCRIPT)) {
						Msg::ErrMsg('Invalid time stamp "'.$str.'" - using "'.$t.'"');
	    				foreach (ErrorHandler::Stack() as $rec)
	    				    Msg::InfoMsg($rec);
	    			}
				}
				$v = strtotime($t);
			}
		}

		return strval($v);
	}

	/**
	 * 	Validate PHP time zone
	 *
	 * 	@param 	- Time zone name (Australia/Perth) or Short name (GMT) or UTC- / Daylight-offset (3600/-3600)
	 * 	@return	- Time zone name or null
	 */
	static function getTZName(string $name): ?string {

		$name = trim($name);

		// sepecial hack to catch "Etc/UTC" and "Etc/GMT"
		if (stripos($name, "etc") !== false)
			$name = "UTC";

		// validate time zone name (e.g. Australia/Perth)
		if (in_array($name, timezone_identifiers_list())) {

            Msg::InfoMsg('Time zone "'.$name.'" validated');
            return $name;
	   	}

	   	$tzn = strtolower($name);
		$tz  = timezone_abbreviations_list();

		// find time zone by abbreviation (GMT)
		if (isset($tz[$tzn])) {

			// we take the first one
            $tzid = $tz[$tzn][0]['timezone_id'];
            Msg::InfoMsg('Time zone "'.$name.'" converted to "'.$tzid.'"');
            return $tzid;
		}

        // get time zone name by offset (28800/-3600)
		if (strpos($name, '/') === false) {

        	Msg::InfoMsg('Invalid time zone "'.$name.'" - must be "utc-offset/dst-offset"');
			return null;
		}

		list($utc, $dst) = explode('/', $name);

		$tzid = null;
		$dst  = boolval($dst);

		foreach ($tz as $z) {

			foreach ($z as $t) {

				if ($t['offset'] == $utc && $t['timezone_id'] && $dst === $t['dst']) {

					$tzid = $t['timezone_id'];
					break;
				}
			}

			if ($tzid)
				break;
		}

		if (!$tzid) {

			$chk  = [];
			foreach ($tz as $a) {

	        	foreach ($a as $c) {

	        		// no id provided?
	            	if (!isset($c['timezone_id']))
	            		continue;

	            	// not in check buffer
		        	if (!isset($chk[$c['timezone_id']]))
				        $chk[$c['timezone_id']] = 0;

			        // save potential candidate
				    if ((!$c['dst'] && $c['offset'] == $utc) || ($c['dst'] && $c['offset'] == $dst))
			        	$chk[$c['timezone_id']]++;
    	    	}
    	    }

			// find best match
	        $tzid = null;
			$fnd  = 0;

			foreach ($chk as $t => $c) {

				// delete unused entry
				if (!$c)
					unset($chk[$t]);

				if ($c > $fnd) {

					$fnd  = $c;
					$tzid = $t;
				}
			}
        }

		if ($tzid)
	        Msg::InfoMsg('Time zone "'.$name.'" converted to "'.$tzid.'"');
		else
	        Msg::WarnMsg($chk, 'Time zone "'.$name.'" not found!');

       	return $tzid;
	}

	/**
	 * 	Adjust time offset relativ to UTC for a given time stamp
	 *
	 * 	@param 	- Unix time stamp
	 * 	@param	- true = Use negative offset; false = Don't convert
	 * 	@return	- Converted time (Unix time stamp)
	 */
	static function mkTZOffset(string $tme, bool $neg = false): string {

        $cnf  = Config::getInstance();
		$tzid = new \DateTimeZone($cnf->getVar(Config::TIME_ZONE));
		$utc  = new \DateTimeZone('UTC');
		$s	  = gmdate(Config::UTC_TIME, intval($tme));
    	$udt  = new \DateTime($s, $tzid);
    	$dt   = new \DateTime($s, $utc);
    	$t    = $utc->getOffset($dt) - $tzid->getOffset($udt);

	    return strval($neg ? $tme + $t * -1 : $tme + $t);
	}

	/**
	 * 	Get time zone changes in a given period
	 *
	 * 	@param 	- Name of time zone
	 * 	@param 	- Start Time
	 * 	@param 	- End Time
	 * 	@return - Transition buffer
	 */
	static function getTransitions(string $name, int $start, int $end): array {

		$start = mktime(0, 0, 0, 1, 1, intval(gmdate('Y', $start)));
		Msg::InfoMsg('Checking time between "'.gmdate(Config::UTC_TIME, intval($start)).
									'" and "'.gmdate(Config::UTC_TIME, intval($end)).'"');

		$trans = [ 'STANDARD' => [], 'DAYLIGHT' => [] ];
		$tz = new \DateTimeZone($name);
		$tz = $tz->getTransitions();

		for($i=0; isset($tz[$i]); $i++) {

	   		if ($tz[$i]['ts'] > $end)
	   			break;
			if ($tz[$i]['ts'] > $start) {

				$trans[ $tz[$i]['isdst'] ? 'DAYLIGHT' : 'STANDARD' ][] = $tz[$i];
				if (count($trans['STANDARD']) < count($trans['DAYLIGHT']))
					$trans['STANDARD'][] = $tz[$i - 1];
	   		}
	   	}

	   	if (!count($trans['STANDARD'])) {

			$d = new \DateTime(gmdate(\DateTimeInterface::ISO8601, $start), new \DateTimeZone('UTC'));
			$d->setTimezone(new \DateTimeZone($name));
			$trans['STANDARD'][] = [ 'ts' => $start, 'time' => $d->format(\DateTimeInterface::ISO8601),
									 'offset' => $d->format('Z'), 'isdst' => '', 'abbr' => $d->format('T') ];
	   	}

		Msg::InfoMsg($trans, 'Time zone "'.$name.'" contains '.count($trans['STANDARD']).' "STANDARD" and '.
						   count($trans['DAYLIGHT']).' "DAYLIGHT" entries');

	   	return $trans;
	}

	/**
	 *  Convert ISO-8601 duration
	 *  https://tools.ietf.org/html/rfc5545#section-3.3.6
	 *
	 *  @param  - true = String to seconds; false = Seconds to string
	 *  @param  - Duration parameter
	 *  @return - true = Seconds / String or null on error
	 */
	static function cnvDuration(bool $mod, $str): ?string {

	    if (is_null($str))
	    	return '0';

	    if ($mod) {

    	    if (strpos($str, 'P') === false)
    	        return null;
    	    $sec = strtotime('19700101UTC+'.str_replace(
    	               [ 'P', 'T', 'W', 'D', 'H', 'M', 'S' ], [ '', '', 'Week', 'Day', 'Hours', 'Minute', 'Second' ], $str));
    	    if (!$sec)
    	    	$sec = 0;
    	    Msg::InfoMsg('"'.$str.'" converted to second "'.$sec.'"');
    	    return strval($sec);
	    }
	    $old = $sec = intval($str);
	    $d1  = $sec < 0 ? '-P' : 'P';
	    $sec = abs($sec);
	    // 60*60*24*7
	    if ($n = floor($sec / 604800))
	        $d1 .= sprintf('%dW', $n);
	    $sec %= 604800;
	    // 60*60*24
	    if ($n = floor($sec / 86400))
	        $d1 .= sprintf('%dD', $n);
	    $d2 = '';
        $sec %= 86400;
        // 60*60
        if ($n = floor($sec / 3600))
	        $d2 .= sprintf('%dH', $n);
        $sec %= 3600;
        if ($n = $sec / 60)
	        $d2 .= sprintf('%dM', $n);
        if (($sec %= 60) || !$old)
    	    $d2 .= sprintf('%dS', $sec);

    	$dur = $d1.($d2 ? 'T' : '').$d2;

        Msg::InfoMsg('"'.$str.'" converted to duration "'.$dur.'"');

        return $dur;
	}

	/**
	 * 	Check for binary string
	 *
	 * 	@param	- String
	 * 	@return	- true or false
	 */
	static function isBinary(string $data): bool {

		// https://stackoverflow.com/questions/25343508/detect-if-string-is-binary
		return !preg_match('//u', $data); // the string is binary
	}

	/**
	 * 	Convert picture
	 *
	 * 	@param	- Binary image data
	 * 	@param	- Output format (e.g. PNG)
	 * 	@param	- Optional new width (or 0)
	 * 	@param	- Optional new height (or 0)
	 * 	@return	- Image information array or null on error
	 */
	static function cnvImg(string $data, string $ofmt, int $w = 0, int $h = 0) {

	    // empty data?
	    if (!$data)
	        return null;

		$t = Util::getTmpFile();
		if (file_put_contents($t, $data) === false) {

		    unlink($t);
			return null;
		}

		if (($inf = getimagesize($t)) === false) {

		    unlink($t);
			return null;
		}

		$ofmt = strtolower($ofmt);

		// do we support requested output format
		if (!isset(self::PIC_FMT[$ofmt])) {

		    unlink($t);
			return null;
		}

		// get function name to convert
		$ofunc = self::PIC_FMT[$ofmt];

		// get conversion functions
		switch($inf[2]) {
		case IMAGETYPE_GIF:
			$ifunc = 'imagecreatefromgif';
			$ifmt  = 'gif';
			break;

		case IMAGETYPE_JPEG:
			$ifunc = 'imagecreatefromjpeg';
			$ifmt  = 'jpeg';
			break;

		case IMAGETYPE_PNG:
			$ifunc = 'imagecreatefrompng';
			$ifmt  = 'png';
			break;

		case IMAGETYPE_WBMP:
			$ifunc = 'imagecreatefromwbmp';
			$ifmt  = 'wbmp';
			break;

		case IMAGETYPE_XBM:
			$ifunc = 'imagecreatefromxbm';
			$ifmt  = 'xbm';
			break;

		case IMAGETYPE_SWF:
		case IMAGETYPE_PSD:
		case IMAGETYPE_BMP:
		case IMAGETYPE_TIFF_II:
		case IMAGETYPE_TIFF_MM:
		case IMAGETYPE_JPC:
		case IMAGETYPE_JP2:
		case IMAGETYPE_JPX:
		case IMAGETYPE_JB2:
		case IMAGETYPE_SWC:
		case IMAGETYPE_IFF:
		default:
		    unlink($t);
			return null;
		}

		// new picture size given?
		if ($w && $h && ($inf[0] != $w || $inf[1] != $h)) {

			// get ratio
			$r1 = $w / $inf[0];
			$r2 = $h / $inf[1];
			$r = $r1 < $r2 ? $r1 : $r2;
			// resize image
			$w = ceil($inf[0] * $r);
			$h = ceil($inf[1] * $r);
		} else {

			// same size, same format?
			if ($ofmt == $ifmt) {

				$inf['newdata'] = $data;
				return $inf;
			}

			// we only convert format
			$w = $inf[0];
			$h = $inf[1];
		}

		// create working picture
		$np = imagecreatetruecolor($w, $h);

		// convert image to internal format
		if (!($s = @$ifunc($t))) {
		    unlink($t);
			imagedestroy($np);
			return null;
		}

		// resample image
		@imagecopyresampled($np, $s, 0, 0, 0, 0, $w, $h, $inf[0], $inf[1]);
		// convert image to new format
		@$ofunc($np, $t);

		// get new image data
		$inf = @getimagesize($t);

		// cleanup memory
		imagedestroy($np);

		// load new picture
		$inf['newdata'] = @file_get_contents($t);
   		unlink($t);

		// save input information
		$inf['old_format'] 	= $ifmt;
		$inf['old_with'] 	= $w;
		$inf['old_height']	= $h;

		Msg::InfoMsg($inf, 'Converting picture');

		return $inf;
	}

	/**
	 * 	Create unique hash value
	 *
	 * 	@param 	- String to hash
	 * 	@return - Hash value
	 */
	static function Hash(string $str): string {

		return hash('adler32', $str);
	}

	/**
	 * 	Returns a GUIDv4 string
	 *
	 * @param 	- Encode Windows like in brackets
	 * @return 	- New GUID
	 */
	static function WinGUID(bool $trim = true): string {

		// Windows
    	if (function_exists('com_create_guid') === true) {

    	    if ($trim === true)
        	    return trim(com_create_guid(), '{}');
        	else
        	    return com_create_guid();
    	}

    	// OSX/Linux
    	if (function_exists('openssl_random_pseudo_bytes') === true) {

    	    $data = openssl_random_pseudo_bytes(16);
    	    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);    // set version to 0100
    	    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);    // set bits 6-7 to 10
    	    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    	}

	    // Fallback (PHP 4.2+)
	    mt_srand((double)microtime() * 10000);
	    $charid = strtolower(md5(uniqid(rand(), true)));
	    $hyphen = chr(45);                  // "-"
	    $lbrace = $trim ? "" : chr(123);    // "{"
	    $rbrace = $trim ? "" : chr(125);    // "}"
	    $guidv4 = $lbrace.
	              substr($charid,  0,  8).$hyphen.
	              substr($charid,  8,  4).$hyphen.
	              substr($charid, 12,  4).$hyphen.
	              substr($charid, 16,  4).$hyphen.
	              substr($charid, 20, 12).
	              $rbrace;


		return $guidv4;
	}

}
