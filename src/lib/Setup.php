<?php
declare(strict_types=1);

namespace syncgw\lib;

use Symfony\Component\Filesystem\Filesystem;
use Composer\Script\Event;
use Composer\Installer\PackageEvent;

/*
 *  PHP interfaces functions
 *
 *	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

class Setup {

	/**
	 * 	Initialaize symbolic links
	 */
    public static function postInstall(Event|PackageEvent $event) {

	    $fs = new Filesystem();

	    // check for symlink for RoundCube >= 1.6
	    if ($fs->exists('public_html')) {

		    $link = 'public_html/vendor/syncgw';
	    	$path = $event->getComposer()->getConfig()->get('vendor-dir').'/syncgw';

	   		echo 'Linking "'.$path.'" to "'.$link.'"'."\n";

    		$fs->symlink($path, $link);

		    $link = 'public_html/sync.php';
		    $path = $event->getComposer()->getConfig()->get('vendor-dir').'/syncgw/core-bundle/src/sync.php';

	   		echo 'Linking "'.$path.'" to "'.$link.'"'."\n";

	    	$fs->symlink($path, $link);
	    } else {

	    	$link = 'sync.php';
		    $path = $event->getComposer()->getConfig()->get('vendor-dir').'/syncgw/core-bundle/src/sync.php';

	   		echo 'Linking "'.$path.'" to "'.$link.'"'."\n";

	    	$fs->symlink($path, $link);
	    }
    }

	/**
	 * 	Delete symbolic links
	 */
    public static function postUninstall(Event|PackageEvent $event) {

		$fs = new Filesystem();

		$fs->remove('public_html/vendor/syncgw');
		$fs->remove('public_html/sync.php');
		$fs->remove('sync.php');
    }

}