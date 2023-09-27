<?php
declare(strict_types=1);

/*
 *  Photo field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Attachment;
use syncgw\lib\Log;
use syncgw\lib\Util;
use syncgw\lib\XML;

class fldPhoto extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'Photo';

	/*
	 PHOTO-param = "VALUE=uri" / altid-param / type-param
                 / mediatype-param / pref-param / pid-param / any-param
     PHOTO-value = URI
    */
	const RFCA_PARM			= [
		// description see fldHandler:check()
	    'uri'			  	=> [
		  'VALUE'			=> [ 1, 'uri ' ],
		  'ALTID'			=> [ 0 ],
		  'TYPE'			=> [ 1, ' work home x- ' ],
		  'MEDIATYPE'		=> [ 7 ],
		  'PREF'			=> [ 2, '1-100' ],
		  'PID'			  	=> [ 0 ],
		  '[ANY]'			=> [ 0 ],
		],
		'text'			 	=> [
		  'VALUE'			=> [ 1, 'text ' ],
		  'ALTID'			=> [ 0 ],
		  'TYPE'			=> [ 0 ],
		  'PREF'			=> [ 2, '1-100' ],
		  'PID'			  	=> [ 0 ],
		  '[ANY]'			=> [ 0 ],
		]
	];

	/*
	 image      = "IMAGE" imageparam
                (
                  (
                    ";" "VALUE" "=" "URI"
                    ":" uri
                  ) /
                  (
                    ";" "ENCODING" "=" "BASE64"
                    ";" "VALUE" "=" "BINARY"
                    ":" binary
                  )
                )
                CRLF

     imageparam = *(
                 ;
                 ; The following is OPTIONAL for a URI value,
                 ; RECOMMENDED for a BINARY value,
                 ; and MUST NOT occur more than once.
                 ;
                 (";" fmttypeparam) /
                 ;
                 ; The following are OPTIONAL,
                 ; and MUST NOT occur more than once.
                 ;
                 (";" altrepparam) / (";" displayparam) /
                 ;
                 ; The following is OPTIONAL,
                 ; and MAY occur more than once.
                 ;
                 (";" other-param)
                 ;
                 )
	 */
	const RFCC_PARM			= [
		// description see fldHandler:check()
	    'uri'			  	=> [
		  'VALUE'			=> [ 1, 'uri ' ],
		  'FMTTYPE'		  	=> [ 8 ],
		  'ALTREP'		   	=> [ 0 ],
		  'DISPLAY'		  	=> [ 1, ' BADGE GRAPHIC FULLSIZE THUMBNAIL ' ],
		  '[ANY]'			=> [ 0 ],
		],
		'binary'		   	=> [
		  'VALUE'			=> [ 1, 'binary ' ],
		  'ENCODING'		=> [ 1, ' 8BIT BASE64 ' ],
		  'FMTTYPE'		  	=> [ 8 ],
		  'ALTREP'		   	=> [ 0 ],
		  'DISPLAY'		  	=> [ 1, ' BADGE GRAPHIC FULLSIZE THUMBNAIL ' ],
		  '[ANY]'			=> [ 0 ],
		]
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldPhoto
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldPhoto {

		if (!self::$_obj) {

            self::$_obj = new self();
			// clear tag deletion status
			unset(parent::$Deleted[self::TAG]);
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

		$xml->addVar('Opt', sprintf('&lt;%s&gt; field handler', self::TAG));
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
				$var = 'text';
				$p = parse_url($rec['D']);
				if (isset($p['scheme'])) {

					// data:image/jpeg;base64,/9j/4AAQ...
					if (strtolower($p['scheme']) == 'data') {

						list($t, $p['path']) = explode(';', $p['path']);
						if ($t)
							$rec['P']['TYPE'] = $t;
						list($t, $v) = explode(',', $p['path']);
						$t = strtolower($t);
						if ($t == 'base64') {

							$rec['D']    = $v;
							$p['scheme'] = $t;
						}
					} else
						$var = 'uri';
				}
				// check parameter
				parent::check($rec, self::RFCA_PARM[$var]);
				parent::delTag($int, $ipath);
				if ((isset($p['scheme']) && $p['scheme'] == 'base64') || $var == 'text') {

					if (!isset($rec['P']['TYPE']))
						$rec['P']['TYPE'] = '';
					$rec['D'] = base64_decode($rec['D']);
					$rec['D'] = $att->create($rec['D'], $rec['P']['TYPE'] == 'JPEG' ? 'image/jpeg' : $rec['P']['TYPE']);
					unset($rec['P']['TYPE']);
				}
				if ($var != 'text')
				    $rec['P']['VALUE'] = $var;
				unset($rec['P']['DATA']);
				$int->addVar(self::TAG, $rec['D'], false, $rec['P']);
				$rc = true;
			}
			break;

		case 'text/calendar':
		case 'text/x-vcalendar':
			foreach ($ext as $rec) {

				if ($rec['T'] != $xpath || !strlen($rec['D']))
					continue;
				$var = 'binary';
				$p = parse_url($rec['D']);
				if (isset($p['scheme']))
					$var = 'uri';
				// check parameter
				parent::check($rec, self::RFCC_PARM[$var]);
				parent::delTag($int, $ipath);
				if (isset($p['scheme']) && $p['scheme'] != 'base64')
					$val = $rec['D'];
				else
					$val = $att->create(base64_decode($rec['D']));
				$rec['P']['VALUE'] = $var;
				unset($rec['P']['DATA']);
				unset($rec['P']['ENCODING']);
				$int->addVar(self::TAG, $val, false, $rec['P']);
				$rc = true;
	  		}
			break;

		case 'application/activesync.gal+xml':
		case 'application/activesync.contact+xml':
	   		if ($ext->xpath($xpath, false))
				parent::delTag($int, $ipath, '16.0');

			while (($val = $ext->getItem()) !== null && strlen($val)) {

				$int->addVar(self::TAG, $att->create(base64_decode($val), 'image/jpeg'));
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
		$att  = Attachment::getInstance();
		$tags = explode('/', $xpath);
		$tag  = array_pop($tags);
		$log  = Log::getInstance();

		if (!$int->xpath($ipath.self::TAG, false))
			return $rc;

		switch ($typ) {
		case 'text/vcard':
		case 'text/x-vcard':
			$recs = [];
			// we assume $rec['D'] is attachment record id
			while (($val = $int->getItem()) !== null) {

				$a = $int->getAttr();
				if (isset($a['VALUE']) && $a['VALUE'] == 'uri') {

					if ($ver == 2.1)
						$a['VALUE'] = 'URL';
					$rec = [ 'T' => $tag, 'P' => $a, 'D' => $val ];
				} else {

					// check if attachment could be loaded
					if (!($data = $att->read($val)))
						continue;
					$rec = [ 'T' => $tag, 'P' => $a, 'D' => base64_encode($data) ];
					if ($ver != 4.0) {

	   				  	if (strpos($att->getVar('MIME'), 'jpeg') === null) {

	   				  		if (!($t = Util::cnvImg(base64_decode($rec['D']), 'JPEG'))) {

					    		$log->setLogMsg([ 13111 => 'Error converting picture [%s] to \'JPEG\'' ]);
								$log->logMsg(Log::WARN, 13111, $val);
								continue;
	   				  		}
							if ($t['newdata'])
								$rec['D'] = $t['newdata'];
   			   			}
						$rec['P']['TYPE']	 = 'JPEG';
						$rec['P']['ENCODING'] = $ver == 2.1 ? 'base64' : 'b';
					} else
			 			$rec['D'] = 'data:'.$att->getVar('MIME').';base64,'.$rec['D'];
				}
				$recs[] = $rec;
			}
			if (count($recs))
				$rc = $recs;
			break;

		case 'text/calendar':
		case 'text/x-vcalendar':
			$recs = [];
			while (($val = $int->getItem()) !== null) {

				$a = $int->getAttr();
				if ($a['VALUE'] == 'uri')
					$rec = [ 'T' => $tag, 'P' => $a, 'D' => $val ];
				else {

					// check if attachment could be loaded
					if (!($data = $att->read($val)))
						continue;
					$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => base64_encode($data) ];
					if ($ver != 4.0) {

						$rec['P']['VALUE']	  = 'BINARY';
	   				  	$rec['P']['FMTTTYPE'] = $att->getVar('MIME');
						$rec['P']['ENCODING'] = 'BASE64';
					} else
			 			$rec['D'] = 'data:'.$att->getVar('MIME').';base64,'.$rec['D'];
				}
				$recs[] = $rec;
			}
			if (count($recs))
				$rc = $recs;
			break;

		// Warning: This is not the final output format! for more information see MAS-Search.php, masFind.php and masResolveRecipients.php
		case 'application/activesync.gal+xml':
			if (!class_exists($class = 'syncgw\\activesync\\masHandler') ||
				$class::getInstance()->callParm('BinVer') < 14.1)
				break;

			while (($val = $int->getItem()) !== null) {

				$p = $ext->savePos();
				$ext->addVar($tag, null, false, $ext->setCP(XML::AS_GAL));

				// we assume $val is attachment record id
	   			if (!($val = $att->read($val)))
	   				continue;
   				if ($att->getVar('MIME') != 'image/jpeg') {

   					if (!($t = Util::cnvImg($val, 'JPEG'))) {

						$log->setLogMsg([ 13112 => 'Error converting picture [%s] to \'JPEG\'' ]);
						$log->logMsg(Log::WARN, 13112, $val);
						continue;
			  		}
					$val = $t['newdata'];
				}
				$ext->addVar('Status', '1');
				$ext->addVar('Data', base64_encode($val));
				$rc	= true;
				$ext->restorePos($p);
			}
			break;

		case 'application/activesync.contact+xml':
			while (($val = $int->getItem()) !== null) {

				// we assume $val is attachment record id
	   			if (!($val = $att->read($val)))
	   				continue;
   				if ($att->getVar('MIME') != 'image/jpeg') {

   					if (!($t = Util::cnvImg($val, 'JPEG'))) {

						$log->setLogMsg([ 13112 => 'Error converting picture [%s] to \'JPEG\'' ]);
						$log->logMsg(Log::WARN, 13112, $val);
						continue;
			  		}
					$val = $t['newdata'];
				}
				$ext->addVar($tag, base64_encode($val), false, $ext->setCP(XML::AS_CONTACT));
				$rc	= true;
			}
			break;

		default:
			break;
		}

		return $rc;
	}

}
