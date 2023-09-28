<?php
declare(strict_types=1);

namespace syncgw\lib;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Composer\Script\Event;
use Composer\Installer\PackageEvent;

/*
 *  PHP interfaces functions
 *
 *	@package	sync*gw
 *	@subpackage	Core
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

class Setup {

	/**
	 * 	Initialaize symbolic links
	 */
    public static function postInstall(Event $event) {

    	echo ($event->isDevMode() ? 'yes' : 'no')."\n";

        $fs = new Filesystem();
        $path = 'vendor/syncgw/core-bundle/src/sync.php';
        $link = 'sync.php';

   		echo 'Linking "'.$path.'" to "'.$link.'"'."\n";

		if ('\\' === \DIRECTORY_SEPARATOR)
	    	$fs->symlink($path, $link);
	    else
	    	$fs->symlink(Path::makeRelative($path, Path::getDirectory($link)), $link);
	}

	/**
	 * 	Delete symbolic links
	 */
    public static function postUninstall(PackageEvent $event) {

    	var_dump($event->getOperations());
        $fs = new Filesystem();
    	$fs->remove('sync.php');
    }

}