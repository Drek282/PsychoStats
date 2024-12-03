<?php
/**
 *	This file is part of PsychoStats.
 *
 *	Written by Jason Morriss
 *	Copyright 2008 Jason Morriss
 *
 *	PsychoStats is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	PsychoStats is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with PsychoStats.  If not, see <http://www.gnu.org/licenses/>.
 *
 *	Version: $Id: class_PS.php 367 2008-03-17 17:47:45Z lifo $
 */

/**
 *	PsychoStats factory class
 *
 *	Example:
 *		include("class_PS.php");
 *		$dbconf = array( ... DB settings ... );
 *		$ps = PsychoStats::create($dbconf, [gametype, modtype]);
 *
 *		$top100 = $ps->get_player_list(array( ... params ... ));
 *		print_r($top100);
 *
 *		$clans = $ps->get_clan_list(array( ... params ... ));
 *		print_r($clans);
 * @package PsychoStats
 */

if (defined("CLASS_PSYCHOSTATS_PHP")) return 1;
define("CLASS_PSYCHOSTATS_PHP", 1);

// automatically updated by packaging script when a release is made.
// this may differ from the version stored in the database, differences may indicate 
// an invalid installation.
define('PSYCHOSTATS_VERSION', '3.2.8n');

/**
 * PsychoStats factory class. This is a self contained API class for PsychoStats. It can be included almost
 * anywhere to fetch stats from a PsychoStats database. The API is simple and provides several methods
 * for fetching player statistics from the database.
 * No other libraries (except the DB class) are needed.
 * @package PsychoStats
 * 
 */
class PsychoStats {

// Factory function to create the object. This is not a class method.
public static function &create($dbconf = array(), $gametype = null, $modtype = null) {
	$db = null;
	if (isset($dbconf['dbhandle'])) {
		$db =& $dbconf['dbhandle'];
	} else {
		require_once(__DIR__ . "/class_DB.php");
		$db = PsychoDB::create($dbconf);
	}

	// determine the game::mod class to try and load
	if ($db->table_exists($db->dbtblprefix . "config")) {
		$cmd = "SELECT value FROM " . $db->dbtblprefix . "config WHERE conftype='main' AND section IS NULL AND ";
	} else {
		die("PsychoStats has not been installed properly.  See INSTALL.md for details.");
	}
	if (!$gametype and !$modtype) {
		$cmd .= "var IN ('gametype', 'modtype') ORDER BY var";
		list($gametype,$modtype) = $db->fetch_list($cmd);
	} elseif (!$gametype) {
		$cmd .= "var='gametype'";
		list($gametype) = $db->fetch_list($cmd);
	} elseif (!$modtype) {
		$cmd .= "var='modtype'";
		list($modtype) = $db->fetch_list($cmd);
	}

	// find the best sub class to use based on game::mod
	$root = __DIR__;
	$parts = array( $gametype, $modtype );
	$class = '';
	while ($parts) {
		$file = $root . DIRECTORY_SEPARATOR . 'PS' . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts) . '.php';
		if (file_exists($file)) {
			if (include_once($file)) {
				$class = 'PS_' . implode('_', $parts);
				break;
			} else {
				die("Error loading PsychoStats subclass $file");
			}			
		}
		array_pop($parts);
	}

	// use the base class if no subclasses were found
	if (!$class) {
		include_once('PS' . DIRECTORY_SEPARATOR . 'PS.php');
		$class = 'PS';
	} 

	$obj = new $class($db);
	return $obj;
}

}

?>
