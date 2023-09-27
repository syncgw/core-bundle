<?php
declare(strict_types=1);

/*
 *  Org field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Util;
use syncgw\lib\XML;

class fldOrganization extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG       	    = 'Organization';
	const SUB_TAG		  	= [
	                            self::TAG,
								fldCompany::TAG,
								fldDepartment::TAG,
								fldOffice::TAG,
	];

	// Content value	   	P0  - Organization name
	//					 	P1  - Company name
	//					 	P2  - Department name
	//					 	P3  - Office name

	/*
	 ORG-param = "VALUE=text" / sort-as-param / language-param
               / pid-param / pref-param / altid-param / type-param
               / any-param
     ORG-value = component *(";" component)
	 */
	const RFCA_PARM			= [
		// description see fldHandler:check()
	    'text'			 	=> [
		  'VALUE'			=> [ 1, 'text ' ],
		  'SORT-AS'		  	=> [ 0 ],
		  'LANGUAGE'		=> [ 6 ],
		  'PID'			  	=> [ 0 ],
		  'PREF'			=> [ 2, '1-100' ],
		  'ALTID'			=> [ 0 ],
		  'TYPE'			=> [ 1, ' work home x-other x-intl x-dom x-postal x-parcel x- ' ],
		  '[ANY]'			=> [ 0 ],
		],
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldOrganization
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldOrganization {

		if (!self::$_obj) {

            self::$_obj = new self();
			// clear tag deletion status
			foreach (self::SUB_TAG as $tag)
			    parent::$Deleted[$tag] = 0;
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

		$xml->addVar('Opt', sprintf('&lt;%s&gt; field handler'), 'Org');
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
				// check parameter
				parent::check($rec, self::RFCA_PARM['text']);
				if ($a = Util::unfoldStr(str_replace("\;", "\#", $rec['D']), ';', 4)) {

					for ($i=0; $i < 4; $i++) {

                		// clear tag deletion status
	       			    if (isset(parent::$Deleted[$i]) && !parent::$Deleted[self::SUB_TAG[$i]])
			     	        unset(parent::$Deleted[self::SUB_TAG[$i]]);
					    parent::delTag($int, self::SUB_TAG[$i]);
						if (!$a[$i])
						    continue;
						$a[$i] = parent::rfc6350(str_replace('\#', ';', $a[$i]));
						$int->addVar(self::SUB_TAG[$i], $a[$i], false, $rec['P']);
					}
				}
				$rc = true;
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
			$vals = [];
			$attr = [];
			// collect all data
			foreach (self::SUB_TAG as $t) {

				$ip = $int->savePos();
				$int->xPath($ipath.$t, false);
				while (($vals[$t][] = $int->getItem()) !== null)
					$attr[$t][] = $int->getAttr();
				array_pop($vals[$t]);
				$int->restorePos($ip);
			}
			// send all data
			while (1) {

				$val = [];
				$a   = [];
				$ok  = false;
				foreach (self::SUB_TAG as $t) {

				    if (count($vals[$t])) {

				        $ok = true;
    					$val[] = parent::rfc6350(array_shift($vals[$t]), false);
				    } else
    					$val[] = '';
					if (isset($attr[$t]))
 						$a += is_array($attr[$t]) && count($attr[$t]) ? array_shift($attr[$t]) : [];
				}
				if (!$ok)
					break;
				if ($ver != 4.0) {

					if (isset($a['TYPE']))
						$a['TYPE'] = str_replace('x-', '', $a['TYPE']);
					if (isset($a['PREF'])) {

						$a['TYPE'] .= ',pref';
						unset($a['PREF']);
					}
				}
				// strip off from back
				$v = '';
				while ($t = array_shift($val))
					$v .= $t.';';
				$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => substr($v, 0, -1) ];
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
