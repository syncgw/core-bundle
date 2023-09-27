<?php
declare(strict_types=1);

/*
 * 	Document handler class
 *
 *	@package	sync*gw
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document;

use syncgw\lib\Config;
use syncgw\lib\DB;
use syncgw\lib\DataStore;
use syncgw\lib\Device;
use syncgw\lib\HTTP;
use syncgw\lib\Lock;
use syncgw\lib\Log;
use syncgw\lib\Msg;
use syncgw\lib\Util;
use syncgw\lib\XML;

class docHandler extends XML {

	/**
	 * 	Handler ID
	 * 	@var docHandler
	 */
	protected $_hid;

	/**
	 * 	Supported MIME classes
	 * 	@var array
	 */
	protected $_mimeClass;

	/**
	 *  Construct class
	 */
	protected function _init() {

		// set log message codes 13001-13100
		Log::getInstance()->setLogMsg([

				13001 => 'Unsupported MIME type \'%s\' version \'%s\'',
		        13002 => 'Error exporting record in MIME format \'%s\' version \'%s\'',
		]);
	}

    /**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
     *	@param 	- true = Provide status information only (if available)
	 */
	public function getInfo(XML &$xml, bool $status): void {

		$xml->addVar('Name', sprintf('%s document handler', Util::HID(Util::HID_ENAME, $this->_hid, true)));

		if (!$status) {

			$xml->addVar('Opt', 'Document base handler');

			$xml->addVar('Opt', 'Internal data store name');
			$xml->addVar('Stat', '"'.Util::HID(Util::HID_ENAME, $this->_hid, true).'"');
		} else {

			$xml->addVar('Opt', 'Status');
			$xml->addVar('Stat', (Config::getInstance()->getVar(Config::ENABLED, true)
								& $this->_hid) ? 'Enabled' : 'Disabled');
		}

		foreach($this->_mimeClass as $mime)
			$mime->getInfo($xml, $status);
	}

	/**
	 * 	Import external document into internal and external data store
	 *
	 * 	@param 	- External record
	 * 	@param	- Query modus (DataStore::ADD, DataStore::UPD)
	 * 	@param 	- Group name
	 * 	@param	- LUID
	 * 	@return	- true = Ok; false = Error
	 */
	public function import(XML &$ext, int $qry, string $grp = '', string $luid = ''): bool {

		$db = DB::getInstance();

		$xqry = $qry;
		if ($qry & DataStore::ADD) {

			// create new skeleton
			$doc = $db->mkDoc($this->_hid, [ 'Group' => $grp, 'LUID' => $luid], true );
			$qry = DataStore::UPD;
			parent::loadXML($doc->saveXML());
		}

		// be sure to set proper location in document
		parent::getVar('Data');

		// figure out MIME type
		if ($ext->getVar('ApplicationData', false) !== null) {

			$class = 'syncgw\\activesync\\mime\\mimAs'.Util::HID(Util::HID_TAB, $this->_hid, true);
			$mime = $class::MIME[0];

		} else {

			$data = $ext->getVar('Data');
			if (strpos($data, 'VNOTE')) {

				$class = 'syncgw\\webdav\\mime\\mimv'.Util::HID(Util::HID_TAB, $this->_hid, true);
				$mime  = $class::MIME[0];

			} elseif (strpos($data, 'VCALENDAR') || strpos($data, 'VCARD')) {

				if (strpos($data, 'VTODO'))
					$class = 'syncgw\\webdav\\mime\\mimvTask';
				else
					$class = 'syncgw\\webdav\\mime\\mim'.(strpos($data, 'VCALENDAR') ? 'vCal' : 'vCard');

				$v = substr($data, strpos($data, 'VERSION:') + 8);
				$v = trim(substr($v, 0, strpos($v, "\n")));

				foreach ($class::MIME as $mime)
					if ($mime[1] == $v)
						break;
			} else {

				$class = 'syncgw\\webdav\\mime\\mimPlain';
				$mime  = $class::MIME[0];

			}
		}

		$class = $class::getInstance();
		if (!$class->import($mime[0], floatval($mime[1]), $ext, $this)) {

			Msg::WarnMsg('No MIME type found');
			return false;

		} else
			Msg::InfoMsg('--- Document imported with "'.$mime[0].'" "'.sprintf('%1.1f', $mime[1]).'"');

		// set default group
		if ($grp && !parent::getVar('Group'))
			parent::updVar('Group', $grp);

		// update group?
		if ($qry & DataStore::ADD) {

		    if ($g = $db->Query($this->_hid, DataStore::RGID, $grp))
		    	parent::updVar('extGroup', $g->getVar('extID'));

		} else
			parent::updVar('SyncStat', DataStore::STAT_OK);

		// save/update document in external data store
		if ($qry) {

			if (is_string($xid = $db->Query(DataStore::EXT|$this->_hid, $xqry, $this)))
				$this->updVar('extID', $xid);

			if ($xid === false || $db->Query($this->_hid, $qry, $this) === false) {

			    $this->getVar('syncgw');
    			Msg::WarnMsg($this, 'Never should go here - "'.$qry.'" failed!');
	   		    return false;
		    }

		}

		return true;
	}

	/**
	 * 	Export document to client supported MIME type
	 *
	 * 	@param	- External document
	 * 	@param	- Internal document
	 * 	@return	- true = Ok; false = Error
	 */
	public function export(XML &$ext, XML &$int): bool {

		$dev = Device::getInstance();
		if (!$dev->xpath('//DataStore/HandlerID[text()="DataStore::'.
							strtoupper(Util::HID(Util::HID_TAB, $this->_hid, true)).'"]/../MIME'))
			return false;

		$dev->getItem();
		$p = $dev->savePos();
		$mt = $dev->getVar('Name', false);
		$dev->restorePos($p);
		$mv = floatval($dev->getVar('Version', false));
		$dev->restorePos($p);

		// find MIME handler
		$ok = false;
		foreach ($this->_mimeClass as $class) {

			foreach ($class::MIME as $mime)
				if (!strcasecmp($mime[0], $mt) && $mime[1] == $mv) {

					$ok = true;
					break;
				}
			if ($ok)
				break;
		}

		// mime handler found?
		if (!$ok) {

			Log::getInstance()->logMsg(Log::ERR, 13001, $mt, $mv);
			return false;
		}

		if (!$class->export($mt, floatval($mv), $int, $ext)) {

			// we should never go here!
			Log::getInstance()->logMsg(Log::WARN, 13002, $mt, $mv);
			return false;
		}

		return true;
	}

	/**
	 * 	Delete internal (and external) document
	 *
	 * 	@param	- Record ID (GUID or LUID)
	 * 	@return	- true = Ok; false = Error
	 */
	public function delete(string $id): bool {

		$db = DB::getInstance();

		// get internal record
		if (!($doc = $db->Query($this->_hid, DataStore::RGID, $id)))
			if (!($doc = $db->Query($this->_hid, DataStore::RLID, $id)))
				return false;

		// first delete external document
		if ($xid = $doc->getVar('extID'))
			$db->Query(DataStore::EXT|$this->_hid, DataStore::DEL, $xid);

		// then delete internal document - needs to be separated because external datastore may be disabled
		return $db->Query($this->_hid, DataStore::DEL, $id);
	}

	/**
	 * 	Synchronize internal with external data store
	 *
	 * 	@param 	- Internal group ID to synchronize
	 * 	@param 	- false = Groups only; true = All records
	 * 	@return	- true = Ok; false = Error
	 */
	public function syncDS(?string $grp = '', bool $all = false): bool {

		$db   = DB::getInstance();
        $cnf  = Config::getInstance();
		$http = HTTP::getInstance();
        $lck  = Lock::getInstance();

		if (($s = $cnf->getVar(Config::DBG_SCRIPT)) == 'DB' || $s == 'DBExt' || $s == 'MIME04') {

			Msg::WarnMsg('----------------- Caution: syncDS() for "'.
							Util::HID(Util::HID_ENAME, $this->_hid, true).'" skipped');
			return false;
		}

        // we try to lock to disable parallel execution
		if (!$lck->lock($lock = $http->getHTTPVar('REMOTE_ADDR').'-'.$http->getHTTPVar('User')))
			return false;

        // group mapping table
		$gmap = [];

		// load records to process
		if ($grp) {

			if (!($xml = $db->Query($this->_hid, DataStore::RGID, $grp))) {

				$lck->unlock($lock);
				return false;
			}

			$xgrp = $xml->getVar('extID');
		} else
			$xgrp = $grp;

		if ($all)
			$xids = $db->getRIDS(DataStore::EXT|$this->_hid, $xgrp);
		else
			$xids = $db->Query(DataStore::EXT|$this->_hid, DataStore::GRPS, $xgrp);

		if (!count($xids))
			Msg::InfoMsg('No external '.($all ? 'records' : 'groups').
					   ' found in data store "'.Util::HID(Util::HID_ENAME, $this->_hid, true).
					   '"'.($xgrp ? 'in group ['.$xgrp.']' : ''));
		else
    		Msg::InfoMsg($xids, 'List of external '.($all ? 'records' : 'groups').
    				   ' in data store "'.Util::HID(Util::HID_ENAME, $this->_hid, true).
    				   '"'.($xgrp ? ' in group ['.$xgrp.']' : ''));

		// get list of internal records
		if ($all)
			$iids = $db->getRIDS($this->_hid, $grp);
		else
			$iids = $db->Query($this->_hid, DataStore::GRPS, $grp);

	    if ($iids === false)
	    	$iids = [];

	    if (!count($iids))
			Msg::InfoMsg('No internal '.($all ? 'records' : 'groups').
					   ' found in data store "'.Util::HID(Util::HID_ENAME, $this->_hid, true).
					   '"'.($grp ? ' in group ['.$grp.']' : ''));
        else
	   		Msg::InfoMsg($iids, 'List of internal '.($all ? 'records' : 'groups').
	   				   ' in data store "'.Util::HID(Util::HID_ENAME, $this->_hid, true).
	   				   '"'.($grp ? ' in group ['.$grp.']' : ''));

    	// external data store available?
        $int = ($int = $cnf->getVar(Config::DATABASE)) == 'mysql' || $int == 'file';

        // get list of supported fields
        $flds = $db->getflds($this->_hid);

        // checking internal data store records
		foreach ($iids as $id => $typ) {

			// read record
		    if (!($idoc = $db->Query($this->_hid, DataStore::RGID, $id))) {

		        Msg::ErrMsg('Error reading record ['.$id.'] in data store "'.
				           Util::HID(Util::HID_ENAME, $this->_hid, true).'"');
			    continue;
		    }

			// already soft-deleted?
			if ($idoc->getVar('SyncStat') == DataStore::STAT_DEL)
			    continue;

			// external data store available
			if (!$int) {

    			// record ever synchonized?
	       		if (!($xid = $idoc->getVar('extID'))) {

			        Msg::InfoMsg('Record ['.$id.'] in data store "'.
					        	Util::HID(Util::HID_ENAME, $this->_hid, true).
			        		'" has no external record reference - deleting record');
    				$db->Query($this->_hid, DataStore::DEL, $id);
    			    continue;
		      	}

		      	// external record available?
    			if (!isset($xids[$xid])) {

    				Msg::InfoMsg('Internal record ['.$id.'] set to DataStore::STAT_DEL '.
      						   '- external record ['.$xid.'] does not exist');

	       			// delete external reference
			     	$idoc->updVar('extID', '');
    				// rewrite record
	       			$db->setSyncStat($this->_hid, $idoc, DataStore::STAT_DEL, false, true);
	       			continue;
       			}
			}

			// start compare records

			// load external record
			if (!isset($xid) || !($xdoc = $db->Query(DataStore::EXT|$this->_hid, DataStore::RGID, $xid)))
			    continue;

			// save mapping
			if ($typ != DataStore::TYP_DATA)
				$gmap[$xid] = $id;

			// swap unsupported tags from internal to external record
			$idoc->getVar('Data');
			$idoc->getChild(null, false);
		    while (($v = $idoc->getItem()) !== null) {

		        $a = $idoc->getAttr();

	      		if (!in_array($n = $idoc->getName(), $flds)) {

	 	            $xdoc->getVar('Data');
	 	            // not existing?
	 	            if (!$xdoc->getVar($n, false))
			            $xdoc->addVar($n, $v, false, $a);
			        else {
	 	            	// swap value
	 	            	$xdoc->setVal($v);
	 	            	// delete existing attributes
	 	            	foreach ($xdoc->getAttr() as $a1)
	 	            		$xdoc->delAttr($a1);
	 	            	// set internal attributes
	 	            	$xdoc->setAttr($a);
			        }
		        }
		    }

			// get external record
			$xdoc->getVar('Data');
			$xr = preg_replace('|(<[a-zA-Z]+)[^>]*?>|', '$1/>', $xdoc->saveXML(false, false));
			$xr = str_replace('><', ">\n<", $xr);
			$xr = explode("\n", $xr);

			// remove "<Data>"
			array_shift($xr);
			array_pop($xr);

			// serialize
			sort($xr);

			// get internal record
			$idoc->getVar('Data');
			$ir = preg_replace('|(<[a-zA-Z]+)[^>]*?>|', '$1/>', $idoc->saveXML(false, false));
			$ir = str_replace('><', ">\n<", $ir);
			$ir = explode("\n", $ir);

			// remove "<Data>"
			array_shift($ir);
			array_pop($ir);

			// serialize
			sort($ir);

			// check differences
            list($c, $str) = Util::diffArray($ir, $xr);
            $str; // disable Eclipse warning

			// any differences?
            if ($c) {

				Msg::WarnMsg($str, 'Records not equal - replacing internal record ['.$id.
							'] with external record ['.$xid.'] ('.($c / 2).' changes)');
                Msg::InfoMsg($xdoc, 'External record');
                Msg::InfoMsg($idoc, 'Internal record');

                // replace document <Data>
				$idoc->delVar(null, false);
				$idoc->append($xdoc, false);

				// rewrite record
				$db->setSyncStat($this->_hid, $idoc, DataStore::STAT_REP, false, true);
			}

			// external record is processed
			unset($xids[$xid]);
		}

		// add remaining unknown external records
		foreach ([ DataStore::TYP_GROUP, DataStore::TYP_DATA ] as $chk) {

			foreach ($xids as $xid => $typ) {

				// type to check
				if ($typ != $chk)
					continue;

				// load external record
				if (!($xdoc = $db->Query(DataStore::EXT|$this->_hid, DataStore::RGID, $xid))) {

					$lck->unlock($lock);
					return false;
				}

                // create new document. This must be done in this way to get a proper GUID
				$idoc = $db->mkDoc($this->_hid, [ 'Status' => DataStore::STAT_ADD ], true);

				// replace document <Data>
				$idoc->getVar('Data');
				$idoc->delVar(null, false);
				$xdoc->getVar('Data');
				$idoc->append($xdoc, false);

				// swap type
				$idoc->updVar('Type', $typ);

				// swap external record id
				$idoc->updVar('extID', $xdoc->getVar('extID'));

				// swap external group
				$xgid = $xdoc->getVar('extGroup');
				$idoc->updVar('extGroup', $xgid);

				// set group
				$idoc->updVar('Group', isset($gmap[$xgid]) ? $gmap[$xgid] : strval($grp));

				// save mapping
				if ($typ != DataStore::TYP_DATA)
					$gmap[$xid] = $idoc->getVar('GUID');

				// count record
				$id = $idoc->getVar('GUID');
				$idoc->getVar('syncgw');
				Msg::InfoMsg($idoc, 'Creating new internal record ['.$id.'] from unknown external record ['.
						   $xid.'] in group ['.$grp.']');

				// add record
				$db->Query($this->_hid, DataStore::UPD, $idoc);
			}
		}

		// map other groups
		if (count($gmap))
			Msg::InfoMsg($gmap, 'Group mapping table');

		$lck->unlock($lock);

		return true;
	}

}
