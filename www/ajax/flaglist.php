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
 *	Version: $Id: flaglist.php 450 2008-05-20 11:34:52Z lifo $
 *
 *      This ajax request simply returns a list of all available country flags
 *      on the system either as a comma separated list (CSV), or a list of <img>
 *	tags.
*/
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_SUBPAGE", true);
include(__DIR__ . "/../includes/common.php");
include("./ajax_common.php");

// only allow logged in users to request a list?
/**/
if (!$cms->user->logged_in()) {
	header("HTTP/1.0 404 Not Found");
	header("Status: 404 Not Found");
	print "File not found!";
	exit;
}
/**/

// collect url parameters ...
$t = strtolower($_GET['t']);
$idstr = $_GET['id'];
$idstr = preg_replace('|[^0-9]|', '', $idstr); //Mitigates XSS attack
$idstr = str_replace(' ', '', urldecode($idstr));	// strip spaces and make sure it's not double url encoded
if ($idstr == '') $idstr = 'icon-';

if (!in_array($t, array('csv','xml','dom','img'))) $t = 'img';

$list = array();

// first build a list of icons from our local directory
$dir = $ps->conf['theme']['flags_dir'];
$url = $ps->conf['theme']['flags_url'];
if ($dh = @opendir($dir)) {
	while (($file = @readdir($dh)) !== false) {
		if (substr($file, 0, 1) == '.') continue;	// skip dot files
		list($cc) = explode('.', $file);
		if ($cc == '00' or $cc == 'a2') continue;	// skip the blank flags
		$fullfile = catfile($dir, $file);
		if (is_dir($fullfile)) continue;		// skip directories
		if (is_link($fullfile)) continue;		// skip symlinks
		$info = getimagesize($fullfile);
		$size = @filesize($fullfile);
		$list[$file] = array(
			'filename'	=> rawurlencode($file),
			'url'		=> catfile($url, rawurlencode($file)),
			'desc'		=> strtoupper($cc), //ps_escape_html(sprintf("%s - %dx%d - %s", $file, $info[0], $info[1], abbrnum($size))),
			'size'		=> $size,
			'width'		=> $info[0],
			'height'	=> $info[1],
			'attr'		=> $info[3],
		);
	}
	@closedir($dh);
}
ksort($list);

$fields = array( 'filename', 'url', 'size', 'width', 'height' );

output_list($t, $list, $fields, $idstr);

?>
