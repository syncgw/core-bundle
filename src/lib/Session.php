<?php
declare(strict_types=1);

/*
 * 	Session handler class
 *
 *	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

/**
 * 	Variables used in session object:
 *
 *  <GUID/>                 		Global Unique Identified: Server unique session id
 *  <Group/>						Record group: ''
 *  <Type/>				    		Record type: DataStore::TYP_DATA
 *  <Created/>						Time of record creation
 *  <LastMod/>			    		Time of last modification
 *  <Data>
 *
 *  ==== General configuration parameter ================================================================================================================
 *
 *   <OneTimeMessage/>         		One time message per session (used in lib/Log.php)
 *
 *  ==== DAV configuration parameter =====================================================================================================================
 *
 *   <DAVSync/>						1= Datastore syncronization done for this session
 *
 *  ==== ActiveSync configuration parameter ==============================================================================================================
 *
 *   <ItemPart NO=n>				<ItemOperations> for ID (NO=part #)
 *   <BodyPart NO=n/>				<Body> part  for ID (NO=Part #)
 *
 *  </Data>
 */

namespace syncgw\lib;

class Session extends XML {

    /**
     * 	Singleton instance of object
     * 	@var Session
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): Session {

		if (!self::$_obj) {

            self::$_obj = new self();

			// set log message codes 10901-11000
			Log::getInstance()->setLogMsg([
					10901 => 'Session [%s] started',
					10902 => 'Session [%s] restarted',
					10903 => 'Cleanup %d session records',
			]);

			// create new session record
			self::$_obj->loadXML('<syncgw>'.
					'<GUID/>'.
					'<LUID/>'.
					'<SyncStat>'.DataStore::STAT_OK.'</SyncStat>'.
					'<Group/>'.
					'<Type>'.DataStore::TYP_DATA.'</Type>'.
					'<LastMod>'.time().'</LastMod>'.
					'<Created>'.time().'</Created>'.
					'<CRC/>'.
					'<extID/>'.
					'<extGroup/>'.
					'<Data>'.
						'<DAVSync>0</DAVSync>'.
	    			    '<OneTimeMessage/>'.
					'</Data>'.
				'</syncgw>');

			// register shutdown function
			Server::getInstance()->regShutdown(__CLASS__);

		}

		return self::$_obj;
	}

    /**
	 * 	Shutdown function
	 */
	public function delInstance(): void {

		if (self::$_obj && self::$_obj->getVar('GUID'))
			DB::getInstance()->Query(DataStore::SESSION, DataStore::UPD, self::$_obj);

		self::$_obj = null;
	}

    /**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
	 */
	public function getInfo(XML &$xml): void {

		$xml->addVar('Name', 'Session handler');

		$xml->addVar('Opt', 'Session timeout (in seconds)');
		$xml->addVar('Stat', strval(Config::getInstance()->getVar(Config::SESSION_TIMEOUT)));
		$xml->addVar('Opt', 'Data base "Cookie" handler');
		$xml->addVar('Stat', 'Implemented');
	}

	/**
	 * 	Create or restart session
	 *
	 * 	@return	true = Ok; false = Error
	 */
	public function mkSession(): bool {

		// session already existent?
		if ($id = parent::getVar('GUID'))
			return true;

		$http = HTTP::getInstance();
		$cnf  = Config::getInstance();
		$log  = Log::getInstance();
		$db   = DB::getInstance();

        // get session id (e.g. 'MAS-127.0.0.1'
        $id = $cnf->getVar(Config::HANDLER).'-'.$http->getHTTPVar('REMOTE_ADDR');

		// are we debugging?
		if ($cnf->getVar(Config::DBG_LEVEL) == Config::DBG_TRACE &&
			// not already prefix by debug prefix?
			strncmp($id, Config::DBG_PREF, Config::DBG_PLEN) &&
			// no forced trace running?
			!($cnf->getvar(Config::TRACE) & Config::TRACE_FORCE))
			$id = Config::DBG_PREF.$id;

		// create full id

		// load request time
		$t = intval($http->getHTTPVar('REQUEST_TIME'));

		// get session timeout
		$w = intval($cnf->getVar(Config::SESSION_TIMEOUT));
		$t = intval($t / $w);

		// old session id
		$oid = $id.'-'.($t - 1);

		// create new session ID
		$id = $id.'-'.$t;

		// try to load "new" session data
		if ($xml = $db->Query(DataStore::SESSION, DataStore::RGID, $id)) {

			// load record
			parent::loadXML($xml->saveXML());

			return true;
		}

		// check for existing session

		// try to load "old" session
		if ($xml = $db->Query(DataStore::SESSION, DataStore::RGID, $oid)) {

			// load record
			parent::loadXML($xml->saveXML());

			// rewrite new group record
			parent::updVar('GUID', $id);
			parent::updVar('LastMod', strval(time()));

			// create new session record
			$db->Query(DataStore::SESSION, DataStore::ADD, $this);

			// delete old master record
			$db->Query(DataStore::SESSION, DataStore::DEL, $oid);

			return true;
		}

		// set new session ID
		parent::updVar('GUID', $id);
		$db->Query(DataStore::SESSION, DataStore::ADD, $this);

		$log->logMsg(Log::DEBUG, 10901, $id);

		return true;
	}

	/**
	 * 	Create or update session variable
	 *
	 * 	@param	- Variable name
	 * 	@param	- Value; null = Don't change value
	 * 	@param	- Handler ID
	 * 	@return	- Old value; null = If variable is created
	 */
	public function updSessVar(string $name, ?string $val = null, int $hid = 0): ?string {

		// data store specific variable?
		if ($hid) {

			parent::xpath('//DataStore[HandlerID="'.$hid.'"]/.');
			if (parent::getItem() === false)  {

				Msg::WarnMsg('Datastore/HandlerID="'.sprintf('%04X', $hid).'" not found!');
				parent::getVar('Data');
			}
		} else
			parent::getVar('Data');

		if ($val === null) {

			$old = parent::getVar($name, false);
			Msg::InfoMsg('Get session variable "'.$name.'"'.
				($hid ? ' for "'.Util::HID(Util::HID_TAB, $hid).'"' : '').' - value is "'.(is_array($old) ? 'ARRAY()' : $old).'"');
		} else {

			$old = parent::updVar($name, strval($val), false);
			Msg::InfoMsg('Update session variable "'.$name.'" = "'.(is_array($val) ? 'ARRAY()' : $val).'"'.
					   ($hid ? ' for "'.Util::HID(Util::HID_TAB, $hid).'"' : '').' - old value is "'.
					   (is_array($old) ? 'ARRAY()' : $old).'"');
		}

		return $old;
	}

	/**
	 * 	Perform session expiration
	 */
	public function Expiration(): void {

		$cnf = Config::getInstance();

	    // delete expired records
		if (!($tme = $cnf->getVar(Config::SESSION_EXP)))
			return;

		// convert hour to seconds
		$tme *= 3600;

		$cnt = 0;
		$db  = DB::getInstance();

		// delete old session records
		foreach ($db->Query(DataStore::SESSION, DataStore::RIDS) as $gid => $unused) {

			// read record
			if (!($xml = $db->Query(DataStore::SESSION, DataStore::RGID, $gid)))
			    continue;

			// check expiration
			$t = $xml->getVar('LastMod');

			if ($t + $tme < time()) {

				// delete group record
				$db->Query(DataStore::SESSION, DataStore::DEL, $gid);
				$cnt++;
			}
		}
		$unused; // disable Eclipse warining

		if ($cnt > 0)
			Log::getInstance()->logMsg(Log::DEBUG, 10903, $cnt);
	}

}
