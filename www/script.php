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
 *	Version: $Id: script.php 539 2008-08-15 19:24:26Z lifo $
 *
 */

// this file is included() from admin/script.php so we don't want to
// duplicate the theme setup if it was done already.
if (!defined('PSYCHOSTATS_PAGE')) {
	define("PSYCHOSTATS_PAGE", true);
	include(__DIR__ . "/includes/common.php");
}

// collect url parameters ...
$validfields = array('src');
$cms->globalize_request_vars($validfields);

// nothing to do if there's no sources given
if (empty($src)) exit();

// collect the sources and sanitize them.
$sources = explode(',', $src);
trim_all($sources);		// remove whitespace

$root = $cms->theme->template_dir; //$ps->conf['theme']['template_dir'];
$files = array();
$missing = array();
$lastupdate = 0;

// Make sure each source is a relative path within the theme directory and does
// not contain '..'
for ($i=0, $j=count($sources); $i < $j; $i++) {
	// I can't use the sanity checks below because some themes will use
	// relative paths with ../ which is perfectly valid and legal.
	
	// remove double dots and force backslashes to forward slashes
//	$sources[$i] = str_replace(array('../','\\'), array('/','/'), $sources[$i]);
	// remove duplicate directory separators and remove the leading slash
//	$sources[$i] = preg_replace(array('|//+|','|^/|'), array('/',''), $sources[$i]);

	// ignore sources that have been sanitized into nothing...
	if (empty($sources[$i])) {
		unset($sources[$i]);
		continue;
	}

	// make sure each file exists and update the newest timestamp
	$len = strlen($root);
	$file = realpath($root . '/' . $sources[$i]);

	// Only allow files within the template directory. Avoids exploiting
	// other files like ../../../etc/passwd
	if (substr($file, 0, $len) == $root and @file_exists($file)) {
		$files[] = $file;
		$lastupdate = max($lastupdate, filemtime($file));
	} else {
		$missing[] = $sources[$i];
	}
}


// create a resource name for this set of files. This mimics the way
// Smarty creates a compiled file.
$hex = sprintf('%8X', $lastupdate ? $lastupdate : time());
$compiled_file = catfile($cms->theme->compile_dir, 
	$cms->theme->theme() . '-' .
	$cms->theme->language() . '-' .
	$cms->theme->compile_id . '^%%' .
	substr($hex,0,2) . '^' .
	substr($hex,0,3) . '^' .
	$hex . '%%' . 
	md5(implode('', $sources)) . '.js'
);
$is_compiled = file_exists($compiled_file);

// Check and see if the client has the text cached.
// This only works on apache servers.
if ($is_compiled and function_exists('apache_request_headers')) {
	$headers = apache_request_headers();
	$if_modified_since = preg_replace('/;.*$/', '', $headers['If-Modified-Since']);
	if ($if_modified_since) {
		$gmtime = gmdate("D, d M Y H:i:s", $lastupdate) . " GMT";
		if ($if_modified_since == $gmtime) {
			header("HTTP/1.1 304 Not Modified", true);
			exit();
		}
	}
}

// the client does not have the file cached... so we output it below ...
header("Content-Type: text/javascript", true);
header("Cache-Control: public, must-revalidate");
header("Last-Modified: " . gmdate("D, d M Y H:i:s", $lastupdate) . " GMT");
header("Connection: close");

// delete any previously cached compiled file
$parts = explode('%%', $compiled_file);
$match = $parts[0] . '*' . $parts[ count($parts) -1];
$delete = glob($match, GLOB_NOSORT);
if ($delete) {
	foreach ($delete as $f) {
		@unlink($f);
	}
}

$fh = @fopen($compiled_file, 'w');
if ($fh) {
	if ($missing) {
		fwrite($fh, "/* Missing files: " . implode(', ', $missing) . " */\n\n");
	}
	
	foreach ($files as $f) {
		fwrite($fh, file_get_contents($f) . ";\n");	// force a semicolon
	}
	fclose($fh);
}

readfile($compiled_file);

// The theme code will automatically compress and set the proper headers
// for content-length, etc.

?>
