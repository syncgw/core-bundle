<?php
declare(strict_types=1);

/*
 *  MessageClass field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Msg;
use syncgw\lib\XML;

class fldMessageClass extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'MessageClass';

	const ASN_MAP 			= [
			'IPM.StickyNote',
	];

	const ASM_MAP 			= [
			'IPM.Note',							// Normal e-mail message.
			'IPM.Note.SMIME',					// The message is encrypted and can also be signed.
			'IPM.Note.SMIME.MultipartSigned',	// The message is clear signed.
			'IPM.Note.Receipt.SMIME',			// The message is a secure read receipt.
			'IPM.InfoPathForm',					// An InfoPath form, as specified by [MS-IPFFX].
			'IPM.Schedule.Meeting',				// Meeting request.
			'IPM.Notification.Meeting',			// Meeting notification.
			'IPM.Post',							// Post.
			'IPM.Octel.Voice',					// Octel voice message.
			'IPM.Voicenotes',					// Electronic voice notes.
			'IPM.Sharing',						// Shared message
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldMessageClass
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldMessageClass {

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
		$map   = null;
		$ipath .= self::TAG;

		switch ($typ) {
		case 'application/activesync.note+xml':
			$map = self::ASN_MAP;

		case 'application/activesync.mail+xml':
			if (!$map)
				$map = self::ASM_MAP;

			if ($ext->xpath($xpath, false))
				parent::delTag($int, $ipath);

			while (($val = $ext->getItem()) !== null) {

				foreach ($map as $w) {

					if (strstr($val, $w) !== false) {

						$int->addVar(self::TAG, $val);
						$rc = true;
						break;
					}
				}
				if (!$rc)
					Msg::InfoMsg('['.$xpath.'] - Invalid value "'.$val.'"');
			}
			break;

		default:
			$rc = true;
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
		case 'application/activesync.note+xml':
			if (!class_exists($class = 'syncgw\\activesync\\masHandler') ||
				$class::getInstance()->callParm('BinVer') < 14.0)
				break;
			$cp = XML::AS_NOTE;

		case 'application/activesync.mail+xml':
			if (!$cp)
				$cp = XML::AS_MAIL;

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
