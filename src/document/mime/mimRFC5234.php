<?php
declare(strict_types=1);

/*
 * 	RFC5234: Augmented BNF for Syntax Specifications: ABNF
 *
 *	@package	sync*gw
 *	@subpackage	MIME support
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\mime;

class mimRFC5234 extends mimRFC2425 {

    /**
     * 	Singleton instance of object
     * 	@var mimRFC5234
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mimRFC5234 {

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
	protected function decode(string $data): array {

		// unfold lines
		$recs = parent::decode($data);

		foreach ($recs as $k => $rec) {

			foreach ([ '%x', '%d', '%b' ] as $nasc) {

				$wrk = $rec['D'];
				$rec['D'] = '';

				// check for special character
				while (($pos = stripos($wrk, $nasc)) !== false) {

					$rec['D'] .= substr($wrk, 0, $pos);
					$pos += 2;
					$c = '';
					// get everything up to seperator
					for($m=strlen($wrk); $pos < $m && ($x = $wrk[$pos]) != ' ' && $x != '.' && $x != '%';) {

						if ($nasc == '%x' && (strpos('abcdefABCDEF0123456789', $x) === false) || strlen($c) == 2)
							break;
						if ($nasc == '%b' && strpos('01', $x) === false)
							break;
						if ($nasc == '%d' && strpos('0123456789', $x) === false)
							break;
						$c .= $x;
						$pos++;
					}
					if (!strlen($c)) {

						$rec['D'] .= $nasc;
						$wrk = substr($wrk, $pos);
						continue;
					}
					switch ($nasc) {
					// hex.
					case '%x':
						$c = hexdec($c);
						break;

					// binary
					case '%b':
						$c = bindec($c);
						break;

					// %d - decimal
					default:
						break;
					}

					// move buffer
					$wrk = substr($wrk, $pos);
					// check for concatenation
					if (strlen($wrk) && $wrk[0] == '.')
						$wrk = $nasc.substr($wrk, 1);
					// rebuild buffer
					$rec['D'] .= chr(intval($c));
				}
				$rec['D'] .= $wrk;
			}

			$recs[$k]['D'] = $rec['D'];
		}

		return $recs;
	}

	/**
	 * 	Encode data (Augmented Backus-Naur Form - ABNF)
	 *
	 * 	@param	- [ 'T' => Tag; 'P' => [ Parm => Val ]; 'D' => Data ]
	 * 	@return	- Converted data string
	 */
	protected function encode(array $rec): string {

		// non 7-Bit ASCII character?
		// for speed optimization reason, we assume we do not have any 8-bit characters
		if (mb_detect_encoding(strval($rec['D'])) != 'ASCII') {

    		$wrk = '';
    		for ($i=0, $m=strlen($rec['D']); $i < $m; $i++) {

    			$c = $rec['D'][$i];
    			$wrk .= ctype_cntrl($c) ? sprintf('%%x%02x', ord($c)) : $c;
    		}
		    $rec['D'] = $wrk;
		}

		return parent::encode($rec);
	}

}
