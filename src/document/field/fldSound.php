<?php
declare(strict_types=1);

/*
 *  BinSound field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Msg;
use syncgw\lib\XML;

class fldSound extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'Sound';

	/*
     SOUND-param = "VALUE=uri" / language-param / pid-param / pref-param
                 / type-param / mediatype-param / altid-param
                 / any-param
     SOUND-value = URI
	 */
	const RFCA_PARM			= [
		// description see fldHandler:check()
	    'uri' => [
		  'VALUE'			=> [ 1, 'uri ' ],
		  'LANGUAGE'		=> [ 0 ],
		  'PID'			  	=> [ 0 ],
		  'PREF'			=> [ 2, '1-100' ],
		  'TYPE'			=> [ 1, ' work home x- ' ],
		  'MEDIATYPE'		=> [ 7 ],
		  'ALTID'			=> [ 0 ],
		  '[ANY]'			=> [ 0 ],
		],
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldSound
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldSound {

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

				if ($rec['T'] != $xpath || !strlen($rec['D']))
					continue;
				$var = 'text';
				$p   = parse_url($rec['D']);
				if (isset($p['scheme']))
					$var = 'uri';
				if ($var != 'uri') {

					Msg::InfoMsg('['.$rec['T'].'] wrong data type "text" for "'.
												$rec['D'].'" - should be "uri" - dropping record');
					continue;
				}
				// check parameter
				parent::check($rec, self::RFCA_PARM[$var]);
				parent::delTag($int, $ipath);
				$rec['P']['VALUE'] = $var;
				$int->addVar(self::TAG, $rec['D'], false, $rec['P']);
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
		$tags = explode('/', $xpath);
		$tag  = array_pop($tags);

		if (!$int->xpath($ipath.self::TAG, false))
			return $rc;

		switch ($typ) {
		case 'text/vcard':
		case 'text/x-vcard':
			$recs = [];
			while (($val = $int->getItem()) !== null)
				$recs[] = [ 'T' => $tag, 'P' => $int->getAttr(), 'D' => $val ];
			if (count($recs))
				$rc = $recs;
			break;

		default:
			break;
		}

		return $rc;
	}

}
