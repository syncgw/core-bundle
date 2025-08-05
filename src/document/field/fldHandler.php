<?php
declare(strict_types=1);

/*
 * 	Field handler class
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Msg;
use syncgw\lib\Device;
use syncgw\lib\Config;
use syncgw\lib\XML;

class fldHandler {

	static protected $Deleted = [];

	/**
	 * 	Delete existing tag from record
	 *
	 * 	@param 	- Internal document
	 *  @param  - Path to delete
	 *  @param  - Check ActiveSync Ghosted parameter = Version
	 *  @param  - Force deletion
	 */
	protected function delTag(XML &$int, string $path, string $minver = '', bool $force = false): void {

		if ((!$force && isset(self::$Deleted[$path])) || $int->getName() == $path)
			return;

		self::$Deleted[$path] = true;
		$ip = $int->savePos();

		if ($minver) {

			if (!class_exists($class = 'syncgw\\activesync\\masHandler'))
				return;

			$actver = $class::getInstance()->callParm('BinVer');

			// In protocol version 16.0 and 16.1, Calendar class elements are ghosted by default and
			// CLIENTS SHOULD NOT send unchanged elements in Sync command requests.
			// Ghosted elements are not sent to the server. Instead of deleting these excluded properties,
			// the server preserves their previous value.
			if ($actver >= 16.0) {

				$int->xpath('/syncgw/Data/'.$path);
				while ($int->getItem() !== null)
					$int->delVar(null, false);
				$int->restorePos($ip);
				return;
			}

			// load supported ghosting tags on client
			$dev = Device::getInstance();
			// is tag ghosted?
			if (strpos($dev->getVar('ClientManaged'), $path.';') === false || $actver > $minver) {

				$int->xpath('/syncgw/Data/'.$path);
				while ($int->getItem() !== null)
					$int->delVar(null, false);
				$int->restorePos($ip);
				return;
			}
			$int->getVar('Data');
		} else {

			$int->xpath('/syncgw/Data/'.$path);
			while ($int->getItem() !== null)
				$int->delVar(null, false);
			$int->restorePos($ip);
		}
	}

	/**
	 * 	Clean parameter
	 *
	 * 	@param 	- Record to check [ 'T' => Tag; 'P' => [ Parm => Val ]; 'D' => Data ]
	 *  @param  - [ [ typ, values ] ]
	 *             0 -Nothing to check
	 *             1 - Check constant
	 *             2 - Check range value
	 *             3 - Check e-mail
	 *             4 - Check text
	 *             5 - Check uri
	 *             6 - Check language
	 *             7 - Check mediatype
	 *             8 - Check format type
	 */
	protected function check(array &$rec, array $parms): void {

		$cnf = Config::getInstance();

		foreach ($rec['P'] as $tag => $val) {

			if ($cnf->getVar(Config::DBG_SCRIPT) == 'MIME01' || $cnf->getVar(Config::DBG_SCRIPT) == 'MIME02')
				Msg::InfoMsg('['.$rec['T'].'] ['.$tag.'] checking "'.$val.'"');

			// check [ANY] parameter
			if (substr($tag, 0, 2) == 'X-' && isset($parms['[ANY]'])) {
				if ($cnf->getVar(Config::DBG_SCRIPT) == 'MIME01' || $cnf->getVar(Config::DBG_SCRIPT) == 'MIME02')
					Msg::InfoMsg('['.$rec['T'].'] ['.$tag.'] allowed');
				continue;
			}

			// is paramneter supported?
			if (!isset($parms[$tag])) {
				Msg::InfoMsg('['.$rec['T'].'] ['.$tag.'] unknown - dropping parm ');
				unset($rec['P'][$tag]);
				continue;
			}

			// now we check in detail
			switch ($parms[$tag][0]) {
			// 0	 Nothing to check
	        // 6     @todo Check language (LANGUAGE)
	        // 7     @todo Check mediatype (MEDIATYPE)
	        // 8     @todo Check format type (FMTTYPE)
			case 0:
			case 6:
			case 7:
			case 8:
				if ($cnf->getVar(Config::DBG_SCRIPT) == 'MIME01' || $cnf->getVar(Config::DBG_SCRIPT) == 'MIME02')
					Msg::InfoMsg('['.$rec['T'].'] ['.$tag.'] any value allowed');
				break;

			// 1	 Check constant
			case 1:
				if ($cnf->getVar(Config::DBG_SCRIPT) == 'MIME01' || $cnf->getVar(Config::DBG_SCRIPT) == 'MIME02')
					Msg::InfoMsg('['.$rec['T'].'] ['.$tag.'] found - checking constant "'.$parms[$tag][1].'"');

	   			// [ANY] contant allowed?
	   			$xtag = stripos($parms[$tag][1], 'x-');
	   			$out  = '';

				// walk through constants
				foreach (explode(',', $val) as $t) {

					// normalize parameter
					$c = strtoupper($t);
   					if (substr($c, 0, 2) == 'X-' && $xtag)
   						$out .= $t.',';
					elseif (stripos($parms[$tag][1], $c.' ') !== false)
		   				$out .= $t.',';
				}
   				if (!$out) {
   					if ($val) {
   						Msg::InfoMsg('['.$rec['T'].'] ['.$tag.'] no constants matches "'.$val.'" - dropping parm');
						unset($rec['P'][$tag]);
   					}
   				} else {
					if ($val != substr($out, 0, -1))
						Msg::InfoMsg('['.$rec['T'].'] ['.$tag.'] value changed from "'.$val.'" to "'.
									 substr($out, 0, -1).'"');
					$rec['P'][$tag] = strtolower(substr($out, 0, -1));
				}
   				break;

			// 2	 Check range value
			case 2:
				if ($cnf->getVar(Config::DBG_SCRIPT) == 'MIME01' || $cnf->getVar(Config::DBG_SCRIPT) == 'MIME02')
					Msg::InfoMsg('['.$rec['T'].'] ['.$tag.'] - "'.$val.'" range check "'.$parms[$tag][1].'"');
				list($l, $h) = explode('-', $parms[$tag][1]);
				if ($val < $l || $val > $h) {
					Msg::InfoMsg('['.$rec['T'].'] ['.$tag.'] "'.$val.'" out of range - dropping parm');
					unset($rec['P'][$tag]);
				}
				break;

			// 3	 Check e-mail
			case 3:
				if ($cnf->getVar(Config::DBG_SCRIPT) == 'MIME01' || $cnf->getVar(Config::DBG_SCRIPT) == 'MIME02')
					Msg::InfoMsg('['.$rec['T'].'] ['.$tag.'] - e-mail check');
				if (!preg_match('|[^0-9<][A-z0-9_]+([.][A-z0-9_]+)*[@][A-z0-9_]+([.][A-z0-9_]+)*[.][A-z]{2,4}|i', $val)) {
					Msg::InfoMsg('['.$rec['T'].'] ['.$tag.'] "'.$val.'" invalid e-mail - dropping parm');
					unset($rec['P'][$tag]);
				}
				break;

    		// 4     Convert text
			case 4:
			    $rec['P'][$tag] = str_replace([ "\;", "\,", "\\\\" ], [ ";", ",", "\\" ], $rec['P'][$tag]);
			    break;

	        // 5     Check uri
			case 5:
			    $p = parse_url($rec['P'][$tag]);
			    if (!isset($p['scheme'])) {
					Msg::InfoMsg('['.$rec['T'].'] ['.$tag.'] "'.$val.'" invalid URI - dropping parm');
					unset($rec['P'][$tag]);
				}
				break;

			default:
				Msg::ErrMsg('['.$rec['T'].'] ['.$tag.'] - parm not found');
				break;
			}
		}
	}

	/**
	 *  Sanitize value
	 *
	 *  @param  - Value
	 *  @param  - true=In; false=Out
	 *  @return - Sanitized value
	 */
	protected function rfc6350(string $val, bool $mod = true): string {

		if ($mod)
		    return str_replace([ "\;", "\,", "\\\\", '\n' ], [ ";", ",", "\\", "\n" ], $val);
		else
		    return str_replace([ "\\", ";", ",", "\n" ], [ "\\\\", "\;", "\,", '\n' ], $val);
	}

	/**
	 *  Sanitize value
	 *
	 *  @param  - Value
	 *  @param  - true=In; false=Out
	 *  @return - Sanitized value
	 */
	protected function rfc5545(string $val, bool $mod = true): string {

		if ($mod)
		    return str_replace([ "\;", "\,", "\\\\", '\n', '\N' ], [ ";", ",", "\\", "\n", "\n" ], $val);
		else
		    return str_replace([ "\\", ";", ",", "\n" ], [ "\\\\", "\;", "\,", '\n' ], $val);
	}

	/**
	 *  Match parameter
	 *
	 * 	@param 	- Record to check [ 'T' => Tag; 'P' => [ Parm => Val ]; 'D' => Data ]
	 *  @param  - Parameter to check Tag => [ Match, [ Parm => Val ] ]
	 *  @return - Internal tag name or null
	 */
	protected function match(array &$rec, array $parms): ?string {

		foreach ($parms as $tag => $parm) {

			$match = 0;
			foreach ($parm[1] as $t => $v) {

				if (!isset($rec['P'][$t]))
					continue;
				$rec['P'][$t] = strtolower($rec['P'][$t]);

				foreach (explode(',', $v) as $v) {
					if (stripos($rec['P'][$t], $v) !== false)
						$match++;
					if (substr($v, 0, 2) == 'x-' && stripos($rec['P'][$t], substr($v, 2)) !== false)
						$match++;
				}
			}
			if ($match >= $parm[0]) {

				if (isset($rec['P'][$t]) && $rec['P'][$t]) {

					$p = array_flip(explode(',', $rec['P'][$t]));
					// remove matched tag from parameter
					foreach ($parm[1] as $t => $v) {

						foreach (explode(',', $v) as $v1) {

							unset($p[$v1]);
							unset($p['x-'.$v1]);
							if (substr($v1, 0, 2) == 'x-')
								unset($p[substr($v1, 2)]);
						}
					}
					if (!count($p))
						unset($rec['P'][$t]);
					else
						$rec['P'][$t] = implode(',', array_flip($p));
				}
				return $tag;
			}
		}

		return null;
	}

}
