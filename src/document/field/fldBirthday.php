<?php
declare(strict_types=1);

/*
 *  Birthday field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Config;
use syncgw\lib\Util;
use syncgw\lib\XML;

class fldBirthday extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 			 = 'Birthday';

	/*
	 BDAY-param = BDAY-param-date / BDAY-param-text
     BDAY-value = date-and-or-time / text
       ; Value and parameter MUST match.

     BDAY-param-date = "VALUE=date-and-or-time"
     BDAY-param-text = "VALUE=text" / language-param

     BDAY-param =/ altid-param / calscale-param / any-param
       ; calscale-param can only be present when BDAY-value is
       ; date-and-or-time and actually contains a date or date-time.
	 */
	const RFCA_PARM			= [
		// description see fldHandler:check()
	    'date-time' 		=> [
		  'VALUE'			=> [ 1, 'date-time ' ],
		  'ALTID'			=> [ 0 ],
		  'CALSCALE'		=> [ 1, ' gregorian x- ' ],
		  '[ANY]'			=> [ 0 ],
		],
		'date' 	=> [
		  'VALUE'			=> [ 1, 'date' ],
		  'ALTID'			=> [ 0 ],
		  'CALSCALE'		=> [ 1, ' gregorian x- ' ],
		  '[ANY]'			=> [ 0 ],
		],
		'time' 	=> [
   		   'VALUE'			=> [ 1, 'time ' ],
		  'ALTID'			=> [ 0 ],
		  'CALSCALE'		=> [ 1, ' gregorian x- ' ],
		  '[ANY]'			=> [ 0 ],
		],
		'text' 				=> [
		  'VALUE'			=> [ 1, 'text ' ],
		  'LANGUAGE'		=> [ 6 ],
		],
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldBirthday
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldBirthday {

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
		case 'text/vcard':
		case 'text/x-vcard':
			foreach ($ext as $rec) {

				if ($rec['T'] != $xpath)
					continue;
				// check type
				$var = 'text';
				$p = date_parse($rec['D']);
				if (!$p['warning_count'] && !$p['error_count']) {

					if (!$p['day'])
						$var = 'time';
					elseif (!$p['hour'])
						$var = 'date';
					else
						$var = 'date-time';
				}
				// check parameter
				parent::check($rec, self::RFCA_PARM[$var]);
				parent::delTag($int, $ipath);
				if ($var != 'date-time')
					$rec['P']['VALUE'] = $var;
				$int->addVar(self::TAG, Util::unxTime($rec['D']), false, $rec['P']);
				$rc = true;
			}
			break;

		case 'application/activesync.contact+xml':
	   		if ($ext->xpath($xpath, false))
				parent::delTag($int, $ipath, '2.5');
			while (($val = $ext->getItem()) !== null) {

				if ($val) {
					$int->addVar(self::TAG, Util::unxTime(substr($val, 0, 11).'00:00:00Z'), false, [ 'VALUE' => 'date' ]);
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
		case 'text/vcard':
		case 'text/x-vcard':
			$recs = [];
			while (($val = $int->getItem()) !== null) {

				$a = $int->getAttr();
   				unset($a['VALUE']);
				$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => gmdate(Config::STD_DATE, intval($val)) ];
			}
			if (count($recs))
				$rc = $recs;
			break;

		case 'application/activesync.contact+xml':
			while (($val = $int->getItem()) !== null) {

				$ext->addVar($tag, gmdate(Config::masTIME, intval($val)), false, $ext->setCP(XML::AS_CONTACT));
				$rc	= true;
			}
			break;

		default:
			break;
		}

		return $rc;
	}

}
