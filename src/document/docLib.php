<?php
declare(strict_types=1);

/*
 * 	Document library document handler class
 *
 *  @package	sync*gw
 *	@subpackage	Test scripts
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	https://www.gnu.org/licenses/gpl-3.0.en.html
 */

namespace syncgw\document;

use syncgw\lib\DataStore;

class docLib extends docHandler {

   /**
     * 	Singleton instance of object
     * 	@var docLib
     */
    static private $_obj = null;

	/**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): docLib {

	   	if (!self::$_obj) {

            self::$_obj = new self();
			$class = '\\syncgw\\activesync\\mime\\mimAsDocLib';
			if (class_exists($class))
				self::$_obj->_mimeClass[] = $class::getInstance();
			self::$_obj->_hid = DataStore::DOCLIB;

			self::$_obj->_init();
	   	}

		return self::$_obj;
	}

}
