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
 *	Version: $Id: go-dbinit.php 559 2008-09-05 13:15:47Z lifo $
 */

/*
	Initialize DB schema with defaults.
*/
if (!defined("PSYCHOSTATS_INSTALL_PAGE")) die("Unauthorized access to " . basename(__FILE__));

// we need to raise the memory limit, since the defaults.sql file will require more than 8MB
// and some systems will default to 8MB.
@ini_set('memory_limit', '128M');

$validfields = array('gametype','modtype','overwrite','dropdb');
$cms->theme->assign_request_vars($validfields, true);

$gametypes = array(
	'source'	=> "Half-Life 2"
);

$modtypes = array(
	'bg3'	=> "The Battle Grounds III"
);

$gamesupport = array(
	'source'	=> array( 'bg3' )
);

// make DB connection
load_db_opts();
$db->config(array(
	'dbhost' => $dbhost,
	'dbport' => $dbport,
	'dbname' => $dbname,
	'dbuser' => $dbuser,
	'dbpass' => $dbpass,
	'dbtblprefix' => $dbtblprefix
));
$db->clear_errors();
$db->connect();

if (!$db->connected) {
	if ($ajax_request) {
		print "<script>window.location = 'go.php?s=db&re=1&install=" . urlencode($install) . "';</script>";
		exit;
	} else {
		gotopage("go.php?s=db&re=1&install=" . urlencode($install));
	}
}


$allow_next = false;
$db_init = false;
$errors = array();
$actions = array();
$schema = array();
$defaults = array();

$cms->theme->assign_by_ref('db_init', $db_init);
$cms->theme->assign_by_ref('errors', $errors);
$cms->theme->assign_by_ref('actions', $actions);

// no need to 'overwrite' if we are dropping the database entirely
if ($dropdb) $overwrite = false;

// validate gametype's selected
$list = is_array($gametype) ? $gametype : array();
$gametype = array();
foreach ($list as $k) {
	if (array_key_exists($k, $gametypes)) {
		$gametype[] = $k;
	}
}
//if (!$gametype) $gametype[] = 'source';

// validate modtype's selected
$list = is_array($modtype) ? $modtype : array();
$modtype = array();
foreach ($list as $k) {
	if (array_key_exists($k, $modtypes)) {
		$modtype[] = $k;
	}
}
//if (!$modtype) $modtype[] = 'cstrike';


$cms->theme->assign(array(
	'gamesupport'	=> $gamesupport,
	'gametypes'	=> $gametypes,
	'modtypes'	=> $modtypes,
));

if ($ajax_request) {
//	sleep(1);
	$pagename = 'go-dbinit-results';
	do_init($gametype, $modtype);
	$cms->tiny_page($pagename, $pagename);
	exit();
}

// the DB will already exist (assuming the user did the 'db' step already; which they should have)
function do_init($games, $mods) {
	global $cms, $db, $db_init, $errors, $actions, $overwrite, $dropdb,
		$allow_next, $schema, $defaults;
	$i = 1;
	$exists = array();
	$dropped = array();
	$ignore = array();

	if (!$games) {
		$errors[] = "No 'game type' selected! You must select at least one.";
	}
	// a 'mod' must be selected if 'source' is selected as a game type.
	// the other games supported at this time do not have mods.
	if (!$mods and in_array('source',$games)) {
		$errors[] = "No 'mod type' selected! You must select at least one.";
	}
	if ($errors) return false;

	// get a list of all PS tables in the database (ignore tables without our prefix
	$db->query("SHOW TABLES LIKE '" . $db->escape($db->dbtblprefix) . "%'");
	while ($r = $db->fetch_row(0)) {
		$exists[ $r[0] ] = true;
	} 

	// load our SQL schema 
	$schema = load_schema($db->type() . "/basic.sql");
	if (!$schema) $errors[] = "Unable to read basic database schema for installation!";
	
	// load our SQL defaults
	$defaults = load_schema($db->type() . "/defaults.sql");
	if (!$defaults) $errors[] = "Unable to read database defaults for installation!";

	//load our Maxmind database
	$maxmind = load_schema($db->type() . "/maxmind.sql");
	if(!$maxmind) $errors[] = "Unable to read Maxmind GeoIP database for installation!";

	// load the modtype defaults, if avaialble
	// bug: the same modtype from different games will be loaded... not an issue right now though.
	foreach ($games as $g) {
		assign_sql(load_schema($db->type() . "/$g.sql"));
		foreach ($mods as $m) {
			assign_sql(load_schema($db->type() . "/$g/$m.sql"));
		}
	}
	if ($errors) return false;
	
	// recreate DB if needed
	if ($dropdb || !$db->dbexists($db->dbname)) {
		$exists = array();
		if (!$db->dbexists($db->dbname) || $db->dropdb($db->dbname)) {
			if ($db->createdb($db->dbname, "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
				$actions[$db->dbname] = array( 'status' => 'good', 'msg' => "RECREATED DATABASE '$db->dbname'" );
				$db->selectdb();
                $db->clear_errors();
                $db->connect();
			} else {
				$errors[] = "Error creating database: " . $db->errstr;
			}
		} else {
			$errors[] = "Error dropping current database: " . $db->errstr;
		}
	}
	if ($errors) return false;
	$queries = array_merge($schema, $defaults,$maxmind);

	$allow_next = true;
	foreach ($queries as $sql) {
		if (empty($sql)) continue;
		$action = substr($sql, 0, 6);
		$is_create = (strtolower($action) == 'create');
		if (!preg_match('/(?:CREATE TABLE|INSERT INTO) ([^\w])([\w\d_]+)\1/i', $sql, $m)) continue;
		$table = $m[2];

		// fix the table name to use the proper prefix
		if ($db->dbtblprefix != 'ps_') {
			$table = preg_replace('/^ps_/', $db->dbtblprefix, $table);
			$sql = str_replace($m[2], $table, $sql);
		}

		// if the table exists and overwrite is true, drop it first.
		$exists[$table] = $exists[$table] ?? null;
		if ($exists[$table] and $overwrite and $is_create) {
			if ($db->droptable($table)) {
				unset($exists[$table]);
				$actions[$table] = array('status' => 'good', 'msg' => "Dropped table '$table'");
				$dropped[$table] = true;
			} else {
				$actions[$table] = array('status' => 'bad', 'msg' => "Error dropping table '$table'");
				$allow_next = false;
				continue;
			}
		}

		if ($is_create) {
			// don't try to create a table that already exists
			if ($exists[$table]) {
				$ignore[$table] = true;
				$actions[$table] = array(
					'status' => 'warn',
					'msg' => "Ignoring table '$table' (already exists)"
				);
			} else {
				if ($db->query($sql)) {
                    $dropped[$table] = $dropped[$table] ?? null;
					$actions[$table] = array( 'status' => 'good', 'msg' => ($dropped[$table] ? "Rec" : "C") . "reated table '$table'" );
				} else {
					$actions[$table] = array( 'status' => 'bad', 'msg' => "Error creating table '$table': " . $db->errstr );
				}
			}
		} else {
			// do 'insert' query
			$ignore[$table] = $ignore[$table] ?? null;
			if ($ignore[$table]) continue;
			if ($db->query($sql)) {
				$actions[$table] = array( 'status' => 'good', 'msg' => "Created and initialized table '$table'" );
			} else {
				$actions[$table] = array( 'status' => 'bad', 'msg' => "Error initializing table '$table': " . $db->errstr );
			}
		}
	} // foreach $queries ...

	// initialize some configuration defaults
	$tbl = $db->table('config');
	// only update the config table if it was created/initialized above ...
	if ($actions[$tbl] and $actions[$tbl]['status'] == 'good') {
		// default game/mod type to the first ones selected in the form
		$db->query("UPDATE " . $db->qi($tbl) . " SET value='$games[0]' WHERE conftype='main' AND section IS NULL AND var='gametype'");
#		if ($db->errstr) $errors[] = $db->errstr;
		$db->query("UPDATE " . $db->qi($tbl) . " SET value='$mods[0]'  WHERE conftype='main' AND section IS NULL AND var='modtype'");
#		if ($db->errstr) $errors[] = $db->errstr;
	}
}

function assign_sql($sql) {
	global $schema, $defaults;
	if (!$sql) return;
	foreach ($sql as $d) {
		$q = strtolower(substr($d, 0, 6));
		if ($q == 'create') {
			$schema[] = $d;
		} else {
			$defaults[] = $d;
		}
	}
}

function load_schema($file) {
	$sql = preg_split('/;\n/', 
		implode("\n", 
			array_map('rtrim', 
				preg_grep("/^(--|(UN)?LOCK|DROP|\\/\\*)/", (array)@file($file), PREG_GREP_INVERT)
			)
		)
	);
	if (empty($sql[ count($sql)-1 ])) array_pop($sql);
	return $sql;
}

function err($msg) {
	global $cms, $pagename;
//	print "<h3>Fatal Error!</h3>";
	print "<p class='row'><span class='bad'>$msg</span></p>";
	$cms->tiny_page($pagename, $pagename);
	exit();
}

?>
