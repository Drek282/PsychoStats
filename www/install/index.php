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
 *	Version: $Id: index.php 442 2008-05-13 10:30:11Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_INSTALL_PAGE", true);
require_once("./common.php");


$opts = init_session_opts(true);
//$newer_avail = false;

//$h = new HTTP_Request('http://updates.psychostats.com/releases/' . PS_INSTALL_VERSION);
//$res = $h->download();
//if ($h->status() == '200' and $res) {
//	$newer_avail = $res[0] ? trim($res[0]) : 0;
//	$release_date = $h->header('x-psychostats-date');
//	$ps_version = $h->header('x-psychostats-ver');
//}

//$newest_url = 'http://www.psychostats.com/downloads/psychostats/';

$validfields = array('s','re');
$cms->theme->assign_request_vars($validfields, true);
$cms->theme->assign(array(
	'install'		=> $opts['install'], 
//	'newer_avail'		=> $newer_avail,
	'release_date'		=> $release_date,
	'ps_version'		=> $ps_version,
	'local_ps_version'	=> PS_INSTALL_VERSION,
//	'newest_url'		=> $newest_url,
));

// display the output
$basename = basename(__FILE__, '.php');
$cms->theme->add_css('css/2column.css');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

?>
