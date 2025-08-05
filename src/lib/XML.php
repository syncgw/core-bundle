<?php
declare(strict_types=1);

/*
 *  XML handler class
 *
 *	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\lib;

class XML {

	// node types
	const NODETYP 		= [
            XML_ELEMENT_NODE            => 'XML_ELEMENT_NODE',
            XML_ATTRIBUTE_NODE          => 'XML_ATTRIBUTE_NODE',
    		XML_TEXT_NODE               => 'XML_TEXT_NODE',
    		XML_CDATA_SECTION_NODE      => 'XML_CDATA_SECTION_NODE',
		    XML_ENTITY_REF_NODE         => 'XML_ENTITY_REF_NODE',
		    XML_ENTITY_NODE             => 'XML_ENTITY_NODE',
			XML_PI_NODE                 => 'XML_PI_NODE',
			XML_COMMENT_NODE            => 'XML_COMMENT_NODE',
			XML_DOCUMENT_NODE           => 'XML_DOCUMENT_NODE',
			XML_DOCUMENT_TYPE_NODE      => 'XML_DOCUMENT_TYPE_NODE',
			XML_DOCUMENT_FRAG_NODE      => 'XML_DOCUMENT_FRAG_NODE',
			XML_NOTATION_NODE           => 'XML_NOTATION_NODE',
			XML_HTML_DOCUMENT_NODE      => 'XML_HTML_DOCUMENT_NODE',
			XML_DTD_NODE                => 'XML_DTD_NODE',
			XML_ELEMENT_DECL_NODE       => 'XML_ELEMENT_DECL_NODE',
			XML_ATTRIBUTE_DECL_NODE     => 'XML_ATTRIBUTE_DECL_NODE',
			XML_ENTITY_DECL_NODE        => 'XML_ENTITY_DECL_NODE',
			XML_NAMESPACE_DECL_NODE     => 'XML_NAMESPACE_DECL_NODE',
    ];

    const ENTITY		= [ [ '&', '<', '>', ], [ '&#38;', '&#60;', '&#62;', ] ];

	const AS_BASE 		= 01;
	const AS_AIR 		= 02;

	const AS_FOLDER  	= 10;
	const AS_ESTIMATE	= 11;
	const AS_ITEM 	 	= 12;
	const AS_MOVE     	= 13;
	const AS_PING 		= 14;
	const AS_PROVISION	= 15;
	const AS_SETTING 	= 16;
	const AS_RESOLVE 	= 17;
	const AS_RIGTHM 	= 18;
	const AS_SEARCH 	= 19;
	const AS_CERT 		= 20;
	const AS_MRESPONSE	= 21;
	const AS_DocLib 	= 22;
	const AS_COMPOSE	= 23;
	const AS_FIND 		= 24;

	const AS_CONTACT 	= 30;
	const AS_CONTACT2	= 31;
	const AS_GAL 		= 32;
	const AS_CALENDAR	= 33;
	const AS_TASK 		= 34;
	const AS_NOTE 		= 35;
	const AS_MAIL 		= 36;
	const AS_MAIL2 		= 37;

	const CP 		    = [
		self::AS_BASE 		=> 'activesync:AirSyncBase',
		self::AS_AIR		=> 'activesync:AirSync',

		self::AS_FOLDER		=> 'activesync:FolderHierarchy',
		self::AS_ESTIMATE	=> 'activesync:GetItemEstimate',
		self::AS_ITEM		=> 'activesync:ItemOperations',
		self::AS_MOVE		=> 'activesync:Move',
		self::AS_PING		=> 'activesync:Ping',
		self::AS_PROVISION	=> 'activesync:Provision',
		self::AS_SETTING	=> 'activesync:Settings',
		self::AS_RESOLVE	=> 'activesync:ResolveRecipients',
		self::AS_RIGTHM		=> 'activesync:RightsManagement',
		self::AS_SEARCH		=> 'activesync:Search',
		self::AS_CERT		=> 'activesync:ValidateCert',
		self::AS_MRESPONSE	=> 'activesync:MeetingResponse',
		self::AS_DocLib		=> 'activesync:DocumentLibrary',
		self::AS_COMPOSE	=> 'activesync:ComposeMail',
		self::AS_FIND		=> 'activesybc:Find',

		self::AS_CONTACT	=> 'activesync:Contacts',
		self::AS_CONTACT2	=> 'activesync:Contacts2',
		self::AS_GAL		=> 'activesync:GAL',
		self::AS_CALENDAR	=> 'activesync:Calendar',
		self::AS_TASK		=> 'activesync:Tasks',
		self::AS_NOTE		=> 'activesync:Notes',
		self::AS_MAIL		=> 'activesync:Mail',
		self::AS_MAIL2		=> 'activesync:Mail2',
	];

	/**
	 * 	XML object
	 * 	@var \DOMDocument
	 */
	private $_doc;

	/**
	 * 	Position in object
	 * 	@var \DOMNode
	 */
	private $_pos;

	/**
	 * 	Search object
	 * 	@var \DOMXPath
	 */
	private $_xpath;

	/**
	 * 	List of found DOMNodes objects
	 * 	@var array
	 */
	private $_list;

	/**
	 * 	Object update counter
	 * 	@var int
	 */
	private $_upd;

	/**
	 *  Assigned code page
	 *  @var array
	 */
	private $_cp;

	/**
	 * 	Build class object
	 *
	 * 	@param 	- Optional: Object to copy data from
	 * 	@param	- true = Copy from top (default); false = Copy from current position
	 */
	public function __construct(XML $obj = null, bool $top = true) {

		$this->_doc = new \DOMDocument('1.0', 'UTF-8');

		self::setTop();
		$this->_list = [];
		$this->_upd  = 0;
		$this->_cp   = null;

		if ($obj)
			self::loadXML($obj->saveXML($top));
	}

	/**
	 * 	Delete class object
	 */
	public function __destruct() {

		$this->_doc   = $this->_pos = null;
		$this->_xpath = '';
		$this->_list  = [];
		$this->_upd   = 0;
	}

 	/**
	 * 	Get/Set update status
	 *
	 *	@param	- 0 = Read update counter; 1 = Increment counter; -1 = Reset counter
	 * 	@return	- Number of times modified
	 */
	public function updObj(int $mod = 0): int {

		if ($mod > 0)
			$this->_upd++;
		elseif ($mod < 0)
			$this->_upd = 0;

			Msg::InfoMsg('Update counter is "'.$this->_upd.'"');

		return $this->_upd;
	}

	/**
	 * 	Get document type
	 *
	 * 	@return	- [ 'name', 'publicID', 'link' ]
	 */
	public function getDocType(): array {

		if (!isset($this->_doc->doctype))
			return [ '', '', '' ];

		return [ $this->_doc->doctype->name, $this->_doc->doctype->publicId, $this->_doc->doctype->systemId ];
	}

	/**
	 * 	Set document type
	 *
	 * 	@param	- URI
	 * 	@param	- DTD name
	 * 	@param	- HTTP link
	 */
	public function setDocType(string $uri, string $dtd, string $link): void {

		// be sure to clean strings
		$uri  = self::cnvStr($uri);
		$dtd  = self::cnvStr($dtd);
		$link = self::cnvStr($link);

		Msg::InfoMsg('['.$uri.'], ['.$dtd.'] and ['.$link.']');

		$dim  = new \DOMImplementation();
		$dtd  = $dim->createDocumentType($uri, $dtd, $link);
		$this->_doc = $dim->createDocument('', '', $dtd);

		self::setTop();
		$this->_upd++;
	}

	/**
	 *	Load XML string into object - Warning: You need to take care about cnvStr()!
	 *
	 *  @param	- XML formatted string
	 *  @return	- true = Ok; false = Error
	 */
	public function loadXML(string $data): bool {

		$this->_xpath = '';
		$this->_upd   = 0;
		if (!($rc = $this->_doc->loadXML($data))) {

			Util::Save(__FUNCTION__.'%d.xml', $data);
			ErrorHandler::getInstance()->Raise(10001, 'loadXML() error');
		}
		$this->_pos = $this->_doc;

		return $rc;
	}

	/**
	 *	Save object as XML string
	 *
	 *	@param	- true = Whole document; false = From current position
	 * 	@param	- true = Format output; false = Compress output
	 * 	@return	- XML String
	 */
	public function saveXML(bool $top = true, bool $fmt = false): string {

		Msg::InfoMsg(($top ? 'From top' : 'From current position').' '.
									($fmt ? 'format output' : 'compress outout'));

		$this->_doc->formatOutput = $fmt;

		return $this->_doc->saveXML($top ? $this->_doc : $this->_pos);
	}

	/**
	 * 	Get node type
	 *
	 * 	@return	- Node type
	 */
	public function getType(): int {

        Msg::InfoMsg('['.$this->_pos->nodeName.'] = "'.self::NODETYP[$this->_pos->nodeType].'"');

        return $this->_pos->nodeType;
	}

	/**
	 * 	Current node name
	 *
	 * 	@return	- Current node name
	 */
	public function getName(): string {

		if ($this->_pos->nodeName)
			Msg::InfoMsg('['.$this->_pos->nodeName.']');

		return $this->_pos->nodeName ? $this->_pos->nodeName : '';
	}

	/**
	 * 	Rename current node
	 *
	 * 	@param	- New name of node
	 */
	public function setName(string $name): void {

		Msg::InfoMsg('['.$this->_pos->nodeName.'] = ['.$name.']');

		// rename document?
		if ($this->_pos->parentNode->nodeType == XML_DOCUMENT_NODE) {

			$doc = new \DOMDocument('1.0', 'UTF-8');
			$p = $doc->appendChild($doc->createElement($name));
			// don't forget to copy attributes
			foreach ($this->_pos->attributes as $a)
				$p->setAttribute($a->nodeName, $a->nodeValue);
			foreach ($this->_pos->childNodes as $c)
				$p->appendChild($doc->importNode($c->cloneNode(true), true));
			// swap variables
			$this->_doc = $doc;
			$this->_pos = $p;
		} else {

			// create new node on parent level
			$p = $this->_pos->parentNode->appendChild($this->_doc->createElement($name));
			// swap all child nodes
			foreach ($this->_pos->childNodes as $c)
				$p->appendChild($c->cloneNode(true));
			// swap attributes
			foreach ($this->_pos->attributes as $a)
				$p->setAttribute($a->nodeName, $a->nodeValue);
			// replace node
			$this->_pos = $this->_pos->parentNode->replaceChild($p, $this->_pos);
		}

		$this->_xpath = '';
		$this->_upd++;
	}

	/**
	 * 	Get current node value
	 *
	 * 	@return	- Current node value
	 */
	public function getVal(): string {

		// check for supported nodes
		if (!self::hasChild(XML_TEXT_NODE) && !self::hasChild(XML_CDATA_SECTION_NODE)) {

			Msg::InfoMsg('['.$this->_pos->nodeName.'] = ""');
			return '';
		}

		Msg::InfoMsg('['.$this->_pos->nodeName.'] = "'.$this->_pos->nodeValue.'"');

		return self::cnvStr(strval($this->_pos->nodeValue), false);
	}

	/**
	 * 	Set current node value
	 *
	 * 	@param	- New value to set
	 */
	public function setVal(?string $val): void {

		Msg::InfoMsg('['.$this->_pos->nodeName.'] "'.str_replace([ "\n", "\r" ], [ '.',  '' ], ($val ? $val : '')).'"');

		$this->_pos->nodeValue = ($val ? self::cnvStr($val) : $val);

		$this->_upd++;
	}

	/**
	 * 	Delete variable
	 *
	 * 	@param	- Name of variable; null = Delete current node
	 * 	@param	- true = Delete all; false = Delete first found field
	 * 	@return	- true = Ok; false = Not found
	 */
	public function delVar(?string $name = null, bool $all = true): bool {

		$this->_upd++;

		if (!$name) {

			Msg::InfoMsg('['.$this->_pos->nodeName.'] - all child nodes');
			$p = $this->_pos;
			if ($this->_pos = $this->_pos->parentNode)
    			$this->_pos->removeChild($p);
			return true;
		}

		$p = $this->_doc->getElementsByTagName($name);
		if ($p->length) {

			if ($all) {

				while ($p->length)
					$p->item(0)->parentNode->removeChild($p->item(0));
			} else
				$p->item(0)->parentNode->removeChild($p->item(0));
			Msg::InfoMsg('['.$name.'] - '.($all ? 'all nodes' : 'first node'));
			return true;
		}

		if (!$all)
			Msg::InfoMsg('['.$name.'] - not found');

		return false;
	}

	/**
	 * 	Get variable
	 *
	 * 	@param	- Name of variable
	 * 	@param 	- true = Search whole document; false = Search from current position
	 * 	@return	- Variable content or null = Not found
	 */
	public function getVar(string $name, bool $top = true): ?string {

		$p = $top ? $this->_doc : $this->_pos;
		if (!$p) {

			Msg::WarnMsg('-- ['.$name.'] from '.($top ? 'top' : 'current').' position - No position');
			return null;
		}

		$p = $p->getElementsByTagName($name);
		if (!$p->length) {

			Msg::InfoMsg('['.$name.'] from '.($top ? 'top' : 'current').' position - Not found');
			return null;
		} else
			$this->_pos = $p->item(0);

		$val = self::getVal();

		if (is_array($val))
			Msg::InfoMsg($val, '['.$name.'] from '.($top ? 'top' : 'curremt').' position');
	    else {

	        $v = str_replace([ "\n", "\r" ], [ '.', '' ], $val);
			if (strlen($v) > 128)
                $v = substr($v, 0, 128).' ['.strlen($v).'-CUT@128]';
			Msg::InfoMsg('['.$name.'] from '.($top ? 'top' : 'current').' position = "'.$v.'"');
	    }

		return $val;
	}

	/**
	 * 	Add variable
	 *
	 * 	@param	- Name of variable
	 * 	@param	- String value to store. null = A new sub record is created (default)
	 * 	@param	- true = Save data as CDATA; false = Ignore
	 * 	@param	- Optional attributes to add [key => Val]
	 */
	public function addVar(string $name, ?string $val = null, bool $cdata = false, array $attr = []): void {

		$this->_upd++;

		// if node has text content, switch to parent
		if (self::hasChild(XML_TEXT_NODE) && isset($this->_pos->parentNode))
			$this->_pos = $this->_pos->parentNode;

		if ($val === null) {

			Msg::InfoMsg('['.$name.']');
			$p = $this->_pos = $this->_pos->appendChild($this->_doc->createElement($name));
		} else {

		    // convert characters to internal format
			if (!$cdata)
		    	$val = self::cnvStr($val);

			if ($cdata) {

				Msg::InfoMsg('['.$name.'] "'.str_replace([ "\n", "\r"] , [ '.', '' ], $val).'" as "CDATA"');
				$this->_pos = $p = $this->_pos->appendChild($this->_doc->createElement($name));
				$p = $this->_pos;
				$this->_pos->appendChild($this->_doc->createCDATASection($val));
			} else {

       	        $v = str_replace([ "\n", "\r" ], [ '.', '' ], $val);
       			if (strlen($v) > 128)
					$v = substr($v, 0, 1280).' ['.strlen($v).'-CUT@128]';
				Msg::InfoMsg('['.$name.'] "'.$v.'"');
				if (strlen($val))
					$p = $this->_pos = $this->_pos->appendChild($this->_doc->createElement($name, $val));
				else
					$p = $this->_pos->appendChild($this->_doc->createElement($name));
			}
		}

		// swap attributes
		if (is_array($attr)) {

		    foreach ($attr as $k => $v)
   	       		$p->setAttribute($k, self::cnvStr(strval($v)));
		}
	}

	/**
	 * 	Update (or add) variable
	 *
	 * 	@param	- Name of variable
	 * 	@param	- String value to store
	 * 	@param 	- true = Search whole document; false = Search from current position
	 * 	@return	- Old value stored
	 */
	public function updVar(string $name, string $val, bool $top = true): string {

		if (($v = self::getVar($name, $top)) === null) {

			if (is_array($val))
				Msg::InfoMsg($val, '['.$name.'] = "array()" from '.($top ? 'top' : 'current').' position');
			else {

    	        $v = str_replace([ "\n", "\r" ], [ '.', '' ], $val);
    			if (strlen($v) > 128)
                    $v = substr($v, 0, 1280).' ['.strlen($v).'-CUT@128]';
			    Msg::InfoMsg('['.$name.'] "'.$v.'" from '.($top ? 'top' : 'current').' position');
			}
			self::addVar($name, $val);
		} else {

			if (is_array($val))
				Msg::InfoMsg($val, '['.$name.'] "array()" from '.($top ? 'top' : 'current').' position');
			else {

    	        $v = str_replace([ "\n", "\r" ], [ '.', '' ], $v);
    			if (strlen($v) > 128)
                    $v = substr($v, 0, 1280).' ['.strlen($v).'-CUT@128]';
			    Msg::InfoMsg('['.$name.'] "'.$v.'" from '.($top ? 'top' : 'current').' positon');
			}
			self::setVal($val);
		}

		return strval($v);
	}

	/**
	 * 	Add comment
	 *
	 * 	@param	- Comment
	 */
	public function addComment(string $text): void {

		$this->_pos->appendChild($this->_doc->createComment(' '.$text.' '));
	}

	/**
	 * 	Search variable in object
	 *
	 * 	@param	- xpath query string
	 * 	@param 	- true = Search whole document; false = Search from current position
	 * 	@return	- Number of items found
	 */
	public function xpath(string $xpath, bool $top = true): int {

		$this->_xpath = new \DOMXPath($this->_doc);

		$this->_list = [];
		if (!($l = $this->_xpath->query($xpath, $top ? $this->_doc : $this->_pos))) {

			Msg::WarnMsg('"'.$xpath.'" from '.($top ? 'top' : 'current').' position - Failed');
		    return 0;
		}

		for ($i=0; $i < $l->length; $i++)
			$this->_list[] = $l->item($i);

		Msg::InfoMsg('"'.$xpath.'" from '.($top ? 'top' : 'current').' position - '.count($this->_list).' elements found');

		return count($this->_list);
	}

	/**
	 * 	Search value in object and get parent
	 *
	 * 	@param	- Node name
	 * 	@param	- Node value
	 * 	@param	- Optional parent node path (e.g. /ab/de/cd/)
	 * 	@param 	- true = Search whole document; false = Search from current position
	 * 	@return	- Number of items found
	 */
	public function xvalue(string $name, string $val, ?string $par = null, bool $top = true): int {

		if (!$this->_xpath)
			$this->_xpath = new \DOMXPath($this->_doc);

		// nothing found
		$this->_list = [];

		// locate all nodes in document
		if (!($l = $this->_xpath->query('//'.$name.'/.', $top ? $this->_doc : $this->_pos))) {

			Msg::ErrMsg('"'.$name.'", "'.$val.'", "'.$par.'" from '.($top ? 'top' : 'curremt').' position - Failed');
			return 0;
		}

		$path = $par ? array_reverse(explode('/', substr($par, 1))) : [];

		// set termination flag
		$path[] = '#';

		// find value
		$val = self::cnvStr($val);
		for ($i=0; $i < $l->length; $i++) {
			if (strcasecmp($l->item($i)->nodeValue, $val))
				continue;
			$p = $l->item($i)->parentNode;
			foreach ($path as $n) {

				if (!$n)
					continue;
				if ($n == '#')
					break;
				if ($n != $p->nodeName)
					break;
				$p = $p->parentNode;
			}
			if ($n == '#')
				$this->_list[] = $l->item($i)->parentNode;
		}
		Msg::InfoMsg('"'.$name.'", "'.$val.'", "'.$par.'" from '.($top ? 'top' : 'current').' position- '.
				   count($this->_list).' elements found');

		return count($this->_list);
	}

	/**
	 * 	Check if XML node has valid child nodes
	 *
	 * 	@param	- Node type; 0 = Any
	 * 	@return	- true = Yes; false = No
	 */
	public function hasChild(int $typ = XML_ELEMENT_NODE): bool {

		if ($this->_pos->hasChildNodes()) {

			foreach ($this->_pos->childNodes as $c) {

				if ($c->nodeType == $typ || $typ === 0) {
					Msg::InfoMsg('['.$this->_pos->nodeName.'] true');
					return true;
				}
			}
		}
		Msg::InfoMsg('['.$this->_pos->nodeName.'] false');

		return false;
	}

	/**
	 * 	Get XML_ELEMENT_NODE child nodes
	 *
	 * 	@param	- Name of variable to search or null for current position
	 * 	@param	- true = Search from top; false = Search from current position
	 * 	@return - Number of child(s) found
	 */
	public function getChild(?string $name = null, bool $top = true): int {

		$this->_list = [];

		if ($name && ($this->_pos->nodeName != $name || $top)) {

			if (self::getVar($name, $top) === null) {

				Msg::InfoMsg('['.$name.'] from '.($top ? 'top' : 'current').' position - '.count($this->_list).
						   ' elements found');
				return 0;
			}
		}

		if (self::hasChild()) {
			foreach ($this->_pos->childNodes as $node) {

				if ($node->nodeType == XML_ELEMENT_NODE)
					$this->_list[] = $node;
			}
		}

		Msg::InfoMsg('['.$name.'] from '.($top ? 'top' : 'current').' position - '.count($this->_list).' elements found');

		return count($this->_list);
	}

	/**
	 *	Set position to item from list
	 *
	 *	@return	- Item value; null = No more items
	 */
	public function getItem(): ?string {

		if (count($this->_list)) {

			$this->_pos = array_shift($this->_list);
			$rc = self::getVal();
			Msg::InfoMsg('['.$this->_pos->nodeName.'] true');
			return $rc;
		}

		Msg::InfoMsg('['.$this->_pos->nodeName.'] false');

		return null;
	}

	/**
	 * 	Get attribute from current node
	 *
	 * 	@param	- Optional: Attribute name or (null for all)
	 * 	@return	- Attribute content or [ $name => $value ]
	 */
	public function getAttr(?string $name = null) {

		if (!$name) {

			$val = [];
			if ($this->_pos->hasAttributes()) {

				foreach ($this->_pos->attributes as $attr) {

				    $val[$attr->nodeName] = self::cnvStr($attr->nodeValue, false);
					Msg::InfoMsg('['.$this->_pos->nodeName.'] -> "'.$attr->nodeName.'"="'.$attr->nodeValue.'"');
				}
			}
		} else {

			if ($this->_pos->nodeType != XML_ELEMENT_NODE)
				$val = [];
			else
			    $val = self::cnvStr($this->_pos->getAttribute($name), false);
			Msg::InfoMsg('['.$this->_pos->nodeName.'] -> "'.$name.'"="'.$val.'"');
		}

		return $val;
	}

	/**
	 * 	Set attribute to current node
	 *
	 * 	@param	- [ Attribute name, Attribute value ]
	 */
	public function setAttr(array $attr): void {

		$this->_upd++;
		foreach ($attr as $k => $v) {

    		Msg::InfoMsg('['.$this->_pos->nodeName.'] "'.$k.'" = "'.$v.'"');
    		$this->_pos->setAttribute($k, self::cnvStr($v));
		}
	}

	/**
	 * 	Delete attribute
	 *
	 * 	@param	- Attribute name
	 */
	public function delAttr(string $name): void {

		$this->_upd++;

		Msg::InfoMsg('['.$this->_pos->nodeName.'] "'.$name.'"');

		$this->_pos->removeattribute($name);
	}

	/**
	 * 	Load XML document from file - Warning: You need to take care about cnvStr()!
	 *
	 * 	@param	- File name
	 * 	@param	- true = Append to existing document; false = Replace content
	 * 	@return	- true = Ok; false = Error
	 */
	public function loadFile(string $file, bool $append = false): bool {

		$this->_upd = 0;

		if (!file_exists($file)) {

			ErrorHandler::getInstance()->Raise(10001, 'loadXML() - file not found "'.$file.'"');
			return false;
		}

		$wrk = @file_get_contents($file);

		// convert all namespace tags "xmlsns=" to "xml-ns="
		$wrk = str_replace([ 'xmlns=', ], [ 'xml-ns=', ], $wrk);

		// remove XML declaration (REQUIRED)
		if ($append) {

			$wrk = preg_replace('/<\?xml.*\?>/', '', $wrk);
			// remove comments
			$wrk = preg_replace('/<!--(.*?)-->/si', '', $wrk);
			// remove DOCTYPE
			$wrk = preg_replace('/(.*)(<!.*">)(.*)/', '${1}${3}', $wrk);
		}

		// stripp off blank lines and HTML new lines
		$wrk = preg_replace('/>\s+</', '><', $wrk);

		if ($append) {

			$seg = $this->_doc->createDocumentFragment();
			if ($rc = $seg->appendXML($wrk))
				$this->_doc->appendChild($seg);
		} else {

		    $rc = $this->_doc->loadXML($wrk);
			$this->_pos = $this->_doc;
			$this->_xpath = '';
		}

		if ($rc)
			Msg::InfoMsg('"'.$file.'" - '.($append ? 'Appending' : 'Replace XML content'));
		else {

			Util::Save(__FUNCTION__.'%d.xml', $wrk);
			if (!Config::getInstance()->getVar(Config::DBG_SCRIPT))
				ErrorHandler::getInstance()->Raise(10001, 'loadXML() error for "'.$file.'"');
		}

		return $rc;
	}

	/**
	 * 	Save XML document to file
	 *
	 * 	@param	- File name
	 * 	@param	- true = Format output; false = Compress output
	 * 	@return	- true = Ok; false = Error
	 */
	public function saveFile(string $file, bool $fmt = false): bool {

		$this->_doc->formatOutput = $fmt;

   		// convert all namespace tags "xml-ns" to "xmlns"
		$wrk = str_replace([ 'xml-ns=', 'xml-ns:', ], [ 'xmlns=',  'xmlns:', ], $this->_doc->saveXML($this->_doc));

		// do not use DOMDocument::save - it sometimes crashes without any reason
		// $rc = $this->_doc->save($file);
		$rc = file_put_contents($file, $wrk);

		if ($rc === false)
			Msg::ErrMsg('Saving XML object to file "'.$file.'" - failed');
		else
			Msg::InfoMsg('Saving to file "'.$file.'" - ('.$rc.') bytes');

		return $rc === false ? $rc : true;
	}

	/**
	 * 	Append one node to another
	 *
	 * 	@param	- Node to append
	 * 	@param	- true = Append whole document; false = Append from current position
	 * 	@param	- true = As first child node; false = As child node
	 */
	public function append(XML &$node, bool $top = true, bool $first = false): void {

		$this->_upd++;

		$xml = new \DOMDocument('1.0', 'UTF-8');
		$rc  = $xml->loadXML($node->saveXML($top));
		if (!$rc) {

			Util::Save(__FUNCTION__.'%d.xml', $node->saveXML($top));
			ErrorHandler::getInstance()->Raise(10001, 'loadXML() error');
			return;
		}

		// if node has text content, switch to parent
		if (self::hasChild(XML_TEXT_NODE))
			$this->_pos = $this->_pos->parentNode;

		// does already have child nodes
		if ($first && self::hasChild()) {

			foreach ($this->_pos->childNodes as $n) {
				if ($n->nodeType == XML_ELEMENT_NODE)
					break;
			}
			$n->parentNode->insertBefore($this->_doc->importNode($xml->documentElement, true), $n);
		} else
			$this->_pos->appendChild($this->_doc->importNode($xml->documentElement, true));

    	$this->_cp = null;
	}

	/**
	 * 	Duplicate current node
	 *
	 * 	@param 	- Number of additional nodes
	 */
	public function dupVar(int $cnt): void {

		if ($cnt < 1)
			return;

		$p   = self::savePos();
		$dup = new XML($this, false);
		$tag = $t = self::getName();
		self::setParent();
		for ($n=1; $n <= $cnt; $n++) {

			$dup->getVar($t);
			$dup->setName($t = $tag.$n);
			$dup->setTop();
			self::append($dup, true, false);
		}
		for ($n=1; $n <= $cnt; $n++) {

			if (self::getVar($tag.$n) === null)
				break;
			self::setName($tag);
		}
		self::restorePos($p);
	}

	/**
	 * 	Convert object to HTML output
	 *
	 * 	@param	- true = From current position; false = Current position
	 * 	@param 	- Optional line prefix
	 * 	@return	- HTML string
	 */
	public function mkHTML(bool $top = true, ?string $pref = null): string {

		$wrk = str_replace("\r", '&#13;', self::saveXML($top, true));
		$str = '';
		$inco = false;

		$a = explode("\n", $wrk);
		if (!strlen($a[count($a) - 1]))
			array_pop($a);

		foreach ($a as $rec) {

			if (strpos($rec, '<!--') !== false)
				$inco = true;
			if ($inco)
				$str .= '<code style="'.Config::CSS_INFO.'">'.$pref.str_replace(' ', '&nbsp;',
						htmlspecialchars($rec)).'</code><br>';
			else {

				if (strlen($rec) > 1024)
					$rec = substr($rec, 0, 1024).'['.strlen($rec).'-CUT@1024]';
				$str .= '<code style="'.Config::CSS_CODE.'">'.$pref.str_replace(' ', '&nbsp;',
						htmlspecialchars($rec)).'</code><br>';
			}
			if (strpos($rec, '-->') !== false)
				$inco = false;
		}

		return $str;
	}

	/**
	 * 	Set position to parent node
	 *
	 * 	@return - true = Ok; false = No parent available
	 */
	public function setParent(): bool {

		if (!$this->_pos->parentNode)
			return false;

		$this->_pos = $this->_pos->parentNode;
		$this->_xpath = '';

		return true;
	}

	/**
	 * 	Set position to next node
	 */
	public function setNext(): void {

		if ($this->_pos->firstChild) {

			$this->_pos = $this->_pos->firstChild;
			return;
		}

		$pos = $this->_pos;

		do {

			if (!($pos = $pos->parentNode))
				return;
		} while(!$pos->nextSibling && $pos->nodeType != XML_DOCUMENT_NODE);

		if ($pos->nodeType != XML_DOCUMENT_NODE)
			$this->_pos = $pos;

		do {

			if ($this->_pos->nextSibling)
				$this->_pos = $this->_pos->nextSibling;
		} while ($this->_pos->nextSibling && $this->_pos->nodeType != XML_ELEMENT_NODE);

		$this->_xpath = '';
	}

	/**
	 * 	Save position
	 *
	 * 	@return	- Current position
	 */
	public function savePos(): array {

		return [ $this->_pos, $this->_list, $this->_xpath ];
	}

	/**
	 * 	Restore position
	 *
	 * 	@param	- Position to restore
	 */
	public function restorePos(array $pos): void {

		list( $this->_pos, $this->_list, $this->_xpath ) = $pos;
	}

	/**
	 * 	Swap XML to associative array
	 *
	 *  @param  - Optional level
	 * 	@return - [] = [ T=Tag; P=Parm; D=Data ]
	 */
	public function XML2Array(int $lvl = 0): array {

		$recs = $rec = [];

		$p = $this->savePos();

		$rec['T'] = $this->getName();
		$rec['P'] = $this->getAttr();
		$this->getChild(null, false);
		if(!$lvl) {

			$rec['D'] = self::XML2Array(1);
			$recs[] = $rec;
		} else {

			while (($v = $this->getItem()) !== null) {

				$rec['T'] = $this->getName();
				$rec['P'] = $this->getAttr();
				if ($this->hasChild())
					$rec['D'] = self::XML2Array(1);
				else
					$rec['D'] = $v;
				$recs[] = $rec;
			}
		}
		$this->restorePos($p);

		return $recs;
	}

	/**
	 * 	Swap associative array XML
	 *
	 * 	@param	- [ [T] => Tag; [P] => Parm; [D] => Data ]
	 */
	public function Array2XML(array $arr): void {

		// single record?
		if (isset($arr['T']))
			$arr = [ $arr ];

		// walk down all arrays
		foreach ($arr as $unused => $v) {

			if (!isset($v['T'])) {

				self::Array2XML($v);
				continue;
			}

			if (!is_array($v['D']))
				self::addVar($v['T'], $v['D'], false, $v['P']);
			else {

				$p = self::savePos();
				self::addVar($v['T'], null, false, $v['P']);
				self::Array2XML($v['D']);
				self::restorePos($p);
			}
		}
		$unused; // disable Eclipse warning
	}

	/**
     *	Set Activesync code page
     *
     *	@param  - Code page number
     *	@param  - true = Force setting; false = Only change if required
     *	@return - [ 'xml-ns' => code page name ]
     */
    public function setCP(int $no, bool $force = false): array {

    	$old = ($force ? '' : $this->_cp);
		$this->_cp = self::CP[$no];

		return $old != $this->_cp ? [ 'xml-ns' => $this->_cp ] : [];
    }

	/**
	 * 	Set position to top
	 */
	public function setTop(): void {

		$this->_doc->preserveWhiteSpace = false;
		$this->_pos = $this->_doc;
		$this->_xpath = '';
	}

	/**
	 *  Convert HTML entities and filter unallowed control characters but leave \n and \t
	 *
	 *  @param  - Text to convert (must be UTF-8 encoded)
	 *  @param  - true = Convert HTML entities to internal; false = Decode HTML entities to external
	 *  @return - Converted string
	 */
	static function cnvStr(string $str, bool $cnv = true): string {

	    if ($cnv) {

	    	// https://www.php.net/manual/en/parle.regex.unicodecharclass.php
	    	if (($wrk = preg_replace('/(?!\n|\t)[\p{Cc}]/u', '', $str)) === null) {

	    		if (!Config::getInstance()->getVar(Config::DBG_SCRIPT)) {

	    			Msg::ErrMsg('cnvStr() - error converting string');
	    			Config::getInstance()->updVar(Config::DBG_SCRIPT, 'Exit');
	    		}
	    		return bin2hex($str);
	    	}
			return str_replace(self::ENTITY[0], self::ENTITY[1], $wrk);
	    } else
			return str_replace(self::ENTITY[1], self::ENTITY[0], $str);
	}

}
