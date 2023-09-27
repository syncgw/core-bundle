<?php
declare(strict_types=1);

/*
 *  Member field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Msg;
use syncgw\lib\XML;

class fldMember extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG		  		= [
								fldGovernmentId::TAG  => [ 1, [ 'ALTID' => 'gov'  ]],
						   		fldCustomerId::TAG	=> [ 1, [ 'ALTID' => 'cust' ]],
						   		'OtherId'				=> [ 0, [				    ]],
	];

	/*
	 MEMBER-param = "VALUE=uri" / pid-param / pref-param / altid-param
                    / mediatype-param / any-param
     MEMBER-value = URI
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

   	/**
     * 	Singleton instance of object
     * 	@var fldMember
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldMember {

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
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
     *	@param 	- true = Provide status information only (if available)
	 */
	public function getInfo(XML &$xml, bool $status): void {

		$xml->addVar('Opt', sprintf('&lt;%s&gt; field handler'), 'Members');

		foreach ([ fldGovernmentId::TAG, fldCustomerId::TAG, 'OtherId' ] as $tag)
			$xml->addVar('Opt', sprintf('&lt;%s&gt; field handler', $tag));
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
				// defaults to type text
				$var = 'text';
				$p = parse_url($rec['D']);
				if (isset($p['scheme']))
					$var = 'uri';
				if ($var != 'uri') {

					Msg::InfoMsg('['.$rec['T'].'] wrong data type "text" for "'.
												$rec['D'].'" - should be "uri" - dropping record');
					continue;
				}
				// check parameter
				parent::check($rec, self::RFCA_PARM['uri']);
				if ($t = parent::match($rec, self::TAG)) {

            		// clear tag deletion status
				    if (isset(parent::$Deleted[$t]) && !parent::$Deleted[$t])
				        unset(parent::$Deleted[$t]);
				    parent::delTag($int, $t);
					unset($rec['P']['VALUE']);
					$int->addVar($t, $rec['D'], false, $rec['P']);
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
			if ($ver != 4.0) {

				Msg::InfoMsg('['.$xpath.'] not supported in "'.$typ.'" "'.
										($ver ? sprintf('%.1F', $ver) : 'n/a').'"');
				break;
			}

			$recs = [];
			foreach (self::TAG as $t => $parm) {

				$ip = $int->savePos();
				$int->xPath($ipath.$t, false);
				while (($val = $int->getItem()) !== null) {

					$a = $int->getAttr();
					// $a['VALUE'] = 'URI';
					if ($parm[0])
						$a['ALTID'] = isset($a['ALTID']) ? $a['ALTID'].','.$parm[1]['ALTID'] : $parm[1]['ALTID'];
					$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => $val ];
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
