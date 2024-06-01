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
 *	Version: $Id: awards.php 547 2008-08-24 23:13:29Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");

$cms->crumb('Manage', ps_url_wrapper($_SERVER['REQUEST_URI']));
$cms->crumb('Awards', ps_url_wrapper($php_scnm));

$validfields = array('ref','start','limit','order','sort','type','gametype','modtype','filter','move','ajax','id');
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

// get a list of gametypes and modtypes
$gametypes  = $ps->db->fetch_list("SELECT DISTINCT gametype FROM $ps->t_config_awards WHERE gametype <> '' ORDER BY gametype");
$modtypes   = $ps->db->fetch_list("SELECT DISTINCT modtype  FROM $ps->t_config_awards WHERE modtype <> ''  ORDER BY modtype");
$awardtypes = $ps->db->fetch_list("SELECT DISTINCT type     FROM $ps->t_config_awards ORDER BY type");

// re-order awards
if ($move and $id) {
	$list = $ps->db->fetch_rows(1, "SELECT id,idx FROM $ps->t_config_awards ORDER BY idx");
	$inc = $move == 'up' ? -15 : 15;
	$idx = 0;
	// loop through all items and set their idx linearly
	for ($i=0; $i < count($list); $i++) {
		$list[$i]['idx'] = ++$idx * 10;
		if ($list[$i]['id'] == $id) $list[$i]['idx'] += $inc;
		$ps->db->update($ps->t_config_awards, array( 'idx' => $list[$i]['idx'] ), 'id', $list[$i]['id']);
	}
	unset($submit);

	if ($ajax) {
		print "success";
		exit;
	}
}

// get a list of awards
$awards = array();
$cmd = "SELECT * FROM $ps->t_config_awards WHERE ";
$where = "1";
if (!empty($type)) $where .= " AND type=" . $ps->db->escape($type, true);
if (!empty($gametype)) $where.= " AND gametype=" . $ps->db->escape($gametype, true);
if (!empty($modtype)) $where .= " AND modtype=" . $ps->db->escape($modtype, true);
if ($filter != '') {
	$f = '%' . $ps->db->escape($filter) . '%';
	$where .= " AND (name LIKE '%$f%' OR description LIKE '%$f%')";
}
$cmd .= $where . " " . $ps->getsortorder($_order);
$list = $ps->db->fetch_rows(1, $cmd);

$total = $ps->db->count($ps->t_config_awards, '*', $where);
$pager = pagination(array(
	'baseurl'	=> ps_url_wrapper(array('type' => $type, 'gametype' => $gametype, 'modtype' => $modtype, 'filter' => $filter) + $_order),
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
$awards = array();
$first = $list ? $list[0]['id'] : array();
$last  = $list ? $list[ count($list) - 1]['id'] : array();
foreach ($list as $aw) {
	$aw['id'] ??= null;
	$aw['up'] ??= null;
	$aw['down'] ??= null;
	if ($aw['id'] == $first) {
		$aw['down'] = 1;
	} elseif ($aw['id'] == $last) {
		$aw['up'] = 1;
	} else {
		$aw['down'] = 1;
		$aw['up'] = 1;
	}
	$awards[] = $aw;
}

// assign variables to the theme
$cms->theme->assign(array(
	'page'			=> $basename, 
	'pager'			=> $pager,
	'gametypes'		=> $gametypes,
	'modtypes'		=> $modtypes,
	'awardtypes'	=> $awardtypes,
	'awards'		=> $awards,
	'text'			=> $text ??= null,
));

// display the output
$cms->theme->add_css('css/2column.css');
$cms->theme->add_css('css/forms.css');
$cms->theme->add_js('js/awards.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
