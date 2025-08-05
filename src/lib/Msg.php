<?php
declare(strict_types=1);

/*
 * 	Message handler class
 *
 *	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\lib;

class Msg {

	/**
     * 	Write message
     *
     * 	@param	- object, [] or string
     * 	@param	- Title
     * 	@param	- Position in string
     * 	@param	- Limit length to show
     *  @param  - Output color
     */
    static public function InfoMsg($obj, string $title = '', int $pos = -1, int $limit = 0,
    							   string $color = Config::CSS_CODE): void {

    	static $_gui = -1;

    	$cnf = Config::getInstance();

    	// GUI status check done?
    	if (is_int($_gui) && $_gui == -1) {

    		if ($cnf->getVar(Config::DBG_SCRIPT))
				$_gui = null;
			elseif ($cnf->getVar(Config::HANDLER) == 'GUI')
	            $_gui = class_exists($class = 'syncgw\\gui\\guiHandler') ?
									 $class::getInstance() : null;
    	}

    	if (is_int($_gui) && $_gui == -1)
    		return;

        // check for class / functions exclusions from debugging
		$call = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$ok = false;
		$inc = array_flip($cnf->getVar(Config::DBG_INCL));
		$exc = array_flip($cnf->getVar(Config::DBG_EXCL));

	    if (strcmp($color, Config::CSS_ERR)) {

	    	if (isset($exc['*']))
	    		return;

	    	foreach ($call as $k => $c) {

	            // normalize array
	            if (!isset($c['class']))
	                $c['class'] = $call[$k]['class'] = '';
	            if (!isset($c['function']))
	                $c['class'] = $call[$k]['function'] = '';

	            foreach ($inc as $chk => $unused) {

	            	if (strpos($c['class'], $chk) !== false || strpos($c['function'], $chk) !== false) {

	            		$ok = true;
	            		break;
	            	}
	            }
	            $unused; // disable Eclipse warning

	            if ($ok)
	            	break;

	            if (isset($exc[$c['class']]) ||
	                (isset($exc[$c['class'].':'.$c['function']]) && $exc[$c['class'].':'.$c['function']]) ||
	                (isset($exc[$c['function']]) && $exc[$c['function']]))
	                return;
	        }
        }

       	// get caller
        while (count($call) > 1 && !strcmp(substr($call[0]['file'], -7), 'Msg.php'))
            array_shift($call);
        $call = $call[0];
        $call['file'] = substr($call['file'], strlen($_SERVER['DOCUMENT_ROOT']) - 1 + 7);
		if (strlen($call['file']) > 50)
			$call['file'] = substr($call['file'], -50);
		// build output string
      	$msgs = null;

        // array?
        if (is_array($obj)) {

            ob_start();
       		print_r($obj);
       		$msg = ob_get_contents();
       		ob_end_clean();
            $msgs = explode("\n", str_replace("\r", '', $msg));
            array_pop($msgs);
        }
        // object?
        elseif (is_object($obj)) {

        	if (strpos(get_class($obj), 'syncgw\\') !== false)
    	        $msgs = explode("\n", $obj->saveXML(false, true));
        	else
        		$msgs = explode("\n", print_r($obj, true));
        }
		// it must be string
        else {

	       // dump string
	        if ($pos > -1 || $limit > 0) {

	           	// set limit
	       		$l = $obj ? strlen($obj) : 0;
	           	if ($limit)
	                $limit = $l > $limit ? $limit : $l;
	       		else
	           		$limit = $l;

	       		$wrk = sprintf('%08X', $pos);
	       		for ($str='', $hex='', $i=0; $i < $limit; $i++) {

	       			$c    = $obj[$pos++];
	           		$hex .= sprintf('%02X ', ord($c));
	       			if (preg_replace('/[\p{Cc}]/u', '', $c))
	       				$str .= $c == ' ' ? '.' : $c;
	       			else
	           			$str .= $c == "\n" ? '.' : ($c == '0' ? $c : '.');
	       			if (!(($i + 1) % 4)) {

	           			$hex .= '| ';
	    		     	$str .= ' | ';
	       			}
	           		if (!(($i + 1) % 20)) {

	           			$msgs[] = $wrk.'  '.$hex.'  '.$str;
	       				$wrk = sprintf('%08X', $pos);
	       				$hex = '';
	       				$str = '';
	       			}
	       		}

	       		// fill up
	       		while ($i++ % 20) {

	           		$hex .= '   ';
	    		    if (!($i % 4))
	       				$hex .= '| ';
	           	}
	           	$msgs[] = $wrk.'  '.$hex.'  '.$str;

	        } elseif (strpos(strval($obj), '<code') !== false)
	        	$msgs = explode('<br>', $obj);
	        else
	        	$msgs[] = str_replace([ "\r", "\n" ], [ '', '<br>' ], strval($obj));
        }

        // build prefix (filename:line no)
	    $pref = '['.(isset($call['file']) ? $call['file'].':'.$call['line'] : '').']';

	    if ($color == Config::CSS_ERR || $color == Config::CSS_WARN) {

	    	if ($title)
		    	$title = '+++ '.$title;
			else
				$msgs[0] = '+++ '.$msgs[0];
	    }

	    if ($_gui) {

			if (!$title)
       			$_gui->putMsg('<div style="width:423px;float:left;">'.XML::cnvStr($pref).'</div>'.
       						 '<div style="float:left;">'.XML::cnvStr($msgs[0]).'</div>', $color);
       		else {
       			$wrk = '';
       			foreach ($msgs as $msg) {

       				if (strpos($msg, '<code') !== false) {

       					$wrk = implode('<br>', $msgs);
       					break;
      				}
       				$wrk .= XML::cnvStr($msg).'<br>';
       			}
         		$_gui->putQBox('<div style="float:left;"><div style="float:left;'.
         					  $color.'width:400px;">'.XML::cnvStr($pref).'</div>'.
   			    			  '<div style="'.$color.'">'.XML::cnvStr($title).'</div></div>', '',
   			                  '<code style="'.$color.'">'.$wrk.'</code>', false, 'Msg');
       		}
       		return;
		}

	    // inject title?
	    if ($title) {

            $title = str_repeat('-', 15).' '.$title.' ';
			if (($l = 116 - strlen($title)) && $l > 0)
               	$title .= str_repeat('-', $l);
	    }

		if ($title)
			array_unshift($msgs, $title);

		// show messages
		foreach ($msgs as $msg) {

			if (strlen($msg) > 512)
				$msg = substr($msg, 0, 512).'['.strlen($msg).'-CUT@512]';

    	   	// show message
			echo '<div><div style="'.$color.'width:400px;float:left;">'.XML::cnvStr($pref).'</div>'.
			   	 '<div style="'.$color.'"> '.XML::cnvStr($msg).'</div>'.'</div>';
	    }

    }

    /**
     * 	Debug object (warning)
     *
     * 	@param	- object, [] or string
     * 	@param	- Title
     * 	@param	- Position in string
     * 	@param	- Limit length to show
     */
    static public function WarnMsg($obj, string $title = '', int $pos = -1, int $limit = 0): void {

    	self::InfoMsg($obj, $title, $pos, $limit, Config::CSS_WARN);
    }

    /**
     * 	Debug object (error)
     *
     * 	@param	- object, [] or string
     * 	@param	- Title
     * 	@param	- Position in string
     * 	@param	- Limit length to show
     */
    static function ErrMsg($obj, string $title = '', int $pos = -1, int $limit = 0): void {

    	self::InfoMsg($obj, $title, $pos, $limit, Config::CSS_ERR);
    }

 }