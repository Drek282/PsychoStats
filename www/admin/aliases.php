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
 *	Version: $Id: aliases.php 389 2008-04-18 15:04:10Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");

$validfields = array('ref','start','limit','filter');
$cms->theme->assign_request_vars($validfields, true);

if (!is_numeric($start) or $start < 0) $start = 0;
if (!is_numeric($limit) or $limit < 0) $limit = 100;

$cmd = "SELECT * FROM $ps->t_plr_aliases";
if ($filter) {
	$f = $ps->db->escape($filter, false);
	$cmd .= " WHERE uniqueid LIKE '%$f%' OR alias LIKE '%$f%'";
}
$cmd .= " ORDER BY uniqueid ASC,alias ASC LIMIT $start,$limit";
$aliases = $ps->db->fetch_rows(1, $cmd);
$total = $ps->db->count($ps->t_plr_aliases);
$pager = pagination(array(
	'baseurl'	=> ps_url_wrapper(array('limit' => $limit, 'filter' => $filter)),
	'total'		=> $total,
	'start'		=> $start,
	'perpage'	=> $limit, 
	'pergroup'	=> 5,
	'separator'	=> ' ', 
	'force_prev_next' => true,
	'next'		=> $cms->trans("Next"),
	'prev'		=> $cms->trans("Previous"),
));

$cms->crumb('Manage', ps_url_wrapper($_SERVER['REQUEST_URI']));
$cms->crumb('Player Aliases', ps_url_wrapper($php_scnm));

// assign variables to the theme
$cms->theme->assign(array(
	'aliases'		=> $aliases,
	'last_uniqueid'	=> $last_uniqueid ??= null,
	'total'			=> $total,
	'order'			=> null,
	'sort'			=> null,
	'pager'			=> $pager,
	'page'			=> $basename, 
));

// display the output
$cms->theme->add_css('css/2column.css');
$cms->theme->add_css('css/forms.css');
//$cms->theme->add_js('js/jquery.interface.js');
//$cms->theme->add_js('js/forms.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
