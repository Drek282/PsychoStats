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
 *	Version: $Id: class_session.php 569 2008-11-05 19:03:02Z lifo $
 */

/***
	PsychoStats Session class

	This session class is for use with the PsychoCMS framework. It provides a basic 
	framework for dealing with user sessions. This version of the session class only 
	works with the PsychoCMS object.

	Plugins may wish to inherit this class in order to allow for other types of sessions, 
	for example, integrating a session handler from 3rd party forum software.

***/

// MYSQL session table for this session class is at the bottom of this file.

if (defined("CLASS_PSYCHO_SESSION_PHP")) return 1;
define('CLASS_PSYCHO_SESSION_PHP', 1);

#[\AllowDynamicProperties]
class PsychoSession {
const CIPHER = "AES-128-CBC";
var $config = array();
var $_is_bot = NULL;
var $_is_new = 0;
var $db = 0;
var $ended = 1;
var $sidmethod = 'get';
var $SESSION_BOTS = array();
var $sid = '';
var $options = array();
var $sessdata = array(				// stores all the session data (stored in the database, not the cookie)
	'session_id'		=> '',
	'session_userid'	=> 0,
	'session_start'		=> 0,
	'session_last'		=> 0,
	'session_ip'		=> 0,
	'session_logged_in'	=> 0,
	'session_key'		=> null,
	'session_key_time'	=> null,
	'session_is_admin'	=> 0,
	'session_is_bot'	=> 0,
);

// session constructor. Sets defaults and will automatically start a new session or load an existing one
function __construct($_config = array()) {
	$this->config = array(
		'cms'			=> null,			// a CMS object MUST be passed in
		'dbhandle'		=> 0,
		'delaystart'		=> 0,
		'cookielife'		=> 60 * 60,			// 1 hour
		'cookielifeoptions'	=> 60 * 60 * 24 * 30,		// ~30 days
		'cookiedomain'		=> '',
		'cookiepath'		=> '/',
		'cookiesecure'		=> host_secure() ? 1 : 0,	// only set if host is using HTTPS
		'cookiesalt'		=> '',				// mcrypt module must be installed if this is !empty	
		'cookiecompress'	=> TRUE,
		'cookieencode'		=> TRUE,
		'login_callback_func'	=> '',
		'match_agent_ip'	=> FALSE,			// i haven't kept up-to-date on bot IPs

		'db_session_table'	=> 'sessions',
		'db_user_table'		=> '',				// if blank, extra user features are ignored...
		'db_user_session_last'	=> 'session_last',		// table field name to update the users last session request
		'db_user_login_key'	=> 'session_login_key',		// table field name to hold auto-login token
		'db_user_last_visit'	=> 'lastvisit',			// table field name to update the users last (previous) visit
		'db_user_id'		=> 'userid',			// table field name for the users "user id"

		// these aren't used in this version of the session class; pass a dbhandle above instead
		'dbuser'		=> '',
		'dbpass'		=> '',
		'dbhost'		=> 'localhost',
		'dbname'		=> 'sessions',
	);

	// *** This list is way out of date; It's too tedious to try and maintain it ***
	// user agent, name, ip substrs
	$this->SESSION_BOTS = array();
	$this->SESSION_BOTS[] = NULL;		// we don't want index 0 to be used
	$this->SESSION_BOTS[] = array('Google', 	'Googlebot', 	'216.239.46.|64.68.8|64.68.9|164.71.1.|192.51.44.|66.249.71.|66.249.64.|66.249.65.|66.249.66.');
	$this->SESSION_BOTS[] = array('ia_archiver', 	'Alexa', 	'66.28.250.|209.237.238.');
	$this->SESSION_BOTS[] = array('Slurp/', 	'Inktomi', 	'216.35.116.|66.196.|66.94.230.|202.212.5.');
	$this->SESSION_BOTS[] = array('Infoseek', 	'Infoseek', 	'204.162.9|205.226.203|206.3.30.|210.236.233.');
	$this->SESSION_BOTS[] = array('Scooter', 	'Alta Vista', 	'194.221.84.|204.123.28.|208.221.35|212.187.226.|66.17.148.');
	$this->SESSION_BOTS[] = array('Lycos', 		'Lycos', 	'208.146.27.|209.202.19|209.67.22|202.232.118.');
	$this->SESSION_BOTS[] = array('alltheweb', 	'FAST', 	'146.101.142.2|216.35.112.|64.41.254.2|213.188.8.');
	$this->SESSION_BOTS[] = array('WISEnut', 	'WiseNut', 	'64.241.243.|209.249.67.1|216.34.42.|66.35.208.');
	$this->SESSION_BOTS[] = array('msnbot/', 	'MSN',  	'131.107.3.|204.95.98.|131.107.1|65.54.164.95|65.54.164.3|65.54.164.4|65.54.164.5|65.54.164.6|207.46.98.');
	$this->SESSION_BOTS[] = array('MARTINI', 	'Looksmart', 	'64.241.242.|207.138.42.212');
	$this->SESSION_BOTS[] = array('teoma', 		'Ask Jeeves', 	'216.200.130.|216.34.121.|63.236.92.1|64.55.148.|65.192.195.|65.214.36.');

	$this->config = array_merge($this->config, $_config);

	// A CMS object must be passed to us. We primarily need it for inputs
	if (empty($this->config['cms'])) {
		trigger_error("Session class instantiated without a CMS object!", E_ERROR);
	}
	$this->cms =& $this->config['cms'];

	// a database object must be passed to us
	$this->db =& $this->config['dbhandle'];

	$this->is_bot();

	if (!$this->config['delaystart']) $this->start();		// start session if its not 'delayed'
}
 
function PsychoSession($_config = array()) {
        self::__construct($_config);
}

// gets/sets the admin flag of the session
function is_admin($toggle = null) {
	$old = $this->sessdata['session_is_admin'] ?? null;
	if ($toggle !== null) $this->sessdata['session_is_admin'] = $toggle;
	return $old;
}

function is_bot() {
	if (!is_null($this->_is_bot)) return $this->_is_bot;
	$ip = $_SERVER['REMOTE_ADDR'];
	$agent = $_SERVER['HTTP_USER_AGENT'];
	$ip_match = $this->config['match_agent_ip'] ? 0 : 1;
	$agent_match = 0;

	foreach ($this->SESSION_BOTS as $idx => $row) {
#		print "$idx => $value<br>";
		if (!$row) continue;
		foreach (explode('|', $row[0]) as $bot_agent) {
#			print "$agent == $bot_agent<BR>";
			if ($bot_agent != '' && preg_match('/' . preg_quote($bot_agent, '/') . '/i', $agent)) {
#				print "AGENT MATCH!!!<BR>\n";
				$agent_match = $idx;
				break;
			}
		}

		if ($agent_match and !$ip_match) {
			foreach (explode('|', $row[2]) as $bot_ip) {
#				print "$ip == $bot_ip<BR>";
				if ($bot_ip != '' && strpos($ip, $bot_ip) === 0) {
#					print "IPADDR MATCH!!!<BR>\n";
					$ip_match = $idx;
					break;
				}
			}
		}

		if ($agent_match and $ip_match) break;
	}

	// agent_match and ip_match will always be the same bot index
	$this->_is_bot = ($agent_match and $ip_match) ? $agent_match : 0;
	return $this->_is_bot;
}

function bot_name($idx) {
	if (array_key_exists($idx, $this->SESSION_BOTS) and is_array($this->SESSION_BOTS[$idx])) {
		return $this->SESSION_BOTS[$idx][1];
	} else {
		return '';
	}
}

// generates a new random SID. If you provide the $random string it will be used to help generate the md5 hash.
function generate_sid($random="") {
	if ($this->is_bot()) {
		return sprintf("%032d", $this->is_bot());
	} else {
//		return md5(time() . mt_rand()  . $random);
		// the UNIQUE_ID may or may not actually be present. If it is, all the better.
		return md5($_SERVER['UNIQUE_ID'] ?? null . uniqid(mt_rand(), true) . $random);
	}
}

// delete expired sessions
function garbage_collect() {
	$now = time();
	$cmd = "DELETE FROM {$this->config['db_session_table']} WHERE ($now - session_last > {$this->config['cookielife']})";
	$res = $this->db->query($cmd);
}

// returns the current session SID from a COOKIE or GET data. Returns FALSE if there is none
function _find_user_sid() {
	$this->garbage_collect();
	$name = $this->sid_name();
	$sid = FALSE;
	$this->cms->cookie[$name] ??= null;
	$this->cms->input[$name] ??= null;
	if ($this->cms->cookie[$name] != '') {
		$this->sidmethod = 'cookie';
		$sid = $this->cms->cookie[$name];
	} elseif ($this->cms->input[$name] != '') {
		$this->sidmethod = 'get';
		$sid = $this->cms->input[$name];
		$this->cms->cookie[$name] = $sid;
	} else {
		$this->sidmethod = 'none';
	}
//	if ($sid != FALSE and get_magic_quotes_gpc()) stripslashes($sid);
	return $sid;
}

function is_new() {
	return $this->_is_new;
}

function is_sid($sid) {
	return preg_match('/^[a-f0-9]{32}$/', strtolower($sid));
}

// sets a cookie for the user based on the cookie settings we have. $suffix is the trailing part of the SID name. 
// '_id' or '_login'
function send_cookie($data, $time=0, $suffix='_id') {
	return setcookie(
		$this->sid_name($suffix), 
		$data, 
		$time, 
		$this->config['cookiepath'], 
		$this->config['cookiedomain'], 
		$this->config['cookiesecure']
	);
}

// returns the contents of a session cookie or false if not found
function get_cookie($suffix = '_id') {
	$name = $this->sid_name($suffix);
	if (array_key_exists($name, $this->cms->cookie)) {
		return $this->cms->cookie[$name];
	} 
	return false;
}

// short-cut method for deleting a users cookie.
function delete_cookie($suffix='_id') {
    $this->cms->cookie[ $this->sid_name($suffix) ] ??= null;
	if ($this->cms->cookie[ $this->sid_name($suffix) ]) {
		unset($this->cms->cookie[ $this->sid_name($suffix) ]);
		return $this->send_cookie("", time()-100000, $suffix);
	} 
	return 0;
}

function _read_session($sid) {
	$res = $this->db->query("SELECT * FROM " . $this->db->qi($this->config['db_session_table']) . " WHERE session_id=" . $this->db->escape($sid, true));
	if (!$res) die("<br>Fatal Session Error: in function  <b>" .  __FUNCTION__ . "</b>, in file <b>" . __FILE__ . "</b> at line <b>" . __LINE__ . "</b>: " . $this->db->lasterr() . "<br>");
	$this->sessdata = $this->db->num_rows() > 0 ? $this->db->fetch_row() : $this->_init_new_session();
}

function _save_session() {
	if ($this->ended) return 1;		// do not save anything if the session was end()'ed
	$res = $this->db->query("SELECT session_id FROM " . $this->db->qi($this->config['db_session_table']) . " WHERE session_id=" . $this->db->escape($this->sessdata['session_id'], true));
	list($exists) = $this->db->fetch_row(0);
	if (!$res) die("<br>Fatal Session Error: in function  <b>" .  __FUNCTION__ . "</b>, in file <b>" . __FILE__ . "</b> at line <b>" . __LINE__ . "</b>: " . $this->db->lasterr() . "<br>");

	// don't allow a blank key, set it to null instead
	if (empty($this->sessdata['session_key'])) $this->sessdata['session_key'] = null;

	if ($exists) {
		$this->db->update($this->config['db_session_table'], $this->sessdata, 'session_id', $this->sessdata['session_id']);
	} else {
		$this->db->insert($this->config['db_session_table'], $this->sessdata);
	}
/*
	$prefix = ($exists) ? "UPDATE" : "INSERT INTO";
	$cmd = "$prefix " . $this->db->qi($this->config['db_session_table']) . " SET ";
	foreach ($this->sessdata as $k => $v) {
		$cmd .= sprintf("%s=%s, ", $this->db->qi($k), $this->db->escape($v, true));
	}
	$cmd = substr($cmd, 0, -2); // strip off trailing ', '
	if ($exists) $cmd .= " WHERE session_id=" . $this->db->escape($this->sessdata['session_id'], true);
//	print "SAVE SESSION: $cmd<br>";
	$res = $this->db->query($cmd);
*/
	if (!$res) die("<br>Fatal Session Error: in function  <b>" .  __FUNCTION__ . "</b>, in file <b>" . __FILE__ . "</b> at line <b>" . __LINE__ . "</b>: " . $this->db->lasterr() . "<br>");
}

function _init_new_session() {
//	print "INIT SESSION ... <br>";
	$this->_is_new = 1;
	$this->sid($this->generate_sid());
	$this->sessdata = array(
		'session_id'		=> $this->sid(),
		'session_userid'	=> 0,
		'session_start'		=> time(),
		'session_last'		=> time(),
		'session_ip'		=> sprintf("%u", ip2long($_SERVER['REMOTE_ADDR'])),
		'session_logged_in'	=> 0,
		'session_is_bot'	=> $this->is_bot(),
	);
}

// private method to get or set the users current SID cookie
function _session_start() {
	global $ps;
	global $cookieconsent;
	$sid = $this->_find_user_sid();
	if (!$sid or !$this->is_sid($sid)) {
#		print "NEW SESSION STARTING ... <BR>";
		$this->_init_new_session();
#		$this->_save_session();				// always SAVE when we create a new session
		if ($cookieconsent or !$ps->conf['main']['security']['enable_cookieconsent']) $this->send_cookie($this->sid());
	} else {
#		print "PREVIOUS SESSION STARTING ... <BR>";
		$this->_read_session($sid);
		$this->sid($sid);
		if ($this->_expired()) {
			$this->delete_session($this->sid());		// deletes old session from database
			$this->_init_new_session();			// generate a new dataset
#			$this->_save_session();
			$this->delete_cookie();				// delete old sess_id cookie
			if ($cookieconsent or !$ps->conf['main']['security']['enable_cookieconsent']) $this->send_cookie($this->sid());		// send a new cookie
		}
	}
}

// starts the session (no need to call this unless delaystart is true) ----------------------------
function start() {
	$this->ended = 0;
	$this->_session_start();
	$this->_initkey();

	$now = time();
	$this->sessdata['session_last'] = $now;

	// If the user is NOT logged in and there is a 'login' cookie set, try to verify and log the user in automatically
//	print "START SESSION<br/>\n";
	if ($this->online_status()==0 and !empty($this->cms->cookie[ $this->sid_name('_login') ])) {
		$auto = $this->load_login();
		if (isset($auto['userid']) and isset($auto['password'])) {
			$func = $this->config['login_callback_func'];
			$userid = is_callable($func) ? call_user_func($func, $auto['userid'], $auto['password']) : false;
			if ($userid) {
				$this->online_status(1, $userid); 		// user is now magically online!
			} else {
				$this->delete_cookie('_login');			// login cookie was invalid, so delete it
			}
		} else {
			$this->delete_cookie('_login');				// login cookie was invalid, so delete it
		}
	}

	// Update the users LAST event timestamp (in the users database, not the session database). 
	// When the cookie expires this will remain in the user data to keep track of the last time the user did anything.
	// Used for keeping track of NEW messages, etc.
	// This must be done AFTER the autlogin block above! otherwise users that are auto logged in will never have the correct
	// 'last visit' timestamp.
	if (!empty($this->config['db_user_table'])) {
		if ($this->sessdata['session_userid'] > 0 and $this->sessdata['session_logged_in']) {
			$cmd = sprintf("UPDATE %s SET %s=$now WHERE %s='%s'", 
				$this->db->qi($this->config['db_user_table']), 
				$this->db->qi($this->config['db_user_session_last']), 
				$this->db->qi($this->config['db_user_id']), 
				$this->db->escape($this->sessdata['session_userid'])
			);
//			print "UPDATE USER: $cmd<br>";
			$res = $this->db->query($cmd);
		}
	}

	// session data will be saved before the script exits
	register_shutdown_function(array(&$this, '_save_session'));
} // end function start()


// Initializes the encryption engine for encrypting user cookies
function _initkey() {
	$this->session_encrypted = false;
	if ($this->config['cookiesalt'] and function_exists('openssl_encrypt')) {
		$salt = $this->config['cookiesalt'] == -1 ? $this->sid() : $this->config['cookiesalt'];
		$this->iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this::CIPHER));
		$this->key = $salt;
		$this->session_encrypted = true;
	}
	return $this->session_encrypted;
}

function encrypt($str) {
    $this->session_encrypted = $this->session_encrypted ?? false;
	if (!$this->session_encrypted) return $str;
	$ciphertext_raw = openssl_encrypt($str, $this::CIPHER, $this->key, $options=OPENSSL_RAW_DATA, $this->iv);
	return base64_encode( $this->iv.$ciphertext_raw );
}
 
function decrypt($str) {
    $this->session_encrypted = $this->session_encrypted ?? false;
	if (!$this->session_encrypted) return $str;
	$c = base64_decode($str);
	$ivlen = openssl_cipher_iv_length($this::CIPHER);
	$iv = substr($c, 0, $ivlen);
	$ciphertext_raw = substr($c, $ivlen);
	return openssl_decrypt($ciphertext_raw, $this::CIPHER, $this->key, $options=OPENSSL_RAW_DATA, $iv);
}

// sets or gets the current online status for the session. If the online status is changed, the previous value is returned.
function online_status($online=-1, $userid=0) {
	$status = $this->sessdata['session_logged_in'];			// get original status by default
	if ($online >= 1) {						// LOGIN THE USER
		$this->sessdata['session_logged_in'] = 1;
		$this->sessdata['session_userid'] = $userid;
#		$this->_save_session();
		if (!empty($this->config['db_user_table'])) {
			$res = $this->db->query(sprintf("SELECT %s FROM %s WHERE %s='%s' LIMIT 1",
				$this->db->qi($this->config['db_user_session_last']),
				$this->db->qi($this->config['db_user_table']),
				$this->db->qi($this->config['db_user_id']),
				$this->db->escape($userid)
			));
			list($last) = $res ? $this->db->fetch_row(0) : time();
			$res = $this->db->query(sprintf("UPDATE %s SET %s=$last WHERE %s='%s'",	// update the USER table
				$this->db->qi($this->config['db_user_table']),
				$this->db->qi($this->config['db_user_last_visit']),
				$this->db->qi($this->config['db_user_id']),
				$this->db->escape($this->sessdata['session_userid'])
			));
		}
 	} elseif ($online == 0) {
		$this->sessdata['session_logged_in'] = 0;
		$this->sessdata['session_is_admin'] = 0;
		$this->sessdata['session_userid'] = 0;
		$this->delete_login();
	}
	return $status;
}

// returns the total seconds 'online' for the session ------------------------------------------------------------
function seconds_online() {
	$diff = $this->sessdata['session_last'] - $this->sessdata['session_start'];
	return ($diff > 0) ? $diff : 0;
}

function onlinetime() {
	return $this->seconds_online();
}

function userid() {
	return $this->sessdata['session_userid'];
}

// returns the total number of active sessions -------------------------------------------------------------------
// 5 minutes is generally a reasonable amount of time to wait before a session is 'inactive'
// if $wantarray is true, a 2 element array is is returned with the total 'members' and 'guests' online, respectively
function total_online($timeframe=300, $wantarray=0) {
	$memebers = 0;
	$guests = 0;
	$now = time();

	$res = $this->db->query(sprintf("SELECT count(DISTINCT session_userid) FROM %s WHERE session_userid != 0 AND session_last + $timeframe > $now", 
		$this->db->qi($this->config['db_session_table'])
	));
	list($members) = $this->db->fetch_row(0);
	$res = $this->db->query(sprintf("SELECT count(*) FROM %s WHERE session_userid=0 AND session_last + $timeframe > $now", 
		$this->db->qi($this->config['db_session_table'])
	));
	list($guests) = $this->db->fetch_row(0);

	if ($wantarray) {
		return array( $members > 0 ? $members : 0, $guests > 0 ? $guests : 0);
	} else {
		$total = $members + $guests;
		return ($total > 0) ? $total : 1;
	}
}

// End/remove the session (including the user's SID cookie)
function end() {
	$this->delete_cookie('_id');
	$this->delete_cookie('_login');
	$this->delete_session($this->sid());
	$this->sid('');
	$this->sessdata = array();
	$this->_init_new_session();			// generate a new dataset, but it's not saved yet ...
	$this->ended = 1;				// don't save the new session at exit
}

// closes the session. There's no need to call this unless you want to make sure the session is updated before 
// redirecting to another page. Other session sub-classes might want to use this.
function close() {
#	$this->_save_session();
}

// internal function, returns true if the session has expired
function _expired() {
    $this->sessdata['session_last'] = $this->sessdata['session_last'] ?? null;
	return (time() - $this->sessdata['session_last'] > $this->config['cookielife']);
}

// returns the time the session started
function session_start() {
	return $this->sessdata['session_start'];
}

// returns the user ID if the session is logged in. Returns 0 otherwise.
function logged_in() {
	return $this->online_status() ? $this->userid() : 0;
}

// returns the cookie name (w/o any suffix; '_id', etc)
function sid_prefix() {
	// If PS is installed in a subfolder the cookie name needs to reflect that.
	$cnsfid = reset(explode('/', ltrim(SAFE_PHP_SCNM, '/')));
	$cnsfid = (($cnsfid != 'admin' or $cnsfid != 'install') and !str_contains($cnsfid, '.php')) ? $cnsfid : null;
	$sidprefix = ($cnsfid) ? $cnsfid . '_sess' : 'sess';
	return $sidprefix;
}

// returns the name of the SID cookie
function sid_name($suffix='_id') {
	return 'ps_' . $this->sid_prefix() . $suffix;
}

// returns the current session ID
function sid($new = null) {
	if ($new === null) {
		return $this->sid;
	} else {
		$old = $this->sid;
		$this->sid = $new;
		$this->sessdata['session_id'] = $new;
		return $old;
	}
}	

// deletes the session specified, or the current session if no $sid is given.
function delete_session($sid = null) {
	if ($sid === null) $sid = $this->sid();
	return $this->db->delete($this->config['db_session_table'], 'session_id', $sid);
}

// loads extra options for the session (separate cookie)
// the options cookie stores session related settings for the session.
// the settings are not tied to the user so even if the user is not logged in
// the session options are still present.
function load_session_options() {
	$sidname = $this->sid_name('_opts');
	$o = array();
	if (array_key_exists($sidname, $this->cms->cookie)) {
		$str = $this->cms->cookie[$sidname];
		// decode -> decrypt -> inflate -> unserialize
		$str = $this->config['cookieencode'] ? base64_decode($str) : $str;
		$decoded = $this->decrypt($str);
		if ($this->config['cookiecompress'] and function_exists('gzinflate')) $decoded = @gzinflate($decoded);
		if ($decoded === FALSE) $this->delete_cookie('_opts');
		$o = unserialize($decoded);
#		print "COOKIE: "; print_r($o); print "<br/>\n";	// DEBUG
	}
	if (!is_array($o)) $o = array();
	$this->options = $o;
	return $o;
}

// saves the session options.
// deflate reduces the cookie size by about 1/2 (plus it obfuscates it)
function save_session_options($opts = null) {
	global $ps;
	global $cookieconsent;
	if ($opts === null) {
		if ($this->options === null) {
			$this->load_session_options();
		}
		$opts = $this->options;
	}
	if (!is_array($opts)) $opts = array();
	// serialize -> deflate -> encrypt -> encode
	$str = serialize($opts);
	if ($this->config['cookiecompress'] and function_exists('gzdeflate')) $str = gzdeflate($str);
	$str = $this->encrypt($str);
	$encoded = $this->config['cookieencode'] ? base64_encode($str) : $str;
	if ($cookieconsent or !isset($ps->conf['main']['security']['enable_cookieconsent'])) $this->send_cookie($encoded, $this->config['cookielifeoptions'] ? time() + $this->config['cookielifeoptions'] : 0, '_opts');
//	$this->send_cookie(strlen($encoded), 0, '_opts_size');	// debug

	// add the modified cookie to memory incase we re-read the options before we exit
	$this->cms->cookie[ $this->sid_name('_opts') ] = $encoded;
}

// deletes the options cookie
function delete_session_options() {
	$this->delete_cookie('_opts');
}

// sets/gets an option; but does not save the cookie, use save_session_options().
// returns false if getting an option doesn't exist.
function opt($key, $value = null) {
	if ($this->options === null) {
		$this->options = $this->load_session_options();
	}
	if (is_array($this->options)) {
		if ($value === null) {
			if (array_key_exists($key, $this->options)) {
				return $this->options[$key];
			}
		} else {
			$old = $this->options[$key];
			$this->options[$key] = $value;
			return $old;
		}
	}
	return false;
}

// sets several session options. 
function set_opts($values = array(), $exclusive = false) {
	if ($exclusive) {
		$this->options = array();
	}
	if ($this->options === null) {
		$this->options = $this->load_session_options();
	}
	if (is_array($values)) {
		foreach ($values as $key => $val) {
			$this->options[$key] = $val;
		}
	}
}

// deletes a session option, but not the entire cookie
function del_opt($key) {
	if ($this->options === null) {
		$this->options = $this->load_session_options();
	}
	$list = is_array($key) ? $key : array( $key );
	foreach ($list as $k) {
		unset($this->options[$k]);
	}
}

// sets/gets the session key (this is not the session_id).
// this is for CSRF security.
function key($value = null) {
	$old = $this->sessdata['session_key'] ?? '';
	if ($value !== null) {
		$this->sessdata['session_key'] = empty($value) ? null : $value;
		$this->sessdata['session_key_time'] = time();
	}
	return $old;
}

// returns the time the session key was generated
function key_time() {
	return $this->sessdata['session_key_time'] ?? null;
}

// returns true if the key given matches the current session and is valid.
// max_age is the maximum seconds a key is allowed to be alive before being invalid.
function verify_key($form_key, $max_age = 1200) {
	$time = $this->key_time();
	if (empty($time)) $time = time();
	$valid = (
		!is_null($form_key) and 
		$this->key() == $form_key and 
		time() - $time <= $max_age
	);
	return $valid;
}

// returns either 'cookie' or 'get' depending how the session was restored/created.
function sid_method() {
	return $this->sidmethod;
}

// saves an auto_login cookie.
// $password is already a hash (from $user->hash())
// Saves the autologin cookie to the users browser so the next time they view the page they will be logged on automatically.
// The user's cookie must have the proper login_key or the auto-login will fail. This prevents another user from attempting
// to forge an auto-login since the login_key is private to the original user.
function save_login($userid, $password) {
	global $ps;
	global $cookieconsent;
	$token = substr(md5(md5($_SERVER["UNIQUE_ID"] . uniqid(mt_rand(), true)) . $userid . $password), mt_rand(0,24), 8);
	$ary = array('userid' => $userid, 'password' => $password, 'token' => $token);
	$data = $this->encrypt(base64_encode(serialize($ary)));
	// save the auto-login key to the user table so we can verify it later when the user tries to auto-login again
	if (!empty($this->config['db_user_table'])) {
		$cmd = $this->db->update($this->config['db_user_table'], array( $this->config['db_user_login_key'] => $token ), 
			$this->config['db_user_id'], $this->sessdata['session_userid']
		);
	}
	if ($cookieconsent or !$ps->conf['main']['security']['enable_cookieconsent']) return $this->send_cookie($data, time()+60*60*24*30, '_login'); 		// autologin cookie is saved for 30 days
}

// returns the auto login cookie or an empty array if not found or not valid.
function load_login() {
	$data = null;
	$enc = $this->cms->cookie[ $this->sid_name('_login') ];
	if (!empty($enc)) {
		$data = unserialize(base64_decode($this->decrypt($enc)));
	}

	// verify the key in the cookie matches the key in the user table
	if (is_array($data) and !empty($this->config['db_user_table'])) {
		$usertoken = $this->db->fetch_item(sprintf("SELECT %s FROM %s WHERE %s='%s' LIMIT 1",
			$this->db->qi($this->config['db_user_login_key']),
			$this->db->qi($this->config['db_user_table']),
			$this->db->qi($this->config['db_user_id']),
			$this->db->escape($data['userid'])
		));
		if (empty($data['token']) or $usertoken != $data['token']) {
			$data = array();
		}
	} 
	return $data;
//	return is_array($data) ? $data : array();
}

// delete the auto_login cookie
function delete_login() {
	$this->delete_cookie('_login');
	return 1;
}

} // end of session class

/**

CREATE TABLE `ps_sessions` (
  `session_id` char(32) NOT NULL default '',
  `session_userid` int(10) unsigned NOT NULL default '0',
  `session_start` int(10) unsigned NOT NULL default '0',
  `session_last` int(10) unsigned NOT NULL default '0',
  `session_ip` int(10) unsigned NOT NULL default '0',
  `session_logged_in` tinyint(1) NOT NULL default '0',
  `session_is_admin` tinyint(1) NOT NULL default '0',
  `session_is_bot` tinyint(1) NOT NULL default '0',
  `session_key` char(32) default NULL,
  `session_key_time` int(10) unsigned default NULL,
  PRIMARY KEY  (`session_id`),
  KEY `session_userid` (`session_userid`)
);

**/

?>
