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
 *	Version: $Id: events.php 495 2008-06-18 18:41:37Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");

$validfields = array('ref','start','limit','order','sort','move','id','filter','ajax');
$cms->theme->assign_request_vars($validfields, true);

if (!is_numeric($start) or $start < 0) $start = 0;
if (!is_numeric($limit) or $limit < 0) $limit = 100;
if (!in_array($order, array('asc','desc'))) $order = 'asc';
$sort = 'idx';

$_order = array(
	'start'	=> $start,
	'limit'	=> $limit,
	'order' => $order, 
	'sort'	=> $sort
);

// re-order events
if ($move and $id) {
	$list = $ps->db->fetch_rows(1, "SELECT id,idx FROM $ps->t_config_events ORDER BY idx");
	$inc = $move == 'up' ? -15 : 15;
	$idx = 0;
	// loop through all log sources and set their idx linearly
	for ($i=0; $i < count($list); $i++) {
		$list[$i]['idx'] = ++$idx * 10;
		if ($list[$i]['id'] == $id) $list[$i]['idx'] += $inc;
		$ps->db->update($ps->t_config_events, array( 'idx' => $list[$i]['idx'] ), 'id', $list[$i]['id']);
	}
	unset($submit);

	if ($ajax) {
		print "success";
		exit;
	}
}

$where = '';
if ($filter) {
	$f = $ps->db->escape($filter, false);
	$where = "WHERE (gametype LIKE '%$f' OR modtype LIKE '%$f%' OR eventname LIKE '%$f%') ";
}

$list = $ps->db->fetch_rows(1, "SELECT * FROM $ps->t_config_events $where" . $ps->getsortorder($_order));
$total = $ps->db->count($ps->t_config_events,'*',$where ? substr($where,6) : null);
$pager = pagination(array(
	'baseurl'	=> ps_url_wrapper(array('sort' => $sort, 'order' => $order, 'limit' => $limit, 'filter' => $filter)),
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
$events = array();
$first = $list ? $list[0]['id'] : array();
$last  = $list ? $list[ count($list) - 1]['id'] : array();
foreach ($list as $ev) {
	$ev['id'] ??= null;
	$ev['up'] ??= null;
	$ev['down'] ??= null;
	if ($ev['id'] == $first) {
		$ev['down'] = 1;
	} elseif ($ev['id'] == $last) {
		$ev['up'] = 1;
	} else {
		$ev['down'] = 1;
		$ev['up'] = 1;
	}
	$events[] = $ev;
}

$cms->crumb('Manage', ps_url_wrapper(array('_base' => 'manage.php' )));
$cms->crumb('Events', ps_url_wrapper(array('_base' => 'events.php' )));


// assign variables to the theme
$cms->theme->assign(array(
	'page'		=> $basename, 
	'events'	=> $events,
	'pager'		=> $pager,
	'text'			=> $text ??= null,
));

// display the output
$cms->theme->add_css('css/2column.css');
$cms->theme->add_css('css/forms.css');
$cms->theme->add_js('js/events.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
