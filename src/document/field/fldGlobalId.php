<?php
declare(strict_types=1);

/*
 *  GlobalObjId field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\XML;

class fldGlobalId extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'GlobalId';

   	/**
     * 	Singleton instance of object
     * 	@var fldGlobalId
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldGlobalId {

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
		case 'application/activesync.mail+xml':
	   		if (!$ext->xpath($xpath, false))
	   			break;

			while (($val = $ext->getItem()) !== null) {

				$val = base64_decode($val);
				if (substr($val, 40, 8) != 'vCal-Uid') {

					for($i=16; $i < 19; $i++)
						$val[$i] = 0x00;
					$int->addVar(self::TAG, bin2hex($val));
				} else {

					$n = unpack('V', $val, 36);
					$n = array_pop($n) - 13;
					$int->addVar(self::TAG, substr($val, 52, $n));
				}
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
		$cp   = null;

		$int->xpath($ipath.self::TAG, false);

		switch ($typ) {
		case 'application/activesync.mail+xml':
			$cp  = XML::AS_MAIL;

			if (!class_exists($class = 'syncgw\\activesync\\masHandler') ||
				$class::getInstance()->callParm('BinVer') < 14.1)
				break;

			// GLOBALOBJID = CLASSID INSTDATE NOW RESERVED BYTECOUNT DATA
			// CLASSID = %x04 %x00 %x00 %x00 %x82 %x00 %xE0 %x00 %x74 %xC5 %xB7 %x10 %x1A %x82 %xE0 %x08
			$pref = "\x04\x00\x00\x00\x82\x00\xE0\x00\x74\xC5\xB7\x10\x1A\x82\xE0\x08";
			// INSTDATE = (%x00 %x00 %x00 %x00) | (YEARHIGH YEARLOW MONTH DATE)
			// ; The high order byte of the year. For example, the year 2004 would be 0x07.
			// YEARHIGH = BYTE
			// ; The low order byte of year. For example, the year 2004 would be 0xD4.
			// YEARLOW = BYTE
			// ; The month of the specific instance.
			// MONTH = %x01-12
			// ; The date of the specific instance.
			// DATE = %x01-31
			$pref .= "\x00\x00\x00\x00";
			// ; The current date expressed as number 100 nanosecond intervals since 1/1/1601 in littleendian byte order.
			// NOW = 4BYTE 4BYTE
			$pref .= "\x00\x00\x00\x00";
			// ; Reserved bytes.
			// RESERVED = 8BYTE
			$pref .= "\x00\x00\x00\x00\x00\x00\x00\x00";
			// ; The length of following data in little-endian byte order.
			// BYTECOUNT = 4BYTE
			while (($v = $int->getItem()) !== null) {

				$val = pack('V', strlen($v) + 15);
				// DATA = OUTLOOKID | VCALID
				// ; The length specified by BYTECOUNT.
				// OUTLOOKID = *BYTE
				// VCALID = VCALSTRING VERSION UID %x00
				// ; A marker indicating that the identifier is a vCal identifier.
				// VCALSTRING = "vCal-Uid"
				$val .= "vCal-Uid";
				// VERSION = %x01 %x00 %x00 %x00
				$val .= "\x01\x00\x00\00";
				// ; The length is BYTECOUNT less the length of VCALSTRING less the length of VERSION ; less 1
				// UID = *BYTE
				// BYTE = %x00-FF
				$val .= '{'.$v.'}';
				// byte for <00>.
				// null = %x00
				$val .= "\x00";
				$ext->addVar($tag, base64_encode($pref.$val), false, $ext->setCP($cp));
				$rc	= true;
			}
			break;

		default:
			break;
		}

		return $rc;
	}

}
