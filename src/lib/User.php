<?php
declare(strict_types=1);

/*
 * 	User handler class
 *
 * 	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

/**
 * 	Variables used in session object:
 *
 *  <GUID/>                 		User name
 *  <LUID/>							Internal user ID
 *  <SyncStat/>						DataStore::STAT_OK
 *  <Group/>						Record group: ''
 *  <Type/>				    		Record type: DataStore::TYP_DATA
 *  <Created/>						Time of record creation
 *  <LastMod/>			    		Time of last modification
 *  <Data>
 *
 *  ==== General configuration parameter ================================================================================================================
 *
 *   <Logins/>			        	Login counter
 *   <Banned/>			        	User is banned
 *   <ActiveDevice/>	        	Active device name
 *   <EMailPrime/>					Primary e-Mail address
 *
 *   <Device>			        	Device information (created dynamically)
 *    <DeviceId/>			        Device name
 *    ...							Additional device specific parameter (see below)
 *   </Device>
 *
 *   ==== Configuration parameter which can be manually modified =========================================================================================
 *
 *   <EMailSec/>					Secondary e-Mail address (may be multiple)
 *   <SendDisabled> 				Disable client send mail messages feasibility (0-Allowed/1-Forbidden)
 *   								Defaults to "0"
 *   <AccountName/>					The account name for the contact
 *   								Defaults to "010000xxxx000000-nn"
 *   								(x = <LUID> in hex., n = <GUID>)
 *
 *   <DisplayName/>					Display name of the user associated with the given account (e.g. Full name)
 *   								Defaults to "sync*gw user"
 *   <SMTPLoginName/>				The SMTP account name. Defaults to <EMailPrime>
 *   <IMAPLoginName/>				The IMAP account name. Defaults to <EMailPrime>
 *   <Photo/>						The user photography. Defaults to source/syncgw.png
 *
 *  ==== DAV configuration parameter =====================================================================================================================
 *
 *   <Device>			        	Device information (created dynamically)
 *	  <SyncKey ID=""/>				Synchronization key (GID=Group iD)
 *   </Device>
 *
 *  ==== ActiveSync configuration parameter ==============================================================================================================
 *
 *   <OutOfOffice>					Out of office message
 *    <Time/> 						Start time (unix).'/'.End time (unix) or null for global property
 *	  <State/>						0 - The Oof property is disabled
 *									1 - The Oof property is enabled
 *    <Message>
 *     <Audience/>					1 - Internal
 *   								2 - Known external user
 *   								3 - Unknown external user
 *	   <Text TYP="TEXT">			Message text (or null)
 *	   <Text TYP="HTML">			Message text (or null)
 *    <Message/>
 *	 </OutOfOffice>
 *	 <FreeBusy>						Free / busy array
 *									0 Free
 *									1 Tentative
 *									2 Busy
 *									3 Out of Office (OOF)
 *									4 No data
 *	  <Slot/>						start time (unix).'/'.end time (unix).'/'.type
 *	 </FreeBusy>
 *
 *   <Device>			        	Device information (created dynamically)
 *	  <SyncKey ID=""/>				Synchronization key (GID=Group iD)
 *    <DataStore>
 *     <HandlerID/>		        	Handler ID
 *     <Ping>   		    		<Ping> folder
 *      <Group/>					Group ID - see activesync/masHandler.php:PingStat()
 *     </Ping>
 *     <Sync>						Cached <Sync> request
 *      <Group/>					Group ID - see activesync/masSync.php
 *     </Sync>
 *     <Search> 					Cached search record information - see activesync/Handler.php:SearchId()
 *      <Record>					$hid.'/'.$grp.'/'.$gid (LongId)
 *     </Search>
 *     <MoveAlways>					Whether to move the specified conversation, including all future emails
 * 									in the conversation, to the folder specified by the <DstFldId> element
 * 									(Destination <GUID>)
 * 		<CID Int="xx" Ext="">		<ConversationId> (Int= Internal folder <GUID>; Ext=External folder <GUID>)
 * 	   </ModeAlways>
 *    </DataStore>
 *   </Device>
 *
 *  </Data>
 */

namespace syncgw\lib;

class User extends XML {

    /**
     * 	Singleton instance of object
     * 	@var User
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): User {

		if (!self::$_obj) {

            self::$_obj = new self();

			// set log message codes 11001-11100
			Log::getInstance()->setLogMsg([
					11001 => 'User \'%s\' connected from \'%s\' invalid password',
					11002 => 'User \'%s\' connected from \'%s\' authorized',
			        11003 => 'User \'%s\' is banned',
	 		]);

			// register shutdown function
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

		// object modified?
		if (self::$_obj->updObj() && self::$_obj->getVar('GUID')) {

			DB::getInstance()->Query(DataStore::USER, DataStore::UPD, self::$_obj);
			self::$_obj->updObj(-1);
		}

		self::$_obj = null;
	}

	/**
	 * 	Log in user
	 *
	 * 	@param	- User name
	 * 	@param	- Password
	 * 	@param	- Device name
	 * 	@return	- true = Ok; false =E rror
	 */
	public function Login(string $uid = null, string $upw = null, ?string $devname = null): bool {

		// could we catch user id and password?
		if (!$uid || !$upw)
			return false;

		$unam = $uid;

		// check for e-mail syntax
		if (strpos($uid, '@'))
			list($uid, $host) = explode('@', $uid);
		else
			$host = '';

		$log = Log::getInstance();
		$db  = DB::getInstance();
		$cnf = Config::getInstance();

		Msg::InfoMsg('Login "'.$uid.'" with password "'.$upw.'" from host "'.$host.'" and device "'.
				   ($devname ? $devname : 'localhost').'"');

		// load user data
		if (!self::loadUsr($uid, $host, $devname))
			return false;

		// perform login
		if (!$upw || !$db->Authorize($uid, $host, $upw)) {

			if ($upw)
    			$log->logMsg(Log::WARN, 11001, $unam, $devname);
		    return false;
 		}

		// update login counter
		$n = parent::getVar('Logins');
		parent::setVal(strval($n + 1));

		// first time login?
	    if ($cnf->getVar(Config::HANDLER) != 'GUI')
   			$log->logMsg(Log::INFO|Log::ONETIME, 11002, $unam, $devname);

		// is device changed?
		if (!$devname || ($act = parent::getVar('ActiveDevice')) == $devname) {

			Msg::InfoMsg('Device not changed');
			return true;
		}
		$act; // disable Eclipse warning

		Msg::InfoMsg('Device changed from "'.$act.'" to "'.$devname.'"');

		// locate "new" device
		if (parent::xpath('//Device[DeviceId="'.$devname.'"]/.')) {

			parent::getItem();
			return true;
		}

	    // create new entry for device
		parent::getVar('Data');
		parent::addVar('Device');
		parent::addVar('DeviceId', $devname);
		$p = parent::savePos();
	    foreach (Util::HID(Util::HID_CNAME, DataStore::DATASTORES, true) as $hid => $unused) {

	        parent::addVar('DataStore');
	        parent::addVar('HandlerID', strval($hid));
			parent::addVar('Ping', '');
	        parent::addVar('Sync', '');
	        parent::addVar('Search', '');
	        parent::addVar('MoveAlways', '');
	    	parent::restorePos($p);
	    }
	    $unused; // disable Eclipse warning

	    parent::updVar('ActiveDevice', $devname);
       	parent::xpath('//Device[DeviceId="'.$devname.'"]/.');
       	parent::getItem();

       	Msg::InfoMsg($this, 'New device "'.$devname.'" assigned to user');

		return true;
	}

	/**
	 * 	Load user data (or create new one), load assignd or default device
	 *
	 * 	@param	- User name
	 * 	@param  - Host name
	 * 	@param	- Device name
	 * 	@return	- true = Ok; false = Error
	 */
	public function loadUsr(string $uid, ?string $host = null, ?string $devname = null): bool {

		// user ID available?
		if (!$uid)
		    return false;

		// normalize user name
		if (strpos($uid, '@') !== false)
			list($uid, ) = explode('@', $uid);

		// check for banned user
		if (parent::getVar('Banned')) {

			Log::getInstance()->logMsg(Log::WARN, 11003, $uid);
		    return false;
		}

		// already loaded?
		if (parent::getVar('GUID') != $uid) {

		    $db = DB::getInstance();

    		// load user data
	       	if (!($doc = $db->Query(DataStore::USER, DataStore::RGID, $uid))) {

	       		// disable record tracing
	       		$cnf = Config::getInstance();
	       		$mod = $cnf->updVar(Config::TRACE, Config::TRACE_OFF);

    			// get new internal user ID
    			$uno = 1;
    			foreach ($db->Query(DataStore::USER, DataStore::RIDS, '') as $id => $unused) {

    				$doc = $db->Query(DataStore::USER, DataStore::RGID, $id);
    				if (($v = $doc->getVar('LUID')) >= $uno)
    					$uno = $v + 1;
    			}
				$unused; // disable Eclipse warning

				// re-enable trace processing
				$cnf->updVar(Config::TRACE, $mod);

				// create default picture
    			$att = Attachment::getInstance();
    			$pic = $att->create(file_get_contents($cnf->getVar(Config::ROOT).'/core-bundle/assets/syncgw.jpg'));

				// create user object
    			parent::loadXML(
    				'<syncgw>'.
    					'<GUID>'.$uid.'</GUID>'.
    					'<LUID>'.$uno.'</LUID>'.
    					'<SyncStat>'.DataStore::STAT_OK.'</SyncStat>'.
    					'<Group/>'.
    					'<Type>'.DataStore::TYP_DATA.'</Type>'.
    					'<LastMod>'.time().'</LastMod>'.
    					'<Created>'.time().'</Created>'.
    					'<CRC/>'.
					    '<extID/>'.
						'<extGroup/>'.
						'<Data>'.
    				      '<Logins>0</Logins>'.
    					  '<Banned>0</Banned>'.
    					  '<SendDisabled>0</SendDisabled>'.
    					  '<AccountName>'.str_replace('xxxx', sprintf('%04X', $uno), '010000xxxx000000-'.$uid).'</AccountName>'.
    					  '<DisplayName>'.$uid.'</DisplayName>'.
    					  '<SMTPLoginName/>'.
    					  '<IMAPLoginName/>'.
    					  '<EMailPrime/>'.
    					  '<Photo>'.$pic.'</Photo>'.
     					  '<ActiveDevice/>'.
    					  '<FreeBusy/>'.
    					'</Data>'.
    				'</syncgw>'
    			);

    			// save user - it's required here because in follow up we only use DataStore::UPD
       			$db->Query(DataStore::USER, DataStore::ADD, $this);
	       	} else
    			parent::loadXML($doc->saveXML());
		}

		if ($host && !parent::getVar('EMailPrime'))
			parent::updVar('EMailPrime', $uid.'@'.$host);

		if (!parent::getVar('SMTPLoginName'))
			parent::updVar('SMTPLoginName', parent::getVar('EMailPrime'));

		if (!parent::getVar('IMAPLoginName'))
			parent::updVar('IMAPLoginName', parent::getVar('EMailPrime'));

		// load device
		if (!$devname)
			$devname = parent::getVar('ActiveDevice');

		// activate device
		if (Config::getInstance()->getVar(Config::DBG_SCRIPT) != 'User') {

			if ($devname)
    			Device::getInstance()->actDev($devname);
		}

		$gid = parent::getVar('GUID');
		$lid = parent::getVar('LUID');
		parent::getVar('syncgw');
		if (Config::getInstance()->getVar(Config::DBG_SCRIPT) != 'Document')
			Msg::InfoMsg($this, 'User "'.$gid.'" loaded with id "'.$lid.'" for device "'.$devname.'"');

		return true;
	}

	/**
	 * 	Add / update Out-of-Office records
	 * 	Expired record will automatically be deleted
	 *
	 * 	@param  - 0= Delete property; 1= Enable Oof property; 2= Disable Oof property
	 * 	@param 	- 1= Internal, 2= Known external user, 3=´ Unknown external
	 * 	@param 	- Start Unix time stamp.'/'.End Unix time stamp (or null for global property)
	 * 	@param 	- Text message (or null)
	 * 	@param 	- HTML message (or null)
	 */
	public function setOOF(int $mod, int $audience, ?string $slot = null, ?string $text = null, ?string $html = null): void {

		// check property
    	if (!parent::xpath('//OutOfOffice[Time="'.($slot ? $slot : '').'"]/.')) {

			parent::getVar('Data');
    		parent::addVar('OutOfOffice');
    	} else
    		parent::getItem();

    	// delete entry?
    	if (!$mod) {
    		parent::delVar();
    		return;
    	}

		parent::updVar('Time', ($slot ? $slot : ''), false);
		parent::updVar('State', $mod == 1 ? '1' : '0', false);

		parent::xpath('Message[Audience="'.$audience.'"]/Text/.');
		if (!parent::getItem()) {

			parent::addVar('Message');
			parent::addVar('Audience', strval($audience));
			parent::addVar('Text', strval($text), false, [ 'TYP' => 'TEXT' ]);
			parent::addVar('Text', strval($html), false, [ 'TYP' => 'HTML' ]);
		} else {

			while (parent::getItem() !== null) {

				if (parent::getAttr('TYP') == 'TEXT')
					parent::setVal(isnull($text) ? '' : $text);
				else
					parent::setVal(isnull($html) ? '' : $html);
			}
		}
 	}

	/**
	 * 	Add / update synchronization key
	 *
	 *	@param 	- GUID
	 *  @param 	- 0 = return value only; n = update value for synckey
	 * 	@return	- Value
	 */
	public function syncKey(string $gid, int $upd = 0): string {

		$n = ($n = parent::getVar('ActiveDevice')) ? '//Device[DeviceId="'.$n.'"]' : '//Data';
		if (!parent::xpath($n.'/SyncKey[@ID="'.$gid.'"]')) {

			parent::xpath($n);
			parent::getItem();
			parent::addVar('SyncKey', $old = '0', false, [ 'ID' => $gid ]);
		} else
			$old = parent::getItem();

		if ($upd)
			parent::setVal($old = strval($old + $upd));

		return $old;
	}

}
