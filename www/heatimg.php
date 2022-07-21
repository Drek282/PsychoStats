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
 *	Version: $Id: heatimg.php 450 2008-05-20 11:34:52Z lifo $
 *
 *	Returns a single heatmap image keyed on the heatid
 */

define("PSYCHOSTATS_PAGE", true);
include(__DIR__ . "/includes/common.php");
include_once(PS_ROOTDIR . "/includes/PS/Heatmap.php");

$id = trim($_REQUEST['id']);
//$key = trim($_REQUEST['key']);

$heat = new PS_Heatmap($ps);
$heat->image_passthru($id);	// output image directly to client, and print proper Content-Type

?>
