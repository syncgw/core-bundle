<?php
declare(strict_types=1);

/*
 *  Label field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Msg;
use syncgw\lib\XML;

class fldLabels extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG			  	= [
						   		'LabelHome'		=> [ 1, [ 'TYPE' => 'home'   ]],
							   	'LabelWork' 	=> [ 1, [ 'TYPE' => 'work'   ]],
							   	'LabelOther'	=> [ 0, [					 ]],
 	];

	// Parameter (v3.0)		TYPE=dom		- Domestic address
	//					 	TYPE=intl		- International address
	//					 	TYPE=postal	  	- Postal address
	//					 	TYPE=parcel	  	- Parcel address
	// Parameter (v2.1)		DOM			  	- Domestic address
	//					 	INTL			- International address
	//					 	POSTAL		   	- Postal address
	//					 	PARCEL		   	- Parcel address
	//					 	HOME			- Home address
	//					 	WORK			- Business address

	const RFCA_PARM			= [
		// description see fldHandler:check()
	    'text'			 	=> [
		  'VALUE'			=> [ 1, 'text ' ],
		  '[ANY]'			=> [ 0 ],
		],
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldLabels
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldLabels {

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

					$t   			  = '';
					$rec['P']['TYPE'] = strtolower($rec['P']['TYPE']);
					foreach (explode(',', $rec['P']['TYPE']) as $v) {
						// change all "unknown" types
						if (strpos(' home work ', $v.' ') === false && substr($v, 0, 2) != 'x-') {
							$v  = 'x-'.$v;
							$t .= ($t ? ',' : '').$v;
						} else
							$t .= ($t ? ',' : '').$v;
					}
					$rec['P']['TYPE'] = $t;
				}
				// check parameter
				// parent::check($rec, self::RFCA_PARM['text']);
				if ($t = parent::match($rec, self::TAG)) {

            		// clear tag deletion status
				     if (isset(parent::$Deleted[$t]) && !parent::$Deleted[$t])
				        unset(parent::$Deleted[$t]);
				    parent::delTag($int, $t);
					unset($rec['P']['VALUE']);
					$int->addVar($t, parent::rfc6350($rec['D']), false, $rec['P']);
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
			if ($ver == 4.0) {

   				Msg::InfoMsg('['.$xpath.'] not supported in "'.$typ.'" "'.
   										($ver ? sprintf('%.1F', $ver) : 'n/a').'"');
				break;
			}

			$recs = [];
			foreach (self::TAG as $t => $parm) {

				$ip = $int->savePos();
				$int->xpath($ipath.$t, false);
				while (($val = $int->getItem()) !== null) {

					$a = $int->getAttr();
					if ($parm[0] == 1)
						$a['TYPE'] = isset($a['TYPE']) ? $a['TYPE'].','.$parm[1]['TYPE'] : $parm[1]['TYPE'];
					if ($ver != 4.0) {

						if (isset($a['TYPE']))
							$a['TYPE'] = str_replace('x-', '', $a['TYPE']);
						if (isset($a['PREF'])) {
							$a['TYPE'] .= ',pref';
							unset($a['PREF']);
						}
		   			}
		   			// $a['VALUE'] = 'text';
		   			if (strpos($val, "\n") !== false)
		   				$a['ENCODING'] = 'QUOTED-PRINTABLE';
					$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => parent::rfc6350($val, false) ];
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
