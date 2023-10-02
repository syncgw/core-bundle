<?php
declare(strict_types=1);

/*
 * 	Error handler class
 *
 *	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\lib;

class ErrorHandler {

	// PHP Error
	const PHP_ERR 		 = [
	        E_ERROR             => 'E_ERROR',
			E_WARNING           => 'E_WARNING',
            E_PARSE             => 'E_PARSE',
            E_NOTICE            => 'E_NOTICE',
            E_CORE_ERROR        => 'E_CORE_ERROR',
            E_CORE_WARNING      => 'E_CORE_WARNING',
            E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
            E_USER_ERROR        => 'E_USER_ERROR',
            E_USER_WARNING      => 'E_USER_WARNING',
            E_USER_NOTICE       => 'E_USER_NOTICE',
		    E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
			E_STRICT            => 'E_STRICT',
			E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED        => 'E_DEPRECATED',
    ];

 	/**
	 * 	PHP Error filter
	 * 	@var array
	 */
	private static $_filter = [];

    /**
     * 	Singleton instance of object
     * 	@var ErrorHandler
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): ErrorHandler {

		if (!self::$_obj) {

            self::$_obj = new self();

			// set log messages 11601-11700
			Log::getInstance()->setLogMsg([
	            11601 => '%s',
				11602 => 'Unknown message code [%s]',
			]);

	    	// set error handler
			register_shutdown_function([ self::$_obj, 'catchLastError' ]);
			set_error_handler([ self::$_obj, 'catchError' ]);

			// handle XML erros internaly
			libxml_use_internal_errors(true);

			self::resetReporting();
		}

		return self::$_obj;
	}

    /**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
	 */
	public function getInfo(XML &$xml): void {

		$xml->addVar('Name', 'PHP Error handler');

		$xml->addVar('Opt', 'Capture PHP error');
		$xml->addVar('Stat', Config::getInstance()->getVar(Config::PHPERROR) == 'Y' ? 'Yes' : 'No');
	}

	/**
	 * 	Tell error handler to filter out specifix types of message
	 *
	 *  @param  - PHP error type
	 *  @param 	- File name fragment
	 *  @param 	- PHP function name
	 */
	static function filter(int $typ = E_WARNING, ?string $file = null, ?string $func = null): void {

		self::$_filter[] = [ $typ, $file, $func ];
	}

	/**
	 * 	Catch last PHP error
	 */
	public function catchLastError() {

		if ($msg = error_get_last())
			return self::catchError($msg['type'], $msg['message'], $msg['file'], $msg['line']);
	}

	/**
	 * 	Error catching function
	 *
	 *  @param  - PHP error code
	 *  @param 	- Error Message
	 *  @param 	- File name
	 *  @param  - Line nmber
	 *  @return - true
	 */
	public function catchError(int $typ, string $errmsg, string $file, int $line) {

		// check filters
		foreach (self::$_filter as $fld) {

			if ($typ & $fld[0] && ($fld[1] && stripos($file, $fld[1]) !== false) ||
				($fld[2] && strpos($errmsg, $fld[2]) !== false)) {

				// be sure to clear possible XML errors
				libxml_clear_errors();
				// prevent PHP error handling to appear
    			return true;
			}
		}

		// do not catch errors?
		$cnf = Config::getInstance();
		if ($cnf->getVar(Config::PHPERROR) != 'Y')
			return true;

		// stack trace
		$stack = [];

		// extract back trace stack from fatal error

		switch($typ) {

		case E_ERROR:
		case E_USER_ERROR:
		case E_RECOVERABLE_ERROR:
		case E_CORE_ERROR:
		case E_COMPILE_ERROR:
		case E_DEPRECATED:
		case E_USER_DEPRECATED:
			$msgs   = explode("\n", $errmsg);
			$errmsg = null;
			foreach ($msgs as $msg) {

				if (!$errmsg)
					$errmsg = $msg;
				if (substr($msg, 0, 1) == '#')
					$stack[] = '# '.substr($msg, strpos($msg, ' ') + 1);
			}
			$mtyp = Log::ERR;
			break;

		// case E_WARNING:
		// case E_PARSE:
		// case E_CORE_WARNING:
		// case E_COMPILE_WARNING:
		// case E_USER_WARNING:
		// case E_NOTICE:
		// case E_USER_NOTICE:
		// case E_STRICT:
		default:
			$mtyp = Log::WARN;
			break;
		}

		if (!count($stack))
			$stack = self::Stack();

		// catch error messages
		$recs = [ '+++ '.(isset(self::PHP_ERR[$typ]) ? self::PHP_ERR[$typ] : 'Exception').': '.$errmsg, ];
    	error_clear_last();

    	// swap stack
		foreach ($stack as $rec)
			$recs[] = $rec;

    	// catch XML error
		foreach (libxml_get_errors() as $err) {

			switch ($err->level) {
			case LIBXML_ERR_WARNING:
				$msg = 'Warning';
				break;

			case LIBXML_ERR_ERROR:
				$msg = 'Error';
				break;

			case LIBXML_ERR_FATAL:
			default:
				$msg = 'Fatal Error';
				break;
			}
			$recs[] = '+++ XML '.$msg.': '.$err->message;;
			if ($err->file)
				$recs[] = 'File: '.$err->file.' (line: '.$err->line.', column: '.$err->column.')';
		}
		libxml_clear_errors();

		$log = Log::getInstance();

		// write messages to log file
		foreach ($recs as $rec)
			$log->ForceLogMsg($mtyp, 11601, $rec);

		// send header if we're not debugging
		if ($typ != E_USER_ERROR && !Config::getInstance()->getVar(Config::DBG_SCRIPT)) {

			if (Config::getInstance()->getVar(Config::DBG_LEVEL) == Config::DBG_OFF) {

				header('HTTP/1.0 500 Internal Serer Error');
			   	echo '<font style="'.Config::CSS_TITLE.Config::CSS_ERR.'"><br><br>'.
					 'Unrecoverable PHP error: <br><br>';
				foreach ($recs as $rec)
					echo $rec.'<br>';
			   	echo '<br>Please check log file for more information</font>';
				exit();
			}
		}

    	// prevent PHP error handling to appear
    	return true;
	}

	/**
     * 	Get call stack
     *
     *	@param  - Optional stack position
     * 	@return - Stack array
     */
    static public function Stack(int $pos = 0): array {

    	$stack = [];
        $skip  = true;

    	foreach (debug_backtrace() as $call) {

    	    if ($skip) {

    	        $skip = false;
    	        continue;
    	    }

    	    // class available?
    		if (isset($call['class']))
    			$msg = $call['class'].$call['type'];
    		else
    			$msg = null;

        	$msg = '#'.($pos++).' '.$msg.$call['function'].'(';
        	if (isset($call['args']) && is_array($call['args'])) {

	    		foreach ($call['args'] as $arg) {

	    			if (is_null($arg)) {

	    				$msg .= 'null, ';
	    				continue;
	    			}
	    			if (is_object($arg)) {

	    				$msg .= 'class:'.get_class($arg).', ';
	    				continue;
	    			}
	    			if (is_array($arg)) {

	    				$msg .= 'ARRAY(), ';
	    				continue;
	    			}
	    			if (is_bool($arg)) {

	    				$msg .= $arg ? 'true, ' : 'false, ';
	    				continue;
	    			}
	    			if (is_string($arg)) {

	    				$msg .= '"'.$arg.'", ';
	    				continue;
	    			}
    				$msg .= $arg.', ';
	    		}
				$msg = trim($msg);
    		}
    		if (isset($call['file']))
    			$msg .= ') called at ['.$call['file'].':'.$call['line'].']';
    		else
    			$msg .= ')';
    		$stack[] = $msg;
    	}

    	return $stack;
    }

	/**
	 *  Raise user error and exit
	 *
	 * 	@param	- Message number
	 * 	@param	- Additional parameter
	 */
	public function Raise(int $no, ...$parm): void {

		$msgs = Log::getInstance()->getLogMsg();

		// get message
		if (isset($msgs[$no]))
			$msg = sprintf($msgs[$no], isset($parm[0]) ? $parm[0] : '');
		else
			$msg = sprintf($msgs[11602], $no);

		trigger_error($msg, E_USER_ERROR);
	}

	/**
	 * 	Reset PHP error reposting
	 */
	static public function resetReporting(): void {

		if (Config::getInstance()->getVar(Config::DBG_SCRIPT))
			$parms = [
				'log_errors' 			=> 'On',
   				'html_errors' 			=> 'On',
   				'ignore_repeated_errors'=> 'Off',
   				'display_errors' 		=> 'On',
        	    'display_startup_errors'=> 'On',
   				'error_reporting'		=> E_ALL,
			];
		else
			$parms = [
				'log_errors' 			=> 'Off',
				'html_errors' 			=> 'Off',
				'ignore_repeated_errors'=> 'On',
				'display_errors' 		=> 'Off',
				'error_reporting'		=> E_ALL,
			];

		foreach ($parms as $k => $v) {

			if ($k == 'error_reporting')
				@error_reporting($v);
			else
				@ini_set($k, $v);

			$n = @ini_get($k);
			if ($n != $v)
				Log::getInstance()->logMsg(Log::WARN, 10709, $k, $n, $v);
		}
	}

}
