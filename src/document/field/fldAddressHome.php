<?php
declare(strict_types=1);

/*
 *  AddressHome field handler
 *
 *	@package	sync*gw
 *	@subpackage	Document handler
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\XML;

class fldAddressHome extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'AddressHome';

	const ASA_TAG		  	= [
								'', '', 'HomeAddressStreet', 'HomeAddressCity',
								'HomeAddressState', 'HomeAddressPostalCode', 'HomeAddressCountry'
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldAddressHome
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldAddressHome {

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
		$addr  = [];
		$ipath .= self::TAG;

		switch ($typ) {
		case 'application/activesync.contact+xml':
			$addr = [];
			$p    = $ext->savePos();
			for ($i=0; $i < 7; $i++) {

				if (!self::ASA_TAG[$i])
					continue;
		   		$ext->xpath(self::ASA_TAG[$i], false);
				$cnt = 0;
				while (($val = $ext->getItem()) !== null) {

					if ($val)
						$addr[$cnt][$i] = $val;
					$cnt++;
				}
				$ext->restorePos($p);
			}
			break;

		default:
			break;
		}

		// any data loaded?
		if (count($addr)) {
	   		parent::delTag($int, $ipath);
	  		$ip = $int->savePos();
	   		for ($cnt=0; isset($addr[$cnt]); $cnt++) {

	   			$int->addVar(self::TAG);
				$p = $int->savePos();
				for ($i=0; $i < 7; $i++) {

					if (isset($addr[$cnt][$i]))
						$int->addVar(fldAddresses::SUB_TAG[$i], $addr[$cnt][$i]);
				}
				$int->restorePos($p);
			}
			$int->restorePos($ip);
			$rc = true;
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

		$rc = false;

		if (!$int->xpath($ipath.self::TAG, false))
			return $rc;

		switch ($typ) {
		case 'application/activesync.contact+xml':
			while ($int->getItem() !== null) {

				$ip = $int->savePos();
				for ($i=0; $i < 7; $i++) {

					if (self::ASA_TAG[$i] && ($val = $int->getVar(fldAddresses::SUB_TAG[$i], false)) !== null) {

						$ext->addVar(self::ASA_TAG[$i], $val, false, $ext->setCP(XML::AS_CONTACT));
						$rc = true;
					}
					$int->restorePos($ip);
				}
			}
			break;

		default:
			break;
		}

		return $rc;
	}

}
