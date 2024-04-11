<?php
declare(strict_types=1);

/*
 *  Organizer field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Msg;
use syncgw\lib\XML;

class fldOrganizer extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'Organizer';

	/*
	 organizer  = "ORGANIZER" orgparam ":"
                  cal-address CRLF

     orgparam   = *(

                ; the following are optional,
                ; but MUST NOT occur more than once

                (";" cnparam) / (";" dirparam) / (";" sentbyparam) /
                (";" languageparam) /

                ; the following is optional,
                ; and MAY occur more than once

                (";" xparam)

                )
	 */
   	const RFCC_PARM			= [
		// description see fldHandler:check()
   	    'uri'			  	=> [
		  'VALUE'			=> [ 1, 'uri ' ],
		  'CN'			   	=> [ 0 ],
		  'DIR'			  	=> [ 0 ],
		  'SENT-BY'		  	=> [ 3 ],
		  'LANGUAGE'		=> [ 6 ],
		  'EMAIL'			=> [ 3 ],
		  '[ANY]'			=> [ 0 ],
		],
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldOrganizer
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldOrganizer {

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
				$p = parse_url($rec['D']);
				if (!isset($p['scheme'])) {

					Msg::InfoMsg('['.$rec['T'].'] ['.$rec['D'].'] not "uri" - dropping record');
					continue;
				}
				$rec['D'] = str_replace('MAILTO', 'mailto', $rec['D']);
				// check parameter
				parent::check($rec, self::RFCC_PARM['uri']);
				parent::delTag($int, $ipath);
				unset($rec['P']['VALUE']);
				$int->addVar(self::TAG, $rec['D'], false, $rec['P']);
				$rc = true;
	  		}
			break;

		case 'application/activesync.mail+xml':
            if ($ext->xpath($xpath, false))
		        parent::delTag($int, self::TAG);

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
				// $a['VALUE'] = 'URI';
				if ($typ != 'text/calendar')
					$val = str_replace('mailto', 'MAILTO', $val);
				$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => $val ];
			}
			if (count($recs))
				$rc = $recs;
			break;

		case 'application/activesync.mail+xml':
			while (($val = $int->getItem()) !== null) {

				$ext->addVar($tag, $val, false, $ext->setCP(XML::AS_MAIL));
				$rc	= true;
			}
			break;

		default:
			break;
		}

		return $rc;
	}

}
