<?php
declare(strict_types=1);

/*
 *  Administration interface handler interface definition
 *
 *	@package	sync*gw
 *	@subpackage	Data base
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	https://www.gnu.org/licenses/gpl-3.0.en.html
 */

namespace syncgw\interface;

interface DBAdmin {

	/**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance();

	/**
	 * 	Get installation parameter
	 */
	public function getParms(): void;

	/**
	 * 	Connect to handler
	 *
	 * 	@return - true=Ok; false=Error
	*/
	public function Connect(): bool;

	/**
	 * 	Disconnect from handler
	 *
	 * 	@return - true=Ok; false=Error
	 */
	public function DisConnect(): bool;

	/**
	 * 	Return list of supported data store handler
	 *
	 * 	@return - Bit map of supported data store handler
 	 */
	public function SupportedHandlers(): int;

}