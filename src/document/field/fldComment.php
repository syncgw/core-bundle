<?php
declare(strict_types=1);

/*
 *  Comment field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\XML;

class fldComment extends fldHandler {

	// --------------------------------------------------------------------------------------------
	const TAG 				= 'Comment';

	/*
	 comment    = "COMMENT" commparam ":" text CRLF

     commparam  = *(

                ; the following are optional,
                ; but MUST NOT occur more than once

                (";" altrepparam) / (";" languageparam) /

                ; the following is optional,
                ; and MAY occur more than once

                (";" xparam)

                )
	 */
	const RFCC_PARM			= [
		// description see fldHandler:check()
	    'text'			   => [
		  'VALUE'		   => [ 1, 'text ' ],
		  'ALTREP'		   => [ 0 ],
		  'LANGUAGE'	   => [ 6 ],
		  '[ANY]'		   => [ 0 ],
		],
	];

    /**
     * 	Singleton instance of object
     * 	@var fldComment
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldComment {

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
		case 'text/calendar':
		case 'text/x-vcalendar':
			foreach ($ext as $rec) {

				if ($rec['T'] != $xpath)
					continue;
				// check parameter
				parent::check($rec, self::RFCC_PARM['text']);
				parent::delTag($int, $ipath);
				unset($rec['P']['VALUE']);
				$int->addVar(self::TAG, parent::rfc5545($rec['D']), false, $rec['P']);
				$rc = true;
			 }
			break;

		case 'application/activesync.calendar+xml':
	   		if ($ext->xpath($xpath, false))
				parent::delTag($int, $ipath, '2.5');

			while (($val = $ext->getItem()) !== null) {

				if (!$val)
					continue;
				$int->addVar(self::TAG, $val);
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

		if (!$int->xpath($ipath.self::TAG, false))
			return $rc;

		switch ($typ) {
		case 'text/calendar':
		case 'text/x-vcalendar':
			$recs = [];
			while (($val = $int->getItem()) !== null) {

				$a = $int->getAttr();
				// $a['VALUE'] = 'TEXT';
				$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => parent::rfc5545($val, false) ];
			}
			if (count($recs))
				$rc = $recs;
			break;

		case 'application/activesync.calendar+xml':
			if (!class_exists($class = 'syncgw\\activesync\\masHandler') ||
				$class::getInstance()->callParm('BinVer') < 16.0)
				break;

			while (($val = $int->getItem()) !== null) {

				$ext->addVar($tag, $val, false, $ext->setCP(XML::AS_BASE));
				$rc	= true;
			}
			break;

		default:
			break;
		}

		return $rc;
	}

}
