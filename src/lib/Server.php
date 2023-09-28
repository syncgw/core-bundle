<?php
declare(strict_types=1);

/*
 * 	Server class
 *
 * 	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\lib;

class Server {

	// list of known handler
	const HANDLER = [
		'syncgw\\gui\\guiHandler',
		'syncgw\\interface\\file\\Handler',
		'syncgw\\interface\\mysql\\Handler',
		'syncgw\\interface\\myapp\\Handler',
		'syncgw\\interface\\roundcube\\Handler',
		'syncgw\\interface\\mail\\Handler',
		'syncgw\\activesync\Handler',
		'syncgw\\webdav\\Handler',
		'syncgw\\document\docContact',
		'syncgw\\document\docCalendar',
		'syncgw\\document\docTask',
		'syncgw\\document\docNote',
		'syncgw\\document\docGAL',
		'syncgw\\document\docLib',
		'syncgw\\document\docMail',
		'syncgw\\document\\field\\fldHandler',
		'syncgw\\mapi\\Handler',
		'syncgw\\rpc\\Handler',
		'syncgw\\rops\\pHandler',
		'syncgw\\ics\\Handler',
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

			// set log message codes 10101-10200
			Log::getInstance()->setLogMsg([
					10101 => 'sync*gw not available for devices until upgrade of server has been performed',
			]);

			// register shutdown function on __destruct()
			register_shutdown_function([ self::$_obj, 'shutDown' ]);
		}

		return self::$_obj;
	}

    /**
	 * 	Get information about handler class
     *
     *	@param 	- TRUE = Provide status information only (if available)
     * 	@return	- XML object
	 */
	public function getInfo(bool $status): XML {

		$xml = new XML();
		$xml->addVar('syncgw');
		$xml->addVar('Name', '<strong>sync&bull;gw</strong> server');

		if (!$status) {

			$xml->addVar('Opt', '<a href="http://www.iana.org/time-zones" target="_blank">IANA</a> Time zone data base source');
			$xml->addVar('Stat', 'Implemented');
		}

		// scan library classes
		self::getSupInfo($xml, $status, 'lib', [ 'Server', ]);

		// show supporting classes
		foreach (self::HANDLER as $class) {

			if (class_exists($class) && method_exists($class, 'getInfo'))
				$class::getInstance()->getInfo($xml, $status);
		}

		return $xml;
	}

    /**
	 * 	Get information about supporting classes
     *
     *	@param 	- Output document
     *	@param 	- TRUE = Check status; FALSE = Provide supported features
     *	@param 	- Path to directory
     *	@param 	- File exlision list
	 */
	public function getSupInfo(XML &$xml, bool $status, string $path, array $exclude = []): void {

		// get supporting handler information
		if ($d = opendir($dir = Config::getInstance()->getVar(Config::ROOT).$path)) {

			$path .= '\\';
			while (($file = readdir($d)) !== FALSE) {

				if (is_dir($dir.'/'.$file) || strpos($file, 'Handler') !== FALSE)
					continue;

				$ex = 'ok';
				foreach ($exclude as $ex) {
					if (strpos($file, $ex) !== FALSE) {
						$ex = NULL;
						break;
					}
				}
				if (!$ex)
					continue;

				// strip off file extension
				$class = 'syncgw\\'.$path.substr($file, 0, -4);
				$class = method_exists($class, 'getInstance') ? $class::getInstance() : new $class();
				if ($class && method_exists($class, 'getInfo'))
					$class->getInfo($xml, $status);
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
			(isset($argv[1]) && stripos($argv[1], 'cleanup') !== FALSE) ||
			// special hack for PLESK
			stripos($http->getHTTPVar('QUERY_STRING'), 'cleanup') !== FALSE) {

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
