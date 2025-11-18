<?php
declare(strict_types=1);

/*
 * 	Log handler class
 *
 *	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\lib;

class Log {

	// log level definitions
	const ERR	  	= 0x01;		// error
	const WARN	  	= 0x02;		// warnings
	const INFO	  	= 0x04;		// informational
	const APP	  	= 0x08;		// application
	const DEBUG	  	= 0x10;		// debug

	const ONETIME 	= 0x80;		// one timer

	// message tags
	const MSG_TYP 	=	[
		self::ERR 	=> 'Error',
        self::WARN 	=> 'Warn ',
        self::INFO 	=> 'Info ',
        self::APP	=> 'App  ',
		self::DEBUG	=> 'Debug',
	];

	// log destination
	const UNDEF		= 0x00;
	const OFF	  	= 0x01;
	const SYSL	  	= 0x02;
	const FILE	  	= 0x04;
	const STDOUT	= 0x08;

	/**
	 * 	Log message array
	 * 	@var array
	 */
	private $_msg;

	/**
	 * 	Log output destination
	 * 	@var int
	 */
	private $_dest		= self::UNDEF;

	/**
	 * 	Log file name
	 * 	@var string
	 */
	private $_file 		= null;

	/**
	 * 	Filee / GUI pointer
	 * 	@var object
	 */
	private $_ptr;

	/**
	 * 	Log level
	 * 	@var int
	 */
	private $_loglvl	= self::ERR;

	/**
	 * 	Log plugin buffer
	 * 	@var array
	 */
	private $_plugin	= [];

	/**
	 * 	PHP Error filter
	 * 	@var array
	 */
	private $_filter	= [];

	/**
	 * 	Log catching status
	 * 	@var boolean
	 */
	private $_ob		= false;

    /**
     * 	Singleton instance of object
     * 	@var Log
     */
    static private $_obj = null;

    /**
     * 	Initialization status
     * 	@var bool
     */
    private $_init 		 = false;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): Log {

		if (!self::$_obj) {

            self::$_obj = new self();

			// set log message codess 10001-10100
			self::$_obj->_msg = [

		    	10001 => '%s',
				10002 => 'Error opening file [%s]',
				10003 => 'Error writing file [%s]',
				10004 => 'Cleanup %d log files',
			];

		} elseif (!self::$_obj->_init) {

			self::$_obj->_init = true;

			// catch console output
			self::$_obj->catchConsole(true);

			// register shutdown function
			if (class_exists('\\syncgw\\lib\Server'))
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

		// stop logging?
		if (self::$_obj->_dest == self::FILE && is_resource(self::$_obj->_ptr))
			fclose(self::$_obj->_ptr);

		// turn logging off
		self::$_obj->_dest = self::OFF;

		// do not delete object, since all message gets lost
		// self::$_obj = null;
	}

    /**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
 	 */
	public function getInfo(XML &$xml): void {

		$xml->addVar('Name', 'Log handler');

		$xml->addVar('Opt', 'Logging output send to');
		$xml->addVar('Stat', '"'.Config::getInstance()->getVar(Config::LOG_DEST).'"');

		$xml->addVar('Opt', 'Log level');
		$stat = '';
		$lvl  = Config::getInstance()->getVar(Config::LOG_LVL);
		if ($lvl & self::ERR)
			$stat .= self::MSG_TYP[self::ERR].' ';
		if ($lvl & self::WARN)
			$stat .= self::MSG_TYP[self::WARN].' ';
		if ($lvl & self::INFO)
			$stat .= self::MSG_TYP[self::INFO].' ';
		if ($lvl & self::APP)
			$stat .= self::MSG_TYP[self::APP].' ';
		if ($lvl & self::DEBUG)
			$stat .= self::MSG_TYP[self::DEBUG].' ';
		$xml->addVar('Stat', $stat);

	}

	/**
	 * 	Catch all console output
	 *
	 * 	@param	- true = Start catching; false = Stop
	 */
	public function catchConsole(bool $start): void {

		if ($start && !$this->_ob) {

			ob_start();
			$this->_ob = true;
			return;
		}

		// catching active?
		if (!$this->_ob)
			return;

		$this->_ob = false;

		if ($msg = ob_get_clean()) {

			$recs = explode("\n", str_replace('<br>', "\n", strip_tags($msg, '<font><b><i>')));
			foreach ($recs as $rec) {

				if (strlen(trim($rec)))
					self::LogMsg(self::WARN, 10001, '+++ '.$rec);
			}
		}
	}

	/**
	 * 	Add log message definitions
	 *
	 * 	@param	- Message definition [ num => message ] or [ num => num ]
	 */
	public function setLogMsg(array $msg): void {

		foreach ($msg as $c => $m)
			$this->_msg[$c] = $m;
	}

	/**
	 * 	Get all log message definition(s)
	 *
	 *	@return - All defined log messages
	 */
	public function getLogMsg(): array {

		return $this->_msg;
	}

	/**
	 * 	Add log plugin handler
	 *
	 *  @Param  - Function name
	 * 	@param	- Class object or null
	 */
	public function Plugin(string $func, mixed $class = null): void {

		$c = $class ? get_class($class) : '';
		$k = $c.':'.$func;
		$this->_plugin[$k] = [ $c, $func ];
	}

	/**
	 * 	Suspend logging
	 *
	 * 	@return	- Saved status
	 */
	public function LogSuspend(): array {

		$stat          = [ $this->_dest, $this->_plugin ];
		$this->_dest    = self::OFF;
		$this->_plugin = [];

		return $stat;
	}

	/**
	 * 	Resume logging
	 *
	 * 	@param	- [ Saved status ]
	 */
	public function LogResume(array $stat): void {

		$this->_dest 	= $stat[0];
		$this->_plugin 	= $stat[1];
	}

	/**
	 * 	Create log message
	 *
	 * 	@param	- Message typ (ERR, WARN, INFO, APP, DEBUG)
	 * 	@param	- Message number
	 * 	@param	- Additional parameter
	 * 	@return	- Log message
	 */
	public function LogMsg(int $typ, int $no, ...$parm): string {

		$cnf = Config::getInstance();

		// check logging status?
		if ($this->_dest == self::UNDEF) {

			// check configuration
			switch (strtolower($val = $cnf->getVar(Config::LOG_DEST))) {

			case 'off':
				$this->_dest = self::OFF;
				break;

			case 'syslog':
				$this->_dest = self::SYSL;
				break;

			case 'stdout':
				$this->_dest = self::STDOUT;
				break;

			// must be file output
			default:
				$this->_dest = self::FILE;
				$this->_file = $val;
				break;
			}

			// get logging level
			$this->_loglvl = $cnf->getVar(Config::LOG_LVL);
		}

		// message available?
		if (!isset($this->_msg[$no]))
			ErrorHandler::getInstance()->Raise($no);

		// unfold special message
		if (!$typ && is_array($parm)) {

			$parm = $parm[0];
			$typ = self::ERR;
		}

		// set message number
		$msg = self::MSG_TYP[$typ & ~self::ONETIME].sprintf(' %04d', $no).' ';

		if (isset($this->_msg[$no]))
			$msg .= is_array($parm) ? vsprintf(is_numeric($this->_msg[$no]) ? $this->_msg[$this->_msg[$no]] :
									  $this->_msg[$no], $parm) : $parm;
		else
			$msg .= is_array($parm) ? implode(' ', $parm) : $parm;

		// limit output length
		if (strlen($msg) > 10240)
			$msg = substr($msg, 0, 10240).sprintf('[CUT@%d]', 10240);

		// one time message?
		if ($typ & self::ONETIME) {

		    $sess = Session::getInstance();
   		    $sess->xpath('//OneTimeMessage');
   			if (($v = $sess->getItem()) !== null) {

    			if (strpos($v, strval($no)) !== false)
            		return $msg;
           		$sess->setVal($v.','.$no);
		    }
		}

		// call plugin handler
		foreach ($this->_plugin as $func) {

		    if ($func[0]) {

		        $func[0] = $func[0]::getInstance();
		        $func[0]->{$func[1]}($typ, $msg);
		    } else
		        !$func[1]($typ, $msg);
		}

		// logging disabled
		if ($this->_dest == self::OFF || !($this->_loglvl & $typ))
			return $msg;

		// stdout only
		if ($this->_dest == self::STDOUT) {

			echo '<font style="'.Config::CSS_TITLE.Config::CSS_ERR.'">'.$msg.'<br>';
			return $msg;
		}

		// syslog only
		if ($this->_dest == self::SYSL) {

			syslog(LOG_INFO, $msg);
			return $msg;
		}

		if ($this->_dest == self::FILE && !is_object($this->_dest)) {

 			// open file
			if (!($this->_ptr = fopen($this->_file.'.'.date('Ymd'), 'a+b'))) {
				$this->_dest = self::STDOUT;
				return self::LogMsg(self::ERR, 10002, $this->_file.'.'.date('Ymd'));
			}
		}

		$ip = class_exists('syncgw\\lib\\HTTP') ? HTTP::getInstance()->getHTTPVar('REMOTE_ADDR') : '127.0.0.1';
		if (fwrite($this->_ptr, date('Y M d H:i:s').' '.str_pad('['.$ip.']', 18, ' ').$msg."\n") === false) {

			fclose($this->_ptr);
			$this->_dest = self::STDOUT;
			return self::LogMsg(self::ERR, 10003, $this->_file, '0');
		}

		return $msg;
	}

	/**
	 *  Force a message to syncgw log file
	 *
	 * 	@param	- Message typ (ERR, WARN, INFO, APP, DEBUG)
	 * 	@param	- Message number
	 * 	@param	- Additional parameter
	 * 	@return	- Log message
	 */
	public function ForceLogMsg(int $typ, int $no, ...$parm): void {

	    $cnf = Config::getInstance();

	    if (($cnf->getVar(Config::DBG_SCRIPT) && !is_object($this->_dest)) || $this->_dest == self::STDOUT) {

	    	$this->_dest = self::STDOUT;
	    	$old = $this->_loglvl;
			$this->_loglvl = self::ERR|self::WARN;
	    	$this->logMsg($typ, $no, ...$parm);
	   		$this->_loglvl = $old;
	   		return;
	    }

	    // save plugins
	    $aplugin = $this->_plugin;
	    $this->_plugin = [];
	    // save current log destination
		$amod = $this->_dest;
		// set to "undef"
		$this->_dest = self::UNDEF;
		// turn off debugging
		$adbg = $cnf->updVar(Config::DBG_LEVEL, Config::DBG_OFF);
		// restore original log destination
		$adest = $cnf->updVar(Config::LOG_DEST, $cnf->getVar(Config::LOG_DEST, true));
		// only log error and warnings
		$alvl = $this->_loglvl;
		$this->_loglvl = self::ERR|self::WARN;

	    $this->logMsg($typ, $no, ...$parm);

	   	$this->_loglvl = $alvl;
	   	$cnf->updVar(Config::LOG_DEST, $adest);
		$this->_dest = $amod;
		$this->_plugin = $aplugin;
		$cnf->updVar(Config::DBG_LEVEL, $adbg);
	}

	/**
	 * 	Perform log expiration
	 */
	public function LogExpiration(): void {

		$cnf = Config::getInstance();
		if (!($path = $cnf->getVar(Config::LOG_DEST)))
			return;

        // check existing file count
		$p = dirname($path);
		$f = substr($path, strlen($p) + 1);
		$l = strlen($f);
		$a = [];
        // log rotate?
    	if ($d = opendir($p)) {

	    	while (($file = readdir($d)) !== false) {

	       	    if (substr($file, 0, $l) == $f)
	       			$a[] = $file;
	       	}
    	}
   		closedir($d);

   		sort($a);

   		if (count($a) <= ($cnt = $cnf->getVar(Config::LOG_EXP)))
   			return;

   		for($n=$cnt; $n < count($a); $n++)
   		    unlink($p.'/'.array_shift($a));

		self::LogMsg(self::INFO, 10004, $n - $cnt);
	}

 }
