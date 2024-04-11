<?php
declare(strict_types=1);

/*
 * 	Global address list document handler class
 *
 *	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document;

use syncgw\lib\DataStore;

class docGAL extends docHandler {

    /**
     * 	Singleton instance of object
     * 	@var docGAL
     */
    static private $_obj = null;

	/**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): docGAL {

	   	if (!self::$_obj) {

            self::$_obj = new self();
			$class = '\\syncgw\\activesync\\mime\\mimAsGAL';
			if (class_exists($class))
				self::$_obj->_mimeClass[] = $class::getInstance();
			self::$_obj->_hid = DataStore::GAL;

			self::$_obj->_init();
	   	}

		return self::$_obj;
	}

}
