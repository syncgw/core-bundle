<?php
declare(strict_types=1);

/*
 *  Geographical position field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Msg;
use syncgw\lib\XML;

class fldGeoPosition extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'GeoPosition';

	const RFC_SUB 			= [
			'Longitude',
			'Latitude',
	];

	/*
	 GEO-param = "VALUE=uri" / pid-param / pref-param / type-param
               / mediatype-param / altid-param / any-param
     GEO-value = URI
	 */
	const RFCA_PARM			= [
		// description see fldHandler:check()
	    'uri'			  	=> [
		  'VALUE'			=> [ 1, 'uri ' ],
		  'PID'			  	=> [ 0 ],
		  'PREF'			=> [ 2, '1-100' ],
		  'ALTID'			=> [ 0 ],
		  'MEDIATYPE'		=> [ 7 ],
		  '[ANY]'			=> [ 0 ],
		],
	];

	/*
	 geo        = "GEO" geoparam ":" geovalue CRLF

     geoparam   = *(";" xparam)

     geovalue   = float ";" float
     ;Latitude and Longitude components
	 */
	const RFCC_PARM			= [
		// description see fldHandler:check()
	    'uri'			  	=> [
		  'VALUE'			=> [ 1, 'uri ' ],
		  '[ANY]'			=> [ 0 ],
		],
	];

	const ASC_SUB 			= [
			// <Longitude> specifies the longitude of the event's location
			'Longitude',
			// <Latitude> specifies the latitude of the event's location
			'Latitude',
			// <Accuracy> specifies the accuracy of the values of the <Latitude> element and the <Longitude> element
			'Accuracy',
			// <Altitude> specifies the altitude of an event's location
    		'Altitude',
			// <AltitudeAccuracy> specifies the accuracy of the value of the <Altitude> element
			'AltitudeAccuracy',
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldGeoPosition
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldGeoPosition {

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
				$p = parse_url($rec['D']);
				if ($ver != 4.0 && !isset($p['scheme'])) {

					if (strpos($rec['D'], ';') !== false) {

						$rec['D'] = 'geo:'.$rec['D'];
						$p = parse_url($rec['D']);
					}
				}
				if (!isset($p['scheme'])) {
					Msg::InfoMsg('['.$rec['T'].'] ['.$rec['D'].'] not "uri" - dropping record');
					continue;
				}
				// check parameter
				parent::check($rec, self::RFCA_PARM['uri']);
				parent::delTag($int, $ipath);
				unset($rec['P']['VALUE']);
				// removing uri
				$val = substr($rec['D'], 4);
				// change seperator < v4.0
				$val = str_replace(';', ',', $val);
				$val = explode(',', $val);
				$ip  = $int->savePos();
				$int->addVar(self::TAG, null, false, $rec['P']);
				$int->addVar(self::RFC_SUB[0], $val[0]);
				$int->addVar(self::RFC_SUB[1], $val[1]);
				$int->restorePos($ip);
				$rc = true;
	  		}
			break;

		case 'text/calendar':
		case 'text/x-vcalendar':
			foreach ($ext as $rec) {

				if ($rec['T'] != $xpath)
					continue;
				// check type
				$p = parse_url($rec['D']);
				if (!isset($p['scheme'])) {

					$rec['D'] = 'geo:'.$rec['D'];
					$p = parse_url($rec['D']);
				}
				if (!isset($p['scheme'])) {

					Msg::InfoMsg('['.$rec['T'].'] ['.$rec['D'].'] not "uri" - dropping record');
					continue;
				}
				// check parameter
				parent::check($rec, self::RFCC_PARM['uri']);
				parent::delTag($int, $ipath);
				// removing uri
				$val = substr($rec['D'], 4);
				$val = str_replace(';', ',', $val);
				$val = explode(',', $val);
				$ip  = $int->savePos();
				$int->addVar(self::TAG, null, false, $rec['P']);
				$int->addVar(self::RFC_SUB[0], $val[0]);
				$int->addVar(self::RFC_SUB[1], $val[1]);
				$int->restorePos($ip);
				$rc = true;
	  		}
			break;

		case 'application/activesync.calendar+xml':
	   		if (!$xpath || $ext->xpath($xpath, false))
				parent::delTag($int, $ipath, '2.5');

			while ($ext->getItem() !== null) {

				$xp = $ext->savePos();
				foreach (self::ASC_SUB as $tag) {

					if ($val = $ext->getVar($tag, false)) {

						if (!$rc) {

							$int->addVar(self::TAG);
							$rc = true;
						}
						$int->addVar($tag, $val);
					}
					$ext->restorePos($xp);
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
			while ($int->getItem() !== null) {

				$ip     = $int->savePos();
				$val    = [];
				$val[0] = $int->getVar(self::RFC_SUB[0], false);
				$int->restorePos($ip);
				$val[1] = $int->getVar(self::RFC_SUB[1], false);
				$int->restorePos($ip);
				// change seperator
				if ($ver != 4.0)
					$val = implode(';', $val);
				else
					$val = 'geo:'.implode(',', $val);
				$a = $int->getAttr();
				// $a['VALUE'] ='uri';
				$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => $val ];
			}
			if (count($recs))
				$rc = $recs;
			break;

		case 'text/calendar':
		case 'text/x-vcalendar':
			$recs = [];
			while (($val = $int->getItem()) !== null) {

				$ip     = $int->savePos();
				$val    = [];
				$val[0] = $int->getVar(self::RFC_SUB[0], false);
				$int->restorePos($ip);
				$val[1] = $int->getVar(self::RFC_SUB[1], false);
				$int->restorePos($ip);
				// change seperator
				if ($ver == 1.0)
					$val = implode(';', $val);
				else
					$val = 'geo:'.implode(',', $val);
				$a = $int->getAttr();
				// $a['VALUE'] ='uri';
				$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => $val ];
			}
			if (count($recs))
				$rc = $recs;
			break;

		case 'application/activesync.calendar+xml':
			if (!class_exists($class = 'syncgw\\activesync\\masHandler') ||
				$class::getInstance()->callParm('BinVer') < 16.0)
				break;

			while ($int->getItem() !== null) {

				$ip = $int->savePos();
				foreach (self::ASC_SUB as $tag) {

					if ($val = $int->getVar($tag, false)) {

						if (!$rc) {

							$int->addVar($ipath);
							$rc = true;
						}
						$ext->addVar($tag, $val, false, $ext->setCP(XML::AS_BASE));
					}
					$int->restorePos($ip);
				}
			}
			break;

		default:
			break;
		}

		return $rc;
	}

}
