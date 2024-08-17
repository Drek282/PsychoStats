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
 *	Version: $Id: errlogs.php 389 2008-04-18 15:04:10Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");

$validfields = array('ref','start','limit','filter','view');
$cms->theme->assign_request_vars($validfields, true);

if (!is_numeric($start) or $start < 0) $start = 0;
if (!is_numeric($limit) or $limit < 0 or $limit > 100) $limit = 100;
$order = 'desc';
$sort  = 'timestamp';
$filter = trim($filter ?? '');
$view = trim($view ?? '');

$_order = array(
	'start'	=> $start,
	'limit'	=> $limit,
	'order' => $order, 
	'sort'	=> $sort,
	'filter'=> $filter
);

$total = $ps->db->count($ps->t_errlog);
// the REPLACE() below is used to separate stats.pl error tracebacks so the table width doesn't go crazy
// ie: "Called from main(278)->PS::Game::halflife(167)->..." is changed to "Called from main(278) -> PS::Game::halflife(167) -> "
$cmd = "SELECT l.timestamp,l.severity,l.userid,u.username,replace(l.msg, ')->', ') -> ') msg FROM $ps->t_errlog l LEFT JOIN $ps->t_user u ON u.userid=l.userid ";
if ($filter != '') {
	$f = '%' . $ps->db->escape($filter) . '%';
	$cmd .= "WHERE l.msg LIKE '$f' ";
}
$cmd .= $ps->db->sortorder($_order);
$logs = $ps->db->fetch_rows(1, $cmd);

// download the visible logs as text?
if ($view == 'text') {
	$csv = '';
	$keys = array('timestamp','severity','username','msg');
	foreach ($logs as $l) {
		$csv .= date($ps->conf['theme']['format']['datetime'], $l['timestamp']) . "\t";
		$csv .= $l['severity'] . "\t";
		$csv .= sprintf("%s\t", $l['username'] != '' ? $l['username'] : '-');
		$csv .= $l['msg'];
		$csv .= chr(13).chr(10);
	}

	while (@ob_end_clean());
	header("Pragma: no-cache");
	header("Content-Type: text/plain");
	header("Content-Length: " . strlen($csv));
	header("Content-Disposition: attachment; filename=\"errlog.txt\"");
	print $csv;
	exit();
}

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

$cms->crumb('Manage', ps_url_wrapper(array('_base' => 'manage.php' )));
$cms->crumb('Error Logs', ps_url_wrapper(array('_base' => $php_scnm )));

// assign variables to the theme
$cms->theme->assign(array(
	'page'		=> $basename, 
	'logs'		=> $logs,
	'total'		=> $total,
	'pager'		=> $pager,
));

// display the output
$cms->theme->add_css('css/2column.css');
$cms->theme->add_css('css/forms.css');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
