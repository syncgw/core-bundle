<?php
declare(strict_types=1);

/*
 *  Data base interface class
 *
 *	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

/**
 * 	Variables used in document object:
 *
 *  <GUID/>			Server unique ID
 *  <LUID/>			Client unique ID
 *  <SyncStat/>		Synchronization status
 *  <Group/>		ID of group record
 *  <Type/>			Record type
 *  <Created/>		Time when record was created
 *  <LastMod/>		Time of last modification
 *  <CRC/>			CRC value of data
 *  <extID/>		External record ID
 *  <extGroup/>		External ID of group record
 *  <Data/>			Record data
 */

namespace syncgw\lib;

use syncgw\interface\DBintHandler;
use syncgw\interface\DBextHandler;
use syncgw\document\field\fldColor;
use syncgw\document\field\fldDescription;
use syncgw\document\field\fldGroupName;

use syncgw\document\field\fldAttribute;

class DB implements DBintHandler, DBextHandler {

	// operation description
	const OPS 			 = [
				DataStore::ADD 		=> 'ADD',
				DataStore::UPD     	=> 'UPD',
				DataStore::DEL	 	=> 'DEL',
				DataStore::RGID 	=> 'RGID',
				DataStore::RLID 	=> 'RLID',
				DataStore::GRPS		=> 'GRPS',
				DataStore::RIDS		=> 'RIDS',
				DataStore::RNOK 	=> 'RNOK',
	];

	/**
	 * 	Data base handler
	 * 	@var DB
	 */
	private $_db		 = null;

    /**
     * 	Singleton instance of object
     * 	@var DB
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): DB {

		if (!self::$_obj) {

          	self::$_obj = new self();

			// set log message codes 10801-10900
			Log::getInstance()->setLogMsg([

				// error messages
				10801 => 'External handler class \'%s\' not available',
				10805 => '%s',

				// debug messages
			    10802 => 'Add %s %s [%s] for user (%s)',
				10803 => 'Upd %s %s [%s] for user (%s)',
				10804 => 'Del %s %s [%s] for user (%s)',
			]);

			// any back end available?
			if (!($be = Config::getInstance()->getVar(Config::DATABASE)))
				return self::$_obj;

			// check data base
			if (!class_exists($class = 'syncgw\\interface\\'.$be.'\\Handler')) {

				ErrorHandler::getInstance()->Raise(10801, $class);
	            return self::$_obj;
	        }

 	        // connect data base
			$class = 'syncgw\\interface\\'.$be.'\\Handler';
			self::$_obj->_db = $class::getInstance();

			// register shutdown function
			if (class_exists('syncgw\\lib\Server'))
				Server::getInstance()->regShutdown(__CLASS__);
		}

		return self::$_obj;
	}

	/**
	 * 	Shutdown function
	 */
	public function delInstance(): void {

		self::$_obj = null;
	}

    /**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
     *	@param 	- true = Provide status information only (if available)
	 */
	public function getInfo(XML &$xml, bool $status): void {

  		$xml->addVar('Name', 'Data base interface handler');
	}

	/**
	 * 	Authorize user in external data base
	 *
	 * 	@param	- User name
	 * 	@param 	- Host name
	 * 	@param	- User password
	 * 	@return - true=Ok; false=Not authorized
 	 */
	public function Authorize(string $user, string $host, string $passwd): bool {

		Msg::InfoMsg('Authorize user "'.$user.'" from "'.$host.'" with password "'.$passwd.'"');

		// handler allocated?
		if (!$this->_db)
			return false;

		// is external authorization available?
		if (!method_exists($this->_db, 'Authorize'))
			return true;

		return $this->_db->Authorize($user, $host, $passwd);
	}

	/**
	 * 	Perform query on internal data base
	 *
	 * 	@param	- Handler ID
	 * 	@param	- Query command:<fieldset>
	 * 			  DataStore::ADD 	  Add record                             $parm= XML object<br>
	 * 			  DataStore::UPD 	  Update record                          $parm= XML object<br>
	 * 			  DataStore::DEL	  Delete record or group (inc. sub-recs) $parm= GUID<br>
	 * 			  DataStore::RLID     Read single record                     $parm= LUID<br>
	 * 			  DataStore::RGID     Read single record       	             $parm= GUID<br>
	 * 			  DataStore::GRPS     Read all group records                 $parm= None<br>
	 * 			  DataStore::RIDS     Read all records in group              $parm= Group ID or '' for record in base group<br>
	 * 			  DataStore::RNOK     Read recs with SyncStat != STAT_OK     $parm= Group ID
	 * 	@return	- According  to input parameter<fieldset>
	 * 			  DataStore::ADD 	  New record ID or false on error<br>
	 * 			  DataStore::UPD 	  true=Ok; false=Error<br>
	 * 			  DataStore::DEL	  true=Ok; false=Error<br>
	 * 			  DataStore::RLID     XML object; false=Error<br>
	 * 			  DataStore::RGID	  XML object; false=Error<br>
	 * 			  DataStore::RIDS     [ "GUID" => Typ of record ]<br>
	 * 			  DataStore::GRPS	  [ "GUID" => Typ of record ]<br>
	 * 			  DataStore::RNOK     [ "GUID" => Typ of record ]
	 */

	/**
	 * 	Perform query on external data base
	 *
	 * 	@param	- Handler ID
	 * 	@param	- Query command:<fieldset>
	 * 			  DataStore::ADD 	  Add record                             $parm= XML object<br>
	 * 			  DataStore::UPD 	  Update record                          $parm= XML object<br>
	 * 			  DataStore::DEL	  Delete record or group (inc. sub-recs) $parm= GUID<br>
	 * 			  DataStore::RGID     Read single record       	             $parm= GUID<br>
	 * 			  DataStore::GRPS     Read all group records                 $parm= None<br>
	 * 			  DataStore::RIDS     Read all records in group              $parm= Group ID or '' for record in base group
	 * 	@return	- According  to input parameter<fieldset>
	 * 			  DataStore::ADD 	  New record ID or false on error<br>
	 * 			  DataStore::UPD 	  true=Ok; false=Error<br>
	 * 			  DataStore::DEL	  true=Ok; false=Error<br>
	 * 			  DataStore::RGID	  XML object; false=Error<br>
	 * 			  DataStore::GRPS	  [ "GUID" => Typ of record ]<br>
	 * 			  DataStore::RIDS     [ "GUID" => Typ of record ]
	 */
	public function Query(int $hid, int $cmd, $parm = '') {

		$rc = ($cmd & (DataStore::RIDS|DataStore::RNOK)) ? [] : false;

		// any back end handler allocated?
		if (!$this->_db)
			return $rc;

		$cnf = Config::getInstance();
		$log = Log::getInstance();

		// check command
		if (!($hid & DataStore::EXT)) {
			switch ($cmd) {
			case DataStore::DEL:

				// delete whole group?
				if ($xml = self::Query($hid, DataStore::RGID, $parm)) {
		   	        if ($xml->getVar('Type') == DataStore::TYP_GROUP) {
						$stat = $cnf->updVar(Config::TRACE, Config::TRACE_OFF);
						foreach ($this->_db->Query($hid, DataStore::RIDS, $parm) as $gid => $unused)
							if ($gid != $parm)
								self::Query($hid, $cmd, $gid);
						$cnf->updVar(Config::TRACE, $stat);
					}
				}
				$unused; // disable Eclipse warning

			case DataStore::RGID:
			case DataStore::RLID:
			    $rc = $this->_db->Query($hid, $cmd, $parm);
		    	break;

			case DataStore::ADD:
			case DataStore::UPD:

				// add CRC value
				$parm->updVar('CRC', self::mkCRC($parm));

				// set last time modified
	   	        $parm->updVar('LastMod', $tme = strval(time()));

	   	        // does document is a group?
		    	if ($hid & DataStore::DATASTORES) {
		   	        $stat = $cnf->updVar(Config::TRACE, Config::TRACE_OFF);
		    		if ($gid = $parm->getVar('Group')) {
						if ($xml = $this->_db->Query($hid, DataStore::RGID, $gid)) {
	                    	$xml->updVar('LastMod', $tme);
	                        $this->_db->Query($hid, DataStore::UPD, $xml);
	                    }
				    }
					$cnf->updVar(Config::TRACE, $stat);
		    	}

			// case DataStore::RNOK
			// case DataStore::GRPS
		    // case DataStore::RIDS
			default:

				if (($rc = $this->_db->Query($hid, $cmd, $parm)) && ($cmd & (DataStore::RIDS|DataStore::RNOK)))
	    			ksort($rc, SORT_NATURAL);
	    		break;
			}

		} else {

			if ($cmd & DataStore::RNOK) {
				Msg::ErrMsg('Unsupported external query command '.sprintf('%04X', $cmd));
				return false;
			}

		    // perform data base record operation
			$rc = $this->_db->Query($hid, $cmd, $parm);
		}

		if ($cnf->getVar(Config::TRACE) == Config::TRACE_OFF)
			return $rc;

		if ($cmd & (DataStore::RGID|DataStore::RLID) && is_object($rc) && !($hid & DataStore::SESSION))
			Trace::getInstance()->Save($hid, $rc);
		elseif ($cmd & (DataStore::ADD|DataStore::UPD|DataStore::DEL) && $rc) {
			$typ = [
						DataStore::TYP_DATA  => [
						DataStore::USER			=> 'user record',
						DataStore::TRACE		=> 'trace record',
						DataStore::SESSION		=> 'session record',
						DataStore::DEVICE		=> 'device record',
						DataStore::ATTACHMENT 	=> 'attachment record',
						DataStore::CONTACT 		=> 'contact record',
						DataStore::CALENDAR		=> 'calendar record',
						DataStore::NOTE			=> 'note record',
						DataStore::TASK			=> 'task record',
						DataStore::MAIL 		=> 'mail',
						DataStore::SMS 			=> 'sms record',
						DataStore::DOCLIB 		=> 'DocLib record',
					],
						DataStore::TYP_GROUP => [
						DataStore::USER			=> 'user',
						DataStore::TRACE		=> 'trace',
						DataStore::SESSION		=> 'session',
						DataStore::ATTACHMENT 	=> 'attachment',
						DataStore::DEVICE		=> 'device',
						DataStore::CONTACT 		=> 'address book',
						DataStore::CALENDAR		=> 'calendar',
						DataStore::NOTE			=> 'notes',
						DataStore::TASK			=> 'task list',
						DataStore::MAIL 		=> 'mail box',
						DataStore::SMS 			=> 'sms',
						DataStore::DOCLIB 		=> 'DocLib',
					],
			];
			if (!($usr = User::getInstance()->getVar('GUID')))
	    		$usr = 'system';
			$mod = $hid & DataStore::EXT ? 'external' : 'internal';

			switch ($cmd) {
			case DataStore::ADD:
				$rid = $hid & DataStore::EXT ? $parm->getVar('extID') : $parm->getVar('GUID');
				$t   = substr($parm->getVar('Type'), 0, 1);
				if ($t != DataStore::TYP_DATA && $t != DataStore::TYP_GROUP)
					$t = DataStore::TYP_DATA;
				$log->logMsg(Log::DEBUG, 10802, $mod, $typ[$t][$hid & ~DataStore::EXT], $rid, $usr);
				break;

			case DataStore::UPD:
				$rid = $hid & DataStore::EXT ? $parm->getVar('extID') : $parm->getVar('GUID');
				$t   = substr($parm->getVar('Type'), 0, 1);
				if ($t != DataStore::TYP_DATA && $t != DataStore::TYP_GROUP)
					$t = DataStore::TYP_DATA;
				$log->logMsg(Log::DEBUG, 10803, $mod, $typ[$t][$hid & ~DataStore::EXT], $rid, $usr);
				break;

			case DataStore::DEL:
				$t = substr(strval($parm), 0, 1);
				if ($t != DataStore::TYP_DATA && $t != DataStore::TYP_GROUP)
					$t = DataStore::TYP_DATA;
				$log->logMsg(Log::DEBUG, 10804, $mod, $typ[$t][$hid & ~DataStore::EXT], $parm, $usr);
				break;
			}
		}

		return $rc;
	}

	/**
	 * 	Excute raw SQL query on internal data base
	 *
	 * 	@param	- SQL query string
	 * 	@return	- Result string or []; null on error
	 */
	public function SQL(string $query) {

		return $this->_db->SQL($query);
	}

	/**
	 * 	Get error handler status code
	 *
	 * 	@return - Status message or ''
	 */
	public function getStatus(): string {

		return $this->_db->getStatus();
	}

	/**
	 * 	Reload any cached record information in external data base
	 *
	 * 	@param	- Handler ID
	 * 	@return	- true=Ok; false=Error
	 */
	public function Refresh(int $hid): bool {

		return $this->_db->Refresh($hid);
	}

	/**
	 * 	Check trace record references
	 *
	 *	@param 	- Handler ID
	 * 	@param 	- External record array [ GUID ]
	 * 	@param 	- Mapping table [HID => [ GUID => NewGUID ] ]
	 */
	public function chkTrcReferences(int $hid, array $rids, array $maps): void {

		// reassign / check external record references
		if (!($hid & DataStore::EXT)) {

			$db = DB::getInstance();

			foreach ($rids as $rid) {

				$xml = $db->Query($hid, DataStore::RGID, $rid);
				$chg = 0;

				if (isset($maps[$hid|DataStore::EXT][$id = $xml->getVar('extID')])) {
					if ($ngid = $maps[$hid|DataStore::EXT][$id]) {
						$xml->setVal($ngid);
						Msg::InfoMsg('['.$rid.'] Updating <extID> from ['.$id.'] to ['.$ngid.']');
						$chg = 1;
					}
				}
				if (isset($maps[$hid|DataStore::EXT][$id = $xml->getVar('extGroup')])) {
					if ($ngid = $maps[$hid|DataStore::EXT][$id]) {
						$xml->setVal($ngid);
						Msg::InfoMsg('['.$rid.'] Updating <extGroup> from ['.$id.'] to ['.$ngid.']');
						$chg = 1;
					}
				}

				if ($chg)
					$db->Query($hid, DataStore::UPD, $xml);
			}
		} else
			$this->_db->chkTrcReferences($hid, $rids, $maps);
	}

	/**
	 * 	Get list of supported fields in external data base
	 *
	 * 	@param	- Handler ID
	 * 	@return	- [ field name ]
	 */
	public function getflds(int $hid): array {

		return method_exists($this->_db, 'getflds') ? $this->_db->getflds($hid) : [];
	}

	/**
	 * 	Convert internal record to MIME
	 *
	 * 	@param	- Internal document
	 * 	@return - MIME message or null
	 */
	public function cnv2MIME(XML &$int): ?string {

		return $this->_db->cnv2MIME($int);
	}

	/**
	 * 	Convert MIME string to internal record
	 *
	 *	@param 	- External record id
	 * 	@param	- MIME message
	 * 	@return	- Internal record or null
	 */
	public function cnv2Int(string $rid, string $mime): ?XML {

		return $this->_db->cnv2Int($rid, $mime);
	}

	/**
	 * 	Send mail
	 *
	 * 	@param	- true=Save in Sent mail box; false=Only send mail
	 * 	@param	- MIME data OR XML document
	 * 	@return	- Internal XML document or null on error
	 */
	public function sendMail(bool $save, $doc): ?XML {

		return $this->_db->SendMail($save, $doc);
	}

	/**
	 * 	Load all records IDs from a data store
	 *
	 * 	@param 	- Handler ID
	 * 	@param	- Optional group name     (default: base group)
	 *  @param  - Optional recursive flag (default: true)
	 *  @param  - Optional query modus    (default: DataStore::RIDS)
	 * 	@return - [ record ID => record type ]
	 */
	public function getRIDS(int $hid, string $grp = '', bool $sub = true, int $qry = DataStore::RIDS): array {

		$ids = [];
		$chk = [];

		// build list of groups to check
		if ($grp) {
			$chk[$grp] = 1;
			// read group to ensure it is stored in trace
			self::Query($hid, DataStore::RGID, $grp);
		} elseif ($sub && ($chk = self::Query($hid, DataStore::GRPS)) === false)
			$chk = [];

		// read all records?
		if (!$grp)
			$ids += $chk;

		// check groups
		foreach ($chk as $grp => $unused)
			if (($rc = self::Query($hid, $qry, $grp)) !== false)
				$ids += $rc;
			else
				Msg::ErrMsg('Failed to read ['.$grp.']');

		$unused; // disable Eclipse warning

		// if do not have any group, we go for single records
		if (!count($chk))
			if (($rc = self::Query($hid, $qry)) !== false)
				$ids += $rc;

		return $ids;
	}

	/**
	 * 	Create CRC value for document
	 *
	 * 	@param  - XML Document
	 * 	@return - CRC value
	 */
	public function mkCRC(XML &$doc): string {

		// add CRC value to record
		if ($str = preg_replace('|(.*<Data.*>)(.*)(</Data.*w>)|sU', '$2', $doc->saveXML(true, true))) {
    	    $arr = explode("\n", $str);
            // we need to sort, since it may happen tags arrives in unsorted order
            sort($arr);
        	$str = implode('', $arr);
    	}

		return Util::Hash(strval($str));
	}

	/**
	 * 	Create new "GUID"
	 *
	 *  @param  - Handler ID
	 *  @param 	- Create new empty record
	 * 	@return	- "GUID" or null on error
	 */
	public function mkGUID(int $hid, bool $create = false): ?string {

		$gid = 1;
		// read all records for all user in this datastore - we don't need to read
		// all datastores, because we use different prefixes for every datastore
		foreach (self::getRIDS($hid) as $id => $unused) {
			if (substr($id, 1) >= $gid)
				$gid = substr($id, 1) + 1;
		}
		$unused; // disable Eclipse warning

	    // add data store prefix
        $gid = Util::HID(Util::HID_PREF, $hid, true).$gid;

        if ($create) {
        	$xml = new XML();
        	$xml->loadXML('<syncgw>'.
				'<GUID>'.$gid.'</GUID>'.
				'<LUID/>'.
				'<SyncStat>'.DataStore::STAT_OK.'</SyncStat>'.
				'<Type/>'.
	 			'<Group/>'.
				'<LastMod>'.time().'</LastMod>'.
				'<Created>'.time().'</Created>'.
				'<extID/>'.
				'<extGroup/>'.
				'<CRC/>'.
				'<Data/>'.
			   '</syncgw>');
        	self::Query($hid, DataStore::ADD, $xml);
        }

		return $gid;
	}

	/**
	 * 	Create document skeleton
	 *
	 * 	@param	- Handler ID
	 * 	@param  - [
	 * 			  	'GID',				 e.g. A1<br>
	 * 				'LUID',				 e.g. K8272<br>
	 * 				'Typ',				 e.g. DataStore::TYP_DATA (default)<br>
	 * 			  	'Status',			 e.g. DataStore::STAT_OK (default)<br>
	 * 			  	'Group', 			 e.g. <empty> (default)<br>
	 * 				'extID,				 e.g. <empty> (default)<br>
	 * 			  	'extGroup', 		 e.g. <empty> (default)<br>
	 * 	Optional:<br>
	 * 				fldGroupName::TAG  	Name of group: e.g. Default<br>
	 * 			  	fldDescription::TAG	Description: e.g. Default contact group<br>
	 * 			  	fldColor::TAG 		Color: e.g. #47FF<br>
	 * 				fldAttribute::TAG	 	Attributes<br>
	 * 			  ]<br>
	 * 	@param 	- Create new empty record
	 * 	@return - New document skeleton
	 */
	public function mkDoc(int $hid, array $opts = null, bool $create = false): XML {

		if (!is_array($opts))
			$opts = [];

		$gid  = isset($opts['GID']) 		? $opts['GID'] 			: self::mkGUID($hid, $create);
		$lid  = isset($opts['LUID'])  		? $opts['LUID'] 		: '';
		$grp  = isset($opts['Group']) 		? $opts['Group'] 		: '';
		$typ  = isset($opts['Typ']) 		? $opts['Typ'] 			: DataStore::TYP_DATA;
		$stat = isset($opts['Status']) 		? $opts['Status'] 		: DataStore::STAT_OK;
		$xid  = isset($opts['extID'])  		? $opts['extID'] 		: '';
		$xgrp = isset($opts['extGroup']) 	? $opts['extGroup'] 	: '';

		$xml = new XML();
		$str = '<syncgw>'.
				'<GUID>'.$gid.'</GUID>'.
				'<LUID>'.$lid.'</LUID>'.
				'<SyncStat>'.$stat.'</SyncStat>'.
				'<Type>'.$typ.'</Type>'.
	 			'<Group>'.$grp.'</Group>'.
				'<LastMod>'.time().'</LastMod>'.
				'<Created>'.time().'</Created>'.
				'<extID>'.$xid.'</extID>'.
				'<extGroup>'.$xgrp.'</extGroup>'.
				'<CRC/>'.
				'<Data/>'.
			   '</syncgw>';

		$xml->loadXML($str);

		// add optional flags
		$xml->getVar('Data');
		foreach ([  fldGroupName::TAG, 	fldDescription::TAG,
					fldColor::TAG,		fldAttribute::TAG, 	] as $k) {
			if (isset($opts[$k]))
				$xml->addVar($k, strval($opts[$k]));
		}

		if ($create)
			self::Query($hid, DataStore::UPD, $xml);

		return $xml;
	}

	/**
	 * 	Update document synchronization status
	 *
	 *	@param 	- Handler ID
	 * 	@param	- Document to perform action on
	 * 	@param	- New status:<fieldset>
	 *            DataStore::STAT_OK
	 *            DataStore::STAT_ADD
	 *            DataStore::STAT_DEL
	 *            DataStore::STAT_REP
	 *  @param  - true = Recursive; false = Single document
	 *  @param 	- true = Force record writing
	 * 	@return	- true = Ok; false = Error
	 */
	public function setSyncStat(int $hid, XML &$xml, string $stat, bool $recur = false, bool $force = false): bool {

		// get <GUID>
		$gid = $xml->getVar('GUID');

		// is this record a group record?
		if (($typ = $xml->getVar('Type')) == DataStore::TYP_GROUP && $recur) {

		    $rec = new XML();
            foreach (self::Query($hid, DataStore::RIDS, $gid) as $id => $unused) {

                if ($rec = self::Query($hid, DataStore::RGID, $id))
                    self::setSyncStat($hid, $rec, $stat, $recur, $force);
		    }
			$unused; // disable Eclipse warning
		}

		// should we delete existing record?
		if (($is = $xml->getVar('SyncStat')) == DataStore::STAT_DEL && $stat == DataStore::STAT_OK) {

			// be sure to delete folder from ActiveSync <Ping> list
			if ($typ == DataStore::TYP_GROUP && class_exists($class = 'syncgw\\activesync\\masHandler')) {
				$op = $class::DEL;
				$class::getInstance()->PingStat($op, $hid, $gid);
			}

			return self::Query($hid, DataStore::DEL, $gid);
		}

		// force mode?
		if (!$force &&
			// need to change status?
			($stat == $is ||
			// set status (delete cannot be overwritten)
			$is == DataStore::STAT_DEL) ||
			// add cannot be overriden by replace
			($force && $is == DataStore::STAT_ADD && $stat == DataStore::STAT_REP))
				return true;

		// swap status
		Msg::InfoMsg('Setting synchronization status "'.$stat.'" to ['.$gid.']');
		$xml->updVar('SyncStat', $stat);

		// remove existing <LUID> if we "add" record
		if ($stat == DataStore::STAT_ADD)
			$xml->updVar('LUID', '');

		// save document
		return self::Query($hid, DataStore::UPD, $xml);
	}

}
