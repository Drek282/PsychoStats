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

define("SQL_IDENTIFIER_QUOTE_CHAR", '"');
define("SQL_CATALOG_NAME_SEPARATOR", '.');

class DB_sqlite extends DB_PARENT {

function __construct($conf) {
	$this->DB_PARENT($conf);
	$this->conf = $conf;
	return $this->connect();
}

function  DB_sqlite($conf) {
    self::__construct($conf);
}

function connect() {
	if (!function_exists('sqlite_open')) {
		$this->error("Your installation of PHP v" . PHP_VERSION . " does not include SQLite support.");
		$this->_fatal("Extension Error!");
		return 0;
	}

#	die("DBNAME = " . $this->dbname);
	$this->dbh = @sqlite_open($this->dbname . ".db", 0666, $err);
	if ($this->dbh) {
		$this->connected = 1;
	} else {
		$this->error("<b>SQLITE Error:</b> $err");
		$this->_fatal(sprintf("Error connecting to SQLITE database '<b>%s</b>'", $this->dbname));
		$this->connected = 0;
	}

	$this->escape_func = 'sqlite_escape_string';

	return 1;
}

// Sends a query ...
function query($cmd) {
	if (!$this->connected) return 0;
	$this->totalqueries++;
	$this->lastcmd = $cmd;
	$this->error("");
#	print $this->lastcmd . ";<br><br>\n\n";
	$this->res = sqlite_query($this->dbh, $cmd, $this->errcode);
	if (!$this->res) {
		$this->error("<b>SQLITE Error:</b> " . @sqlite_error_string(sqlite_last_error($this->dbh)));
		$this->_fatal("<b>SQL Error in query string:</b> \n\n$cmd");
	}
	return $this->res;
}

function table_columns($tbl) {
	$list = array();
	$this->query("PRAGMA table_info(" . $this->quote_identifier($tbl) . ")");
	while ($row = $this->fetch_row()) {
		$list[] = $row['name'];
	}
	return $list;
}

}

?>
