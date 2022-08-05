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
 *	Version: $Id: class_user.php 541 2008-08-18 11:24:58Z lifo $
 */

/*
	PsychoStats user class. 
	First conceived on March 22nd, 2007 by Stormtrooper

	Depends: class_session.php, class_DB.php

	Encapsulates a user session data (userid, name, etc...)
	User info can be loaded from and saved to the database.
	The class also provides some utility methods like determining if 
	a username already exists or authorizing a user login, etc...

	This class can be overridden to provide 3rd party integration into
	PsychoStats (ie: using authorization from another CMS or Forum software).

	This relies on the Session and DB PsychoStats classes.

*/

if (defined("CLASS_PSYCHOUSER_PHP")) return 1;
define("CLASS_PSYCHOUSER_PHP", 1);

// some global defines for PsychoUser accesslevels
define("PU_ACL_BANNED", 0);
define("PU_ACL_GUEST", 1);
define("PU_ACL_USER", 2);
define("PU_ACL_CLANADMIN", 10);
define("PU_ACL_ADMIN", 99);

class PsychoUser {
var $info = array();
var $options = array();
var $session = null;
var $db = null;
var $autherr = "";
var $loaded = false;
var $loaderr = "";

function __construct(&$session, &$db) {
	$this->session =& $session;
	$this->db =& $db;
}
 
function PsychoUser(&$session, &$db) {
        self::__construct($session, $db);
}

// loads the user information from the database.
// $key is 'userid' by default but can be changed to load a user based off another column (like 'username')
function load($userid, $key='userid') {
	$u = $this->db->fetch_row(1, sprintf("SELECT * FROM %s WHERE %s='%s'",
		$this->db->table('user'),
		$this->db->qi($key),
		$this->db->escape($userid)
	));
	if (is_array($u)) {
		$this->info = $u;
		$this->loaded = true;
		$this->loaderr = '';
	} else {
		// if there was an error loading the user then default to a guest.
		$this->loaded = false;
		$this->loaderr = $this->db->errstr;
		$this->info(array(
			'userid'	=> 0,
			'username'	=> 'Guest',
			'password'	=> '',
			'confirmed'	=> 0,
			'lastvisit'	=> $this->session->session_start(),
			'session_last'	=> time(),
			'accesslevel'	=> PU_ACL_USER,
		));
	}

	return $this->loaded;	
}

// saves the CURRENT user to the database. 
// Do not confuse this with 'update_user' or 'insert_user' utility methods which are used for any user.
function save() {
	// don't try to save a user if there is no username
	if (!$this->username()) {
		// trigger_error(...)
		return false;
	}

	// if we don't have a userid yet set one...
	if (!$this->userid()) {
		$this->userid($this->next_userid());
	}

	// insert or update the user record
	if ($this->userid_exists($this->userid())) {
		return $this->update_user($this->info, $this->userid());
	} else {
		return $this->insert_user($this->info);
	}
}

// delete the current user record from the database.
function delete() {
	if (!$this->userid()) {
		return false;
	}
	return $this->delete_user($this->userid());
}

// loads options that were saved in the users browser cookie
function load_session_options() {
	if (is_object($this->session)) {
		$this->options = $this->session->load_session_options();
		return $this->options;
	}
	return array();
}

// saves the current options into the users browser cookie
function save_session_options($opts = null) {
	if (is_object($this->session)) {
		$this->session->save_session_options($opts !== null ? $opts : $this->options);
	}
}

// warning: a session's userid can possibly get out-of-sync if you are not careful
// changing the user's ID here will not update the session.
function userid($new = null) {
	if ($new === null) {
		return $this->info['userid'];
	} else {
		$old = $this->info['userid'];
		$this->info['userid'] = $new;
		return $old;
	}
}

// The username of the current user.
function username($new = null) {
	if ($new === null) {
		return $this->info['username'];
	} else {
		$old = $this->info['username'];
		$this->info['username'] = $new;
		return $old;
	}
}

// the users password... duh.
function password($new = null) {
	if ($new === null) {
		return $this->info['password'];
	} else {
		$old = $this->info['password'];
		$this->info['password'] = $new;
		return $old;
	}
}

// the users confirmation flag
function confirmed($new = null) {
	if ($new === null) {
		return $this->info['confirmed'];
	} else {
		$old = $this->info['confirmed'];
		$this->info['confirmed'] = $new;
		return $old;
	}
}

// return the current access level of the user (See the PU_* constants)
function accesslevel($new = null) {
	if ($new === null) {
		return $this->info['accesslevel'];
	} else {
		$old = $this->info['accesslevel'];
		$this->info['accesslevel'] = $new;
		return $old;
	}
}

// the last time the user started a new session.
function lastvisit($new = null) {
	if ($new === null) {
		return $this->info['lastvisit'];
	} else {
		$old = $this->info['lastvisit'];
		$this->info['lastvisit'] = $new;
		return $old;
	}
}

// populate the user information all at once
function info($info = array()) {
	// TODO: add some validation to the info array
	$this->info = $info;
}

// returns true if the user's accesslevel is at least the minimum specified.
function has_access($min = PU_ACL_USER) {
	return ($this->accesslevel() >= $min);
}

function is_admin() {
	return ($this->accesslevel() >= PU_ACL_ADMIN);
}

// --- Utility methods for users are below. These methods operate on any user. 
// --- These methods need to be overridden if the default user behavior is changed from a plugin.

// returns true if the user is actually logged in (not an anonymous or guest user)
function logged_in() {
	return $this->session->logged_in();
}

// returns true if the user is actually logged in and is an ADMIN session
function admin_logged_in() {
	return ($this->session->logged_in() and $this->session->is_admin());
}

// returns a hashed string from a plain text string (usually a password).
// this is used to hash passwords. Usually md5() is enough but some 3rd party systems have more complex algorithms
function hash($password) {
	return md5($password);
}

// return a userid if the username and password are valid. 
// returns false if the authorization was invalid.
// sets $autherr property so the caller can determine why it failed.
// if $ishash is true then the password has already been hashed (md5(), etc).
function auth($username, $password, $ishash = false) {
	$auth = false;
	$this->autherr = "";
	if (!$ishash) $password = $this->hash($password);

	list($realpass, $userid, $confirmed, $acl) = $this->db->fetch_list(
		sprintf("SELECT %s,userid,confirmed,accesslevel FROM %s WHERE %s='%s' LIMIT 1", 
			$this->db->qi('password'),
			$this->db->table('user'),
			$this->db->qi('username'),
			$this->db->escape($username)
		)
	);

	// if $realpass is null then the username didn't exist
	if ($realpass == null or $realpass != $password) {
		$this->autherr = "Invalid username or password";	// be ambiguous
	} elseif (!$confirmed) {
		$this->autherr = "User is not confirmed";
	} elseif ($acl < PU_ACL_USER) {
		$this->autherr = "User does not have permission";
	}

	if ($this->autherr == "") {	// no errors
		$auth = $userid;
	}

	return $auth;
}

// returns true if the username given already exists in the database
function username_exists($username) {
	return $this->db->exists($this->db->table('user'), 'username', $username);
}

// returns true if the userid given already exists in the database
function userid_exists($userid) {
	return $this->db->exists($this->db->table('user'), 'userid', $userid);
}

// deletes a user from the database matching the key (usually 'userid')
// note: this does not de-associate the user from a player profile.
function delete_user($userid, $key='userid') {
	return $this->db->delete($this->db->table('user'), $key, $userid);
}

// sets the confirm flag for the user specified
function confirm_user($flag, $userid, $key='userid') {
	return $this->db->update($this->db->table('user'), array( 'confirmed' => $flag ), $key, $userid);
}

// updates a user record in the database matching the key (usually 'userid')
function update_user($set, $userid, $key='userid') {
	return $this->db->update($this->db->table('user'), $set, $key, $userid);
}

// inserts a new user into the database. Note: the primary key for the user (ie: userid)
// is assumed to already be defined in the $set array.
function insert_user($set) {
	return $this->db->insert($this->db->table('user'), $set);
}

// returns the next available ID to be used for a new user
function next_userid() {
	return $this->db->next_id($this->db->table('user'), 'userid');
}

// loads a user from the database and returns it. This does not affect the current object
function load_user($userid, $key='userid') {
	$u = $this->db->fetch_row(1, sprintf("SELECT * FROM %s WHERE %s='%s'",
		$this->db->table('user'),
		$this->db->qi($key),
		$this->db->escape($userid)
	));
	return $u;
}

// returns a list of users.
// @param $join_plr if true will cause the matching player profile to be included in the results.
// @param $filter defines the start,limit,sort,order and search filter.
function get_user_list($join_plr = false, $filter = array()) {
	$filter += array(
		'start'		=> 0,
		'limit'		=> 100,
		'order'		=> 'asc',
		'sort'		=> 'username',
		'username'	=> '',		// search filter "LIKE"
		'confirmed'	=> null,
		'accesslevel' => null
	);

	if (!is_numeric($filter['start']) or $filter['start'] < 0) $filter['start'] = 0;
	if (!is_numeric($filter['limit']) or $filter['limit'] < 0) $filter['limit'] = 100;
	if (!in_array($filter['order'], array('asc','desc'))) $filter['order'] = 'asc';
	if (!in_array($filter['sort'], array('username'))) $filter['sort'] = 'username'; // only allow 'username' for now

	$fields = '';
	if ($join_plr) $fields = 'pp.*,p.plrid,';
	$fields .= 'u.*';

	$cmd  = "SELECT $fields FROM " . $this->db->table('user') . " u ";
	if ($join_plr) {
		$cmd .= "LEFT JOIN " . $this->db->table('plr_profile') . " pp ON pp.userid=u.userid ";
		$cmd .= "LEFT JOIN " . $this->db->table('plr') . " p ON p.uniqueid=pp.uniqueid ";
	}

	$where = "";
	if ($filter['username']) {
		if (!$where) $where .= "WHERE ";
		$where .= "username LIKE '%" . $this->db->escape($filter['username']) . "%' ";
	}

	if ($filter['confirmed'] != null and $filter['confirmed'] > -1) {
		$where .= $where ? "AND " : "WHERE ";
		$where .= "confirmed = " . $this->db->escape($filter['confirmed'], true);
	}

	if ($filter['accesslevel'] != '') {
		$where .= $where ? "AND " : "WHERE ";
		$where .= "accesslevel = " . $this->db->escape($filter['accesslevel'], true);
	}


	$cmd .= " " . $where;
	$cmd .= " " . $this->db->sortorder($filter);
	$list = $this->db->fetch_rows(1, $cmd);
	return $list;
}

// returns the total number of users in the database, optionally matching those to the $filter supplied.
function total_users($filter = array()) {
	$where = "";
	$filter['username'] ??= null;
	if ($filter['username'] != '') {
		$where .= "username LIKE '%" . $this->db->escape($filter['username']). "%' ";
	}
    
	$filter['confirmed'] ??= null;
	if ($filter['confirmed'] != '') {
		if ($where) $where .= "AND ";
		$where .= "confirmed = " . $this->db->escape($filter['confirmed'], true);
	}
    
	$filter['accesslevel'] ??= null;
	if ($filter['accesslevel'] != '') {
		if ($where) $where .= "AND ";
		$where .= "accesslevel = " . $this->db->escape($filter['accesslevel'], true);
	}

	return $this->db->count($this->db->table('user'), '*', $where);
}

function acl_str($acl) {
	switch ($acl) {
		case PU_ACL_BANNED: 	$str = 'Banned'; break;
		case PU_ACL_GUEST: 	$str = 'Guest'; break;
		case PU_ACL_USER: 	$str = 'User'; break;
		case PU_ACL_CLANADMIN: 	$str = 'Clan Admin'; break;
		case PU_ACL_ADMIN: 	$str = 'Administrator'; break;
		default: 		$str = '';
	}
	return $str;
}

function dberr() {
	return $this->db->errstr;
}

// Accesslevel values for users
function acl_banned() 	{ return PU_ACL_BANNED; }
function acl_guest() 	{ return PU_ACL_GUEST; }
function acl_user()  	{ return PU_ACL_USER; }
function acl_clanadmin(){ return PU_ACL_CLANADMIN; }
function acl_admin() 	{ return PU_ACL_ADMIN; }

// returns an array of access level values
function accesslevels() {
	return array(
		PU_ACL_BANNED		=> $this->acl_str(PU_ACL_BANNED),
		PU_ACL_GUEST		=> $this->acl_str(PU_ACL_GUEST),
		PU_ACL_USER		=> $this->acl_str(PU_ACL_USER),
		PU_ACL_CLANADMIN	=> $this->acl_str(PU_ACL_CLANADMIN),
		PU_ACL_ADMIN		=> $this->acl_str(PU_ACL_ADMIN),
	);
}

// form functions
// init the form. allows other user objects to add more fields to the form
// ... not sure how this will work yet ...
function & init_form(&$form) {
	$form->default_modifier('trim');
}

// returns an array of key=>value pairs to populate a user form
function to_form_input() {
	return array(
		'userid'	=> $this->info['userid'],
		'username'	=> $this->info['username'],
//		'password'	=> $this->info['password'],	// hashed, so it's useless in a form
		'accesslevel'	=> $this->info['accesslevel'],
		'confirmed'	=> $this->info['confirmed']
	);
}


} // end of PsychoUser

?>
