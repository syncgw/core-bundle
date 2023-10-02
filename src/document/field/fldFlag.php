<?php
declare(strict_types=1);

/*
 *  Flag field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\XML;

class fldFlag extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'Flag';

    const ASM_SUB			= [
	        'Subject'						=> 'fldSummary',
    		'Status'						=> 'fldStatus',
    		'FlagType'						=> 'FlagType',
    		'DateCompleted'					=> 'fldCompleted',
        	'CompleteTime'					=> 'fldCompleted',
        	'StartDate'						=> 'fldStartTime',
		    'UtcStartDate'					=> 'fldStartTime',
		    'DueDate'						=> 'fldDueDate',
	        'UtcDueDate'					=> 'fldDueDate',
	        'ReminderTime'					=> 'fldAlarm',
			// 'ReminderSet'				// Handled in fldAlarm
			'OrdinalDate'					=> 'fldOrdinal',
			'SubOrdinalDate'				=> 'fldOrdinalSub',
    ];

   	/**
     * 	Singleton instance of object
     * 	@var fldFlag
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldFlag {

		if (!self::$_obj) {

            self::$_obj = new self();
			// clear tag deletion status
			unset(parent::$Deleted[self::TAG]);
		}

		return self::$_obj;
	}

 	/**
	 * 	Import field
	 *
	 *  @param  - MIME type
	 *  @param  - MIME version
	 *	@param  - External path
	 *  @param  - [[ 'T' => Tag; 'P' => [ Parm => Val ]; 'D' => Data ]] or external document
	 *  @param  - Internal path
	 * 	@param 	- Internal document
	 *  @return - true = Ok; false = Skipped
	 */
	public function import(string $typ, float $ver, string $xpath, $ext, string $ipath, XML &$int): bool {

		$rc    = false;
		$ipath .= self::TAG;

		switch ($typ) {
		case 'application/activesync.mail+xml':
			if (!$ext->xpath($xpath, false))
	        	break;

	        self::delTag($int, $ipath);
			$ip = $int->savePos();
	        $int->addVar(self::TAG);

	        $xp = $ext->savePos();
    	    foreach (self::ASM_SUB as $key => $class) {

	        	if (substr($class, 0, 3) == 'fld') {

               		$class = 'syncgw\\document\\field\\'.$class;
	               	$field = $class::getInstance();
               		$t = explode(',', $key);
               		if (isset($t[1]))
               			$t = $xpath.'/'.$t[0].','.$xpath.'/'.$t[1];
               		else
               			$t = $xpath.'/'.$key;
					if ($field->import($typ, $ver, $t, $ext, $ipath.'/', $int))
						$rc = true;
	        	} elseif ($val = $ext->getVar($key, false)) {

  		        	$int->addVar($key, $val);
					$rc = true;
	        	}
	        	$ext->restorePos($xp);
	       	}
    	   	$int->restorePos($ip);
			if (!$rc)
				$int->delVar(self::TAG, false);

		default:
			break;
		}

		return $rc;
	}

	/**
	 * 	Export field
	 *
	 *  @param  - MIME type
	 *  @param  - MIME version
 	 *	@param  - Internal path
	 * 	@param 	- Internal document
	 *  @param  - External path
	 *  @param  - External document
	 *  @return - [[ 'T' => Tag; 'P' => [ Parm => Val ]; 'D' => Data ]] or false=Not found
	 */
	public function export(string $typ, float $ver, string $ipath, XML &$int, string $xpath, ?XML $ext = null) {

		$rc  = false;
		if (!class_exists($class = 'syncgw\\activesync\\masHandler'))
			return $rc;

		$ver = $class::getInstance()->callParm('BinVer');

		if (!$int->xpath($ipath.self::TAG, false))
			return $rc;

		switch ($typ) {

		case 'application/activesync.mail+xml':
			$xp = $ext->savePos();
			$ext->addVar('Flag', null, false, $ext->setCP(XML::AS_MAIL));

        	while ($int->getItem() !== null) {

		        $ip = $int->savePos();
        		foreach (self::ASM_SUB as $key => $class) {

					if (substr($class, 0, 3) == 'fld') {

						$class = 'syncgw\\document\\field\\'.$class;
	               		$field = $class::getInstance();

	               		if (strpos($key, 'Date') !== false || strpos($key, 'Subj') !== false)
	               			$typ = 'application/activesync.task+xml';
	                    $field->export($typ, $ver, '', $int, self::TAG.'/'.$key, $ext);
	                    $typ = 'application/activesync.mail+xml';
					} elseif ($val = $int->getVar($key, false)) {

						if ($ver < 12.0)
							break;
						$ext->addVar($class, $val);
						$int->restorePos($ip);
					}
					$int->restorePos($ip);
	            }
	            $rc = true;
       		}
       		$ext->restorePos($xp);
       		break;

		default:
			break;
		}

		return $rc;
	}

}
