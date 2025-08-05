<?php
declare(strict_types=1);

/*
 *  Address field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Util;
use syncgw\lib\XML;

class fldAddresses extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG		  		= [
							   fldAddressHome::TAG	    => [ 1, [ 'TYPE' => 'home'   ]],
							   fldAddressBusiness::TAG	=> [ 1, [ 'TYPE' => 'work'   ]],
							   fldAddressOther::TAG 	    => [ 0, [					 ]],
   	];
	const SUB_TAG		  	= [ 'PostOffice', 'ExtendedAddress', 'Street', 'City', 'Region', 'PostalCode', 'Country' ];

	/*
	 label-param = "LABEL=" param-value

     ADR-param = "VALUE=text" / label-param / language-param
               / geo-parameter / tz-parameter / altid-param / pid-param
               / pref-param / type-param / any-param
     ADR-value = ADR-component-pobox ";" ADR-component-ext ";"
                 ADR-component-street ";" ADR-component-locality ";"
                 ADR-component-region ";" ADR-component-code ";"
                 ADR-component-country
     ADR-component-pobox    = list-component
     ADR-component-ext      = list-component
     ADR-component-street   = list-component
     ADR-component-locality = list-component
     ADR-component-region   = list-component
     ADR-component-code     = list-component
     ADR-component-country  = list-component
	 */
	const RFCA_PARM			= [
		// description see fldHandler:check()
	    'text'				=> [
		  'VALUE'			=> [ 1, 'text ' ],
		  'LABEL'			=> [ 0 ],
		  'LANGUAGE'		=> [ 6 ],
		  'GEO'			  	=> [ 0 ],
		  'TZ'			   	=> [ 0 ],
		  'ALTID'			=> [ 0 ],
		  'PID'			  	=> [ 0 ],
		  'PREF'			=> [ 2, '1-100' ],
		  'TYPE'			=> [ 1, ' work home x- ' ],
		  '[ANY]'			=> [ 0 ],
		]
	];

  	/**
     * 	Singleton instance of object
     * 	@var fldAddresses
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldAddresses {

		if (!self::$_obj) {

            self::$_obj = new self();
			// clear tag deletion status
			foreach (self::TAG as $tag => $unused)
			    parent::$Deleted[$tag] = 0;
			$unused; // disable Eclipse warning
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

		$rc = false;

		switch ($typ) {
		case 'text/vcard':
		case 'text/x-vcard':
			 foreach ($ext as $rec) {

				if ($rec['T'] != $xpath)
					continue;
				if ($ver != 4.0 && isset($rec['P']['TYPE'])) {

					$t = '';
					$rec['P']['TYPE'] = strtolower($rec['P']['TYPE']);
					foreach (explode(',', $rec['P']['TYPE']) as $v) {

						// these types are allowed
						if (strpos(' home work ', $v.' ') !== false)
							$t   .= ($t ? ',' : '').$v;
						// these types needs to be changed
						if (strpos(' intl dom postal parcel ', $v.' ') !== false  && substr($v, 0, 2) != 'x-') {

							$v  = 'x-'.$v;
							$t .= ($t ? ',' : '').$v;
						}
					}
					if ($t)
						$rec['P']['TYPE'] = $t;
				}
				// check parameter
				parent::check($rec, self::RFCA_PARM['text']);
                unset($rec['P']['VALUE']);

				if ($t = parent::match($rec, self::TAG)) {

            		// clear tag deletion status
				    if (isset(parent::$Deleted[$t]) && !parent::$Deleted[$t])
				        unset(parent::$Deleted[$t]);
				    parent::delTag($int, $t);
					$ip = $int->savePos();
					$int->addVar($t, null, false, $rec['P']);
				    if ($a = Util::unfoldStr(str_replace("\;", "\#", $rec['D']), ';')) {

						for ($i=0; $i < 7; $i++) {

							if (!isset($a[$i]) || !$a[$i])
							    continue;
    						$a[$i] = parent::rfc6350(str_replace('\#', ';', $a[$i]));
							$int->addVar(self::SUB_TAG[$i], $a[$i]);
						}
					}
					$int->restorePos($ip);
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

		switch ($typ) {
		case 'text/vcard':
		case 'text/x-vcard':
			$recs = [];
			foreach (self::TAG as $t => $parm) {

				$ip = $int->savePos();
				$int->xPath($ipath.$t, false);
				while ($int->getItem() !== null) {

					$val = [];
					$a   = $int->getAttr();
					if (isset($parm[1]['TYPE']))
						$a['TYPE'] = isset($a['TYPE']) ? $a['TYPE'].','.$parm[1]['TYPE'] : $parm[1]['TYPE'];
					for ($i=0; $i < 7; $i++) {

						$p = $int->savePos();
						if ($v = $int->getVar(self::SUB_TAG[$i], false))
							$val[$i] = parent::rfc6350($v, false);
						else
							$val[$i] = '';
						$int->restorePos($p);
					}
					if ($ver != 4.0) {

						if (isset($a['TYPE']))
							$a['TYPE'] = str_replace('x-', '', $a['TYPE']);
						if (isset($a['PREF'])) {
							$a['TYPE'] .= ',pref';
							unset($a['PREF']);
						}
		   			}
					$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => Util::foldStr($val, ';') ];
				}
				$int->restorePos($ip);
			}
			if (count($recs))
				$rc = $recs;
			break;

		default:
			break;
		}

		return $rc;
	}

}
