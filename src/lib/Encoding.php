<?php
declare(strict_types=1);

/*
 * 	Encoding handler class
 *
 *	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\lib;

class Encoding extends XML {

 	/**
	 * 	External character set encoding name
	 * 	@var string
	 */
	private $_ext = '';

	/**
	 * 	Multibyte flag (true=Available)
	 * 	@var bool
	 */
	private $_mb;

    /**
     * 	Singleton instance of object
     * 	@var Encoding
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): Encoding {

		if (!self::$_obj) {

        	self::$_obj = new self();

			// set log messages 10301-10400
			Log::getInstance()->setLogMsg([
					10301 => 'Unknown character set \'%s\'',
			]);

			// load encoding
			self::$_obj->loadFile(Config::getInstance()->getVar(Config::ROOT).'core-bundle/assets/charset.xml');

			// check for multi byte encoding functions
			self::$_obj->_mb = function_exists('mb_convert_encoding');

			// set internal encoding
			mb_internal_encoding('UTF-8');
		}

		return self::$_obj;
	}

    /**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
     *	@param 	- true = Provide status information only (if available)
	 */
	public function getInfo(XML &$xml, bool $status): void {

		$xml->addVar('Name', 'Encoding handler');

		if ($status) {

			$xml->addVar('Opt', 'Internal encoding');
			$xml->addVar('Stat', 'UTF-8');
		} else {
			parent::xpath('//Charset/.');
			while (parent::getItem() !== null) {
				$pos = parent::savePos();
				$cs = parent::getVar('Name', false);
				$xml->addVar('Opt', sprintf('Character set "%s"', $cs));
				$xml->addVar('Stat', 'Implemented');

				parent::xpath('../Alias/.', false);
				while ($n = parent::getItem()) {
					$xml->addVar('Opt', sprintf('Alias "%s" of character set "%s"', $n, $cs));
					$xml->addVar('Stat', 'Implemented');
				}
				parent::restorePos($pos);
			}
		}
	}

	/**
	 * 	Set external character set encoding
	 *
	 * 	@param	- Character set ID or name
	 * 	@return	- Name of character set or null for UTF-8
	 */
	public function setEncoding(string $cs): ?string {

		// sepcial ActiveSync hack - we assume character set 0 = UTF-8
		if (!$cs)
			$cs = 'utf-8';
		else
			$cs = strtolower($cs);

		if (!parent::xpath('//Charset[Id="'.$cs.'"]/.') && !parent::xpath('//Charset[Cp="'.$cs.'"]/.') &&
			!parent::xpath('//Charset[Name="'.$cs.'"]/.') && !parent::xpath('//Charset[Alias="'.$cs.'"]/.')) {

				Msg::InfoMsg('Set external character set encoding "'.$cs.'" to ""');
			return $this->_ext = null;
		}

		parent::getItem();
		$this->_ext = parent::getVar('Name', false);
		parent::setParent();
		Msg::InfoMsg('Set external character set encoding "'.$cs.'" to "'.$this->_ext.'"');

		if (Config::getInstance()->getVar(Config::DBG_SCRIPT) == 'Encoding')
			Msg::InfoMsg($this, 'Character set selected');

		return $this->_ext;
	}

	/**
	 * 	Get code page for character set
	 *
	 *  see: https://docs.microsoft.com/en-us/windows/win32/intl/code-page-identifiers (05/31/2018)
	 *       https://www.iana.org/assignments/character-sets/character-sets.xml (2020-01-04)
	 *
	 * 	@param  - Character set name
	 *  @return - MicroSoft code page
	 */
	public function getMSCP(string $cs): string {

		$cs = strtolower($cs);

		if (!parent::xpath('//Charset[Cp="'.$cs.'"]/.') &&
			!parent::xvalue('Name', $cs, '/Charset/') &&
			!parent::xvalue('Alias', $cs, '/Charset/')) {
			Log::getInstance()->logMsg(Log::WARN, 10301, $cs);
			return self::getMSCP('utf-8');
		}

		parent::getItem();
		$cp = parent::getVar('Cp', false);

		Msg::InfoMsg('Get code page "'.$cp.'" for character set "'.$cs.'"');

		return $cp;
	}

	/**
	 * 	Get external encoding
	 *
	 * 	@return	- Active character set name or null
	 */
	public function getEncoding(): ?string {

		return $this->_ext;
	}

	/**
	 * 	Decode data from external to internal encoding
	 *
	 * 	@param	- String to decode
	 * 	@return	- Converted string
	 */
	public function import(string $str): string {

		if (!$this->_ext || !$this->_mb)
			return $str;

		return $this->_ext == 'UTF-8' ? $str : mb_convert_encoding($str, 'UTF-8', $this->_ext);
	}

	/**
	 * 	Encode data from internal to external encoding
	 *
	 * 	@param	- String to encode
	 * 	@return	- Converted string
	 */
	public function export(string $str): string {

		if (!$this->_ext || !$this->_mb)
			return $str;

		return $this->_ext == 'UTF-8' ? $str : mb_convert_encoding($str, $this->_ext, 'UTF-8');
	}

}
