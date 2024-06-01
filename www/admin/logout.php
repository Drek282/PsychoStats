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
 *	Version: $Id: logout.php 389 2008-04-18 15:04:10Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
define("PSYCHOSTATS_LOGOUT_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");
$cms->theme->assign('page', $basename);

$validfields = array('ref');
$cms->theme->assign_request_vars($validfields, true);

// we don't want to actually log the user out of their session, just disable their ADMIN flag.
if ($cms->user->admin_logged_in()) {
	$cms->session->is_admin(0);
}
previouspage(rtrim(dirname(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\')), '/\\'));

// A page is never displayed for logout. Just redirect somewhere else.

// display the output
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

?>
