<?php
declare(strict_types=1);

/*
 * 	RFC6868: Parameter Value Encoding in iCalendar and vCard
 *
 *	@package	sync*gw
 *	@subpackage	MIME support
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\mime;

class mimRFC6868 extends mimRFC5234 {

    // mapping
    const MAP = [
            [ '^n',     '^^',   '^\'' ],
            [ "\r\n",   '^',    '"'   ],
    ];

   /**
     * 	Singleton instance of object
     * 	@var mimRFC6868
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mimRFC6868 {

	   	if (!self::$_obj)
            self::$_obj = new self();

		return self::$_obj;
	}

	/**
	 * 	Decode data (Augmented Backus-Naur Form - ABNF)
	 *
	 * 	@param	- Data buffer
	 * 	@return	- [ 'T' => Tag; 'P' => [ Parm => Val ]; 'D' => Data ]
	 */
	public function decode(string $data): array {

        $recs = parent::decode($data);

        foreach ($recs as $key => $rec) {

            if (isset($rec['P'])) {

        	    foreach ($rec['P'] as $k => $v)
            		$rec['P'][$k] = str_replace(self::MAP[0], self::MAP[1], $v);
    	       $rec[$key] = $rec;
            }
        }

   		return $recs;
	}

	/**
	 * 	Encode data (encode parameter value)
	 *
	 * 	@param	- [ 'T' => Tag; 'P' => [ Parm => Val ]; 'D' => Data ]
	 * 	@return	- Converted data string
	 */
	public function encode(array $rec): string {

	    foreach ($rec['P'] as $k => $v)
    		$rec['P'][$k] = str_replace(self::MAP[1], self::MAP[0], $v);

        return parent::encode($rec);
	}

}
