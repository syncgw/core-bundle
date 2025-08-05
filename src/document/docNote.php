<?php
declare(strict_types=1);

/*
 * 	Notes document handler class
 *
 *  @package	sync*gw
 *	@subpackage	Test scripts
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document;

use syncgw\lib\Config;
use syncgw\lib\DataStore;

class docNote extends docHandler {

   /**
     * 	Singleton instance of object
     * 	@var docNote
     */
    static private $_obj = null;

	/**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): docNote {

	   	if (!self::$_obj) {

            self::$_obj = new self();

            $cnf = Config::getInstance();

			$class = '\\syncgw\\webdav\\mime\\mimPlain';
			if (class_exists($class))
				self::$_obj->_mimeClass[] = $class::getInstance();
			$class = '\\syncgw\\webdav\\mime\\mimvNote';
			if (class_exists($class))
				self::$_obj->_mimeClass[] = $class::getInstance();
			if ($cnf->getVar(Config::DBG_SCRIPT) != 'DB' && $cnf->getVar(Config::DBG_SCRIPT) != 'docNote') {
			    $class = '\\syncgw\\activesync\\mime\\mimAsNote';
				if (class_exists($class))
			    	self::$_obj->_mimeClass[] = $class::getInstance();
			}
			self::$_obj->_hid = DataStore::NOTE;

			self::$_obj->_init();
	   	}

		return self::$_obj;
	}

}
