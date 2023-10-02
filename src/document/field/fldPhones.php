<?php
declare(strict_types=1);

/*
 *  Phone field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Msg;
use syncgw\lib\Config;
use syncgw\lib\XML;

class fldPhones extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG		 	 	= [
						   			fldHomePhone::TAG		    => [ 2, [ 'TYPE' => 'home',			  'PREF' => '1'  ]],
						   			fldHomePhone2::TAG		=> [ 2, [ 'TYPE' => 'home',			  'PREF' => '2'  ]],
						   			fldHomeFax::TAG	        => [ 2, [ 'TYPE' => 'home,fax',						 ]],

						   			fldBusinessPhone::TAG		=> [ 2, [ 'TYPE' => 'work',			  'PREF' => '1'  ]],
						   			fldBusinessPhone2::TAG	=> [ 2, [ 'TYPE' => 'work',			  'PREF' => '2'  ]],
						   			fldBusinessFax::TAG	    => [ 2, [ 'TYPE' => 'work,fax',						 ]],

						   			fldCompanyPhone::TAG	    => [ 2, [ 'TYPE' => 'work',			  'PREF' => '10' ]],
						   			fldAssistantPhone::TAG	=> [ 2, [ 'TYPE' => 'work,x-assistant',				 ]],

						   			fldPager::TAG		        => [ 1, [ 'TYPE' => 'pager',						 ]],
						   			fldMobilePhone::TAG	 	=> [ 1, [ 'TYPE' => 'cell',							 ]],
						   			fldCarPhone::TAG	 	    => [ 1, [ 'TYPE' => 'x-car',						 ]],
						   			fldMMSPhone::TAG			=> [ 1, [ 'TYPE' => 'text',							 ]],
						   			fldRadioPhone::TAG		=> [ 1, [ 'TYPE' => 'x-radio',						 ]],
						   			fldVideoPhone::TAG		=> [ 1, [ 'TYPE' => 'video',						 ]],

						   			fldUmCallerID::TAG		=> [ 2, [ 'TYPE' => 'x-other',		   'PREF' => '1' ]],
	];
	// level 2
	const LVL2		 		= [
						   			fldHomePhone::TAG		    => [ 1, [ 'TYPE' => 'home'							  ]],
						   			fldBusinessPhone::TAG		=> [ 1, [ 'TYPE' => 'work'							  ]],
						   			fldUmCallerID::TAG		=> [ 0, [										      ]],
	];

	// Parameter (v4.0)		TYPE=home		- Home number
	//					 	TYPE=work		- Business number
	//					 	TYPE=text		- Supports text messages (SMS)
	//					 	TYPE=voice	   	- Voice number
	//					 	TYPE=fax		- Facsimile number
	//					 	TYPE=cell		- Cellular number
	//					 	TYPE=video	   	- Video-phone number
	//					 	TYPE=pager	   	- Pager number
	//					 	TYPE=textphone  - Telecommunication device for people with hearing or speech difficulties
	// Parameter (v3.0)		TYPE=home		- Home number
	//					 	TYPE=work		- Business number
	//					 	TYPE=msg		- Messaging service on the number
	//					 	TYPE=pref		- Preffered number
	//						TYPE=voice	   	- Voice number
	//					 	TYPE=cell		- Cellular number
	//					 	TYPE=video	   	- Video-phone number
	//					 	TYPE=video	   	- Video-phone number
	//					 	TYPE=pager	   	- Pager number
	//					 	TYPE=bbs		- Bulletin board service number
	//					 	TYPE=modem	   	- MODEM number
	//					 	TYPE=car		- Car-phone number
	//					 	TYPE=isdn		- ISDN number
	//					 	TYPE=pcs		- Personal communication services
	// Parameter (v2.1)		HOME			- Home number
	//					 	WORK			- Business number
	//					 	PREF			- Prefferred number
	//					 	VOICE			- Voice number (Default)
	//					 	FAX			  	- Facsimile number
	//					 	MSG			  	- Messaging service on the number
	//					 	CELL			- Cellular number
	//					 	PAGER			- Pager number
	//					 	BBS			  	- Bulletin board service number
	//					 	MODEM			- MODEM number
	//					 	CAR			  	- Car-phone number
	//					 	ISDN			- ISDN number
	//					 	VIDEO			- Video-phone number

	/*
	 TEL-param = TEL-text-param / TEL-uri-param
     TEL-value = TEL-text-value / TEL-uri-value
       ; Value and parameter MUST match.

     TEL-text-param = "VALUE=text"
     TEL-text-value = text

     TEL-uri-param = "VALUE=uri" / mediatype-param
     TEL-uri-value = URI

     TEL-param =/ type-param / pid-param / pref-param / altid-param
                / any-param

     type-param-tel = "text" / "voice" / "fax" / "cell" / "video"
                    / "pager" / "textphone" / iana-token / x-name
       ; type-param-tel MUST NOT be used with a property other than TEL.
	 */
	const RFCA_PARM			= [
		// description see fldHandler:check()
	    'uri'			  	=> [
		  'VALUE'			=> [ 1, 'uri ' ],
		  'MEDIATYPE'		=> [ 7 ],
		  'TYPE'			=> [ 1, ' work home text voice fax cell video pager textphone x- ' ],
		  'PID'			  	=> [ 0 ],
		  'PREF'			=> [ 2, '1-100' ],
		  'ALTID'			=> [ 0 ],
		  '[ANY]'			=> [ 0 ],
		],
		'text'			 	=> [
		  'VALUE'			=> [ 1, 'text ' ],
		  'TYPE'			=> [ 1, ' work home text voice fax cell video pager textphone x-assistant x-car x-radio x- ' ],
		  'PID'			  	=> [ 0 ],
		  'PREF'			=> [ 2, '1-100' ],
		  'ALTID'			=> [ 0 ],
		  '[ANY]'			=> [ 0 ],
		]
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldPhones
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldPhones {

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
				// defaults to type text
				$var = 'text';
				$p = parse_url($rec['D']);
				if (isset($p['scheme']))
					$var = 'uri';
				// check parameter
				parent::check($rec, self::RFCA_PARM[$var]);
				if (!isset($rec['P']['TYPE'])) {
					Msg::InfoMsg($rec, '['.$rec['T'].'] [TYPE] missing - skipping record');
					continue;
				}
				// remove tel:
				if ($var == 'uri')
				   $rec['D'] = substr($rec['D'], 3);
				foreach ([ self::TAG, self::LVL2 ] as $chk) {

					if ($t = parent::match($rec, $chk)) {

            		    // clear tag deletion status
    				    if (isset(parent::$Deleted[$t]) && !parent::$Deleted[$t])
	       			        unset(parent::$Deleted[$t]);
					    parent::delTag($int, $t);
						unset($rec['P']['VALUE']);
						$int->addVar($t, $rec['D']);
						$rc = true;
						break;
					}
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
				$int->xpath($ipath.$t, false);
				while (($val = $int->getItem()) !== null) {

					$a = $int->getAttr();
					// $a['VALUE'] = '???';
					if (isset($parm[1]['PREF']))
						$a['PREF'] = isset($a['PREF']) ? $a['PREF'].','.$parm[1]['PREF'] : $parm[1]['PREF'];
					if (isset($parm[1]['TYPE']))
						$a['TYPE'] = isset($a['TYPE']) ? $a['TYPE'].','.$parm[1]['TYPE'] : $parm[1]['TYPE'];
					if ($ver != 4.0) {

						$t = isset($a['TYPE']) ? $a['TYPE'] : '';
						if (isset($a['PREF']) && $a['PREF'] != '1') {

		   			  		$t .= ($t ? ',' : '').'pref';
				  			unset($a['PREF']);
						}
		   			  	if ($t) {

				  			$a['TYPE'] = '';
							foreach (explode(',', $t) as $v) {

								if ($ver != 4.0) {

					 				if (stripos('x-msg x-bbs x-modem x-car x-isdn ', $v.' ') !== false)
							  			$v = substr($v, 2);
			  		   				 elseif ($v == 'text' || $v == 'textphone')
	   			   			  			continue;
		   						}
				   				// filter v3.0 parameter
			  			 		if ($ver == 3.0 && ($v == 'text' || $v == 'textphone'))
			  		 				continue;
					  			if ($a['TYPE'])
			  				  		$a['TYPE'] .= ','.$v;
								else
				 					$a['TYPE'] = $v;
			 				}
			   			}
		   				// special hack?
				 		$cnf  = Config::getInstance();
						$hack = $cnf->getVar(Config::HACK);
						if (isset($a['TYPE']) && $hack & Config::HACK_NOKIA)
		   					$a['TYPE'] = str_replace('voice', '', $a['TYPE']);
					}
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
