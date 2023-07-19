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
 *	Version: $Id: class_PQ.php 516 2008-07-08 18:27:37Z lifo $
 */

/***
	PsychoQuery class

	Main PsychoQuery Factory class. This returns a new PQ object based on the type of server you intend to query.
	This is the only file you need to 'include' into your applications. The PQ directory must be in the same directory
	This class is completely self-contained and does not require any other files from the PsychoStats software in 
	order to be used in other scripts. Have fun.

	Example:

		include("class_PQ.php");

		$pq = PQ::create($conf);
		print_r($pq->query_info('1.2.3.4:27015'));

***/

if (defined("CLASS_PQ_PHP")) return 1;
define("CLASS_PQ_PHP", 1);

include_once(__DIR__ . "/PQ/PQ_PARENT.php");

class PQ {

// Our factory method to create a valid object for our querytype specified
public static function &create($conf) {
	if (!is_array($conf)) {			// force $conf into an array.
		$ip = $conf;			// If $conf is not an array it's assumed to be an ipaddr[:port]
		$conf = array( 'ip' => $ip );
		unset($ip);
	}

	// Add defaults to the config. Defaults do not override values passed in the $conf array
	$conf += array(
		'ip'		=> '',
		'port'		=> '',
		'querytype'	=> 'halflife',
		'master'	=> 0,
		'timeout'	=> 3,
		'retries'	=> 1,
	);

	// Separate IP:Port if needed
	if (strpos($conf['ip'], ':') !== FALSE) {
		$ipport = $conf['ip'];
		list($conf['ip'], $conf['port']) = explode(':', $ipport, 2);
		if (!is_numeric($conf['port'])) {
			$conf['port'] = '';
		}
	} else {
//		$conf['port'] = '';
	}

	// If no 'querytype' is specified default to 'halflife'
	if (!$conf['querytype']) {
		$conf['querytype'] = 'halflife';
	}

	// Attempt to load the proper class for our specified 'querytype'.
	$filename = strtolower($conf['querytype']);
	$classname = "PQ_" . $filename;

	if (!file_exists(__DIR__ . "/PQ/" . $filename . ".php")) {
		trigger_error("Support for ({$conf['querytype']}) not installed", E_USER_ERROR);
	} else {
		include_once(__DIR__ . "/PQ/" . $filename . ".php");
		$pq = new $classname($conf);
		return $pq;
	}
}

}  // end of class PQ

// returns an array of query types that are allowed when creating a new PQ object. 
// this is not an object method. It's a plain function.
if (!function_exists('pq_query_types')) {
	function pq_query_types() {
		// will only list the query types for the currently installed game.
		global $ps;
		$gametype = $ps->conf['main']['gametype'];
		$q = array();
		$q[$gametype] = '';
		switch ($gametype) {
			case 'halflife':
				$q['halflife'] 		= 'Halflife 1';
				break;
			case 'oldhalflife':
				$q['oldhalflife'] 	= 'Halflife 1 only (no steam)';
				break;
			case 'source':
				$q['source'] 		= 'Halflife 2';
				break;
			case 'quake3':
				$q['quake3'] 		= 'Quake 3';
				break;
			case 'cod4':
				$q['cod4']          = 'Call of Duty 4';
				break;
			case 'cod4x':
				$q['cod4x']          = 'Call of Duty 4X';
				break;
		}
		return $q;
	}
}

?>
