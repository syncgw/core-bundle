<?php
declare(strict_types=1);

namespace syncgw;

/*
 * 	sync*gw interface
 *
 *	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

/**
 * 	Multibyte extension loaded?
 */
if (!function_exists('mb_detect_encoding')) {
	function mb_detect_encoding($str): string				{ return !preg_match('/[^\x00-\x7F]/i', $str) ? 'ASCII' : 'UNKNOWN'; }
    function mb_internal_encoding($encoding) 		        { }
    //  function mb_convert_encoding()                      { }
}

use syncgw\lib\Server;

if (file_exists($file = $_SERVER['DOCUMENT_ROOT'].'/vendor/syncgw/testing-bundle/Loader.php'))
	require ($file);
else
	require ($_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php');

// get server object
$srv = Server::getInstance();

// process request
$srv->Process();
