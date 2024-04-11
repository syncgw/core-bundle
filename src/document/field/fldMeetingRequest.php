<?php
declare(strict_types=1);

/*
 *  MeetingRequest field handler
 *
 *	@package	sync*gw
 *	@subpackage	Tag handling
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\field;

use syncgw\lib\XML;

class fldMeetingRequest extends fldHandler {

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	const TAG 				= 'MeetingRequest';

    const ASM_SUB			= [
	        'BusyStatus'				=> 'fldBusyStatus',
		    'DisallowNewTimeProposal'	=> 'fldDisallowNewProposal',
    		'EndTime'					=> 'fldEndTime',
			// 'AllDayEvent'    		=> Handled by fldEndTime
		    'Forwardees'				=> 'fldMailOther',
    		'GlobalObjId'				=> 'fldGlobalId',
    		'InstanceType'				=> 'InstanceType',
    	    'Location'					=> 'fldLocation',
		    'MeetingMessageType'		=> 'MeetingMessageType',
    		'Organizer'					=> 'fldOrganizer',
		    'ProposedEndTime'			=> 'fldEndTimeProposal',
    		'ProposedStartTime'			=> 'fldStartTimeProposal',
    		'RecurrenceId'				=> 'fldRecurrenceId',
            'Recurrences'				=> 'fldRecurrence',
			'Reminder'					=> 'fldAlarm',
    		'ResponseRequested'			=> 'ResponseRequested',
			'Sensitivity'				=> 'fldClass',
			'StartTime'					=> 'fldStartTime',
			'Timezone'					=> 'fldTimezone',
    		'Uid'						=> 'fldUid',
    ];

   	/**
     * 	Singleton instance of object
     * 	@var fldMeetingRequest
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): fldMeetingRequest {

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
					$field->import($typ, $ver, $xpath.'/'.$key, $ext, $ipath.'/', $int);
				} elseif ($val = $ext->getVar($key, false))
  		        	$int->addVar($key, $val);
				$rc = true;
            	$ext->restorePos($xp);
			}
			$int->restorePos($ip);
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

		$rc = false;

		if (!$int->xpath($ipath.self::TAG, false))
			return $rc;

		switch ($typ) {
		case 'application/activesync.mail+xml':

			if (!class_exists($class = 'syncgw\\activesync\\masHandler', false))
				break;

			$mver = $class::getInstance()->callParm('BinVer');
			$ext->addVar('MeetingRequest', null, false, $ext->setCP(XML::AS_MAIL));

        	while ($int->getItem() !== null) {

		        $ip = $int->savePos();
        		foreach (self::ASM_SUB as $key => $class) {

					if (substr($class, 0, 3) == 'fld') {

						$class = 'syncgw\\document\\field\\'.$class;
	               		$field = $class::getInstance();
	                    $field->export($typ, $ver, '', $int, self::TAG.'/'.$key, $ext);
					} elseif ($val = $int->getVar($key, false)) {

						// 0 A single appointment.
						// 1 A master recurring appointment.
						// 2 A single instance of a recurring appointment.
						// 3 An exception to a recurring appointment.
						// 4 An orphan instance of a recurring appointment
						if ($class == 'InstanceType' && $mver < 16.0 && $val == '4')
							continue;

						if ($key == 'MeetingMessageType') {

							if ($mver < 14.1)
								continue;
							$ext->addVar($class, $val, false, $ext->setCP(XML::AS_MAIL2));
						} else
							$ext->addVar($class, $val, false, $ext->setCP(XML::AS_MAIL));

						$int->restorePos($ip);
					}
					$int->restorePos($ip);
	            }
	            $rc = true;
       		}
       		break;

		default:
			break;
		}

		return $rc;
	}

}
