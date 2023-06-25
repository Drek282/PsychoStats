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
 *	Version: $Id: go-theme.php 476 2008-06-04 00:28:42Z lifo $
 */

/*
	Configure and setup smarty theme support.
*/
if (!defined("PSYCHOSTATS_INSTALL_PAGE")) die("Unauthorized access to " . basename(__FILE__));

$validfields = array('compiledir','nosave','dosubmit');
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
$can_write = false;
$allow_next = false;
$open_basedir = ini_get('open_basedir');

$defaultdir = realpath(__DIR__.'/../temp/');
if(!is_writable($defaultdir)) @chmod($default_temp,0755); //try to fix, but won't work in most cases due to insufficent privileges

// Windows/IIS seems to have permission issues with creating and writting files to a sub-dir of windows\temp\...
// so we'll default to the temp directory w/o an extra sub-directory for our themes.
if (php_sapi_name() == 'isapi') $defaultdir = rtrim(dirname($defaultdir), '/\\');

// we're not going to check the locked variable here...
list($orig_compiledir,$is_locked) = $db->fetch_row(0,"SELECT value,locked FROM " . $db->table('config') . " WHERE conftype='theme' AND var='compile_dir' LIMIT 1");

$cms->theme->assign_by_ref('errors', $errors);
$cms->theme->assign_by_ref('can_write', $can_write);

// no form was submitted, default to whatever is in the configuration
if (!$dosubmit) {
	$compiledir = $orig_compiledir;
	list($nosave) = $db->fetch_list("SELECT value FROM " . $db->table('config') . " WHERE conftype='theme' AND var='fetch_compile' LIMIT 1");
	$nosave = !$nosave;
	$allow_next = $nosave;
}
$compiledir = empty($compiledir) ? $defaultdir : $compiledir;
$dir = realpath($compiledir);
if ($dir != false) $compiledir = $dir;

// remove trailing slash, since it causes problems with is_dir/is_writable on windows
if (substr($compiledir, -1) == DIRECTORY_SEPARATOR) $compiledir = substr($compiledir, 0, -1);

// first check if the dir exists and is not already a file
if (!is_dir($compiledir)) {
	// create the directory; or at least try.
	$ok = true;
        if (version_compare(PHP_VERSION, '5.0', '>=')) {
		$ok = @mkdir($compiledir, 0777, true);		// php5 is recursive
	} else { 						// php4 is not
		$dirs = explode(DIRECTORY_SEPARATOR, $compiledir);
		// prefix path with a drive letter (windows)
		// or, prefix with a dot (relative path)
		$path = preg_match('/^\w:|\./', $dirs[0]) ? array_shift($dirs) : '';
		for ($i=0; $i < count($dirs); $i++) {
			if (empty($dirs[$i])) continue;
			$path .= DIRECTORY_SEPARATOR . $dirs[$i];
//			print "$path<br/>\n";
			if (!is_dir($path) && !@mkdir($path, 0777)) {
				$ok = false;
				break;
			}
		};
	}
	if (!$ok) {
		$errors[] = "Error creating compile directory (Permission Denied)";
	}
}


if (is_writable($compiledir)) {
	// now absolutely make sure we can create a file in the directory (open_basedir restrictions)
	$file = catfile($compiledir, 'pstest_' . uniqid(rand(), true));
	$fh = @fopen($file, "w");
	if (!$fh || !@fwrite($fh, "test")) {
		$errors[] = "Directory is not writable! (probably do to open_basedir restrictions)";
	} else {
		$can_write = $allow_next = true;
	}
	@fclose($fh);
	@unlink($file);
}

// change $compiledir to empty if it matches the default
/* on second thought, lets not.... always write the path no matter what
if ($compiledir == $defaultdir) {
	$compiledir = '';
}
*/
// save the compiledir and fetch_compile (nosave) options
//if ($dosubmit) {
	if ($can_write) {
		$db->query("UPDATE " . $db->table('config') . " SET value=" . $db->escape($compiledir, true) . " WHERE conftype='theme' AND (section IS NULL OR section LIKE '') AND var='compile_dir'");
	}
	$db->query("UPDATE " . $db->table('config') . " SET value=" . ($nosave ? 0 : 1) . " WHERE conftype='theme' AND section IS NULL AND var='fetch_compile'");
//}

$cms->theme->assign(array(
	'web_user'		=> function_exists('posix_getpwuid') ? posix_getpwuid( posix_getuid() ) : array( 'name' => get_current_user() ),
	'orig_compiledir'	=> $orig_compiledir,
	'is_locked'		=> $is_locked,
	'system_default_dir'	=> $defaultdir,
	'open_basedir'		=> $open_basedir,
));

if ($ajax_request) {
//	sleep(1);
	$pagename = 'go-theme-results';
	$cms->tiny_page($pagename, $pagename);
	exit();
}

?>
