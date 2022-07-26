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
 *	Version: $Id$
 */
if (defined("CLASS_DB_MYSQL_PHP")) return 1;
define("CLASS_DB_MYSQL_PHP", 1);

define("SQL_IDENTIFIER_QUOTE_CHAR", '`');
define("SQL_CATALOG_NAME_SEPARATOR", '.');

class DB_mysql extends DB_PARENT {
public $lastcmd = '';

function __construct($conf=array()) {
	$this->DB_PARENT($conf);
	$this->conf = $conf;	

	// determine what escaping function we should use
	if (function_exists('mysqli_real_escape_string')) {		// PHP >= 4.3.0
		$this->escape_func = 'mysqli_real_escape_string';
	} elseif (function_exists('mysqli_escape_string')) {		// PHP >= 4.0.3
		$this->escape_func = 'mysqli_escape_string';
	} else {							// PHP >= 3
		$this->escape_func = 'addslashes';
	}

	return $this->connect();
}

function DB_mysql($conf=array()) {
    self::__construct($conf);
}

function connect($force_select = false) {
	
	if (!function_exists('mysqli_connect')) {
		$this->error("Your installation of PHP v" . PHP_VERSION . " does not include MySQL support.");
		$this->fatal(true);
		$this->_fatal("Extension Error!");
		return false;
	}
	
    $this->dbname = $GLOBALS['dbname'];
    $this->dbuser = $GLOBALS['dbuser'];
    $this->dbpass = $GLOBALS['dbpass'];

	$host = !$this->dbport ? $this->dbhost : "$this->dbhost:$this->dbport";
	
	$this->dbh = @mysqli_connect($host, $this->dbuser, $this->dbpass); // remove @ from mysqli_connect to see errors
	$this->connected = ($this->dbh);
	if ($this->connected and (!$this->conf['delaydb'] or $force_select)) {
		$this->selected = $this->selectdb();
	} else {
		$this->error(@mysqli_error());
		$this->_fatal(sprintf("Error connecting to MySQL server '<b>%s</b>' (database '<b>%s</b>') using username '<b>%s</b>'", 
			$host, $this->dbname, $this->dbuser),
			true
		);
	}

	$this->query("SET NAMES 'utf8mb4'");

	return ($this->connected && $this->selected);
}

function selectdb($dbname = null) {
	if (!$this->dbh) return false;
	if (empty($dbname)) $dbname = $this->dbname;
	$ok = @mysqli_select_db($this->dbh, $dbname);
	if (!$ok) {
		$this->error(@mysqli_error());
		$this->_fatal(sprintf("Error accessing database '<b>%s</b>' on server <b>%s</b> using username '<b>%s</b>'", 
			$dbname, $this->dbhost, $this->dbuser)
		);
	}
	return $ok;
}

// Sends a query ...
function query($cmd) {
	if (!$this->connected) return false;
	$this->totalqueries++;
	$this->lastcmd = $cmd;
	$this->queries[] = $cmd;
	$this->errstr = '';
#	print $this->lastcmd . ";<br><br>\n\n";
	$this->res = @mysqli_query($this->dbh, $cmd);
	if (!$this->res) {
		$this->error(@mysqli_error());
		$this->_fatal("<b>SQL Error in query string:</b> \n\n$cmd");
	}
	return $this->res;
}

// fetches the next row from the last query performed (only use after a SELECT query)
// If $cmd is specified, it will be queried first, and then the first row returned (if no errors occur)
function fetch_row($assoc=1, $cmd="") {
	if ($cmd) $this->query($cmd);
	if (!$this->res) return array();
	return ($assoc) ? mysqli_fetch_assoc($this->res) : mysqli_fetch_array($this->res, MYSQLI_NUM);
}

// returns the number of rows from the last SELECT query performed
function num_rows() {
	if (!$this->res) return 0;
	return @mysqli_num_rows($this->res);
}

// returns the number of rows that were affected from the last INSERT, UPDATE, or DELETE query performed
function affected_rows() {
	if (!$this->res) return 0;
	return @mysqli_affected_rows($this->dbh);
}

// returns the last auto_increment ID used
function last_insert_id() {
	return @mysqli_insert_id($this->dbh);
}

// returns server version and/or information
function server_info() {
	$cmd = "SELECT VERSION() AS 'version', CURRENT_USER() as 'current_user'";
	$this->query($cmd);
	return $this->fetch_row();
}

function dbexists($dbname) {
	//Unreliable in some cases (OVH-hosted)
	//$list = $this->fetch_list("SHOW DATABASES");
	//return in_array($dbname, $list);
	$this->query("SELECT IF(EXISTS (SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '".$dbname."'), 'yes','no') AS db_exists");
	$status = $this->fetch_row();
	return ($status['db_exists']=='yes')?true:false;
}

function table_columns($tbl) {
	$list = array();
	$this->query("EXPLAIN " . $this->quote_identifier($tbl));
	while ($row = $this->fetch_row()) {
		$list[] = $row['Field'];
	}
	return $list;
}

function optimize($tbl) { 
	if (!is_array($tbl)) $tbl = array($tbl);
	return $this->query("OPTIMIZE TABLE " . join(", ", $tbl));
}

function createdb($dbname, $extra=null) {
	$cmd = "CREATE DATABASE " . $this->qi($dbname);
	if (!empty($extra)) {
		$cmd .= " $extra";
	}
	return $this->query($cmd);
}

function truncate($tbl) {
	return $this->query("TRUNCATE TABLE $tbl");
}

function error($e, $force = false) {
	$e = trim($e);
	if (!empty($e)) {
		if(function_exists('mysqli_query')) $this->errno = $this->dbh ? @mysqli_errno($this->dbh) : @mysqli_errno();
		else $this->errno = 0;
		$e = "ERR " . $this->errno . ": " . $e;
	}
	parent::error($e, $force);
}

}  // end of class

?>
