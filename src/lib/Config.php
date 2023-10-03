<?php
declare(strict_types=1);

/*
 *  Configuration handler class
 *
 *	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\lib;

class Config {

	const GITVER			= '9.19.80';					// sync*gw version

	// configuration paremeters
	const ROOT				= 'RootDirectry';				// root directory of syncgw project
	const ADMPW				= 'AdminPassword';				// administrator passwort
	const CRONJOB			= 'CronJob';					// use cron job for expiration handling
	const LOG_DEST			= 'LogDestination';				// log output destination (system/off/stdout/file name prefix)
	const LOG_LVL			= 'LogLevel';					// logging level
	const LOG_EXP			= 'LogFileExp';					// log file expiration
	const TRACE_CONF		= 'TraceConfig';				// trace configuration
	const TRACE_DIR			= 'TraceDir';					// trace directory
	const TRACE_EXP 		= 'TraceExpiration';			// trace file expiration
	const TRACE_EXC			= 'TraceException';				// trace SabreDAV exceptions
	const SESSION_TIMEOUT	= 'SessionTimeout';				// session timeout
	const SESSION_EXP		= 'SessionExp';					// session record expiration
	const PHPERROR			= 'PHPError';					// capture PHP fatal errors
	const ENABLED			= 'Datastores';					// enabled data stores
	const DATABASE			= 'Database';					// data base connection

	const MAXOBJSIZE        = 'MaxObjectSize';				// max. object size for DAV (e.g. attachments)

	const HEARTBEAT			= 'HeartBeat';					// ActiveSync: Max. heatbeat in seconds
	const PING_SLEEP        = 'PingSleep';					// ActiveSync: Max. sleep time during <Ping> processing

	// "file" interface parameter
	const FILE_DIR			= 'FileDirectory';				// "file" handler parameter

	// "mysql" interface parameter
	const DB_HOST			= 'MySQLHost';					// host name
	const DB_PORT			= 'MySQLPort';					// post number
	const DB_USR			= 'MySQLUser';					// user name
	const DB_UPW			= 'MySQLPassword';				// password
	const DB_NAME			= 'MySQLDatabase';				// data base
	const DB_PREF			= 'MySQLPrefix';				// syncgw table name prefix
	const DB_RSIZE          = 'MySQLSize';					// max. data base record size
	const DB_RETRY 			= 'MySQLRetry';					// "[2006] MySQL server has gone away" repeation max

	// "RoundCube" interface parameter
	const RC_DIR			= 'RCDirectory';				// directory where code is located

	// "mail" interface parameter
	const CON_TIMEOUT 		= 'ConnectionTimeout';			// connection test timeout
	const MAILER_ERR 		= 'MailerError';				// throw external exceptions in PHPMAILER

	const IMAP_HOST         = 'ImapHost';					// the IMAP server to get connected
    	const IMAP_PORT     = 'ImapPort';					// TCP port to connect to
    	const IMAP_ENC      = 'ImapEncryption';				// encryption to use
    	const IMAP_CERT     = 'ImapValidateCert';			// validate certificate

	const SMTP_HOST         = 'SMTPHost';					// the SMP server to get connected
    	const SMTP_PORT     = 'SMTPPort';					// TCP port to connect to
    	const SMTP_AUTH     = 'SMTPAuth';					// authoriization to use
    	const SMTP_ENC      = 'SMTPEncryption';				// encryption to use
		const SMTP_DEBUG	= 'SMTPDebug';					// SMTP class debug output mode

    // internal configuration parameters
	const VERSION			= 'Version';					// syncgw version
    const EXECUTION			= 'MaxExecutionTime';			// max. PHP execution time
	const SOCKET_TIMEOUT	= 'SocketTimeout';				// Default timeout for socket based streams.
    const TMP_DIR			= 'TmpDir';						// temporary directory
	const TRACE 			= 'Trace';						// trace status
		const TRACE_OFF		= 0x01;							// trace is off
		const TRACE_ON		= 0x02;							// trace is on
		const TRACE_FORCE	= 0x04;							// forced trace is running
	const TIME_ZONE			= 'Timezone';					// default system time zone

	const HACK				= 'Hack';						// hack bit field
		const HACK_SIZE     = 0x0001;						// do NOT limit attachment size
		const HACK_NOKIA	= 0x0002;						// general Nokia
		const HACK_CDAV		= 0x0004;						// special hack for CardDAV-Sync (Android) (Only for 0.3.8.2 required)
		const HACK_WINMAIL	= 0x0008;						// windows mail program

	const DBG_LEVEL			= 'DebugLevel'; 				// debug level
		const DBG_OFF		= 0x01;							// debugging is off
		const DBG_VIEW		= 0x02;							// view trace records
		const DBG_TRACE		= 0x04;							// process trace records
		const DBG_GUI		= 0x08;							// debug gui explorer processing
	const DBG_USR			= 'DebugUser'; 					// debug user
	const DBG_UPW			= 'DebugPassword'; 				// debug user password
	const DBG_DIR           = 'DebugDirectory'; 			// debug directory
	const DBG_CLASS			= 'DebugClass'; 				// which classes to debug
	const DBG_EXCL			= 'DebugExclude';				// classes/functions to exclude in debugging messages
	const DBG_INCL			= 'DebugInclude';				// classes/functions to include in debugging messages
	const DBG_SCRIPT		= 'DebugScript';				// name of running test script

	// handler parameter
	const HANDLER			= 'Protocol';					// MAS=ActiveSync, DAV=WebDAV,
															// GUI=Browser Interface. MAPI=MAPI over HTTP
	const HTTP_CHUNK        = 'SendSize';					// max. of bytes (1 MB) send in one chunk
	const FORCE_TASKDAV     = 'TaskDAV';					// force WebDAV task list synchronization

	// value types
    const VAL_TYP           = 0;							// type of value
	const VAL_DEF			= 1;							// default value
	const VAL_POSS			= 2;							// possible value
	const VAL_NAME			= 3;							// constant name
	const VAL_ORG			= 4;							// orginal value
	const VAL_CURR			= 5;							// current value
	const VAL_SAVE          = 6;							// save to .ini file

	// CSS message types definition
	const CSS_NONE	  		= '';
	const CSS_TITLE	  		= 'font-weight: bold; font-weight: bold;';
	const CSS_ERR	  		= 'color: #DF0101; font-weight: bold;';		// red
	const CSS_WARN	  		= 'color: #FF8000; font-weight: bold;';		// yellow
	const CSS_INFO	  		= 'color: #01DF01;';						// green
	const CSS_APP	  		= 'color: #06D5F7;';						// ligth blue
	const CSS_DBG	  		= 'color: #FF00FB;';						// turquoise

	const CSS_CODE	  		= 'color: #0040FF;';						// blue
	const CSS_QBG	  		= 'background-color: #F2F2F2;';				// back ground color for Q-boxed
	const CSS_SBG	  		= 'background-color: #E6E6E6;';				// back ground color for selected record

	// time definitions
	const UTC_TIME    		= 'Ymd\THis\Z';								// UTC date/time format
	const STD_TIME    		= 'Ymd\THis';								// standard date/time format
	const STD_DATE    		= 'Ymd';									// standard day format
	const masTIME   		= 'Y-m-d\TH:i:s.\0\0\0\Z';					// Activesync date/time format
																		// YYYY-MM-DDTHH:MM:SS.MSSZ where
																		// YYYY = Year (Gregorian calendar year)
																		// MM = Month (01 - 12)
																		// DD = Day (01 - 31)
																		// HH = Number of complete hours since midnight (00 - 24)
																		// MM = Number of complete minutes since start of hour (00 - 59)
																		// SS = Number of seconds since start of minute (00 - 59)
																		// MSS = Number of milliseconds. This portion of the string is optional.
																		// The T serves as a separator, and the Z indicates that this time is in UTC
	const RFC_TIME			= 'D, M Y G:i:s e';							// Fri, 30 Sep 2022 09:03:21 GMT

	const DBG_PREF 	 = 'Dbg-';											// debug device record prefix
	const DBG_PLEN   = 4;												// debug prefix length

	/**
	 * 	Configuration file name
	 * 	@var string
	 */
	public $Path;

	/**
	 * 	Configuration definition array<fieldset>
	 *	@var array
	 */
	private $_conf;

    /**
     * 	Singleton instance of object
     * 	@var Config
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
	public static function getInstance(): Config {

		if (!self::$_obj) {

            self::$_obj = new self();

 			// set default configuration definitions
			// 0 VAL_TYP:  0 - String;  1 - Integer
		 	// 1 VAL_DEF:  Default value
			// 2 VAL_POSS: [ Possible values ]
		 	// 3 VAL_NAME: Constant name
		    // 4 VAL_ORG:  Original loaded value
		    // 5 VAL_CURR: Current value
		    // 6 VAL_SAVE: 1 - Save to .INI file
			self::$_obj->_conf = [

				self::ROOT				=> [ 0, $_SERVER['DOCUMENT_ROOT'].'/vendor/syncgw/', [], null, [], [], 0		],
				self::ADMPW				=> [ 0, null, [], null, [], [], 1                                               ],
				self::CRONJOB			=> [ 0, 'N', [ 'Y', 'N' ], 'CRONJOB', [], [], 1	                                ],
				self::TRACE				=> [ 1, self::TRACE_OFF, [ self::TRACE_OFF, self::TRACE_ON, self::TRACE_FORCE,
																   self::TRACE_ON|self::TRACE_FORCE ], null, [], [], 0  ],
				self::TRACE_CONF		=> [ 0, 'Off', [], null, [], [], 1                                              ],
				self::TRACE_DIR			=> [ 0, null, [], null, [], [], 1		                                        ],
				self::TRACE_EXP			=> [ 0, 24, [], 'TRACE_EXP', [], [], 1     		                                ],
				self::TRACE_EXC			=> [ 0, 'N', [ 'Y', 'N' ], 'TRACE_EXC', [], [], 1     		                    ],
				self::TIME_ZONE			=> [ 0, date_default_timezone_get(), [], 'TIME_ZONE', [], [], 0 		        ],
				self::SESSION_TIMEOUT	=> [ 1, 10, [], 'SESSION_TIMEOUT', [], [], 1                                    ],
				self::SESSION_EXP		=> [ 0, 24, [], 'SESSION_EXP', [], [], 1                   			            ],
				self::LOG_DEST          => [ 0, 'Off', [], null, [], [], 1                                              ],
				self::LOG_LVL			=> [ 1, Log::ERR, [], 'LOG_LVL', [], [], 1       								],
				self::LOG_EXP 			=> [ 1, 7, [], 'LOG_EXP', [], [], 1                                             ],
				self::PHPERROR			=> [ 0, 'Y', [ 'Y', 'N' ], 'PHPERROR', [], [], 1                                ],
				self::DATABASE			=> [ 0, null, [], 'DATABASE', [], [], 1                                         ],
			    self::MAXOBJSIZE        => [ 1, 1024000, [], 'MAXOBJSIZE', [], [], 1                                    ],
			    self::ENABLED			=> [ 1, DataStore::DATASTORES, [], 'ENABLED', [], [], 1                         ],

			    self::VERSION			=> [ 0, self::GITVER, [], 'VERSION', [], [], 0              					],

				self::TMP_DIR           => [ 0, null, [], null, [], [], 1                                               ],
				self::EXECUTION			=> [ 1, 910, [], 'EXECUTION', [], [], 1											],
				self::SOCKET_TIMEOUT	=> [ 1, 60, [], 'SOCKET_TIMEOUT', [], [], 1										],
				self::HACK				=> [ 1, 0, [], 'HACK', [], [], 0                         				        ],

				self::DBG_LEVEL			=> [ 1, self::DBG_OFF, [ self::DBG_OFF, self::DBG_VIEW,
											 self::DBG_TRACE, self::DBG_GUI ], null, [], [], 0  						],
				self::DBG_USR			=> [ 0, null, [], null, [], [], 1                                               ],
				self::DBG_UPW			=> [ 0, null, [], null, [], [], 1                                               ],
				self::DBG_DIR			=> [ 0, null, [], null, [], [], 1                                               ],
				self::DBG_CLASS			=> [ 0, null, [], null, [], [], 1  				   								],
				self::DBG_INCL			=> [ 0, [], [], null, [], [], 0 												],
				self::DBG_EXCL			=> [ 0, [], [], null, [], [], 0 												],
				self::DBG_SCRIPT		=> [ 0, '', [], null, [], [], 0 												],

				// force WebDAV task list synchronization
				self::FORCE_TASKDAV     => [ 0, null, [], 'FORCE_TASKDAV', [], [], 1                                    ],

			    // ActiveSync
				self::PING_SLEEP        => [ 1, 60, [] , 'PING_SLEEP', [], [], 1                                        ],
				self::HEARTBEAT			=> [ 1, 900, [], 'HEARTBEAT', [], [], 1                                         ],

			    // HTTP chunk size
			    self::HTTP_CHUNK        => [ 1, 1000000, [], 'HTTP_CHUNK', [], [], 1                                    ],

				// connection test timeout
				self::CON_TIMEOUT		=> [ 1, 5, [], null, [], [], 0                                                  ],

				// "file" handler parameter
				self::FILE_DIR			=> [ 0, null, [], null, [], [], 1                                               ],

				// "mysql" handler parameter
				self::DB_HOST			=> [ 0, 'localhost', [], null, [], [], 1                                        ],
				self::DB_PORT			=> [ 1, 3306, [], null, [], [], 1                                               ],
				self::DB_USR			=> [ 0, null, [], null, [], [], 1                                               ],
				self::DB_UPW			=> [ 0, null, [], null, [], [], 1                                               ],
				self::DB_NAME			=> [ 0, null, [], null, [], [], 1                                               ],
				self::DB_PREF			=> [ 0, 'syncgw', [], '', [], [], 1                                             ],
				self::DB_RETRY			=> [ 1, 10, [], null, [], [], 1                                                 ],

			    // database record size (10 MB)
			    self::DB_RSIZE         	=> [ 1, 10485760, [], 'DB_RSIZE', [], [], 1                                     ],

				// "RoundCube" handler parameter
				self::RC_DIR			=> [ 0, '.', [], null, [], [], 1                                                ],

				// "PHPMAILER" handler parameter
				self::MAILER_ERR		=> [ 1, 0, [ 0, 1 ], '', [], [], 1                               				],

				// IMAP configuration parameter
			    self::IMAP_HOST         => [ 0, 'localhost', [], 'IMAP_HOST', [], [], 1                                 ],
			    self::IMAP_PORT		    => [ 1, 143, [ 143, 993 ], 'IMAP_PORT', [], [], 1		                        ],
				self::IMAP_ENC			=> [ 0, null, [ null, 'TLS', 'SSL' ], 'IMAP_ENC', [], [], 1                     ],
				self::IMAP_CERT			=> [ 0, 'Y', [ 'Y', 'N' ], 'IMAP_CERT', [], [], 1                               ],

			    // SMTP configuration parameter
			    self::SMTP_HOST         => [ 0, 'localhost', [], 'SMTP_HOST', [], [], 1                                 ],
			    self::SMTP_PORT		    => [ 1, 25, [ 25, 587, 465, 2525 ], 'SMTP_PORT', [], [], 1                      ],
				self::SMTP_AUTH			=> [ 0, 'N', [ 'Y', 'N' ], 'SMTP_AUTH', [], [], 1		                        ],
				self::SMTP_ENC			=> [ 0, null, [ null, 'TLS', 'SSL' ], 'SMTP_ENC', [], [], 1                     ],
				self::SMTP_DEBUG		=> [ 1, 0, [ 0, 1, 2, 3, 4 ], '', [], [], 1                               		],

				// available protocols
				self::HANDLER			=> [ 0, null, [ null, 'MAS', 'MAS', 'DAV', 'GUI', 'MAPI', ], null, [], [], 0	],
			];

			// set config file name
			if (file_exists($_SERVER['DOCUMENT_ROOT'].'/config/defaults.inc.php'))
				self::$_obj->Path = $_SERVER['DOCUMENT_ROOT'].'/config/syncgw.php';
			else
				self::$_obj->Path = $_SERVER['DOCUMENT_ROOT'].'/syncgw.php';

			// set temp. directory
			if (!strlen(self::$_obj->_conf[self::TMP_DIR][self::VAL_DEF] = ini_get('upload_tmp_dir')))
				self::$_obj->_conf[self::TMP_DIR][self::VAL_DEF] = sys_get_temp_dir();
			self::$_obj->_conf[self::TMP_DIR][self::VAL_DEF] .= '/';

			// set default values
			foreach (self::$_obj->_conf as $k => $v)
				self::$_obj->_conf[$k][self::VAL_ORG] = self::$_obj->_conf[$k][self::VAL_CURR] = $v[self::VAL_DEF];

			// "normalize" time zone
			date_default_timezone_set('UTC');

			// load configuration
			self::$_obj->loadConf();

		} elseif (!self::$_obj->_init) {

			self::$_obj->_init = true;

			// set log messages codes 10701-10800
			Log::getInstance()->setLogMsg( [
				10701 => 'Error writing to directory [%s]. Please change user permission settings',
				10702 => 'Error writing file [%s]',
				10703 => 'Invalid configuration parameter \'%s\'',
				10704 => 10703,
				10705 => 10703,
				10706 => 10703,
				10707 => 10703,
				10708 => 'Invalid value \'%s\' for configuration parameter \'%s\'',
				10709 => 'Error setting PHP.INI setting \'%s\' from \'%s\' to \'%s\'',
			]);
		}

		return self::$_obj;
	}

    /**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
	 */
	public function getInfo(XML &$xml): void {

		$xml->addVar('Name', 'Configuration handler');

		$xml->addVar('Opt', 'INI file support');
		$xml->addVar('Stat', 'Implemented');
	}

	/**
	 * 	Load configuration
	 *
	 * 	@param	- Optional configuration array()
	 */
	public function loadConf(array $conf = null): void {

		if (!$conf) {

            // load .INI file
			if (file_exists($this->Path)) {

				$c = @parse_ini_file($this->Path);
			} else
			    $c = null;
		} else
			$c = $conf;

		if (is_array($c)) {

			// swap data
		    foreach ($c as $k => $v) {
		        if (!isset($this->_conf[$k]))
		            continue;
                if (substr($k, 0, 4) != 'Usr_' && $this->_conf[$k][self::VAL_TYP])
		            $v = intval($v);
			    $this->_conf[$k][self::VAL_ORG] = $this->_conf[$k][self::VAL_CURR] = $v;
		    }
		}

		// set max. execution timer to 5 minutes
		@set_time_limit($this->_conf[self::EXECUTION][self::VAL_ORG]);

		// set socket timeout
		@ini_set('default_socket_timeout', strval($this->_conf[self::SOCKET_TIMEOUT][self::VAL_ORG]));

		// configure error logging
		if (class_exists('syncgw\\lib\\ErrorHandler')) {

			ErrorHandler::resetReporting();
			ErrorHandler::filter(E_WARNING, '', 'unlink');
		}

		// rewrite config file?
		if ($conf && $this->_conf[self::DATABASE][self::VAL_ORG]) {

			self::updVar(self::DATABASE, $this->_conf[self::DATABASE][self::VAL_ORG]);
			self::saveINI();
		}
	}

	/**
	 * 	Save configuration table
	 *
	 * 	@return - true=Ok or false=Error
	 */
	public function saveINI(): bool {

		// create new .ini file
		$wrk  = ';<?php die(); ?>'."\n";

   		foreach ($this->_conf as $k => $v) {

   			if (!$v[self::VAL_SAVE] || !isset($v[self::VAL_CURR]))
       			continue;
   			$wrk .= $k.' = "'.$v[self::VAL_CURR].'"'."\n";
		}

		if (file_exists($this->Path) && !is_writeable($this->Path)) {

			Log::getInstance()->logMsg(Log::WARN, 10701, $this->Path);
			return false;
		}

		if (!file_put_contents($this->Path, $wrk)) {

			Log::getInstance()->logMsg(Log::WARN, 10702, $this->Path);
			return false;
		}

		return true;
	}

	/**
	 * 	Get configuration parameter
	 *
	 * 	@param 	- Parameter name or null for complete active configuration
	 * 	@param 	- true=Get original value; false=Get current (default)
	 * 	@return - Configuration value or null
	 */
	public function getVar(?string $id, bool $org = false) {

		// get definitions?
		if (!$id) {

			$a = [];
			foreach ($this->_conf as $k => $v) {

				if ($v[self::VAL_NAME])
					$a['Config::'.$v[self::VAL_NAME]] = $this->_conf[$k][self::VAL_CURR];
			}
			Msg::InfoMsg($a, 'Get configuration definition array()');
			return $a;
		}

		if (!isset($this->_conf[$id][$org ? self::VAL_ORG : self::VAL_CURR])) {

			// no defaults for user defined variables
			if (substr($id, 0, 4) == 'Usr_')
				return null;

			if (!isset($this->_conf[$id])) {

				Log::getInstance()->logMsg(Log::WARN, 10706, $id);
				return null;
			}
			Msg::InfoMsg('['.$id.'] = "'.$this->_conf[$id][self::VAL_DEF].'"');
			return $this->_conf[$id][self::VAL_DEF];
		}

		return $this->_conf[$id][$org ? self::VAL_ORG : self::VAL_CURR];
	}

	/**
	 * 	Update configuration variable
	 *
	 * 	@param 	- Parameter name
	 * 	@param 	- New value
	 * 	@return - Current value
	 */
	public function updVar(string $id, $val) {

		$old = isset($this->_conf[$id]) ? $this->_conf[$id][self::VAL_CURR] : $val;

 		// allow user defined variables
		if (substr($id, 0, 4) == 'Usr_') {

			$this->_conf[$id][self::VAL_CURR] = $val;
			$this->_conf[$id][self::VAL_SAVE] = 1;
			Msg::InfoMsg('['.$id.'] = "'.$val.'"');
			return $old;
		}

		if (!isset($this->_conf[$id])) {

			Log::getInstance()->logMsg(Log::WARN, 10707, $id);
			return $old;
		}

		// check parameter
		if (count($this->_conf[$id][self::VAL_POSS])) {

			$f = false;
			foreach ($this->_conf[$id][self::VAL_POSS] as $v) {

				if ($val == $v) {

					$this->_conf[$id][self::VAL_CURR] = $this->_conf[$id][self::VAL_TYP] ? intval($val) : $val;
					$f = true;
					break;
				}
			}
			if (!$f) {

				Log::getInstance()->logMsg(Log::WARN, 10708, $val, $id);
				return $old;
			}
		} else
			$this->_conf[$id][self::VAL_CURR] = $this->_conf[$id][self::VAL_TYP] ? intval($val) : $val;

		// set execution time?
		if ($id == self::EXECUTION)
			set_time_limit($this->_conf[$id][self::VAL_CURR]);

		return $old;
	}

}
