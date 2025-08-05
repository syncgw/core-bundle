<?php
declare(strict_types=1);

/*
 *  Attachment field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Attachment;
use syncgw\lib\XML;

class fldAttach extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'Attachment';
    // specifies the attachment name
	const SUB_TAG 			= [ 'DisplayName', 'FileReference', 'ContentLocation', 'ContentId', ];

	/*
	 attach     = "ATTACH" attparam ":" uri  CRLF

     attach     =/ "ATTACH" attparam ";" "ENCODING" "=" "BASE64"
                   ";" "VALUE" "=" "BINARY" ":" binary

     attparam   = *(

                ; the following is optional,
                ; but MUST NOT occur more than once

                (";" fmttypeparam) /
                ; the following is optional,
                ; and MAY occur more than once

                (";" xparam)

                )
	 */
	const RFCC_PARM			= [
		// description see fldHandler:check()
	    'uri'			  	=> [
		  'FMTTYPE'		  	=> [ 8 ],
		  'VALUE'			=> [ 1, 'URI ' ],
		  '[ANY]'			=> [ 0 ],
		],
		'binary'		   	=> [
		  'ENCODING'		=> [ 1, 'BASE64 '],
		  'FMTTYPE'		  	=> [ 8 ],
		  'VALUE'			=> [ 1, 'BINARY ' ],
		  '[ANY]'			=> [ 0 ],
		]
	];

	// application/activesync.calendar+xm
	// application/activesync.mail+xml
    const AS_SUB			= [
    	// ONLY in <Sync> command response with a <Responses>
    	'ClientId',
    	// is used to reference the attachment within the item to which the attachment belongs
    	'ContentId',
    	// contains the relative URI for an attachment, and is used to associate the attachment in other items
    	// with URI defining its location
    	'ContentLocation',
		// specifies the display name of the attachment
    	'DisplayName',
  		'EstimatedDataSize',
    	// specifies the location of an item on the server to retrieve
		'FileReference',
    	// specifies whether the attachment is embedded in the message
    	'IsInline',
   		// identifies the method in which the attachment was attached
    	// Value 	Meaning 			Notes
		// 1 		Normal attachment 	The attachment is a normal attachment. This is the most common value.
		// 2 		Reserved 			Do not use.
		// 3 		Reserved 			Do not use.
		// 4 		Reserved 			Do not use.
		// 5 		Embedded message	Indicates that the attachment is an e-mail message, and that the attachment
		// 								file has an .eml extension.
		// 6 		Attach OLE 			Indicates that the attachment is an embedded Object Linking and Embedding
		//								(OLE) object, such as an inline image.
    	'Method',
		// specifies a client generated temporary identifier that links to the file that is being added as an attachment
     	// specifies the duration of the most recent electronic voice mail attachment in seconds
    	'UmAttDuration',
    	// identifies the order of electronic voice mail attachments
    	'UmAttOrder',

    	// <Attachment><Add>
    	// specifies the type of data contained in the Content element for an attachment that is being added to a calendar item or to a draft email item
		'ContentType',
    	// the content of the attachment that is being added to a calendar item or to a draft email item
		'Content',
    ];

	// application/activesync.mail+xml
    const ASM_SUB 		 	= [
    	// specifies the location of the attachment file to be retrieved from the server
    	'AttName'			=> 'FileReference',
    	// specifies the estimated size, in bytes, of the attachment file.
    	'AttSize'			=> 'EstimatedDataSize',
    	// specifies the method in which the attachment was attached.
    	// 1	Normal attachment	The attachment is a normal attachment. This is the most common value.
		// 2	Reserved			Do not use.
		// 3	Reserved			Do not use.
		// 4	Reserved			Do not use.
		// 5	Embedded message	Indicates that the attachment is an e-mail message, and that the attachment file
		//							has an .eml extension.
		// 6	Attach OLE			Indicates that the attachment is an embedded Object Linking and Embedding (OLE)
		// 							object, such as an inline image.
    	'AttMethod'			=> 'Method',
    	// specifies the unique identifier of the attachment
    	'AttOid'			=> 'ClientId',
    	// specifies the name of the attachment file as displayed to the user
    	'DisplayName'		=> 'DisplayName',
    ];

   	/**
     * 	Singleton instance of object
     * 	@var fldAttach
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldAttach {

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
		case 'text/calendar':
		case 'text/x-vcalendar':
			 foreach ($ext as $rec) {

				if ($rec['T'] != $xpath || !strlen($rec['D']))
					continue;

				$var = 'binary';
				$p   = parse_url($rec['D']);
				if (isset($p['scheme']))
					$var = 'uri';

				// check parameter
				parent::check($rec, self::RFCC_PARM[$var]);
				parent::delTag($int, $ipath);

				// <ContentLocation> always specifies a URI
				// $rec['P']['VALUE'] = $var;
				unset($rec['P']['ENCODING']);
				$ip = $int->savePos();

				if ($var == 'uri') {

					$rec['P']['X-TYP'] = $rec['P']['FMTTYPE'];
					unset($rec['P']['FMTTYPE']);
					unset($rec['P']['VALUE']);
					$int->addVar(self::TAG, null, false, $rec['P']);
					$int->addVar(self::SUB_TAG[2], $rec['D']);
				} else {

					// file name available?
					if (isset($rec['P']['X-NAME'])) {

						$int->addVar(self::SUB_TAG[0], $rec['P']['X-NAME']);
						unset($rec['P']['X-NAME']);
					}
					// we assume $rec['P']['VALUE'] == 'BINARY' && $rec['P']['ENCODING'] == 'BASE64'
					unset($rec['P']['VALUE']);
					$int->addVar(self::TAG, null, false, $rec['P']);
					$int->addVar(self::SUB_TAG[1], $att->create(base64_decode($rec['D']),
								 isset($rec['P']['FMTTYPE']) ? $rec['P']['FMTTYPE'] : ''));
				}
				$rc = true;
				$int->restorePos($ip);
			 }
			 break;

		case 'application/activesync.mail+xml':
		case 'application/activesync.calendar+xml':

			// adds an attachment to a calendar item or to a draft email item
			if ($ext->xpath($xpath.'/Add/.', false))
				parent::delTag($int, $ipath, $typ == 'application/activesync.calendar+xml' ? '16.0' : '');

			while ($ext->getItem() !== null) {

				$xp = $ext->savePos();
				$ip = $int->savePos();
				$int->addVar(self::TAG);
				foreach (self::AS_SUB as $key) {

					if ($val = $ext->getVar($key, false)) {

						if ($key == 'DisplayName')
							$int->addVar(self::SUB_TAG[0], $val);
						elseif ($key == 'Content')
							$int->addVar(self::SUB_TAG[1], $att->create(base64_decode($val)));
						elseif ($key != 'IsInline')
							$int->addVar($key, $val);
					}
					$ext->restorePos($xp);
				}
				$int->restorePos($ip);
			}
		   	$rc = true;
			// <Attachment><Del> - delete attachment - we skip this, because attachment may have multiple references!
			// $ext->xpath($xpath.'/Del/.', false);
			// while ($ext->getItem() !== null) {
			// 	    parent::delTag($int, $ipath, $typ == 'application/activesync.calendar+xml' ? '16.0' : '');
			// 	    $db = DB::getInstance();
			// 	    $xp = $ext->savePos();
			// 	    // specifies the server-assigned unique identifier of the attachment to be deleted
			// 	    $val = $ext->getVar(self::SUB_TAG, false);
			// 	    $db->Query(DataStore::ATTACHMENT, DataStore::DEL, $val);
			// 	    $ext->restorePos($xp);
			// }
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
		if (!class_exists($class = 'syncgw\\activesync\\masHandler'))
			return $rc;
		$mas = $class::getInstance();

		if (!$int->xpath($ipath.self::TAG, false))
			return $rc;

		switch ($typ) {
		case 'text/calendar':
		case 'text/x-vcalendar':
			$recs = [];
			while ($int->getItem() !== null) {

				$p = $int->savePos();
				$attr = $int->getAttr();
				if ($val = $int->getVar(self::SUB_TAG[0], false))
					$attr['X-NAME'] = $val;
				$int->restorePos($p);
				if ($val = $int->getVar(self::SUB_TAG[2], false)) {

					if (isset($attr['X-TYP'])) {

						$attr['FMTTYPE'] = $attr['X-TYP'];
						unset ($attr['X-TYP']);
					}
					$attr['VALUE'] = 'URI';
					$rec = [ 'T' => $tag, 'P' => $attr, 'D' => $val ];
				} else {

					$int->restorePos($p);
					if ($val = $int->getVar(self::SUB_TAG[1], false)) {

						// check if attachment could be loaded
						if (!($data = $att->read($val)))
							continue;
						$attr['ENCODING'] = 'BASE64';
				   		$attr['VALUE'] 	  = 'BINARY';
				   		$attr['FMTTYPE']  = $att->getVar('MIME');
			   			$rec = [ 'T' => $tag, 'P' => $attr, 'D' => base64_encode($data) ];
					}
				}
				$recs[] = $rec;
			}
			if (count($recs))
				$rc = $recs;
			break;

		case 'application/activesync.mail+xml':
			if ($mas->callParm('BinVer') == 2.5) {

				// <Attachments> contains one or more attachment items
				$ext->addVar($tag, null, false, $ext->setCP(XML::AS_MAIL));

   		     	while ($int->getItem() !== null) {

	        		$ip = $int->savePos();
    	    		$zp = $ext->savePos();
					foreach (self::ASM_SUB as $tag => $key ) {

        				if ($val = $int->getVar($key, false))
 	      					$ext->addVar($key, $val);
        			}
    	    		$int->restorePos($ip);
        			$ext->restorePos($zp);
        			$rc = true;
       	     	}
				break;
			}

		case 'application/activesync.calendar+xml':
			if (isset($tags[0]) && $tags[0] == fldExceptions::TAG) {

				if ($mas->callParm('BinVer') < 16.0)
					break;
			}

			$xp = $ext->savePos();

			// <Attachments> contains one or more attachment items
			$ext->addVar($tag, null, false, $ext->setCP(XML::AS_BASE));
        	while ($int->getItem() !== null) {

        		$ip = $int->savePos();
        		$zp = $ext->savePos();
        		// <Attachment> specifies the attachment information for a single attachment item
				$ext->addVar(self::TAG);
				$op = $int->savePos();
				foreach (self::AS_SUB as $key) {

        			if ($val = $int->getVar($key, false)) {
        				if ($key == 'ClientId') {

        					$p = $ext->savePos();
        					// <ClientId> only for <Sync> responses!
        					if ($ext->getVar('Sync'))
    	    					$ext->addVar($key, $val);
	    					$ext->restorePos($p);
        				} else
        					$ext->addVar($key, $val);
        			}
        			$int->restorePos($op);
        		}
        		$int->restorePos($ip);
        		$ext->restorePos($zp);
        		$rc = true;
       		}
       		$ext->restorePos($xp);
       		break;

		default:
			break;
		}

		return $rc;
	}

}
