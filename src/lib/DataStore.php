<?php
declare(strict_types=1);

/*
 *  Datastore definition class
 *
 *	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\lib;

class DataStore {

	// system data stores
    const USER		 = 0x00001;
	const TRACE		 = 0x00002;
	const SESSION	 = 0x00004;
	const DEVICE	 = 0x00008;
	const ATTACHMENT = 0x00010;
	const SYSTEM	 = 0x0001f;	   	   	// USER|SESSION|TRACE|DEVICE|ATTACHMENT

	// user data stores
	const CALENDAR	 = 0x00100;
	const CONTACT	 = 0x00200;
	const NOTE		 = 0x00400;
	const TASK		 = 0x00800;
	const MAIL		 = 0x01000;
	const SMS		 = 0x02000;
	const DOCLIB	 = 0x04000;
	const GAL		 = 0x08000;
	const DATASTORES = 0x0ff00;		   	// CONTACT|CALENDAR|NOTE|TASK|MAIL|SMS|DOCLIB|GAL

	// external data store call
	const EXT		 = 0x10000;

	// all data stores
	const ALL		 = 0x0fffff;

	// <SyncStat> definitions
	const STAT_OK    = 'OK';
	const STAT_ADD   = 'ADD';
	const STAT_REP   = 'REP';
	const STAT_DEL   = 'DEL';

    // data store record type
	const TYP_GROUP	 = 'G';				// group record
	const TYP_DATA	 = 'R';            	// data record

	// record operation
	const ADD		 = 0x0001;         	// add record
	const UPD		 = 0x0002;         	// update record
	const DEL		 = 0x0004;         	// delete record
	const RGID       = 0x0008;         	// read GUID
	const RLID       = 0x0010;         	// read LUID
	const GRPS       = 0x0020;         	// read all groups
	const RIDS       = 0x0040;         	// read all IDs in group
	const RNOK    	 = 0x0080;         	// read status not equals to STAT_OK

}
