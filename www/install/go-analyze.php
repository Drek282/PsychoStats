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
 *	Version: $Id: go-analyze.php 478 2021-07-27 $
 */

/*
	Analyze system for minimum requirements
*/
if (!defined("PSYCHOSTATS_INSTALL_PAGE")) die("Unauthorized access to " . basename(__FILE__));

$validfields = array();
$cms->theme->assign_request_vars($validfields, true);

$min_php_version = '7.1.0';
$min_mysql_version = '5.5.0';

$loaded_exts = array_flip(get_loaded_extensions());

$required_err = 0;
$required_ext = array(
//	'fake'	=> "Fake extension to cause an error",
	'mysqli' => "MySQL support must be enabled in order to view your stats online.",
	'curl'	=> "CURL module is required for to check validity of social profile url's.",
	'xml'   => "XML module is used throughout psychostats, in particular the theming system."
);

$optional_err = 0;
$optional_ext = array(
//	'blarg' => 'This is not a real extension',
	'ftp'	=> "FTP support is only needed if you want to be able to download updates, themes and plugins " . 
		   "directly from your stats web pages. Also, the installer can use FTP to save your configuration.",
	'gd'	=> "GD (version 2) support is recommended so that some dynamic images can be created within the " . 
		   "player stats (charts and graphs).",
	'gmp'	=> "GNU Multiple Precision support is only required for SteamID conversion routines for " . 
            "player profiles.",
	'zip'	=> "ZIP support will allow you to download and install new themes directly from the ACP."
);

// check php and mysql versions for minimum requirement
$php_version_ok = (version_compare($min_php_version, phpversion()) < 1);

$required_ext_ok = array();
foreach ($required_ext as $e => $desc) {
		if (array_key_exists($e, $loaded_exts)) {
			$required_ext_ok[$e] = true; // phpversion($e);
		} else {
			$required_ext_ok[$e] = false;
			$required_err++;
		}
}

$optional_ext_ok = array();
foreach ($optional_ext as $e => $desc) {
		if (array_key_exists($e, $loaded_exts)) {
			$optional_ext_ok[$e] = true; //phpversion($e);
		} else {
			$optional_ext_ok[$e] = false;
			$optional_err++;
		}
}

$allow_next = ($required_err == 0);

$cms->theme->assign(array(
	'php_version'		=> phpversion(),
	'php_version_ok'	=> $php_version_ok,
	'php_sapi_name'		=> php_sapi_name(),
	'min_php_version'	=> $min_php_version,
	'required_err'		=> $required_err,
	'required_ext'		=> $required_ext,
	'required_ext_ok'	=> $required_ext_ok,
	'optional_err'		=> $optional_err,
	'optional_ext'		=> $optional_ext,
	'optional_ext_ok'	=> $optional_ext_ok,
	'server_os'		=> PHP_OS,
	'server_uname'		=> php_uname(),
));

if ($ajax_request) {
//	sleep(1);
	$pagename = 'go-analyze-results';
	$cms->tiny_page($pagename, $pagename);
	exit();
}


?>
