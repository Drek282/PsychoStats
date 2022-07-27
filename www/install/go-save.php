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
 *	Version: $Id: go-save.php 476 2008-06-04 00:28:42Z lifo $
 */

/*
	Save the database settings to config.php
*/
if (!defined("PSYCHOSTATS_INSTALL_PAGE")) die("Unauthorized access to " . basename(__FILE__));

$validfields = array('username','password','ftphost','ftpdir');
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
$method = 'manual';
$is_php5 = version_compare(PHP_VERSION, '5.0', '>=');
$config_text = '';
$can_write = false;
$is_saved = verify_config();
$allow_next = $is_saved;
$config_file = 'config.php';
$real_config_file = realpath(rtrim(dirname(__DIR__), '/\\')) . DIRECTORY_SEPARATOR . $config_file;
$can_ftp = extension_loaded('ftp');

$cms->theme->assign_by_ref('errors', $errors);
$cms->theme->assign_by_ref('method', $method);
$cms->theme->assign_by_ref('can_write', $can_write);
$cms->theme->assign_by_ref('can_ftp', $can_ftp);
$cms->theme->assign_by_ref('is_saved', $is_saved);

$db->site_url = $db->site_url ?? null;
// build the config text
$config_text =
	"<?php\n" . 
	"\$dbtype = '" . addslashes($db->dbtype) . "';\n" . 
	"\$dbhost = '" . addslashes($db->dbhost) . "';\n" . 
	"\$dbport = '" . addslashes($db->dbport) . "';\n" . 
	"\$dbname = '" . addslashes($db->dbname) . "';\n" . 
	"\$dbuser = '" . addslashes($db->dbuser) . "';\n" . 
	"\$dbpass = '" . addslashes($db->dbpass) . "';\n" . 
	"\$dbtblprefix = '" . addslashes($db->dbtblprefix) . "';\n" .
	"\$site_url = '" . addslashes($db->site_url) . "';\n" .
	"?>";

// determine if we're able to write to the config.php directly...
// don't use is_writable() since Windows/IIS is dumb...
if (!$is_saved) {
	$fh = @fopen($real_config_file, "w");
	if ($fh) {
		$method = 'local';
		fwrite($fh, $config_text);
		fclose($fh);
		$is_saved = true;
		$can_write = true;
		$allow_next = true;
	} else {
		$errors[] = "Error writting to config file (Permission Denied)";
		$is_saved = false;
		$can_write = false;
		$allow_next = false;
		$write_attempted = true;
	}
}

if ($can_ftp and !$is_saved) {
	if ($ajax_request) {
		if ($ftphost) {
			if ($write_attempted) $lasterr = array_pop($errors);
			$allow_next = save_via_ftp($ftphost, $username, $password, $ftpdir, $config_text);
			// remove the last error from trying to write the local file...
		} else {
			$errors[] = "Enter your FTP settings in the form below";
		}
	}
	$method = 'ftp';
} else {
	// if the config is not writable, and there's no FTP support I have to assume the user
	// will know how to manually copy the config to config.php. So we default to 'next' to true.
	$allow_next = true;
}

// try to determine reasonable FTP settings if none were supplied
if (empty($ftphost) and empty($ftpdir)) $ftpdir = rtrim(dirname($real_config_file), '/\\');
if (empty($ftphost)) $ftphost = $_SERVER['SERVER_NAME'];
if (empty($username)) $username = 'anonymous';

$cms->theme->assign(array(
	'real_config_file'	=> $real_config_file,
	'web_user'		=> function_exists('posix_getpwuid') ? posix_getpwuid( posix_getuid() ) : array( 'name' => get_current_user() ),
	'config_text'		=> $config_text,
));

if ($ajax_request) {
//	sleep(1);
	$pagename = 'go-save-results';
	$cms->tiny_page($pagename, $pagename);
	exit();
}


// php5 allows for overwriting via the FTP wrapper. Short, and sweet.
// however, using the wrapper limits my error reporting ability, so I'm not using this...
function save_via_ftp_wrapper($ftphost, $username, $password, $ftpdir, $config_text) {
	global $errors;
	if (substr($ftpdir,0,1) == '/') $ftpdir = substr($ftpdir,1);
	if (substr($ftpdir,-1) == '/') $ftpdir = substr($ftpdir,0,-1);
	$uri = "ftp://$username:$password@$ftphost/$ftpdir";
//	print "/* "; print "$uri\n"; print_r(is_dir($uri)); print " */";
	$opts = array('ftp' => array('overwrite' => true));
	$context = stream_context_create($opts);
	ob_start();
	$res = file_put_contents("$uri/config.php", $config_text, null, $context);
	$err = str_replace("\n", '\n', addslashes(strip_tags(ob_get_clean())));
	if (!empty($err)) $errors[] = $err;
	return ($res !== false and $res > 0);
}

// php4 has to do this the old fashion way
function save_via_ftp($ftphost, $username, $password, $ftpdir, $config_text) {
	global $errors;
	list($host,$port) = explode(':', $ftphost);
	if (empty($port)) $port = 21;
	$ftp = ftp_connect($host, $port, 10);
	if ($ftp) {
		if (@ftp_login($ftp, $username, $password)) {
			if ($ftpdir == '' || @ftp_chdir($ftp, $ftpdir)) {
				$file = tempnam(".", "psconf");
				if ($fh = fopen($file, "w")) {
					fwrite($fh, $config_text);
					fclose($fh);
					// delete it first, since some FTP's complain if you overwrite. 
					// we don't care if it fails.
					@ftp_delete($ftp, 'config.php');
					if (!@ftp_put($ftp, 'config.php', $file, FTP_ASCII)) {
						$errors[] = "Unable to upload file to '" . ftp_pwd($ftp) . "'";
						if (!empty($php_errormsg)) $errors[] = $php_errormsg;
					}
					@unlink($file);
				} else {
					$errors[] = "Error creating temporary file for upload!";
				}
			} else {
				$errors[] = "FTP Directory '$ftpdir' does not exist!";
			}
		} else {
			$errors[] = "Unable to login to FTP server. Invalid username or password.";
		}
	} else {
		$errors[] = "Unable to connect to FTP server '$host:$port'";
	}

	if (!$errors) {
		if (!verify_config()) {
			$errors[] = "Config was uploaded to the wrong directory '$ftpdir' (DB config doesn't match).";
			$errors[] = "Make sure the FTP directory below is the proper local directory of your PsychoStats website";
			@ftp_delete($ftp, 'config.php');
		}
	}

	if ($ftp) @ftp_close($ftp);
	return ($errors);
}

// compares the variables found in config.php with those in the $db object. 
// If they're all the same then we know the config.php has the proper settings.
function verify_config() {
	global $db;
	$dbtype = $dbhost = $dbport = $dbname = $dbuser = $dbpass = $dbtblprefix = '';
	if (!@include(PS_ROOTDIR . "/config.php")) return false;
	foreach (array('dbtype','dbhost','dbport','dbname','dbuser','dbpass','dbtblprefix') as $v) {
//		print "$v: " . $$v . " == " . $db->$v . "<br/>\n";
		if ($$v != $db->$v) {
			return false;
		}
	}
	return true;
}

?>
