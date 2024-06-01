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
 *	Version: $Id: overlays.php 537 2008-08-14 12:54:28Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");
$cms->theme->assign('page', 'overlays');

$validfields = array('ref','start','limit','order','sort','move','id','filter','export','ajax');
$cms->theme->assign_request_vars($validfields, true);

if (!is_numeric($start) or $start < 0) $start = 0;
if (!is_numeric($limit) or $limit < 0) $limit = 100;
if (!in_array($order, array('asc','desc'))) $order = 'asc';
$sort = 'gametype,modtype,map';

$_order = array(
	'start'	=> $start,
	'limit'	=> $limit,
	'order' => $order, 
	'sort'	=> $sort
);

$where = '';
if ($filter) {
	$f = $ps->db->escape($filter, false);
	$where = "WHERE (gametype LIKE '%$f' OR modtype LIKE '%$f%' OR map LIKE '%$f%') ";
}

$overlays = $ps->db->fetch_rows(1, "SELECT * FROM $ps->t_config_overlays $where" . $ps->getsortorder($_order));
$total = $ps->db->count($ps->t_config_overlays, '*', $where ? substr($where,6) : null);	// remove 'WHERE' from str
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

// export the overlays as XML and exit...
if ($export and $overlays) {
	$xml  = "<?xml version=\"1.0\" ?>\n<maps>\n";
	foreach ($overlays as $o) {
		$xml .= sprintf(
			"  <map name='%s' gametype='%s' modtype='%s'>\n" .
			"    <minx>%d</minx>\n" .
			"    <miny>%d</miny>\n" .
			"    <maxx>%d</maxx>\n" .
			"    <maxy>%d</maxy>\n" .
			"    <res>%dx%d</res>\n" .
			"    <flipv>%d</flipv>\n" .
			"    <fliph>%d</fliph>\n" .
			"  </map>\n",
			$o['map'],
			$o['gametype'],
			$o['modtype'],
			$o['minx'],
			$o['miny'],
			$o['maxx'],
			$o['maxy'],
			$o['width'], $o['height'],
			$o['flipv'],
			$o['fliph']
		);
	}
	$xml .= "</maps>\n";
	while (@ob_end_clean());
	header("Pragma: no-cache");
	header("Content-Type: text/xml", true);
	header("Content-Length: " . strlen($xml));
	header("Content-Disposition: attachment; filename=\"overlays.xml\"");
	print $xml;
	exit;
}

$cms->crumb('Manage', ps_url_wrapper(array('_base' => 'manage.php' )));
$cms->crumb('Overlays', ps_url_wrapper(array('_base' => 'overlays.php' )));

// assign variables to the theme
$cms->theme->assign(array(
	'overlays'	=> $overlays,
	'pager'		=> $pager,
));

// display the output
$cms->theme->add_css('css/2column.css');
$cms->theme->add_css('css/forms.css');
//$cms->theme->add_js('js/bonuses.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
