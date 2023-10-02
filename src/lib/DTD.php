<?php
declare(strict_types=1);

/*
 * 	DTD handler class
 *
 *	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\lib;

class DTD extends XML {

	/**
	 * 	DTD position or false
	 * 	@var bool|array
	 */
	private $_dtd;

	/**
	 *  Foreced base DTD
	 *  @var string
	 */
	private $_base = '';

    /**
     * 	Singleton instance of object
     * 	@var DTD
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): DTD {

		if (!self::$_obj) {

           	self::$_obj = new self();

			// set messages 10601-10700
			Log::getInstance()->setLogMsg([

					10601 => 'Corrupted DTD "%s" - entry \'%s\' not found',
					10602 => 'No DTD activated',
					10603 => 'No DTD found',
					10604 => 'DTD \'%s\' not found',
			]);

			// load all available DTD
			$a = [];
			$p = Config::getInstance()->getVar(Config::ROOT);
			foreach ([ 'activesync-bundle/assets/', ] as $n) {

				if (!($d = opendir($p.$n)))
					continue;

				while (($f = readdir($d)) !== false) {

					if (substr($f, 0, 3) == 'cp_')
						$a[] = $p.$n.$f;
				}
				closedir($d);
			}
			if (!count($a)) {

				ErrorHandler::getInstance()->Raise(10603);
	            return self::$_obj;
			}

 			// set root
			self::$_obj->loadXML('<CodePage/>');

			// load documents
			$wrk = new XML();
			foreach ($a as $file) {

				if (!$wrk->loadFile($file))
					return self::$_obj;

				$wrk->getVar('CodePage');
				self::$_obj->append($wrk);
			}
		}

		return self::$_obj;
	}

    /**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
 	 */
	public function getInfo(XML &$xml): void {

		$xml->addVar('Name', 'DTD handler');

		if (parent::xpath('//Header/.')) {

			while (parent::getItem() !== null) {

				$ip = parent::savePos();
				$tag   = parent::getVar('Tag', false);
				parent::restorePos($ip);
				$link  = parent::getVar('Link', false);
				parent::restorePos($ip);
				$title = parent::getVar('Title', false);
				parent::restorePos($ip);
				$ver   = parent::getVar('Ver', false);
				$xml->addVar('Opt', '<a href="'.$link.'" target="_blank">'.$tag.'</a> '.
							  $title.' v'.$ver);
				$xml->addVar('Stat', 'Implemented');
			}
		}
	}

	/**
	 * 	Activate DTD
	 *
	 * 	@param	- "PID" or "Name" or "URI"
	 * 	@return	- true=Ok, false=Not found
	 */
	public function actDTD($dtd): bool {

		// patch for ActiveSync
		if ($dtd == 1 && $this->_base)
			$dtd = $this->_base;
		Msg::InfoMsg('Activate DTD "'.$dtd.'"');

		if (!$this->xpath('//PID[text()="'.$dtd.'"]/.. | //Name[text()="'.$dtd.'"]/..') &&
			!$this->xvalue('URI', strval($dtd))) {

			Log::getInstance()->logMsg(Log::WARN, 10604, $dtd);
			$this->_dtd = false;
			return false;
		}
		parent::getItem();
		$this->_dtd = parent::savePos();

		return true;
	}

	/**
	 * 	Get variable
	 *
	 * 	@param	- Optional name of variable; null for all available
	 * 	@param 	- true = Search whole document; false = Search from current position
	 * 	@return	- Variable content; null = Variable not found
	 */
	public function getVar(?string $name = null, bool $top = false): ?string {

		if (!$this->_dtd) {

			Msg::WarnMsg('Get variable "'.$name.'" - no DTD set');
			return null;
		}

		parent::restorePos($this->_dtd);
		$rc = parent::getVar($name, $top);
		Msg::InfoMsg('Get variable "'.$name.'" = "'.$rc.'"');

		return $rc;
	}

	/**
	 * 	Get tag definition
	 *
	 * 	@param	- WBXML code or string
	 * 	@return - WBXML code: Name of Tag; String: WBXML code; null = Error
	 */
	public function getTag(string $tag): ?string {

		Msg::InfoMsg('Get tag definition "'.$tag.'"');

		if (!$this->_dtd) {

			Log::getInstance()->logMsg(Log::WARN, 10602);
			return null;
		}

		// swicth to DTD table
		parent::restorePos($this->_dtd);
		if (is_numeric($tag)) {

			$v = sprintf('0x%02x', $tag);
			if (parent::xpath('../Defs/*[text()="'.$v.'"]/.', false)) {

				if (parent::getItem())
					return parent::getName();
			}

			// create new entry for unknown tag
			parent::restorePos($this->_dtd);
			parent::xpath('../Defs/.', false);
			parent::getItem();
			parent::addVar('Unknown-'.$v, $v);
			return self::getTag($tag);
		}

		if (self::xpath('../Defs/'.$tag.'/.', false))
			return strval(hexdec(parent::getItem()));

		Log::getInstance()->logMsg(Log::ERR, 10601, parent::getVar('URI', false), $tag);

		return null;
	}

}
