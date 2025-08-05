<?php
declare(strict_types=1);

/*
 * 	Lock functions class
 *
 *	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\lib;

class Lock {

 	/**
     *  Lock file buffer
     *  @var array
     */
    private $_lock = [];

    /**
     * 	Singleton instance of object
     * 	@var Lock
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): Lock {

		if (!self::$_obj) {

			self::$_obj = new self();

            // set log message codes
	    	Log::getInstance()->setLogMsg([
	           	    11501 => 'Lock error on [%s] - "%s(%s): %s"',
	    	]);

			// register shutdown function
	    	if (class_exists('syncgw\\lib\\Server'))
				Server::getInstance()->regShutdown(__CLASS__);
		}

		return self::$_obj;
	}

 	/**
	 * 	Shutdown function
	 */
	public function delInstance(): void {

		if (!self::$_obj)
			return;

		foreach (self::$_obj->_lock as $unused => $lock) {
		    fclose($lock[0]);
   		    unlink($lock[1]);
        }
		$unused; // disable Eclipse warning

		self::$_obj = null;
	}

	/**
	 *  Create lock
	 *
	 *  @param  - Lock string
	 *  @param  - true = Wait to get lock; false = Do not wait
	 *  @return - true = Ok; false = Error
	 */
	public function lock(string $name, bool $wait = false): bool {

	    // get file name
	    $path = Config::getInstance()->getVar(Config::TMP_DIR).Util::normFileName($name.'.lock');

        do {
	        if (($fp = @fopen($path, 'c')) === false) {

    			$err = error_get_last();
    			// special hack for windows
    			if (isset($err['message']) && stripos($err['message'], 'Permission denied') === false) {

	    			Log::getInstance()->logMsg(Log::WARN, 11501, $path, $err['file'], $err['line'], $err['message']);
				    return false;
    			}
    			Util::Sleep();
    	    }
        } while (!is_resource($fp));

       	if (@flock($fp, $wait ? LOCK_EX : LOCK_EX|LOCK_NB)) {

	    	// save lock data
    		$this->_lock[$name] = [ $fp, $path ];
	        return true;
       	}

       	return false;
	}

	/**
	 *  Unlock
	 *
	 *  @param  - Lock string
	 */
	public function unlock(string $name): void {

	    if (isset($this->_lock[$name])) {

	        // unlock file
	        flock($this->_lock[$name][0], LOCK_UN);
	        // close lock file
	        fclose($this->_lock[$name][0]);
	        // try to delete file
        	unlink($this->_lock[$name][1]);
            // delete entry
	        unset($this->_lock[$name]);
	    } else {

            $path = Util::getTmpFile('lock', $name);
	        // try to delete file
           	unlink($path);
	    }
	}

}
