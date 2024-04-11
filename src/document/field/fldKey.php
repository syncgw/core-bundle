<?php
declare(strict_types=1);

/*
 *  BinKey field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Attachment;
use syncgw\lib\XML;

class fldKey extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'Key';

	/*
	 KEY-param = KEY-uri-param / KEY-text-param
     KEY-value = KEY-uri-value / KEY-text-value
       ; Value and parameter MUST match.

     KEY-uri-param = "VALUE=uri" / mediatype-param
     KEY-uri-value = URI

     KEY-text-param = "VALUE=text"
     KEY-text-value = text

     KEY-param =/ altid-param / pid-param / pref-param / type-param
                / any-param
	 */
	const RFCA_PARM			= [
		// description see fldHandler:check()
	    'uri'			  	=> [
		  'VALUE'			=> [ 1, 'uri ' ],
		  'MEDIATYPE'		=> [ 0 ],
		  'ALTID'			=> [ 0 ],
		  'PID'			  	=> [ 0 ],
		  'PID'			  	=> [ 0 ],
		  'PREF'			=> [ 2, '1-100' ],
		  'TYPE'			=> [ 1, ' work home x- ' ],
		  '[ANY]'			=> [ 0 ],
		],
		'text'			 	=> [
		  'VALUE'			=> [ 1, 'text ' ],
		  'ALTID'			=> [ 0 ],
		  'PID'			  	=> [ 0 ],
		  'PID'			  	=> [ 0 ],
		  'PREF'			=> [ 2, '1-100' ],
		  'TYPE'			=> [ 1, ' work home x- ' ],
		  '[ANY]'			=> [ 0 ],
		]
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldKey
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldKey {

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
		$att   = Attachment::getInstance();
		$ipath .= self::TAG;

		switch ($typ) {
		case 'text/vcard':
		case 'text/x-vcard':
			foreach ($ext as $rec) {

				if ($rec['T'] != $xpath || !strlen($rec['D']))
					continue;
				// defaults to type text
				$var = 'text';
				$p = parse_url($rec['D']);
				if (isset($p['scheme']))
					$var = 'uri';
				// check parameter
				parent::check($rec, self::RFCA_PARM[$var]);
				parent::delTag($int, $ipath);
				if ($var != 'uri')
					$rec['D'] = $att->create(base64_decode($rec['D']));
				$rec['P']['VALUE'] = $var;
				unset($rec['P']['DATA']);
				unset($rec['P']['ENCODING']);
				$int->addVar(self::TAG, $var == 'text' ? parent::rfc6350($rec['D']) : $rec['D'], false, $rec['P']);
				$rc = true;
				break;
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
		$att  = Attachment::getInstance();
		$tags = explode('/', $xpath);
		$tag  = array_pop($tags);

		if (!$int->xpath($ipath.self::TAG, false))
			return $rc;

		switch ($typ) {
		case 'text/vcard':
		case 'text/x-vcard':
			$recs = [];
			// we assume $rec['D'] is attachment record id
			while (($val = $int->getItem()) !== null) {

				$a = $int->getAttr();
				if ($a['VALUE'] != 'uri') {

					// check if attachment could be loaded
					if (!($data = $att->read($val)))
						continue;
					if ($ver != 4.0) {

						$a['ENCODING'] = $ver == 2.1 ? 'base64' : 'b';
		  		 		$val = base64_encode($data);
					} else
			  			$val = 'data:'.$att->getVar('MIME').';base64,'.base64_encode($data);
				}
				$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => $a['VALUE'] == 'text' ? parent::rfc6350($val, false) : $val ];
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
