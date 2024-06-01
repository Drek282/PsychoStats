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
 *	Version: $Id: clantags.php 389 2008-04-18 15:04:10Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");

$validfields = array('ref','start','limit','order','sort','move','id');
$cms->theme->assign_request_vars($validfields, true);

if (!is_numeric($start) or $start < 0) $start = 0;
if (!is_numeric($limit) or $limit < 0) $limit = 50;
if (!in_array($order, array('asc','desc'))) $order = 'asc';
$sort = 'type,idx';

$_order = array(
	'start'	=> $start,
	'limit'	=> $limit,
	'order' => $order, 
	'sort'	=> $sort,
	'no_quote'=> true
);

// re-order tags
if ($move and $id) {
	$list = $ps->db->fetch_rows(1, "SELECT id,idx FROM $ps->t_config_clantags ORDER BY idx");
	$inc = $move == 'up' ? -15 : 15;
	$idx = 0;
	// loop through all tags and set their idx linearly
	for ($i=0; $i < count($list); $i++) {
		$list[$i]['idx'] = ++$idx * 10;
		if ($list[$i]['id'] == $id) $list[$i]['idx'] += $inc;
		$ps->db->update($ps->t_config_clantags, array( 'idx' => $list[$i]['idx'] ), 'id', $list[$i]['id']);
	}
	unset($submit);
}

$list = $ps->db->fetch_rows(1, "SELECT * FROM $ps->t_config_clantags " . $ps->getsortorder($_order));
$total = $ps->db->count($ps->t_config_clantags);
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

// massage the tags array a bit so we don't have to do the logic in the theme template
$tags = array();
$first = $list ? $list[0]['id'] : array();
$last  = $list ? $list[ count($list) - 1]['id'] : array();
foreach ($list as $tag) {
	$tag['id'] ??= null;
	$tag['up'] ??= null;
	$tag['down'] ??= null;
	if ($tag['id'] == $first) {
		$tag['down'] = 1;
	} elseif ($tag['id'] == $last) {
		$tag['up'] = 1;
	} else {
		$tag['down'] = 1;
		$tag['up'] = 1;
	}
	$tags[] = $tag;
}

$cms->crumb('Manage', ps_url_wrapper(array('_base' => 'manage.php' )));
$cms->crumb('Clan Tags', ps_url_wrapper(array('_base' => 'clantags.php' )));


// assign variables to the theme
$cms->theme->assign(array(
	'page'		=> $basename, 
	'clantags'	=> $tags,
	'pager'		=> $pager,
));

// display the output
$cms->theme->add_css('css/2column.css');
$cms->theme->add_css('css/forms.css');
//$cms->theme->add_js('js/jquery.interface.js');
$cms->theme->add_js('js/forms.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
