<?php
declare(strict_types=1);

/*
 * 	Server class
 *
 * 	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\lib;

class Server {

	// list of known handler
	const HANDLER = [
		'syncgw\\interface\\file\\Handler',
		'syncgw\\interface\\mysql\\Handler',
		'syncgw\\interface\\myapp\\Handler',
		'syncgw\\interface\\roundcube\\Handler',
		'syncgw\\interface\\mail\\Handler',
		'syncgw\\activesync\\masHandler',
		'syncgw\\webdav\\davHandler',
		'syncgw\\mapi\\mapiHandler',
		'syncgw\\rpc\\rpcHandler',
		'syncgw\\rops\\ropHandler',
		'syncgw\\ics\\icsHandler',
	];

    /**
     * 	Singleton instance of object
     * 	@var Server
     */
    static private $_obj = NULL;

	/**
     * 	Shutdown array
     * 	@var array
     */
    private $_mods = [];

	/**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): Server {

	   	if (!self::$_obj) {

            self::$_obj = new self();

			// allocate error handler
			ErrorHandler::getInstance();

			// register shutdown function on __destruct()
			register_shutdown_function([ self::$_obj, 'shutDown' ]);
		}

		return self::$_obj;
	}

    /**
	 * 	Get information about handler class
     *
     * 	@return	- XML object
	 */
	public function getInfo(): XML {

		$xml = new XML();
		$xml->addVar('syncgw');
		$xml->addVar('Name', '<strong>sync&bull;gw</strong> server');

		$xml->addVar('Name', '<a href="http://www.iana.org/time-zones" target="_blank">IANA</a> Time zone data base source');
		$xml->addVar('Stat', 'Implemented');

		// scan library classes
		self::getBundleInfo($xml, 'core-bundle/src/lib', 'lib', [ 'Server' ]);
		// scan document classes
		self::getBundleInfo($xml, 'core-bundle/src/document', 'document');

		// show supporting classes
		foreach (self::HANDLER as $class) {

			if (class_exists($class) && method_exists($class, 'getInfo'))
				$class::getInstance()->getInfo($xml);
		}

		return $xml;
	}

    /**
	 * 	Get information about supporting classes
     *
     *	@param 	- Output document
     *	@param 	- Bundle name
     *	@param	- Associated class name
     *	@param 	- File exlision list
	 */
	public function getBundleInfo(XML &$xml, string $bundle, string $class, array $exclude = []): void {

		// get supporting handler information
		if ($d = opendir($dir = Config::getInstance()->getVar(Config::ROOT).$bundle)) {

			while (($file = readdir($d)) !== false) {

				if (is_dir($dir.'/'.$file) || strpos($file, 'Handler') !== false)
					continue;

				$ex = 'ok';
				foreach ($exclude as $ex) {

					if (strpos($file, $ex) !== false) {

						$ex = NULL;
						break;
					}
				}
				if (!$ex)
					continue;

				// strip off file extension
				$file = substr($file, 0, -4);
				$obj  = 'syncgw\\'.$class.'\\'.$file;
				$obj  = method_exists($obj, 'getInstance') ? $obj::getInstance() : new $obj();
				if ($obj && method_exists($obj, 'getInfo'))
					$obj->getInfo($xml);
			}

			closedir($d);
		}
	}

	/**
	 * 	Process input data
	 */
	public function Process(): void {

		$http = HTTP::getInstance();
		$cnf  = Config::getInstance();

		// allocate msg object to start output catching
		$log = Log::getInstance();

		// get responsible handler
		$mod = $cnf->getVar(Config::HANDLER);

		// receive and format HTTP data
		if ($cnf->getVar(Config::DBG_LEVEL) == Config::DBG_OFF) {

			if (!$http->receive($_SERVER, file_get_contents('php://input')))
				return;

			// reload modus
			$mod = $cnf->getVar(Config::HANDLER);
		}

		if ($mod == 'GUI' && class_exists($class = 'syncgw\\gui\\guiHandler')) {

			$class::getInstance()->Process();
		   	return;
		}

		// handle record expiration
		global $argv;
		if (($cron = $cnf->getVar(Config::CRONJOB)) == 'N' ||
			(isset($argv[1]) && stripos($argv[1], 'cleanup') !== false) ||
			// special hack for PLESK
			stripos($http->getHTTPVar('QUERY_STRING'), 'cleanup') !== false) {

			$log->LogExpiration();
			Session::getInstance()->Expiration();
			Trace::getInstance()->Expiration();

			// cron job call?
			if ($cron == 'Y')
				return;
		}

		// start trace
        $trc = Trace::getInstance();
        $trc->Start();

        // check for ActiveSync
		if ($mod == 'MAS' && (class_exists($class = 'syncgw\\activesync\\MasHandler'))) {

			$class::getInstance()->Process();
			return;
		}

		// check for MAPI over HTTP
		if ($mod == 'MAPI' && (class_exists($class = 'syncgw\\activesync\\mapiHandler'))) {

			$class::getInstance()->Process();
			return;
		}

		// we assume it is WebDAV
		if (class_exists($class = 'syncgw\\webdav\\davHandler'))
			$class::getInstance()->Process();
	}

	/**
	 * 	Register shutdown functions
	 *
	 * 	@param 	Class name
	 */
	public function regShutdown(string $class) {

		$this->_mods[$class] = 1;
	}

	/**
	 * 	Unregister shutdown functions
	 *
	 * 	@param 	Class name
	 */
	public function unregShutdown(string $class) {

		unset($this->_mods[$class]);
	}

	/**
	 * 	Shutdown server<br>
	 * 	Calls to __destruct() cannot be used, since all classes are kept in memory until last reference is removed
	 *  - A constant reference is also a reference!
	 */
	public function shutDown () {

		$db = NULL;
		$mods = array_reverse($this->_mods);
		foreach ($mods as $class => $unused) {

			// skip data base interface handler
			if (substr($class, 0, 2) == 'DB')
				$db = $class;
			else {

				$obj = $class::getInstance();
				// double check for shutdown function
				if (method_exists($obj, 'delInstance')) {
					Msg::InfoMsg('Shutting down "'.$class.'"');
					$obj->delInstance();
				}
			}
		}
		$unused; // disable Eclipse warning

		// stop data base at end
		if ($db) {
			$obj = $db::getInstance();
			if (method_exists($obj, 'delInstance')) {

				Msg::InfoMsg('Shutting down "'.$db.'"');
				$obj->delInstance();
			}
		}

		// device unlock will be done automatically
	}

}
