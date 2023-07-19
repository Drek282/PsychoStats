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
 *	Version: $Id: go-done.php 476 2008-06-04 00:28:42Z lifo $
 */

/*
	Installation is DONE! Explain to the user what to do next.
*/
if (!defined("PSYCHOSTATS_INSTALL_PAGE")) die("Unauthorized access to " . basename(__FILE__));

$validfields = array('done');
$cms->theme->assign_request_vars($validfields, true);

if ($done) {
	$cms->session->delete_cookie('_opts');
	deleteTree('../install/', $keepRootFolder = false);
	$cookieconsent = true;
	gotopage("../admin/logsources.php");
}

// make DB connection
load_db_opts();
$db->config(array(
	'dbtype' => $dbtype,
	'dbhost' => $dbhost,
	'dbport' => $dbport,
	'dbname' => $dbname,
	'dbuser' => $dbuser,
	'dbpass' => $dbpass,
	'dbtblprefix' => $dbtblprefix
));
$db->clear_errors();
$db->connect();

if (!$db->connected || !$db->dbexists($db->dbname)) {
	if ($ajax_request) {
		print "<script>window.location = 'go.php?s=db&re=1&install=" . urlencode($install) . "';</script>";
		exit;
	} else {
		gotopage("go.php?s=db&re=1&install=" . urlencode($install));
	}
}

// now that the DB connection should be valid, reinitialize, so we'll have full access to user and session objects
$cms->init();
$ps = PsychoStats::create(array( 'dbhandle' => &$db ));
$ps->theme_setup($cms->theme);


$cms->theme->assign(array(
));

if ($ajax_request) {
//	sleep(1);
	$pagename = 'go-done-results';
	$cms->tiny_page($pagename, $pagename);
	exit();
}

?>
