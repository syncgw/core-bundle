<?php
declare(strict_types=1);

/*
 * 	RFC2425: A MIME Content-Type for Directory Information - 5.8.1. Line delimiting and folding
 *
 *	@package	sync*gw
 *	@subpackage	MIME support
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\document\mime;

use syncgw\lib\Encoding;

class mimRFC2425 {

    /**
     * 	Singleton instance of object
     * 	@var mimRFC2425
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mimRFC2425 {

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

		// remove soft line breaks (and line folding)
		$rawrecs = explode("\n", str_replace([ "\r", "\n ", "\n\t" ], [ '', '', '' ], $data));

		// unfold lines
		$recs = [];
		$inqp = false;
		for ($in=$on=0; $in < count($rawrecs); $in++) {

			// remove leading and trailing blanks
			$rawrecs[$in] = trim($rawrecs[$in]);

			// do not skip empty lines - they are required for QUOTED-PRINTABLE processing
			// quoted-printable check
			if ($inqp) {

				if (substr($rawrecs[$in], -1) == '=')
					$recs[$on] .= substr($rawrecs[$in], 0, -1);
				else {

					$recs[$on++] .= $rawrecs[$in];
					$inqp = false;
				}
				continue;
			}

			if (stripos($rawrecs[$in], 'QUOTED-P') && substr($rawrecs[$in], -1) == '=') {

				$inqp = true;
				if (!isset($recs[$on]))
					$recs[$on] = '';
				$recs[$on] .= substr($rawrecs[$in], 0, -1);
			} else {

				$l = strlen($rawrecs[$in]);
				// folded line?
				if ($l && ($c = $rawrecs[$in][0]) == ' ' || $c == "\t")
					$recs[$on-1] .= substr($rawrecs[$in], 1);
				else {

					if (!isset($recs[$on]))
						$recs[$on] = '';
					if ($l)
						$recs[$on++] .= $rawrecs[$in];
				}
			}
		}

		// process all lines
		$out = [];
		$pos = 0;
		foreach ($recs as $rec) {

			// stipp off internal comment lines
			if ($rec == '' || $rec[0] == '*')
				continue;

			// get tag and parameter
			$tag    = '';
			$wrk    = [];

			$tagend = false;
			$inparm = false;
			$pcnt   = -1;

			// extract NAME;PARAM:VALUE
			for ($l=strlen($rec); $l; $l--) {

				$c = $rec[0];
				$rec = substr($rec, 1);

				// end of tag and parameter?
				if ($c == ':' && !$inparm)
					break;

				// double quoted parameter?
				if ($c == '"') {

					$inparm = !$inparm;
					continue;
				}

				// end of tag?
				if (!$inparm && $c == ';') {

					$tagend = true;
					$pcnt++;
				}
				if (!$tagend)
					$tag .= $c;
				else {

					if (!isset($wrk[$pcnt]))
						$wrk[$pcnt] = '';

					if ($inparm || $c != ';')
						$wrk[$pcnt] .= $c;
				}
			}

			// remove grouping?
			if ($n = strpos($tag, '.'))
				$tag = substr($tag, $n + 1);

			// process parameter
			$parms = [];
			foreach ($wrk as $r) {

			    if (strpos($r, '=') !== false)
					list($key, $val) = explode('=', $r);
				else {

					$key = $r;
					$val = '';
				}
				if (!$val) {

				    $val = $key;
    				$key = 'TYPE';
			    } if ($key == 'TZID' || $key == 'ALTREP')
					list(, $val) = explode('=', $r);

				// extend parameter
				if (isset($parms[$key]))
					$parms[strtoupper($key)] .= ','.$val;
				else
					$parms[strtoupper($key)] = $val;
			}

			// perform basic transformation
			if (isset($parms['ENCODING'])) {
				$c = $parms['ENCODING'][0];

				// base64 decoded
				if ($c == 'B') {

					$parms['ENCODING'] = 'BASE64';
					$rec = str_replace(' ', '', $rec);
					// quoted-printable
				} elseif ($c == 'Q') {

					$rec = str_replace([ '=0D=0A', '=0A' ], [ "\r\n", "\n" ], $rec);
					$rec = quoted_printable_decode($rec);
					unset($parms['ENCODING']);
				}
			}

			if (isset($parms['CHARSET'])) {

				$enc = Encoding::getInstance();
				$enc->setEncoding($parms['CHARSET']);
				$rec = $enc->import($rec);
   				unset($parms['CHARSET']);
			}

			// save data
			if (!$tag)
				continue;

			$out[$pos]['T']   = $tag;
			$out[$pos]['P']   = $parms;
			$out[$pos++]['D'] = $rec;
		}

		return $out;
	}

	/**
	 * 	Encode data (Augmented Backus-Naur Form - ABNF)
	 *
	 * 	@param	- [ 'T' => Tag; 'P' => [ Parm => Val ]; 'D' => Data ]
	 * 	@return	- Converted data string
	 */
	protected function encode(array $rec): string {

		$out = '';

		// validate
		if (!isset($rec['T']))
			return $out;

		// perform basic transformation
		$data = $rec['D'];

		// character encoding?
		if (isset($rec['P']['CHARSET'])) {

			$enc = Encoding::getInstance();
			$enc->setEncoding($rec['P']['CHARSET']);
			$data = $enc->export($data);
		}

		$qp = false;
		if (isset($rec['P']['ENCODING'])) {

			$e = $rec['P']['ENCODING'][0];
			// quoted printable?
			if ($e == 'Q') {

        		// insert CR
			    $data = str_replace([ '\n', "\n" ], [ "\r\n", "\r\n" ], $data);
			    // convert back all HTML special characters
				$data = rawurlencode(htmlspecialchars_decode($data));
				$data = str_replace([ '%', '=3B', '=5C;' ], [ '=', ';', '=5C=3B' ], $data);
				$qp = true;
			}
			// base 64 encoding?
			// elseif ($e == 'B')
			// we assume parameter is already base64_encoded!
		}

		// walk through parameter
		$cmd = $rec['T'];
		if (isset($rec['P'])) {

		    foreach ($rec['P'] as $k => $v) {

    			// skip internal attributes
       		    if (substr(strval($k), 0, 2) == 'X-')
    		  		continue;
    	  		if (strpos($v, ';') !== false && $k != 'TYPE')
    				$v = '"'.$v.'"';
       		    $cmd .= ';'.$k.($v ? '='.$v : '');
    		}
		}

		$data = $cmd.':'.$data;

		// chunk data
		$lbr = false;

		for ($n=0, $m=strlen($data), $pos=0; $n < $m; $n++) {

			$c = substr($data, $n, 1);
			// end of line reached?
			if ($pos == 73) {

				// don't split in between encoded character
				if ($qp) {

					$e = 0;
					$l = strlen($out);
					if ($out[$l - 1] == '=')
						$e = 1;
					if ($out[$l - 2] == '=')
						$e = 2;
					if ($out[$l - 3] == '=')
						$e = 3;
					if ($e) {
						$out = substr($out, 0, $l - $e);
						$n -= $e;
						$c = substr($data, $n, 1);
					}
				}
				// special check for "&" character
				if (($i = strrpos($out, '&')) && !strpos(substr($out, $i), ';')) {

					$out = substr($out, 0, $i);
					$n -= 74 - $i;
					$c = substr($data, $n, 1);
				}
				if ($pos) {

					$lbr = true;
					if ($qp) {
						$out .= "=\r\n";
						$pos = 0;
					} else {

						$out .= "\r\n ";
						$pos = 1;
					}
				}
			}
			if ($c == "\n")
				$pos = -1;
			$out .= $c;
			$pos++;
		}
		// add additional line break
		if ($lbr)
			$out .= "\r\n";

		return $out."\r\n";
	}

}
