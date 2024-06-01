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
 *	Version: $Id: servers.php 389 2008-04-18 15:04:10Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");

$validfields = array('ref','start','limit','order','sort','move','id','ajax');
$cms->theme->assign_request_vars($validfields, true);

if (!is_numeric($start) or $start < 0) $start = 0;
if (!is_numeric($limit) or $limit < 0) $limit = 50;
if (!in_array($order, array('asc','desc'))) $order = 'asc';
$sort = 'idx';

$_order = array(
	'start'	=> $start,
	'limit'	=> $limit,
	'order' => $order, 
	'sort'	=> $sort
);

// re-order servers
if ($move and $id) {
	$list = $ps->db->fetch_rows(1, "SELECT id,idx FROM $ps->t_config_servers ORDER BY idx");
	$inc = $move == 'up' ? -15 : 15;
	$idx = 0;
	// loop through all and set the idx linearly
	for ($i=0; $i < count($list); $i++) {
		$list[$i]['idx'] = ++$idx * 10;
		if ($list[$i]['id'] == $id) $list[$i]['idx'] += $inc;
		$ps->db->update($ps->t_config_servers, array( 'idx' => $list[$i]['idx'] ), 'id', $list[$i]['id']);
	}
	unset($submit);

	if ($ajax) {
		print "success";
		exit;
	}
}

$list = $ps->db->fetch_rows(1, "SELECT * FROM $ps->t_config_servers " . $ps->getsortorder($_order));
$total = $ps->db->count($ps->t_config_servers);
$pager = pagination(array(
	'baseurl'	=> ps_url_wrapper(array('sort' => $sort, 'order' => $order, 'limit' => $limit)),
	'total'		=> $total,
	'start'		=> $start,
	'perpage'	=> $limit, 
	'pergroup'	=> 5,
	'separator'	=> ' ', 
	'force_prev_next' => true,
	'next'		=> $cms->trans("Next"),
	'prev'		=> $cms->trans("Previous"),
));

// massage the array a bit so we don't have to do the logic in the theme template
$servers = array();
$first = $list ? $list[0]['id'] : array();
$last  = $list ? $list[ count($list) - 1]['id'] : array();
foreach ($list as $log) {
	$log['id'] ??= null;
	$log['up'] ??= null;
	$log['down'] ??= null;
	if ($log['id'] == $first) {
		$log['down'] = 1;
	} elseif ($log['id'] == $last) {
		$log['up'] = 1;
	} else {
		$log['down'] = 1;
		$log['up'] = 1;
	}
	$servers[] = $log;
}

$cms->crumb('Manage', ps_url_wrapper(array('_base' => 'manage.php' )));
$cms->crumb('Servers', ps_url_wrapper(array('_base' => 'servers.php' )));


// assign variables to the theme
$cms->theme->assign(array(
	'page'		=> $basename, 
	'servers'	=> $servers,
	'pager'		=> $pager,
	'text'		=> $text ??= null,
));

// display the output
$cms->theme->add_css('css/2column.css');
$cms->theme->add_css('css/forms.css');
$cms->theme->add_js('js/servers.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
