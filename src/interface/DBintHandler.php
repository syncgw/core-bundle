<?php
declare(strict_types=1);

/*
 *  Internal data base interface definition class
 *
 *	@package	sync*gw
 *	@subpackage	Data base
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\interface;

use syncgw\lib\XML;

interface DBintHandler {

	/**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance();

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
	 * 			  DataStore::GRPS	  [ "GUID" => Typ of record ]<br>
	 * 			  DataStore::RIDS     [ "GUID" => Typ of record ]<br>
	 * 			  DataStore::RNOK     [ "GUID" => Typ of record ]
	 */
	public function Query(int $hid, int $cmd, $parm = '');

	/**
	 * 	Excute raw SQL query on internal data base
	 *
	 * 	@param	- SQL query string
	 * 	@return	- Result string or []; null on error
	 */
	public function SQL(string $query);

}
