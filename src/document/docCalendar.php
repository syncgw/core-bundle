<?php
declare(strict_types=1);

/*
 * 	Calendar document handler class
 *
 *	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document;

use syncgw\lib\DataStore;

class docCalendar extends docHandler {

    /**
     * 	Singleton instance of object
     * 	@var docCalendar
     */
    static private $_obj = null;

	/**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): docCalendar {

	   	if (!self::$_obj) {

            self::$_obj = new self();
			self::$_obj->_mimeClass = [];

			$class = '\\syncgw\\webdav\\mime\\mimvCal';
			if (class_exists($class))
				self::$_obj->_mimeClass[] = $class::getInstance();
		   	$class = '\\syncgw\\activesync\\mime\\mimAsCalendar';
			if (class_exists($class))
				self::$_obj->_mimeClass[] = $class::getInstance();
		   	self::$_obj->_hid = DataStore::CALENDAR;

			self::$_obj->_init();
		}

		return self::$_obj;
	}

}
