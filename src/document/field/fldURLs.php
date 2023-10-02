<?php
declare(strict_types=1);

/*
 *  URL field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Msg;
use syncgw\lib\XML;

class fldURLs extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG		  		= [
						   			fldURLHome::TAG       => [ 1, [ 'TYPE' => 'home'	  	]],
						   			fldURLWork::TAG      	=> [ 1, [ 'TYPE' => 'work'	  	]],
						   			fldURLOther::TAG		=> [ 1, [ 'TYPE' => 'x-other'   ]],
	];

	// Parameter (v4.0)		TYPE=home		- Home URL
	//					 	TYPE=work		- Business URL
	// Parameter (v3.0)		-
	// Parameter (v2.1)		-

	/*
	 URL-param = "VALUE=uri" / pid-param / pref-param / type-param
               / mediatype-param / altid-param / any-param
     URL-value = URI
	 */
	const RFCA_PARM			= [
		// description see fldHandler:check()
	    'uri'			  	=> [
		  'VALUE'			=> [ 1, 'uri ' ],
		  'PID'			  	=> [ 0 ],
		  'PREF'			=> [ 2, '1-100' ],
		  'TYPE'			=> [ 1, 'work home x- ' ],
		  'ALTID'			=> [ 0 ],
		  '[ANY]'			=> [ 0 ],
		]
	];

	/*
	 url        = "URL" urlparam ":" uri CRLF

     urlparam   = *(";" xparam)
	 */
	const RFCC_PARM			= [
		// description see fldHandler:check()
	    'uri'			  	=> [
		  'VALUE'			=> [ 1, 'uri ' ],
		  '[ANY]'			=> [ 0 ],
		],
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldURLs
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldURLs {

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

		$xml->addVar('Name', sprintf('&lt;%s&gt; field handler', 'FieldsURLs'));

		foreach ([ fldURLHome::TAG, fldURLWork::TAG, fldURLOther::TAG] as $tag)
			$xml->addVar('Name', sprintf('&lt;%s&gt; field handler', $tag));
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

		$rc   = false;
		$parm = null;

		switch ($typ) {
		case 'text/vcard':
		case 'text/x-vcard':
			$parm = self::RFCA_PARM['uri'];

		case 'text/calendar':
		case 'text/x-vcalendar':
			if (!$parm)
				$parm = self::RFCC_PARM['uri'];

			foreach ($ext as $rec) {

				if ($rec['T'] != $xpath)
					continue;
				$p = parse_url($rec['D']);
				if (!isset($p['scheme']))
					$rec['D'] = 'http://'.$rec['D'];
				// check parameter
				parent::check($rec, $parm);
				$ok = false;
				if ($t = parent::match($rec, self::TAG)) {

            		// clear tag deletion status
				    if (isset(parent::$Deleted[$t]) && !parent::$Deleted[$t])
				        unset(parent::$Deleted[$t]);
				    parent::delTag($int, $t);
					unset($rec['P']['VALUE']);
					$int->addVar($t, $rec['D'], false, $rec['P']);
					$rc = true;
					$ok = true;
				}
				if (!$ok) {

					$t = fldURLOther::TAG;
					Msg::InfoMsg('['.$rec['T'].'] matching failed - storing as ['.$t.']');
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
			$recs = [];
			foreach (self::TAG as $t => $parm) {

				$ip = $int->savePos();
				$int->xpath($ipath.$t, false);
				while (($val = $int->getItem()) !== null) {

					if ($ver == 4.0) {

						$a		   = $int->getAttr();
						$a['TYPE'] = isset($a['TYPE']) ? $a['TYPE'].','.$parm[1]['TYPE'] : $parm[1]['TYPE'];
					} else
						$a = [];
					// $a['VALUE'] = 'uri';
					$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => $val ];
				}
				$int->restorePos($ip);
			}
			if (count($recs))
				$rc = $recs;
			break;

		case 'text/calendar':
		case 'text/x-vcalendar':
			$recs = [];
			$int->xpath($ipath.fldURLOther::TAG, false);
			while (($val = $int->getItem()) !== null) {

				$a = $int->getAttr();
				// $a['VALUE'] = 'uri';
				$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => $val ];
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
