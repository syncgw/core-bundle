<?php
declare(strict_types=1);

/*
 *  Group attribute field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

class fldAttribute extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 			 = 'Attributes';

	const READ  		= 0x00001;		// allowed to read
	const WRITE 		= 0x00002;		// allowed to write
	const EDIT  		= 0x00004;		// allowed to edit
	const DEL   		= 0x00008;		// allowed to delete
	const ALARM 		= 0x00010; 		// show group alarms
 	const DEFAULT 		= 0x00020; 		// default group

	// special flag for global address book
	const GAL	     	= 0x00040;		// global adress book

	// mail box flags (based on activesync/masFolderType.php)
	const MBOX_IN     	= 0x00100;		// Inbox
	const MBOX_DRAFT  	= 0x00200;		// Drafts
	const MBOX_TRASH	= 0x00400;		// Trash (Deleted)
	const MBOX_SENT 	= 0x00800;		// Sent
	const MBOX_SPAM 	= 0x01000;		// Spam (Junk)
	const MBOX_ARCH	 	= 0x02000;		// Archive
	const MBOX_OUT 	 	= 0x04000;		// Outbox (pending sent)
	const MBOX_SYS	 	= 0x08000;		// System mail box
	const MBOX_USER   	= 0x10000;		// User-created

	const MBOX_TALL		= (self::MBOX_IN|self::MBOX_DRAFT|self::MBOX_TRASH|self::MBOX_SENT|self::MBOX_SPAM|
						  self::MBOX_ARCH|self::MBOX_OUT|self::MBOX_SYS|self::MBOX_USER);

    const ATTR_TXT		= [
    	self::READ			=> 'read',
        self::WRITE			=> 'write',
        self::EDIT			=> 'edit',
        self::DEL			=> 'delete',
        self::ALARM			=> 'alarm',
        self::DEFAULT		=> 'defaultGroup',
    	self::GAL			=> 'gal',
		self::MBOX_IN  		=> 'INBOX',
		self::MBOX_DRAFT 	=> 'DRAFT',
		self::MBOX_TRASH 	=> 'TRASH (deleted)',
		self::MBOX_SENT 	=> 'SENT',
		self::MBOX_SPAM  	=> 'SPAM (junk)',
		self::MBOX_ARCH  	=> 'ARCHIVE',
	   	self::MBOX_OUT  	=> 'OUTBOX',
    	self::MBOX_SYS		=> 'SYSTEM',
    	self::MBOX_USER		=> 'USER',
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldAttribute
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldAttribute {

		if (!self::$_obj) {

            self::$_obj = new self();
			// clear tag deletion status
			unset(parent::$Deleted[self::TAG]);
		}

		return self::$_obj;
	}

 	/**
	 * 	Convert string to attribute
	 *
	 * 	@param 	- Mailbox name
	 *  @return - fldAttribute::MBOX_*
	 */
	static public function getMBoxType(string $name): int {

    	// get type of mail box
    	$typ = fldAttribute::MBOX_USER;
    	foreach (self::ATTR_TXT as $k => $v) {

    		foreach (explode(',', $v) as $n) {

   	 			if (!strcasecmp($name, $n))
    	    		return $k;
    		}
    	}

    	return $typ;
	}

	/**
	 * 	Decode attributes
	 *
	 * 	@param 	- Attribute value
	 * 	@return - Attribute string
	 */
	static public function showAttr(int $attr): string {

     	$msg = '('.$attr.') ';
     	foreach (self::ATTR_TXT as $k => $v)
     		if ($attr & $k)
	        	$msg .= $v.' ';

        return $msg;
	}

}
