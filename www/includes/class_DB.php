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
 *	Version: $Id: class_DB.php 367 2008-03-17 17:47:45Z lifo $
 */

/***
	PsychoDB class. Database abstraction class.

	Basic DB abstraction class for PsychoStats.

***/

include_once(__DIR__ . "/DB/DB_PARENT.php");

class PsychoDB {

// Our factory method to create a valid object for our specified database
public static function &create($conf=array()) {
	if (!is_array($conf)) {				// force $conf into an array.
		$dbhost = $conf;			// If $conf is not an array it's assumed to be a host[:port]
		$conf = array( 'dbhost' => $dbhost );
	}

	// Add defaults to the config. Defaults do not override values passed in the $conf array
	$conf += array(
		'dbtype'	=> 'mysql',
		'dbhost'	=> 'localhost',
		'dbport'	=> '',
		'dbname'	=> 'psychostats',
		'dbuser'	=> '',
		'dbpass'	=> '',
		'dbtblprefix'	=> '',
		'delaydb'	=> 0,
		'fatal'		=> 1,
	);

	// If no 'dbtype' is specified default to "mysql"
	if (!$conf['dbtype']) {
		$conf['dbtype'] = 'mysql';
	}

	// setup the object name and filename to include it
	$filename = strtolower($conf['dbtype']);
	$classname = "DB_" . $filename;
	$filepath = __DIR__ . "/DB/" . $filename . ".php";

	// Attempt to load the proper class for our specified 'dbtype'.
	if (!include_once($filepath)) {
		die("<b>Fatal Error:</b> Unsupported 'dbtype' specified (${conf['dbtype']}) for new DB object.");
	} else {
		$_db = new $classname($conf);
		return $_db;
	}
}  // end of constructor

}  // end of class DB

?>
