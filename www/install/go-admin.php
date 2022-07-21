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
 *	Version: $Id: go-admin.php 476 2008-06-04 00:28:42Z lifo $
 */

/*
	Create Admin user(s)
*/
if (!defined("PSYCHOSTATS_INSTALL_PAGE")) die("Unauthorized access to " . basename(__FILE__));

$validfields = array('username','password','password2','del');
$cms->theme->assign_request_vars($validfields, true);

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

$errors = array();
$filter = array('accesslevel' => $cms->user->acl_admin());
$action = "created";
$admin_list = array();

$cms->theme->assign_by_ref('errors', $errors);
$cms->theme->assign_by_ref('action', $action);
$cms->theme->assign_by_ref('admin_list', $admin_list);

// delete the specified admin 
if ($ajax_request and $del != '') {
	$action = "deleted";
	if (!$cms->user->delete_user($del, 'username')) {
		$errors['fatal'] = "Error deleting admin '$del': " . $cms->user->db->errstr;
	}
}

// load current admin list
$admin_list = load_admins();
$allow_next = ( $cms->user->total_users($filter) > 0 );

$cms->theme->assign(array(
	'deleted'	=> $del,
));

if ($ajax_request) {
//	sleep(1);

	if (!$del) {
		$username = trim($username);
		$password = trim($password);
		$password2 = trim($password2);

		if ($username == '') {
			$errors['username'] = "Please enter a valid username!";
		} elseif ($cms->user->username_exists($username)) {
			$errors['username'] = "Username already exists!";
		}

		if ($password == '') {
			$errors['password'] = "Please enter a password!";
		} elseif ($password != $password2) {
			$errors['password2'] = "Password mismatch, Please try again!";
		}

		if (!$errors) {
			$set = array(
				'userid'	=> $cms->user->next_userid(),
				'accesslevel'	=> $cms->user->acl_admin(),
				'username'	=> $username,
				'password'	=> $cms->user->hash($password),
				'lastvisit'	=> time(),
				'session_last'	=> time(),
				'confirmed'	=> 1
			);
			if (!$cms->user->insert_user($set)) {
				$errors['fatal'] = "Error creating user: " . $cms->user->db->errstr;
			} else {
				$admin_list = load_admins();
				$allow_next = true;
			}
		}

	}

	$pagename = 'go-admin-results';
	$cms->tiny_page($pagename, $pagename);
	exit();
}

function load_admins() {
	global $cms, $filter;
	$list = $cms->user->get_user_list(false, $filter);
	$admin_list = array();
	foreach ($list as $u) {
		$admin_list[] = "<a href='javascript:void(0)'>" . ps_escape_html($u['username']) . "</a>";
	}
	return $admin_list ? join(', ',$admin_list) : '';
}

?>
