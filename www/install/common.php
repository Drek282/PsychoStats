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
 *	Version: $Id: common.php 565 2008-10-10 12:27:02Z lifo $
 */
if (!defined("PSYCHOSTATS_INSTALL_PAGE")) die("Unauthorized access to " . basename(__FILE__));

define("PS_INSTALL_VERSION", '3.2.8n');

define("PS_ROOTDIR", rtrim(dirname(__DIR__), '/\\'));
define("PS_INSTALLDIR", __DIR__);

// enable some sane error reporting (ignore notice errors) and turn off the magic. 
// we also want to to disable E_STRICT.
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
//error_reporting(E_ALL);
//set_magic_quotes_runtime(0);
/**/
@ini_set('display_errors', 'On');
@ini_set('log_errors', 'On');
/**/

// IIS does not have REQUEST_URI defined (apache specific).
// This URI is handy in certain pages so we create it if needed.
if (empty($_SERVER['REQUEST_URI'])) {
	$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
	if (!empty($_SERVER['QUERY_STRING'])) {
		$_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
	}
}

// read in all of our required libraries for basic functionality!
require_once(PS_ROOTDIR . "/includes/functions.php");
require_once(PS_ROOTDIR . "/includes/class_DB.php");
require_once(PS_ROOTDIR . "/includes/class_PS.php");
require_once(PS_ROOTDIR . "/includes/class_CMS.php");
require_once(PS_ROOTDIR . "/includes/class_HTTP.php");
require_once(PS_ROOTDIR . "/includes/class_session.php");

// try to load the current config
$dbtype = 'mysql';
$dbhost = 'localhost';
$dbport = '';
$dbname = 'psychostats';
$dbuser = '';
$dbpass = '';
$dbtblprefix = 'ps_';
$site_url = '';
if (file_exists(PS_ROOTDIR . "/config.php") and file_exists(PS_ROOTDIR . "/install/go-dbinit.php")) {
    @include_once(PS_ROOTDIR . "/config.php");
} else {
    echo "You must install game support before you can install PsychoStats, please see INSTALL.md for details.";
    exit;
}

// Initialize our global variables for PsychoStats. 
// Lets be nice to the global Name Space.
$db		= null;
$cms 		= null;				// global PsychoCMS object
$php_scnm = $_SERVER['SCRIPT_NAME'];		// this is used so much we make sure it's global
// Sanitize PHP_SELF and avoid XSS attacks.
// We use the constant in places we know we'll be outputting $PHP_SELF to the user
define("SAFE_PHP_SCNM", htmlentities($_SERVER['REQUEST_URI'], ENT_QUOTES, "UTF-8"));

// create database handle
$db = PsychoDB::create(array(
	'dbtype' => 'mysql',
	'delaystart' => true,
	'fatal'  => false,
));

// start the PS CMS object
$cms = new PsychoCMS(array(
	'dbhandle'	=> &$db,
	'plugin_dir'	=> PS_ROOTDIR . '/plugins',
	'site_url'	=> $site_url
));

// this session will not actually store a session in a database or file.
// it's mearly used in the install for cookie support.
$cms->session = new PsychoSession(array(
	'cms'			=> $cms,
	'cookiename'		=> 'ps_install_sess',
	'cookiesalt'		=> '',
	'cookiecompress'	=> true,
	'cookieencode'		=> true,
	'cookielifeoptions' 	=> 0,	
	'dbhandle'		=> $db,
	'delaystart'		=> true,
));

$cms->init(true); // quick init; no plugins, session or user

$cms->init_theme('default', array( 
	'theme_default'	=> 'default',
	'theme_opt'	=> 'install_theme',
	'in_db' 	=> false,
	'force_theme'	=> true,
	'fetch_compile'	=> false,
	'compile_id' 	=> 'install',
	'compile_dir'	=> null,
	'js_compress'	=> false,
	'css_compress'	=> false,
	'template_dir' 	=> __DIR__ . '/themes',
	'theme_url'	=> null,
));
$cms->theme->load_styles();
$cms->theme->assign(array(
	'SELF'			=> SAFE_PHP_SCNM,
	'install_version'	=> PS_INSTALL_VERSION
));

// ----------------------------------
function init_session_opts($delete = false) {
	global $cms;
	$opts = $cms->session->load_session_options();
	if ($delete || !$opts || !$opts['install']) {
		$cms->session->set_opts(array('install' => uniqid(rand(),true)), true);
		$cms->session->save_session_options();
		$opts = $cms->session->load_session_options();
	}
	return $opts;
}

// load DB conf from POST'ed form, or session variables if no form variable was found
function load_db_opts($conf = null) {
	global $cms;
	$list = array('dbhost','dbport','dbname','dbuser','dbpass','dbtblprefix');
	$opts = $cms->session->load_session_options();
	foreach ($list as $var) {
		if ($conf and is_array($conf) and array_key_exists($var, $conf)) {
			$GLOBALS[$var] = $conf[$var];
#			print "CONF: $var == '$conf[$var]'<br>";
		} else if (array_key_exists($var, $opts)) {
			$GLOBALS[$var] = $opts[$var];
#			print "OPTS: $var == '$opts[$var]'<br>";
		}
	}
}

function save_db_opts() {
	global $cms, $dbhost, $dbport, $dbname, $dbuser, $dbpass, $dbtblprefix;
	$opts = $cms->session->load_session_options();
	$opts['dbhost'] = $dbhost;
	$opts['dbport'] = $dbport;
	$opts['dbname'] = $dbname;
	$opts['dbuser'] = $dbuser;
	$opts['dbpass'] = $dbpass;
	$opts['dbtblprefix'] = $dbtblprefix;
	$cms->session->save_session_options($opts);
}

?>
