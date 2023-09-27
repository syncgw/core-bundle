<?php
declare(strict_types=1);

/*
 *  EMail field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Msg;
use syncgw\lib\XML;

class fldMails extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG		  	= [
						   		fldMailHome::TAG		=> [ 1, [ 'TYPE' => 'home'	]],
							   	fldMailWork::TAG		=> [ 1, [ 'TYPE' => 'work'	]],
							   	fldMailOther::TAG		=> [ 0, [					]],
	];

	// Parameter (v4.0)		TYPE=home		- Home e-amil
	//					 	TYPE=work		- Business e-mail
	// Parameter (v3.0)		TYPE=internet	- Internet SMTP (default)
	//					 	TYPE=x400		- X.400 service
	// Parameter (v2.1)		AOL			  	- America On-Line
	//					 	AppleLink		- AppleLink
	//					 	ATTMail		  	- AT&T Mail
	//					 	CIS			  	- Indicates CompuServe Information Service
	//					 	eWorld		   	- eWorld
	//					 	INTERNET		- Internet SMTP (default)
	//					 	IBMMail		  	- IBM Mail
	//					 	MCIMail		  	- MCI Mail
	//					 	POWERSHARE	   	- PowerShare
	//					 	PRODIGY		  	- Prodigy information service
	//					 	TLX			  	- Telex number
	//					 	X400			- X.400 service

	/*
	 EMAIL-param = "VALUE=text" / pid-param / pref-param / type-param
                 / altid-param / any-param
     EMAIL-value = text
	 */
	const RFCA_PARM			= [
		// description see fldHandler:check()
	    'text'			 	=> [
		  'VALUE'			=> [ 1, 'text ' ],
		  'PID'			  	=> [ 0 ],
		  'PREF'			=> [ 2, '1-100' ],
		  'TYPE'			=> [ 1, ' work home x- ' ],
		  'ALTID'			=> [ 0 ],
		  '[ANY]'			=> [ 0 ],
		],
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldMails
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldMails {

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

		$xml->addVar('Opt', sprintf('&lt;%s&gt; field handler', 'Adresses'));

		foreach ([ fldMailHome::TAG, fldMailWork::TAG, fldMailOther::TAG ] as $tag)
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
				if ($ver != 4.0 && isset($rec['P']['TYPE'])) {

					$t = '';
					$rec['P']['TYPE'] = strtolower($rec['P']['TYPE']);
					foreach (explode(',', $rec['P']['TYPE']) as $v) {

						// these needs to be changed
						if (strpos(' home work ', $v.' ') === false && substr($v, 0, 2) != 'x-')
							$v  = 'x-'.$v;
						$t .= ($t ? ',' : '').$v;
					}
					$rec['P']['TYPE'] = $t;
				}
				// validate e-mail
				if (!preg_match('|[^0-9<][A-z0-9_]+([.][A-z0-9_]+)*[@][A-z0-9_]+([.][A-z0-9_]+)*[.][A-z]{2,4}|i', $rec['D'])) {

					Msg::InfoMsg('['.$rec['T'].'] "'.$rec['D'].'" invalid e-mail - dropping record');
					continue;
				}
				// check parameter
				parent::check($rec, self::RFCA_PARM['text']);
				if ($t = parent::match($rec, self::TAG)) {

            		// clear tag deletion status
				    if (isset(parent::$Deleted[$t]) && !parent::$Deleted[$t])
				        unset(parent::$Deleted[$t]);
					parent::delTag($int, $t);
					unset($rec['P']['VALUE']);
					if (isset($rec['P']['TYPE']) && !strlen($rec['P']['TYPE']))
						unset($rec['P']['TYPE']);
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

		$rc  = false;
		$tags = explode('/', $xpath);
		$tag  = array_pop($tags);

		switch ($typ) {
		case 'text/vcard':
		case 'text/x-vcard':
			$recs = [];
			foreach (self::TAG as $t => $parm) {

				$p = $int->savePos();
				$int->xPath($ipath.$t, false);
				while (($val = $int->getItem()) !== null) {

					$a = $int->getAttr();
					if ($parm[0]) {

						if (!isset($a['TYPE']))
							$a['TYPE'] = $parm[1]['TYPE'];
						else
							$a['TYPE'] .= ','.$parm[1]['TYPE'];
					}
					if ($ver != 4.0) {

						if (isset($a['TYPE']))
							$a['TYPE'] = str_replace('x-', '', $a['TYPE']);
						if (isset($a['PREF'])) {
							$a['TYPE'] .= ',pref';
							unset($a['PREF']);
						}
					} elseif (isset($a['TYPE'])) {

						$v = explode(',', $a['TYPE']);
						$t = '';
						foreach ($v as $v1) {
							if (substr($v1, 0, 2) != 'x-')
								$t .= ($t ? ',' : '').$v1;
						}
						if ($t)
							$a['TYPE'] = $t;
						else
							unset($a['TYPE']);
					}
					$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => $val ];
				}
				$int->restorePos($p);
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
