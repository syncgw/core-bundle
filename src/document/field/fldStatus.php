<?php
declare(strict_types=1);

/*
 *  Status field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\Msg;
use syncgw\lib\XML;

class fldStatus extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'Status';

	/*
	 status     = "STATUS" statparam] ":" statvalue CRLF

     statparam  = *(";" xparam)

     ;Status values for a "VEVENT"
     statvalue  = "TENTATIVE"           ;Indicates event is tentative.
                / "CONFIRMED"           ;Indicates event is definite.
                / "CANCELLED"           ;Indicates event was cancelled.

     ;Status values for "VTODO".
     statvalue  =/ "NEEDS-ACTION"       ;Indicates to-do needs action.
                / "COMPLETED"           ;Indicates to-do completed.
                / "IN-PROCESS"          ;Indicates to-do in process of
                / "CANCELLED"           ;Indicates to-do was cancelled.

     ;Status values for "VJOURNAL".
     statvalue  =/ "DRAFT"              ;Indicates journal is draft.
                / "FINAL"               ;Indicates journal is final.
                / "CANCELLED"           ;Indicates journal is removed.
	 */
	const RFCC_PARM			= [
		// description see fldHandler:check()
	    'text'			 	=> [
		  'VALUE'			=> [ 1, 'text ' ],
		  '[ANY]'			=> [ 0 ],
		],
	];
	const RFCC_VAL		 	= [
		'event'				=> 'TENTATIVE CONFIRMED CANCELLED ',
		'task'			 	=> 'NEEDS-ACTION COMPLETED IN-PROCESS CANCELLED ',
	];

	const AST_SUB           = 'X-PC';

	// [MS-ASEMAIL] 2.2.2.74 Status
	const ASM_MAP 			= [
		'CLEARED'			=> '0',		// The flag is cleared.
		'COMPLETED'			=> '1',		// The status is set to complete.
		'ACTIVE'			=> '2',		// The status is set to active.
	];

   	/**
     * 	Singleton instance of object
     * 	@var fldStatus
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldStatus {

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
		$map   = null;
		$ipath .= self::TAG;

		switch ($typ) {
		case 'text/calendar':
		case 'text/x-vcalendar':
			foreach ($ext as $rec) {

				if ($rec['T'] != $xpath)
					continue;

				if (strpos($rec['T'], 'VTODO')) {

					if ($rec['D'] == 'NEEDS ACTION') {

						$rec['D'] = 'NEEDS-ACTION';
						Msg::InfoMsg('['.$rec['T'].'] parameter "NEEDS ACTION" converted to "NEEDS-ACTION"');
					}
					$chk = self::RFCC_VAL['task'];
				} else
					$chk = self::RFCC_VAL['event'];
				if (strpos($chk, $rec['D']) === false) {

					Msg::InfoMsg('['.$rec['T'].'] - value "'.$rec['D'].'" invalid - dropping record');
					continue;
				}
				// check parameter
				parent::check($rec, self::RFCC_PARM['text']);
				parent::delTag($int, $ipath);
				unset($rec['P']['VALUE']);
				// add dummy percentage for AsTask
				$rec['P'][self::AST_SUB] = '0';
				$int->addVar(self::TAG, $rec['D'], false, $rec['P']);
				$rc = true;
	  		}
			break;

		case 'application/activesync.task+xml':
		    if ($ext->xpath($xpath, false)) {

		        $p = $int->savePos();
    			$int->xpath('/syncgw/Data/'.$ipath);
    			$int->getItem();
                $pc = $int->getAttr(self::AST_SUB);
                $int->restorePos($p);
				parent::delTag($int, $ipath);
		    } else
		        $pc = 0;

		    while (($val = $ext->getItem()) !== null) {

				$int->addVar(self::TAG, $val == '1' ? 'COMPLETED' : 'IN-PROCESS', false, [ self::AST_SUB => $pc ? $pc : '0' ]);
				$rc = true;
			}
			break;

		case 'application/activesync.mail+xml':
			if ($ext->xpath($xpath, false))
				parent::delTag($int, $ipath);

			while (($val = $ext->getItem()) !== null) {

				$map = array_flip(self::ASM_MAP);
				if (!isset($map[$val])) {

					Msg::WarnMsg('['.$xpath.'] invalid value "'.$val.'" - dropping record');
					continue;
				}
				$int->addVar(self::TAG, $map[$val], false, [ self::AST_SUB => '0' ]);
				$rc = true;
			}
			break;

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

		$rc   = false;
		$tags = explode('/', $xpath);
		$tag  = array_pop($tags);

		if (!$int->xpath($ipath.self::TAG, false))
			return $rc;

		switch ($typ) {
		case 'text/calendar':
		case 'text/x-vcalendar':

			$recs = [];
			while (($val = $int->getItem()) !== null) {

				$a = $int->getAttr();
				// $a['VALUE'] = 'TEXT';
				$recs[] = [ 'T' => $tag, 'P' => $a, 'D' => $val ];
			}
			if (count($recs))
				$rc = $recs;
			break;

		case 'application/activesync.task+xml':
			while (($val = $int->getItem()) !== null) {

				$ext->addVar($tag, $val == 'COMPLETED' ? '1' : '0', false, $ext->setCP(XML::AS_TASK));
				$rc	= true;
			}
			break;

		case 'application/activesync.mail+xml':
			if (!class_exists($class = 'syncgw\\activesync\\masHandler') ||
				$class::getInstance()->callParm('BinVer') < 12.0)
				break;

			while (($val = $int->getItem()) !== null) {

				$ext->addVar($tag, self::ASM_MAP[$val], false, $ext->setCP(XML::AS_MAIL));
				$rc	= true;
			}
			break;

		default:
			break;
		}

		return $rc;
	}

}
