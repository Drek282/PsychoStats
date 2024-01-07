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
if (defined("CLASS_DB_PARENT_PHP")) return 1;
define("CLASS_DB_PARENT_PHP", 1);

#[AllowDynamicProperties]
class DB_PARENT {
var $DEBUG 		= false;
var $errno		= 0;
var $errstr		= '';
var $conf 		= array();
var $dbh		= null;
var $connected 		= false;
var $selected		= false;
var $escape_func	= 'addslashes';

// class constructor
function __construct($conf=array()) {
	$this->conf = $conf;
	$this->config($conf);
	$this->dbtype = $conf['dbtype'] ? $conf['dbtype'] : 'mysql';

	$this->errors = array();
	$this->queries = array();
	$this->totalqueries = 0;

	$this->classname = "DB::" . $this->dbtype;
}

function DB_PARENT($conf=array()) {
    self::__construct($conf);
}

// makes a copy of the current object, making a new DB connection, etc.
// so the new copy will have it's own DB handle for queries. Also, errors and query
// arrays are reset.
function & copy() {
	$class = str_replace('::', '_', $this->classname);
	$copy = new $class($this->conf);
	if ($this->connected and !$copy->connected) {
		$copy->connect();
		if ($this->selected and !$copy->selected) {
			$copy->selectdb();
		}
	}
	return $copy;
}

function config($conf) {
	if (!is_array($conf)) $conf = array();
	$list = array('dbhost','dbport','dbname','dbuser','dbpass','dbtblprefix');
	foreach ($list as $var) {
		if (array_key_exists($var, $conf)) {
			$this->$var = $conf[$var];
			$this->conf[$var] = $conf[$var];
		}
	}
}

function type() {
	return $this->dbtype;
}

function connect() {
	die("Abstract method called: " . $this->classname . "::connect");
}

// Return the MAX() value of the key given
function max($tbl, $key='id', $where='') {
	if (empty($key)) $key = "id";
	if (!empty($where)) $where = "WHERE $where";
	$res = $this->query("SELECT MAX($key) FROM $tbl $where");
	list($max) = ($res) ? $this->fetch_row(0) : array(0);
	return $max;    
}

function min($tbl, $key='id', $where='') {               
	if (empty($key)) $key = "id";
	if (!empty($where)) $where = "WHERE $where";
	$res = $this->query("SELECT MIN($key) FROM $tbl $where");
	list($min) = ($res) ? $this->fetch_row(0) : array(0);           
	return $min;                  
}

function next_id($tbl, $key='id') {
	if (empty($key)) $key = 'id';
	return $this->max($tbl, $key) + 1;
}

function server_info() {
	return array('version' => 0);
}

function table_status($dbname='') {
	if (!empty($dbname)) $dbname = "FROM $dbname";
	$cmd = "SHOW TABLE STATUS $dbname";
	$this->query($cmd);
	return $this->fetch_rows();
}

// returns the column names of a table as an array
function table_columns($tbl) {
	die("Abstract method called: " . $this->classname . "::table_columns");
}

// Sends a query ...
function query($cmd) {
	die("Abstract method called: " . $this->classname . "::query");
}

// fetches the next row from the last query performed (only use after a SELECT query)
// If $cmd is specified, it will be queried first, and then the first row returned (if no errors occur)
function fetch_row($assoc=1, $cmd="") {
	die("Abstract method called: " . $this->classname . "::fetch_row");
}

// fetches all remaining rows from the last SELECT query performed
// If $cmd is specified, it will be queried first, and then all rows returned (if no errors occur)
function fetch_rows($assoc=1, $cmd="") {
	$list = array();
	if ($cmd) $this->query($cmd);
	if (!$this->res) return $list;
	while ($row = $this->fetch_row($assoc)) {
		$list[] = $row;
	}
	return $list;
}

// fetches a list of items from a select. All columns in a row are returned as a single array
function fetch_list($cmd="") {
	$list = array();
	if ($cmd) $this->query($cmd);
	if (!$this->res) return $list;
	while ($row = $this->fetch_row(0)) {
		$list = array_merge($list, $row);
	}
	return $list;
}

// returns the first element from the next row. uses $cmd if needed to start a new query
function fetch_item($cmd="") {
	$row = $this->fetch_row(0, $cmd);
	$row[0] ??= '';
	return $row[0];
}

// returns the number of rows from the last SELECT query performed
function num_rows() {
	die("Abstract method called: " . $this->classname . "::num_rows");
}

// returns the number of rows that were affected from the last INSERT, UPDATE, or DELETE query performed
function affected_rows() {
	die("Abstract method called: " . $this->classname . "::affected_rows");
}

// returns the last auto_increment ID used
function last_insert_id() {
	die("Abstract method called: " . $this->classname . "::last_insert_id");
}

// delete row(s) from a table 
function delete($tbl, $key, $id=NULL) {
	if ($id===NULL) {	// assume $key is a full where clause
		return $this->query("DELETE FROM $tbl WHERE $key");
	} else {
		return $this->query("DELETE FROM $tbl WHERE " . $this->qi($key) . "=" . $this->escape($id, true));
	}
}

// truncates (deletes) the table given entirely. 
function truncate($tbl) {
	return $this->query("DELETE FROM $tbl");
}

// fetch a single row from the DB, matching on a single field name (select * From TBL WHERE key=value)
function select_row($tbl, $values, $key, $id=NULL, $assoc=0) {
	if (!is_array($key)) {
		$res = $this->query(sprintf("SELECT %s FROM %s WHERE %s = %s LIMIT 1", $values, $tbl, $this->qi($key), $this->escape($id, true)));
	} else {
		$where = "";
		foreach ($key as $k => $v) {
			$where .= $this->qi($k) . " = " . $this->escape($v, true) . " and ";
		}
		$where = !empty($where) ? substr($where,0,-5) : "1";		// strip off ' and ', or return '1' if there's no where clause
		$res = $this->query(sprintf("SELECT %s FROM %s WHERE %s LIMIT 1", $values, $tbl, $where));
	}
	return ($res) ? $this->fetch_row($assoc) : array();
}

// returns the total count of a key in a table
function count($tbl, $key='*', $where='') {
	if (empty($key)) $key = "*";
	if (!empty($where)) $where = "WHERE $where";
	$res = $this->query("SELECT count($key) FROM $tbl $where");
	list($total) = ($res) ? $this->fetch_row(0) : array(0);
	return $total;
}

// updates a row in a table with the values in the set array. if set is not an array it's assumed to be a valid query string
// if $id is an array the update is done with an "where $key IN ($id,...)"
function update($tbl, $set, $key, $id) {
	$values = "";
	if (is_array($set)) {
		foreach ($set as $k => $v) {
			$values .= $this->qi($k) . "=" . $this->escape($v, true) . ", ";
		}
		if (strlen($values) > 2) $values = substr($values, 0, -2);
	} else {
		$values = $set;
	}
	$cmd  = "UPDATE $tbl SET $values WHERE " . $this->qi($key);
	if (is_array($id)) {
		$list = array();
		foreach ($id as $_id) {
			$list[] = $this->escape($_id, true);
		}
		$cmd .= " IN (" . join(',',$list) . ")";
	} else {
		$cmd .= "=" . $this->escape($id,true);
	}
	return $this->query($cmd);
}

// inserts a row into the table using the values in set
function insert($tbl, $set) {
	$values = "";
	if (is_array($set)) {
		foreach ($set as $k => $v) {
			$values .= $this->qi($k) . "=" . $this->escape($v, true) . ", ";
		}
		if (count($set)) {
			$values = substr($values, 0, -2);
		}
	} else {
		$values = $set;
	}
	return $this->query("INSERT INTO $tbl SET $values");
}

function sortorder($args, $prefix='') {
	$str = "";
	if (!$args) return $str;
	$args['order'] ??= null;
	$args['sort'] ??= null;
	$order = $args[$prefix . 'order'] ? " " . $args[$prefix . 'order'] : '';
	$fields = array_filter(explode(',', $args[$prefix . 'sort']), 'not_empty');
	foreach ($fields as $sort) {
		$sort = trim($sort);
		if ($sort != '') {
            $args['no_quote'] = $args['no_quote'] ?? null;
			if ($args['no_quote']) {
                $args['fieldprefix'] = $args['fieldprefix'] ?? null;
				if ($args['fieldprefix']) $sort = $args['fieldprefix'] . '.' . $sort;
			} else {
                $args['fieldprefix'] = $args['fieldprefix'] ?? null;
				if ($args['fieldprefix']) {
					$sort = $this->qi($args['fieldprefix']) . '.' . $this->qi($sort);
				} else {
					$sort = $this->qi($sort);
				}
			}
			$str .= " " . $sort;
			$str .= $order;
			$str .= ",";
		}
	}
	if ($fields) $str = substr("ORDER BY " . $str, 0, -1);
	$str .= $this->limit($args, $prefix);
	return $str;    
}

function limit($args, $prefix='') {
	$str = "";
	$args['limit'] ??= null;
	if ($args[$prefix . 'limit'] && !$args[$prefix . 'start']) {
		$str .= " LIMIT " . $args[$prefix . 'limit'];
	} elseif ($args[$prefix . 'limit'] && $args[$prefix . 'start']) {
		$str .= " LIMIT " . $args[$prefix . 'start'] . "," . $args[$prefix . 'limit'];
	}
	return $str;
}

// returns true if a table exists
function table_exists($tbl) {
	$cmd = "SHOW TABLES LIKE '$tbl'";
	$check = $this->query($cmd);
	return ($check->num_rows) ? true : false;
}

// returns true if a column exists in a table or tables
function column_exists($tbls, $cols) {
	$ca = (str_contains($cols, ', ')) ? explode(', ', $cols) : $cols;
	if (is_array($ca)) {
		$f = 0;
		foreach ($ca as $c) {
			if  (is_array($tbls)) {
				foreach ($tbls as $t) {
					$cmd = "SHOW COLUMNS FROM $t LIKE '$c'";
					$check = $this->query($cmd);
					if ($check->num_rows) {
						$f++;
						break;
					}
				}
			} else {
				$cmd = "SHOW COLUMNS from $tbls LIKE '$c'";
				$check = $this->query($cmd);
				if ($check->num_rows) $f++;
			}
		}
		return ($f == 2) ? true : false; 
	}
	if  (is_array($tbls)) {
		foreach ($tbls as $t) {
			$cmd = "SHOW COLUMNS FROM $t LIKE '$ca'";
			$check = $this->query($cmd);
			if ($check->num_rows) return true;
		}
		return false;
	}
	$cmd = "SHOW COLUMNS from $tbls LIKE '$ca'";
	$check = $this->query($cmd);
	return ($check->num_rows) ? true : false;
}

// returns true if a row exists based on the key=id given
function exists($tbl, $key, $id=NULL) {
	if ($id === NULL) {		// assume $key is in the form: 'mykey=value'
		$cmd = "SELECT count(*) FROM $tbl WHERE $key";
	} else {
		$cmd = "SELECT count(*) FROM $tbl WHERE " . $this->qi($key) . "=" . $this->escape($id, true);
	}
	$res = $this->query($cmd);
	$total = 0;
	if ($this->num_rows()) {
		list($total) = $this->fetch_row(0);
	}
	return $total;
}

function dropdb($dbname) {
	return $this->query("DROP DATABASE " . $this->qi($dbname));
}

function droptable($tbl) {
	return $this->query("DROP TABLE " . $this->qi($tbl));
}

// returns true if the database name given exists
function dbexists($dbname) {
	die("Abstract method called: " . $this->classname . "::dbexists");
}

function quote_identifier($id) {
	if ($id === NULL) return $id;
	$quote = SQL_IDENTIFIER_QUOTE_CHAR;
	return $quote . str_replace($quote, $quote.$quote, $id) . $quote;
}
function qi($id) {	// alias for quote_identifier
	return $this->quote_identifier($id); 
}

// optimize the given table (or array of tables)
function optimize($tbl) { }	// abstract method

function begin() {
	$this->query("BEGIN");
}

function commit() {
	$this->query("COMMIT");
}

function rollback() {
	$this->query("ROLLBACK");
}

// escapes a value for insertion into the DB, will try to use the best method available on the current PHP version
function escape($str, $q = false) {
	$func = $this->escape_func;
	$was_null = is_null($str);
	if ($str === false) {
		$str = 0;
	} elseif ($str === true) {
		$str = 1;
	} elseif ($str === null) {
		$str = 'NULL';
	}
	$str = @$func($this->dbh, $str);
	if ($q and !$was_null and !is_numeric($str)) $str = "'$str'";
	return $str;
}

function fatal($new=NULL) {
	$old = $this->conf['fatal'];
	if ($new !== NULL) $this->conf['fatal'] = $new;
	return $old;
}

// reports a fatal error and DIE's
function _fatal($msg) {
	$err = $msg;
	$err .= "\n\n" . $this->errstr . "\n\n" . $msg;
	$err .= "<hr>";
	if ($this->fatal()) die(nl2br($err, false));
}

// returns the last error generated
function lasterr() {
	return $this->errstr;
}

// stores the current error and holds it for future reporting
function error($e, $force = false) {
	$e = trim($e);
	if (!empty($e) and ($e != $this->lasterr() or $force)) {
		$this->errstr = $e;		// assign the current error
		$this->errors[] = array('error' => $e, 'query' => $this->lastcmd);
	}
}

function clear_errors() {
	$old = $this->errors;
	$this->errors = array();
	$this->errstr = '';
	return $old;
}

// returns all errors generated
function allerrors() {
	return $this->errors;
}

// return a table name with the proper prefix
function table($table) {
	return $this->dbtblprefix . $table;
}

function version($force = false) {
	static $info = array();
	if (!$this->connected) return 0;
	if (!$info or $force) $info = $this->server_info();
	return $info['version'];
}

// these expressions are used in queries that combine players together (ie: clan pages)
function _expr_percent($ary) 		{ return "ROUND(IFNULL(SUM($ary[0]) / SUM($ary[1]) * 100, 0.00), 2)"; }
function _expr_percent2($ary) 		{ return "ROUND(IFNULL(SUM($ary[0]) / (SUM($ary[0])+SUM($ary[1])) * 100, 0.00), 2)"; }
function _expr_ratio($ary) 		{ return "ROUND(IFNULL(SUM($ary[0]) / SUM($ary[1]), SUM($ary[0])), 2)"; }
function _expr_ratio_minutes($ary) 	{ return "ROUND(IFNULL(SUM($ary[0]) / (SUM($ary[1]) / 60), SUM($ary[0])), 2)"; }

// same as above but these are used for non-clan pages that aren't compiled (plr_sessions)
function _soloexpr_percent($ary) 	{ return "ROUND(IFNULL($ary[0] / $ary[1] * 100, 0.00), 2)"; }
function _soloexpr_ratio($ary) 		{ return "ROUND(IFNULL($ary[0] / $ary[1], $ary[0]), 2)"; }
function _soloexpr_ratio_minutes($ary) 	{ return "ROUND(IFNULL($ary[0] / ($ary[1] / 60), $ary[0]), 2)"; }

function _expr_min($ary)		{ return "IF($ary[0] < $ary[1], $ary[0], $ary[1])"; }
function _expr_max($ary)		{ return "IF($ary[0] > $ary[1], $ary[0], $ary[1])"; }

}

?>
