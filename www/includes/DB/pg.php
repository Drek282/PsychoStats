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
if (defined("CLASS_DB_PGSQL_PHP")) return 1;
define("CLASS_DB_PGSQL_PHP", 1);

define("SQL_IDENTIFIER_QUOTE_CHAR", '"');
define("SQL_CATALOG_NAME_SEPARATOR", '.');

class DB_pg extends DB_PARENT {

function __construct($conf=array()) {
	return $this->DB_pg($conf);
}

function DB_pg($conf=array()) {
	$this->DB_PARENT($conf);
	$this->conf = $conf;	
	return $this->connect();
}

function connect() {
	if (!function_exists('pg_connect')) {
		$this->error("Your installation of PHP v" . PHP_VERSION . " does not include PGSQL support.");
		$this->_fatal("Extension Error!");
		return 0;
	}

	$dsn = '';
	if ($this->dbhost != 'localhost') $dsn = "host=$this->dbhost ";
	if ($this->dbport) $dsn .= "port=$this->dbport ";
	if ($this->dbuser) $dsn .= "port=$this->dbuser ";
	if ($this->dbpass) $dsn .= "port=$this->dbpass ";
	$this->dbh = @pg_connect($dsn);
	$ok = ($this->dbh);
	if (!$ok) {
#		if (pg_last_error($this->dbh) != '') $this->error("<b>PGSQL Error:</b> " . pg_last_error($this->dbh));
		$this->_fatal(sprintf("Error connecting to PGSQL server '<b>%s</b>' or accessing database '<b>%s</b>' using username '<b>%s</b>'", 
			$this->dbhost, $this->dbname, $this->dbuser)
		);
		$this->connected = 0;
	} else {
		$this->connected = 1;
	}

	$this->escape_func = 'pg_escape_string';

	return $ok;
}

// Sends a query ...
function query($cmd) {
	if (!$this->connected) return 0;
	$this->totalqueries++;
	$this->lastcmd = $cmd;
	$this->error("");
#	print $this->lastcmd . ";<br><br>\n\n";
	$this->res = @pg_query($this->dbh, $cmd);
	if (!$this->res) {
		$this->error("<b>PGSQL Error:</b> " . @pg_last_error());
		$this->_fatal("<b>SQL Error in query string:</b> \n\n$cmd");
	}
	return $this->res;
}

// fetches the next row from the last query performed (only use after a SELECT query)
// If $cmd is specified, it will be queried first, and then the first row returned (if no errors occur)
function fetch_row($assoc=1, $cmd="") {
	if ($cmd) $this->query($cmd);
	if (!$this->res) return array();
	return ($assoc) ? pg_fetch_assoc($this->res) : pg_fetch_array($this->res);
}

// returns the number of rows from the last SELECT query performed
function num_rows() {
	if (!$this->res) return 0;
	return @pg_num_rows($this->res);
}

// returns the number of rows that were affected from the last INSERT, UPDATE, or DELETE query performed
function affected_rows() {
	if (!$this->res) return 0;
	return @pg_affected_rows($this->dbh);
}

// returns server version and/or information
function server_info() {
	$cmd = "SELECT VERSION() AS 'version'";
	$this->query($cmd);
	return $this->fetch_row();
}

// returns the last auto_increment ID used
function last_insert_id() {
	return 0;
}

function dbexists($dbname) {
	$list = $this->fetch_list("SELECT datname FROM pg_database");
	return in_array($dbname, $list);
}

// returns the status of all tables in the current database
function table_status() {
	$cmd = "SELECT * FROM information_schema.tables WHERE table_catalog='" . $this->escape($this->dbname) . "' AND table_type='BASE TABLE'";
	$this->query($cmd);
	return $this->fetch_rows();
}

function table_columns($tbl) {
	$list = array();
	$this->query("SELECT * FROM information_schema.columns WHERE table_name = " . $this->quote_identifier($tbl));
	while ($row = $this->fetch_row()) {
		$list[] = $row['column_name'];
	}
	return $list;
}

function optimize($tbl) { 
	if (!is_array($tbl)) $tbl = array($tbl);
	foreach ($tbl as $t) {
		$this->query("VACUUM $t");
	}
}

function createdb($dbname) {
	return $this->query("CREATE DATABASE " . $this->qi($dbname));
}

}
?>
