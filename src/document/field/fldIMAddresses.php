<?php
declare(strict_types=1);

/*
 *  IM field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Msg;
use syncgw\lib\XML;

class fldIMAddresses extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG		  		= [
									fldIMskype::TAG,
									fldIMicq::TAG,
									fldIMjabber::TAG,
									fldIMaim::TAG,
									fldIMmsn::TAG,
									fldIMyahoo::TAG,
	];

	/*
	 IMPP-param = "VALUE=uri" / pid-param / pref-param / type-param
                / mediatype-param / altid-param / any-param
     IMPP-value = URI
	 */
	const RFCA_PARM			= [
		// description see fldHandler:check()
	    'uri'			  	=> [
		  'VALUE'			=> [ 1, 'uri ' ],
		  'PID'			  	=> [ 0 ],
		  'PREF'			=> [ 2, '1-100' ],
		  'TYPE'			=> [ 1, ' work home x- ' ],
		  'MEDIATYPE'		=> [ 7 ],
		  'ALTID'			=> [ 0 ],
		  '[ANY]'			=> [ 0 ],
		]
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldIMAddresses
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldIMAddresses {

		if (!self::$_obj) {

            self::$_obj = new self();
			// clear tag deletion status
			foreach (self::TAG as $tag)
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

		$xml->addVar('Opt', sprintf('&lt;%s&gt; field handler'), 'IMAdresses');

		foreach ([ fldIMskype::TAG, fldIMicq::TAG, fldIMjabber::TAG, fldIMaim::TAG ,
				   fldIMmsn::TAG, fldIMyahoo::TAG] as $tag)
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

					Msg::InfoMsg('['.$rec['T'].'] wrong data type "text" for "'.$rec['D'].
											'" - should be "uri" - dropping record');
					continue;
				}
				// check parameter
				parent::check($rec, self::RFCA_PARM[$var]);
				$p['scheme'] = strtolower($p['scheme']);
	 			if (strpos('skype icq jabber aim msn yahoo', $p['scheme']) === false) {

					Msg::InfoMsg('['.$rec['T'].'] unsupported scheme "'.$p['scheme'].'" - dropping record');
					break;
				}
				foreach (self::TAG as $tag) {

					if (stripos($tag, $p['scheme']) !== false)
						break;
				}
            	// clear tag deletion status
				 if (isset(parent::$Deleted[$tag]) && !parent::$Deleted[$tag])
					unset(parent::$Deleted[$tag]);
				parent::delTag($int, $tag);
	    		unset($rec['P']['VALUE']);
		      	$int->addVar($tag, $rec['D'], false, $rec['P']);
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
			if ($ver != 4.0) {

				Msg::InfoMsg('['.$xpath.'] not supported in "'.$typ.'" "'.
										($ver ? sprintf('%.1F', $ver) : 'n/a').'"');
				break;
			}

			$recs = [];
			foreach (self::TAG as $t) {

				$ip = $int->savePos();
				$int->xPath($ipath.$t, false);
				while (($val = $int->getItem()) !== null) {

					$a = $int->getAttr();
					// $a['VALUE'] = 'uri';
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
