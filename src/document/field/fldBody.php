<?php
declare(strict_types=1);

/*
 *  Body field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Msg;
use syncgw\lib\DB;
use syncgw\lib\DataStore;
use syncgw\lib\Session;
use syncgw\lib\XML;

class fldBody extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	// <Body X-Typ="2">This is a text</Body>
	const TAG 				= 'Body';

	// Parameter X-TYP= File type extension
	const TYP_TXT           = '1';				// Plain text (default)
	const TYP_HTML          = '2';				// HTML
	const TYP_RTF           = '3';				// RTF (Rich Text Format)

	const TYP_MIME          = '4';				// MIME					- not a real <Body> type!
	const TYP_MD            = '5';				// MD (Markdown)        - internal only
    const TYP_OR6           = '6';				// OR6 for Nokia        - internal only

	// all supported ActiveSync types
	const TYP_AS 			= [
		self::TYP_TXT, self::TYP_HTML, self::TYP_RTF, self::TYP_MIME,
	];

    /*
     NOTE-param = "VALUE=text" / language-param / pid-param / pref-param
                / type-param / altid-param / any-param
     NOTE-value = text
    */
	const RFCA_PARM			= [
		// description see fldHandler:check()
	    'text'			    => [
		  'VALUE'			=> [ 1, 'text ' ],
		  'LANGUAGE'		=> [ 6 ],
		  'PID'			  	=> [ 0 ],
		  'PREF'			=> [ 2, '1-100' ],
		  'TYPE'			=> [ 1, ' work home x- ' ],
		  'ALTID'			=> [ 0 ],
		  '[ANY]'			=> [ 4 ],
		],
	];

	/*
	   description = "DESCRIPTION" descparam ":" text CRLF

       descparam   = *(
                   ;
                   ; The following are OPTIONAL,
                   ; but MUST NOT occur more than once.
                   ;
                   (";" altrepparam) / (";" languageparam) /
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
	    'text'			    => [
		  'ALTREP'		   	=> [ 5 ],
		  'LANGUAGE'		=> [ 6 ],
		  '[ANY]'			=> [ 0 ],
	    ],
	];

	const ASC_LENGTH 		= [
		'0' 				=> 0,			// 0 - Truncate all body text.
		'1'					=> 512,			// 1 - Truncate body text that is more than 512 characters.
		'2' 				=> 1024, 		// 2 - Truncate body text that is more than 1,024 characters.
		'3' 				=> 2048, 		// 3 - Truncate body text that is more than 2,048 characters.
		'4' 				=> 5120, 		// 4 - Truncate body text that is more than 5,120 characters.
		'5' 				=> 10240, 		// 5 - Truncate body text that is more than 10,240 characters.
		'6' 				=> 20480, 		// 6 - Truncate body text that is more than 20,480 characters.
		'7'					=> 51200, 		// 7 - Truncate body text that is more than 51,200 characters.
		'8'					=> 102400,	 	// 8 - Truncate body text that is more than 102,400 characters.
		'9' 				=> -1,			// 9 - Do not truncate body text.
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldBody
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldBody {

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
		$ipath .= self::TAG;
		$mver  = '';
		$ip = $int->savePos();

		switch ($typ) {
		case 'text/plain':
			foreach ($ext as $rec) {

				if ($rec['T'] != $xpath)
					continue;
				parent::delTag($int, self::TAG, '', true);
				$a = [];
				foreach ($rec['P'] as $key => $val)
					$a[$key] = strtolower($val);
				// set default text type
				$a['X-TYP'] = self::TYP_TXT;
				$int->addVar(self::TAG, $rec['D'], false, $a);
				$rc = true;
			}
			break;

		case 'text/x-vnote':
		case 'text/vcard':
		case 'text/x-vcard':
			foreach ($ext as $rec) {

				if ($rec['T'] != $xpath)
					continue;
				parent::check($rec, self::RFCA_PARM['text']);
				parent::delTag($int, $ipath);
				// set default text type
                unset($rec['P']['VALUE']);
				$rec['P']['X-TYP'] = self::TYP_TXT;
				$int->addVar(self::TAG, parent::rfc6350($rec['D']), false, $rec['P']);
				$rc = true;
			 }
			break;

		case 'text/calendar':
		case 'text/x-vcalendar':
			foreach ($ext as $rec) {

				if ($rec['T'] != $xpath)
					continue;
				parent::check($rec, self::RFCC_PARM['text']);
				parent::delTag($int, $ipath);
                unset($rec['P']['VALUE']);
				// set default text type
				$rec['P']['X-TYP'] = self::TYP_TXT;
				$int->addVar(self::TAG, parent::rfc5545($rec['D']), false, $rec['P']);
				$rc = true;
			 }
			break;

		case 'application/activesync.contact+xml':
			$mver = '16.0';

		case 'application/activesync.calendar+xml':
			if ($mver == '')
				$mver = '12.0';

		case 'application/activesync.note+xml':
		case 'application/activesync.task+xml':
		case 'application/activesync.mail+xml':
		    $typ = [];
			if ($ext->xpath($xpath, false)) {

		        // get all existing types to restore "X-TYP"
		        $p = $int->savePos();
		        $int->xpath('//'.self::TAG);
		        while ($int->getItem() !== null)
		            $typ[] = $int->getAttr('X-TYP');
		        $int->restorePos($p);
				parent::delTag($int, $ipath, $mver);
		    }

		    $n = 0;
			while ($ext->getItem() !== null) {

				$p = $ext->savePos();

				if (($val = $ext->getVar('Data', false)) !== null)
    				$val = $val ? $val : str_replace("\r", '', $val);
				$ext->restorePos($p);

				if (!($var = $ext->getVar('Type', false)))
					$var = self::TYP_TXT;
				$a = [ 'X-TYP' => (isset($typ[$n]) && $typ[$n] == self::TYP_MD) ? self::TYP_MD : $var];
				$n++;
				$ext->restorePos($p);

				if ($val && strlen(trim($val))) {

					$int->addVar(self::TAG, $val, false, $a);
					$rc = true;
				}
			}
			break;

		default:
			break;
		}

		$int->restorePos($ip);

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
		$hid  = 0;
		$tags = explode('/', $xpath);
		$tag  = array_pop($tags);

		if (!$int->xpath($ipath.self::TAG, false))
			return $rc;

		// special check for <BodyPart>
		if (strpos($typ, 'activesync') !== false) {

			if (!class_exists($class = 'syncgw\\activesync\\masHandler'))
				return $rc;

			$mas = $class::getInstance();
			$ver = $mas->callParm('BinVer');
			$xp  = $ext->savePos();

			// [MS-ASCON] 3.1.4.10 Requesting a Message Part
			// When a client synchronizes, searches, or fetches an e-mail message, the client can choose to receive a message part
			// by including the <BodyPartPreference> element in the Sync command request the <Search> command request or the
			// <ItemOperations> command request.

			if ($ext->getVar('BodyPartPreference') !== null)
				$tag = 'BodyPart';

			// default allowed body types
			$ok_typ = [ self::TYP_TXT, self::TYP_HTML, self::TYP_RTF ];

			$ext->restorePos($xp);
		}

		switch ($typ) {
		case 'text/plain':
			$rec = [];
			while (($val = $int->getItem()) !== null) {

				$a = [];
				foreach ($int->getAttr() as $key => $v) {

				    if ($key != 'X-TYP')
    					$a[$key] =  $key == 'TYPE' ? strtoupper($v) : $v;
				}
				$rec[] = [ 'T' => $tag, 'P' => $a, 'D' => $val ];
			}
			if (count($rec))
				$rc = $rec;
			break;

		case 'text/x-vnote':
		case 'text/vcard':
		case 'text/x-vcard':
			$rec = [];
			while (($val = $int->getItem()) !== null) {

				$a = $int->getAttr();
				if ($typ == 'text/x-vnote' && isset($a['TYPE']))
					$a['TYPE'] = strtoupper($a['TYPE']);
				// we drop "X-TYP"
				unset($a['X-TYP']);
				$rec[] = [ 'T' => $tag, 'P' => $a, 'D' => parent::rfc6350($val, false) ];
			}
			if (count($rec))
				$rc = $rec;
			break;

		case 'text/calendar':
		case 'text/x-vcalendar':
			$rec = [];
			while (($val = $int->getItem()) !== null) {

				$a = $int->getAttr();
				if ($typ == 'text/x-vnote' && isset($a['TYPE']))
					$a['TYPE'] = strtoupper($a['TYPE']);
				// we drop "X-TYP"
				unset($a['X-TYP']);
				$rec[] = [ 'T' => $tag, 'P' => $a, 'D' => parent::rfc5545($val, false) ];
			}
			if (count($rec))
				$rc = $rec;
			break;

		case 'application/activesync.note+xml':
			if ($ver < 14.0)
				break;
			$hid = DataStore::NOTE;

		case 'application/activesync.contact+xml':
			if (!$hid) {

				$hid = DataStore::CONTACT;
				$cp  = XML::AS_CONTACT;
			}

		case 'application/activesync.calendar+xml':
			if (!$hid) {

				$hid = DataStore::CALENDAR;
				$cp  = XML::AS_CALENDAR;
			}

		case 'application/activesync.task+xml':
			if (!$hid) {

				$hid = DataStore::TASK;
				$cp  = XML::AS_TASK;
			}

		case 'application/activesync.mail+xml':

			// set handler
			if (!$hid) {

				$hid = DataStore::MAIL;
				$cp  = XML::AS_MAIL;
				// extend allowed type with MIME
				$ok_typ = self::TYP_AS;
			}
			// [MS-ASAIRS] 2.2.2.41.3 Type (BodyPartPreference)
			// Only a value of 2 (HTML) SHOULD be used in the Type element of a <BodyPartPreference> element.

			// get last options loaded
			$opts = $mas->getOption('-1');

			// find type to use
			foreach (array_reverse($ok_typ) as $typ) {

				if (isset($opts[$tag.'Preference'.$typ])) {

					$p = $int->savePos();
					if ($int->xpath($ipath.self::TAG.'[@X-TYP="'.$typ.'"]/.', false))
						break;
					$int->restorePos($p);
				}
			}

			// any options found?
			if (!$opts)
				break;

			// [MS-ASCMD] 2.2.3.110.1 MIMESupport (ItemOperations)
			// The <BodyPreference> element with its child element, <Type >having a value of 4 to inform the server
			// that the device can read the MIME binary large object (BLOB).

			// [MS-ASAIRS] 2.2.2.3.2 AllOrNone (BodyPreference)
			// [MS-ASAIRS] 2.2.2.3.1 AllOrNone (BodyPartPreference)
			// The first <BodyPreference> element requests an HTML body, but only if the body size is less than 50 bytes.
			// The second requests an element in plain text format. If the client requests a text body whose native
			// format is HTML, and the size of the data exceeds 50 bytes, the server converts the body to plain text
			// and returns the first 50 bytes of plain text data.

			// find <Body> with appropriate typ
			if ($typ == self::TYP_MIME) {

				$db = DB::getInstance();
				// @opt <MimeSupport> - ignored
				$val = $db->cnv2MIME($int);
				if ($opts['MIMETruncation'] > -1) {

					Msg::InfoMsg('MIME data truncated to '.$opts['MIMETruncation'].
												' bytes (original '.strlen(val).' bytes)');
					$val = substr($val, 0, $opts['MIMETruncation']);
				}
			} else
				$val = $int->getItem();

			// any data found?
			if (!$val)
				break;

			// shorten data? (v2.5 only)
			if ($ver == 2.5 && $hid & (DataStore::CONTACT|DataStore::TASK|DataStore::CALENDAR|DataStore::MAIL)) {

				if (isset($opts['Truncation']) && self::ASC_LENGTH[$opts['Truncation']]) {

					if (!($hid & DataStore::CALENDAR))
						$ext->AddVar('BodySize', strlen($val));
					if (!($hid & DataStore::CALENDAR) && $tags[0] != fldExceptions::TAG)
						$ext->AddVar('BodyTruncated', '1');
					$val = substr($val, 0, self::ASC_LENGTH[$opts['Truncation']]);
				}
				$ext->addVar($tag, $val, false, $ext->setCP($cp));
				break;
			}

			// Either <Body> or <BodyPart>
			$ext->addVar($tag, null, false, $ext->setCP(XML::AS_BASE));

			// should we send a status back?
			if ($ver >= 14.1 && $tag == 'BodyPart') {

				if (isset($opts['TruncationSize']) && strlen($val) > $opts['TruncationSize'] && $opts['AllOrNone']) {

					$class = 'syncgw\\activesync\\masStatus';
					$ext->addVar('Status', $class::CONVSIZE);
					break;
				} else
					$ext->addVar('Status', $class::OK);
			}

			$ext->addVar('Type', $typ);
			$ext->addVar('EstimatedDataSize', strval($len = strlen($val)));

			// any size limitations?
			if (isset($opts['TruncationSize']) && $len > $opts['TruncationSize']) {

				// restrict content from being returned in the response
				if ($opts['AllOrNone'])
					break;

				$p   = $int->savePos();
				$gid = $int->getVar('GUID');
				$int->restorePos($p);

				$sess = Session::getInstance();
				if (!$sess->xpath('//Data/BodyPart[text()="'.$gid.'"]')) {

					$sess->getVar('Data');
    	       		$sess->addVar('BodyPart', $gid, false, [ 'No' => $no = '1' ]);
				} else {

					$sess->getItem();
    	       		$no = $sess->getAttr('No') + 1;
					$sess->setAttr([ 'No' => strval($no) ]);
				}

				$ext->addVar('Truncated', 1);
				$ext->addVar('Part', strval($no), false, $ext->setCP(XML::AS_ITEM));
				$val = substr($val, 0, intval($opts['TruncationSize']));
			} else
				// [MS-ASEMAIL] 2.2.2.10.1 Body (AirSyncBase Namespace)
				// This element is optional in <Sync> command responses and <Search> command responses.
				// This element is optional in <ItemOperations> command responses and is only included if a nonzero
				// <TruncationSize> element value was included in the request and the <AllOrNone> element value
				// included in the request does not restrict content from being returned in the response.
				$ext->addVar('Data', $val, false, $ext->setCP(XML::AS_BASE));

			if ($ver >= 14.0 && isset($opts['Preview']) && $opts['Preview'])
				$ext->addVar('Preview', substr(strip_tags($val), intval($opts['Preview'])));

			$rc = true;
			$ext->restorePos($xp);
			break;

		default:
			break;
		}

		return $rc;
	}

}
