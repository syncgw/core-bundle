<?php
declare(strict_types=1);

/*
 * 	Attachment handling functions class
 *
 *	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

/**
 * 	Variables used in attachment object:
 *
 *------------------------------------------------------------------------------------------------------------------
 *  <GUID/>				Global Unique Identified: user ID.Attachment::SEP.attachment name
 *  <Group/>			Record group: ''
 *  <Type/>				Record type: DataStore::TYP_GROUP
 *  <Data>
 *   <MIME>             MIME type of data
 *   <Encoding>			IMAP encoding type
 *   <Size>             Size of attachment
 *   <Record>           Attachment data
 *  </Data>
 *------------------------------------------------------------------------------------------------------------------
 *  <GUID/>				Global Unique Identified: Attachment GUID.Attachment::SEP.sub record counter
 *  <Group/>			Record group: Attachment record ID
 *  <Type/>				Record type: DataStore::TYP_DATA
 *  <Data>
 *   <MIME>             MIME type of data
 *   <Encoding>			IMAP encoding type
 *   <Size>             Size of attachment
 *   <Record>           Attachment data
 *  </Data>
 *
 **/

namespace syncgw\lib;

class Attachment extends XML {

	const SEP  		=  '-';					// attachment name seperator
	const PREF 		= 'sgw'.self::SEP;		// name prefix
	const PLEN 		= 4;					// length of prefix
    const SIZE 		= 1000000;				// max. attachment chunk size for database (1 MB)

   	/**
     * Debug helper
     * @var string
     */
    public $_gid = null;

    /**
     * 	Singleton instance of object
     * 	@var Attachment
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): Attachment {

		if (!self::$_obj) {
            self::$_obj = new self();

	  		// set log messages codes 11401-11400
	   		Log::getInstance()->setLogMsg([
	   		        11401 => 'Error creating attachment record [%s]',
	   		        11402 => 11401,
	   				11403 => 11401,
	   		        11404 => 'Error reading attachment record [%s]',
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

		$xml->addVar('Name', 'Attachment handler');
		$xml->addVar('Opt', 'SabreDAV max. attachment size');
		$xml->addVar('Stat', strval(Config::getInstance()->getVar(Config::MAXOBJSIZE)));
	}

	/**
	 * 	Get variable
	 *
	 * 	@param	- Name of variable
	 * 	@param 	- true = Search whole document (default); false = Search from current position
	 * 	@return	- Variable content or null = Not found
	 */
	public function getVar(string $name, bool $top = true): ?string {

		$val = null;

        $cnf = Config::getInstance();
		$len = intval(parent::getVar('Size'));

        // check limited attachment size for DAV
      	if (($cnf->getVar(Config::HANDLER)) == 'DAV' &&
      	    !($cnf->getVar(Config::HACK) & Config::HACK_SIZE) &&
            $len > $cnf->getVar(Config::MAXOBJSIZE)) {

      	    $fnam = $cnf->getVar(Config::ROOT).'/core-bundle/assets/TooBig.png';
            Msg::WarnMsg('Attachment data for ['.parent::getVar('GUID').'] replaced with "'.$fnam.'"');
            $len  = 3466;
       	    $mime = 'image/png';
  	    } else
    		$mime = parent::getVar('MIME');

		switch ($name) {
	    case 'MIME':
	        $val = $mime;
    		Msg::InfoMsg('['.$name.'] = "'.$val.'"');
	        break;

	    case 'Size':
	        $val = strval($len);
    		Msg::InfoMsg('['.$name.'] = "'.$val.'"');
	        break;

	    default:
	        $val = parent::getVar($name, $top);
	        break;
		}

		return $val;
	}

	/**
	 *  Create attachment record
	 *
	 *  @param  - Binary data
	 *  @param  - Optional mime type
	 *  @param  - Optional imap encoding type
	 *  @return - Attachment name or null
	 */
	function create(string $bin, ?string $mime = null, int $enc = ENCBINARY): ?string {

	    $db  = DB::getInstance();
        $max = Config::getInstance()->getVar(Config::DB_RSIZE);

        list($gid, $rc) = self::_load(true, $bin, $mime, $enc);

        // does record already exist?
        if (!$rc)
        	return $gid;

        // create encoded string
        $data = base64_encode($bin);
        $len  = strlen($data);

        if ($len < $max) {
        	parent::updVar('Record', $data);
        	$data = '';
        } else
			parent::updVar('Record', substr($data, 0, $max));

  		if ($db->Query(DataStore::ATTACHMENT, DataStore::UPD, $this) === false) {
			Log::getInstance()->logMsg(Log::WARN, 11401, $gid);
  			return null;
  		}

  		// already saved?
  		if (!$data) {
	        Msg::InfoMsg('Attachment GUID "'.$gid.'" MIME type "'.parent::getVar('MIME').
	        							'" with '.$len.' bytes written');
  			return $gid;
  		}

        // create swap record
        $xml = new XML($this);
        $xml->updVar('Type', DataStore::TYP_DATA);
        $xml->updVar('Group', $gid);

        // save record
        for ($pos=$max, $cnt=0; $pos < $len; $cnt++, $pos+=$max) {
            $l = $len - $pos > $max ? $max : $len - $pos;
            $xml->updVar('GUID', $gid.self::SEP.$cnt);
            $xml->updVar('Record', substr($data, $pos, $l));
	   		if (($rc = $db->Query(DataStore::ATTACHMENT, DataStore::ADD, $xml)) === false) {

	   			Log::getInstance()->logMsg(Log::WARN, 11402, $gid.self::SEP.$cnt);
      			$gid = null;
      			break;
   			}
        }

	    Msg::InfoMsg('Attachment GUID "'.$gid.'" MIME type "'.parent::getVar('MIME').
	    							'" with '.$len.' bytes written');

        return $gid;
	}

	/**
	 * 	Read attachment data
	 *
	 *	@param  - Attachment record id
	 *  @return - Binary data or null = Error
	 */
	function read(string $gid): ?string {

        $db  = DB::getInstance();
        $cnf = Config::getInstance();
      	$max = $cnf->getVar(Config::DB_RSIZE);

  		// load attachment record
  		list($gid, $rc) = self::_load(false, $gid);

  		// new record?
  		if ($rc)
  			return null;

  		$len = intval(parent::getVar('Size'));

    	// check limited attachment size for DAV
      	if (($cnf->getVar(Config::HANDLER)) == 'DAV' &&
      	    !($cnf->getVar(Config::HACK) & Config::HACK_SIZE) &&
            $len > $cnf->getVar(Config::MAXOBJSIZE)) {

      	    $fnam = $cnf->getVar(Config::ROOT).'/core-bundle/assets/TooBig.png';
      	    $data = file_get_contents($fnam);
      	    Msg::WarnMsg('Attachment data for ['.$gid.'] replaced with "'.$fnam.'"');
		    return strval($data);
      	}

      	$data = parent::getVar('Record');

	    // do we need to load sub records?
      	if ($len > $max) {
		    $xml = new XML();
		    foreach ($db->Query(DataStore::ATTACHMENT, DataStore::RIDS, $gid) as $id => $unused) {

		    	if (!($xml = $db->Query(DataStore::ATTACHMENT, DataStore::RGID, $id))) {

		    		Log::getInstance()->logMsg(Log::WARN, 11404, $id);
	           	    return null;
		        }
		        $data .= $xml->getVar('Record');
		    }
			$unused; // disable Eclipse warning
      	}

  		Msg::InfoMsg('Reading attachment record "'.$gid.'" '.$len.' bytes');

		return base64_decode($data);
	}

	/**
	 * 	Load / create new attachment record
	 *
	 *  @param  - true=Create reqord if required
	 *  @param  - Attachment record ID or binary data
	 *  @param  - Optional mime type or null
	 *  @param  - Optional imap encoding type
	 *  @return - [ Attachment record ID, true = New; false = Existing record ]
	 */
	private function _load(bool $mod, string $bin, ?string $mime = null, int $enc = ENCBINARY): array {

		$db = DB::getInstance();
		$rc = true;

	    // check, if binary data is a valid GUID
		if (!$this->_gid) {
		    if (substr($bin, 0, self::PLEN) == self::PREF)
		    	$gid = $bin;
	    	else
            	$gid = self::PREF.Util::Hash($bin);
		} else {
			$gid = $this->_gid;
			$this->_gid = null;
		}

		// first try to load record
        if ($xml = $db->Query(DataStore::ATTACHMENT, DataStore::RGID, $gid)) {

            parent::loadXML($xml->saveXML());
            $rc = false;
        } elseif ($mod) {

	        // compile MIME type?
    	    if (!$mime) {
	            $fnam = Util::getTmpFile();
    	        if (file_put_contents($fnam, $bin))
   	    		    $mime = mime_content_type($fnam);
	        }
	   	    parent::loadXML(
	      			'<syncgw>'.
	   			      '<GUID>'.$gid.'</GUID>'.
		   			  '<LUID/>'.
	   			  	  '<SyncStat>'.DataStore::STAT_OK.'</SyncStat>'.
	   			  	  '<Group/>'.
	   			  	  '<Type>'.DataStore::TYP_GROUP.'</Type>'.
		  			  '<LastMod>'.time().'</LastMod>'.
	   		  		  '<Created>'.time().'</Created>'.
	   	    		  '<CRC/>'.
	   	    		  '<extID/>'.
	   	    		  '<extGroup/>'.
	   	    		  '<Data>'.
			  		    '<MIME>'.$mime.'</MIME>'.
			  		    '<Encoding>'.strval($enc).'</Encoding>'.
	   	    			'<Size>'.strval(strlen($bin)).'</Size>'.
	 			        '<Record/>'.
	   				  '</Data>'.
		      		'</syncgw>');

	   		// save group record
  			if ($db->Query(DataStore::ATTACHMENT, DataStore::ADD, $this) === false)
	            Log::getInstance()->logMsg(Log::WARN, 11403, $gid);
        }

        return [ $gid, $rc ];
	}

}
