<?php
declare(strict_types=1);

/*
 *  Title field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\XML;

class fldTitle extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'Title';

	/*
	 TITLE-param = "VALUE=text" / language-param / pid-param
                 / pref-param / altid-param / type-param / any-param
     TITLE-value = text
	 */
	const RFCA_PARM			= [
		// description see fldHandler:check()
	    'text'			 	=> [
		  'VALUE'			=> [ 1, 'text ' ],
		  'LANGUAGE'		=> [ 6 ],
		  'PID'			  	=> [ 0 ],
		  'PREF'			=> [ 2, '1-100' ],
		  'ALTID'			=> [ 0 ],
		  'TYPE'			=> [ 1, ' work home x- ' ],
		  '[ANY]'			=> [ 0 ],
		],
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldTitle
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldTitle {

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

				if ($rec['T'] != $xpath)
					continue;
				// check parameter
				parent::check($rec, self::RFCA_PARM['text']);
				parent::delTag($int, $ipath);
				unset($rec['P']['VALUE']);
				$int->addVar(self::TAG, parent::rfc6350($rec['D']), false, $rec['P']);
				$rc = true;
			}
			break;

		case 'application/activesync.gal+xml':
		case 'application/activesync.contact+xml':
	   		if ($ext->xpath($xpath, false))
				parent::delTag($int, $ipath, '2.5');

			while (($val = $ext->getItem()) !== null) {

				if ($val) {

					$int->addVar(self::TAG, $val);
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
		$cp   = null;
		$tags = explode('/', $xpath);
		$tag  = array_pop($tags);

		if (!$int->xpath($ipath.self::TAG, false))
			return $rc;

		switch ($typ) {
		case 'text/vcard':
		case 'text/x-vcard':
			$recs = [];
			while (($val = $int->getItem()) !== null) {

				// $a['VALUE'] = 'text';
				$recs[]	= [ 'T' => $tag, 'P' => $int->getAttr(), 'D' => parent::rfc6350($val, false) ];
			}
			if (count($recs))
				$rc = $recs;
			break;

		case 'application/activesync.gal+xml':
			$cp  = XML::AS_GAL;

		case 'application/activesync.contact+xml':
			if (!$cp)
				$cp = XML::AS_CONTACT;

			while (($val = $int->getItem()) !== null) {

				$ext->addVar($tag, $val, false, $ext->setCP($cp));
				$rc	= true;
			}
			break;

		default:
			break;
		}

		return $rc;
	}

}
