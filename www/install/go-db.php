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
 *	Version: $Id: go-db.php 476 2008-06-04 00:28:42Z lifo $
 */

/*
	Determine DB settings and verify connectivity
*/
if (!defined("PSYCHOSTATS_INSTALL_PAGE")) die("Unauthorized access to " . basename(__FILE__));

$validfields = array('conf');
$cms->theme->assign_request_vars($validfields, true);

$min_db_version = '4.1.13';

$dbtest = array();

$allow_next = false;

$cms->theme->assign_by_ref('dbtest', $dbtest);
$cms->theme->assign_by_ref('db_errors', $db->errors);
$cms->theme->assign_by_ref('db_queries', $db->queries);
$cms->theme->assign(array(
	'min_db_version'	=> $min_db_version,
));


if ($ajax_request) {
//	sleep(1);
	do_test($dbtest);
	save_db_opts();
	$pagename = 'go-db-results';
	$cms->tiny_page($pagename, $pagename);
	exit();
} else {
	if ($dbhost != '') {
		do_test($dbtest, true);
		if (!$db->connected or !$dbtest['selected']) $dbtest['tested'] = false;
	}
	save_db_opts();
}

function do_test(&$t, $skip_create = false) {
	global $cms, $db, $min_db_version, $allow_next, $conf;
	global $dbhost, $dbport, $dbname, $dbuser, $dbpass, $dbtblprefix;

	load_db_opts($conf);
	$db->config(array(
		'dbhost' => $dbhost,
		'dbport' => $dbport,
		'dbname' => $dbname,
		'dbuser' => $dbuser,
		'dbpass' => $dbpass,
		'dbtblprefix' => $dbtblprefix
	));
	$db->clear_errors();

	$t['tested'] = true;
	$t['type'] = $db->type();
	$db->connect(true);
	$t['connected'] = $db->connected;
	$t['selected'] = $db->selected;
	if ($db->connected) {
		$db_version = $db->version();
		$t['db_ver'] = $db_version;
		$t['min_ver'] = (version_compare($min_db_version, $db_version) < 1);
	}

	// don't bother doing any more tests if the DB version is too low
	if (!$t['min_ver']) return false;

	// the dbname was invalid, so lets try and create it...
	$t['created'] ??= null;
	if (!$t['selected']) {
		if ($db->dbexists($db->dbname)) {
			$t['exists'] = true;
		} else if (!$skip_create and $db->createdb($db->dbname, "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) { // switch charset & collation from utf8_general_ci to utf8mb4_general_ci
			array_pop($db->errors);
			$t['created'] = true;
		}
		if ($t['exists'] || $t['created']) {
			$t['selected'] = $db->selectdb();
		}
	} else {
		$t['exists'] = true;
	}

	// make sure the database charset is 'utf8'. 
	// We won't try to alter the DB if its not since that might mess up existing databases.
	if (version_compare('5.0', $db->version()) < 1) {
		$db2 = $db->copy();
		$db2->selectdb('information_schema');
		list($charset) = $db2->fetch_list("SELECT DEFAULT_CHARACTER_SET_NAME FROM SCHEMATA WHERE SCHEMA_NAME=" . $db->escape($db->dbname, true));
		$t['charset'] = ($charset == 'utf8mb4');
		$t['charset_str'] = $charset;
		unset($db2);
		$db->selectdb();	// both DB objects will be using the same DB resource, so change the DB back.
	} else {
		list($x,$str) = $db->fetch_row(0,"SHOW CREATE DATABASE " . $db->qi($db->dbname));
		if (preg_match('/CHARACTER SETs (\S+)/', $str, $m)) {
			$t['charset'] = ($m[1] == 'utf8mb4');
			$t['charset_str'] = $m[1];
		} else {
			// do not do anything if we can't determine the charset. Incase there is a difference in 
			// other mysql versions that I do not know about. We'll just silently ignore this.
			$t['charset'] = true;
		}
	}

	// perform some privilege tests on the database
	// the user must be able to create tables, select, insert and update
	if ($t['selected']) {
		$tbl = $db->dbtblprefix . 'test_'. substr(md5(uniqid(rand(), true)), 0, 8);
		$t['table'] = $tbl;
		$t['tbl_create'] = $db->query(
			"CREATE TABLE " . $db->qi($tbl) . " ( id INT UNSIGNED NOT NULL ) " . 
			"ENGINE = MYISAM CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci "
		);
		if ($t['tbl_create']) {
			// attempt an insert
			$t['tbl_insert'] = $db->insert($tbl, array('id' => 1));
			$t['tbl_update'] = $db->update($tbl, array('id' => 2), 'id', 1);
			$t['tbl_select'] = ($db->query("SELECT id FROM " . $db->qi($tbl)  . " LIMIT 1") != false);
			$t['tbl_delete'] = $db->delete($tbl, 'id', 2);
			$t['tbl_drop']   = $db->droptable($tbl);
		}
	}

	// determine if we're able to go NEXT. Remove test results that are not actual errors first.
	$err = $t;
	unset($err['charset'], $err['charset_str'], $err['tbl_drop'], $err['table']);
	$false = array_search(false, $err, true);
	$allow_next = (!$false);
}


?>
