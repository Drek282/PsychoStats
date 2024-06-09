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
 *	Version: $Id: query.php 450 2008-05-20 11:34:52Z lifo $
 */

define("PSYCHOSTATS_PAGE", true);
$basename = basename(__FILE__, '.php');
include(__DIR__ . "/includes/common.php");
require_once(PS_ROOTDIR . "/includes/class_PQ.php");

// collect url parameters ...
$validfields = array('r','s','t');
$cms->theme->assign_request_vars($validfields, true);

$rulefilters = array('_tutor_','coop','deathmatch','pausable','r_');

if (!in_array($t,array('details','players','rules','rcon'))) $t = 'details';

str_contains($s, ':') ? list($host,$port) = explode(':', $s) : $host = $s;

if (!isset($port) or !is_numeric($port) or $port < 1 or $port > 65535) $port = 27015;

if (is_numeric($host)) {	// $host is potentially an ID of a server in the database
	$cmd = "SELECT id,host,port,alt,querytype,rcon,cc FROM $ps->t_config_servers s WHERE id='%s' AND enabled=1 ";
	$cmd = sprintf($cmd, $ps->db->escape($host));
} else {			// try to fetch a server based on host:port
	$cmd = "SELECT id,host,port,alt,querytype,rcon,cc FROM $ps->t_config_servers s WHERE host='%s' AND port='%s' AND enabled=1 ";
	$cmd = sprintf($cmd, $ps->db->escape($host), $ps->db->escape($port));
}
$server = $ps->db->fetch_row(1, $cmd);
if ($server['host']) $server['ip'] = gethostbyname($server['host']);

if (!preg_match('/^\d+\.\d+\.\d+\.\d+$/', $server['ip'])) {
	$server['ip'] = 0;
}

// return tiny error page; TODO: replace die() with actual error handler
if (!$server or !$server['id']) {
	die("Invalid Server Queried (s='$s')");
//	$cms->tiny_page_err();
}

// try and resolve the country flag
if (!$server['cc'] and $server['ip']) {
	$row = $ps->db->fetch_row(1, sprintf("SELECT c.cc, c.cn " .
		"FROM $ps->t_geoip_ip ip, $ps->t_geoip_cc c " .
		"WHERE c.cc=ip.cc AND (%u BETWEEN start AND end) ",
		ip2long($server['ip'])
	));
	if ($row) $server = array_merge($server, $row);
} elseif ($server['cc']) {
	$row = $ps->db->fetch_row(0, sprintf("SELECT cn FROM $ps->t_geoip_cc WHERE cc='%s'", $ps->db->escape($server['cc'])));
	if ($row) $server['cn'] = $row[0];
}

// query the remote game server
$pq = PQ::create(array(
	'ip' 		=> $server['host'],
	'port'		=> $server['port'],
	'querytype'	=> $server['querytype'],
	'timeout' 	=> 1, 
	'retries' 	=> 1,
));
$pqinfo = $pq->query(array('info','players','rules'));
if ($pqinfo === false) $pqinfo = array();
if ($pqinfo) {
	$pqinfo['connect_url'] = $pq->connect_url($server['alt']);
	if ($pqinfo['players']) usort($pqinfo['players'], 'killsort');
	if ($pqinfo['rules']) $pqinfo['rules'] = filter_rules($pqinfo['rules'], $rulefilters);
} else {
	$pqinfo['timedout'] = 1;
}

$pqinfo['totalkills'] = 0;
if (isset($pqinfo['players'])) {
	foreach ($pqinfo['players'] as $p) {
		$pqinfo['totalkills'] += $p['kills'];
	}
}

// If we have an RCON command to send (and the user is an admin)
$rcon_result = '';
if ($cms->user->is_admin() and !empty($r) and !empty($server['rcon'])) {
	$rcon_result = $pq->rcon($r, $server['rcon']);
}

// adjust some variables so its easier to use them within the theme
$pqinfo['servertype'] ??= null;
$pqinfo['serveros'] ??= null;
$pqinfo['map'] ??= null;
$pqinfo['gamedir'] ??= null;
$pqinfo['dedicated'] = (bool)($pqinfo['servertype'] == 'd');
$pqinfo['servertype'] = $pqinfo['dedicated'] ? $cms->trans('Dedicated') : $cms->trans('Listen');
$pqinfo['windows'] = (bool)($pqinfo['serveros'] != 'l');
$pqinfo['serveros'] = $pqinfo['windows'] ? $cms->trans("Windows") : $cms->trans("Linux");
$pqinfo['timeleft'] = !empty($pqinfo['rules']['amx_timeleft']) ? $pqinfo['rules']['amx_timeleft'] : '';
$pqinfo['nextmap'] = !empty($pqinfo['rules']['mani_nextmap']) ? $pqinfo['rules']['mani_nextmap'] : '';
$pqinfo['timedout'] ??= null;
$pqinfo['rules']['amx_nextmap'] ??= null;
$pqinfo['rules']['mani_nextmap'] ??= null;

$cms->theme->assign(array(
	'server'	=> array_merge($server, $pqinfo),
	'mapimg'	=> $ps->mapimg($pqinfo['map'], array('pq' => &$pq, 'noimg' => '')),
	'flagimg'	=> $ps->flagimg($server['cc'], array( 'class' => 'flag' )),
	'rcon_result'	=> $rcon_result,
	'active_tab'	=> $t
));

// display the output
$cms->tiny_page($basename, $basename);

// --- Local functions --------------

function killsort($a, $b) {
  if ($a['kills'] == $b['kills']) return onlinesort($a,$b);	// sort by onlinetime if the kills are equal
//  if ($a['kills'] == $b['kills']) return 0;			// remove the above line if 'killsort' is not the original sort!
  return ($a['kills'] > $b['kills']) ? -1 : 1;
}

function onlinesort($a, $b) {
  if ($a['onlinetime'] == $b['onlinetime']) return 0;
  return ($a['onlinetime'] > $b['onlinetime']) ? -1 : 1;
}

function filter_rules($orig, $rulefilters = array()) {
	$ary = array();
	if (!$rulefilters) return $orig;
	foreach ($orig as $rule => $value) {
		$match = 0;
		foreach ($rulefilters as $filter) {
			if (empty($filter)) continue;
			if (preg_match("/$filter/", $rule)) {
				$match++;
				break;
			}
		}
		if (!$match) $ary[$rule] = $value;
	}
	ksort($ary);
	return $ary;
}


?>
