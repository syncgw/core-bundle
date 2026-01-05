<?php
declare(strict_types=1);

namespace syncgw;

/*
 * 	sync*gw interface
 *
 *	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
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

// Check für RoundCube installation
if (strstr($_SERVER['DOCUMENT_ROOT'], 'public_html'))
	define ('SYNCGW_PATH', realpath($_SERVER['DOCUMENT_ROOT'].'/..'));
else
	define ('SYNCGW_PATH', $_SERVER['DOCUMENT_ROOT']);

if (file_exists($file = \SYNCGW_PATH.'/vendor/syncgw/testing-bundle/Loader.php'))
	require ($file);
else
	require (\SYNCGW_PATH.'/vendor/autoload.php');

// get server object
$srv = Server::getInstance();

// process request
$srv->Process();
