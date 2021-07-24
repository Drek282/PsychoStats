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
 *	Version: $Id: plrhist.php 491 2008-06-17 10:51:46Z lifo $
 */

define("PSYCHOSTATS_PAGE", true);
include(__DIR__ . "/includes/common.php");
$cms->init_theme($ps->conf['main']['theme'], $ps->conf['theme']);
$ps->theme_setup($cms->theme);
$cms->theme->page_title('PsychoStats - Player History');

$validfields = array(
	'id',
	'start','sort','order',
	'sstart','ssort','sorder','slimit',
);
$cms->theme->assign_request_vars($validfields, true);

if (!$start or $start < 0) $start = 0;
if (!$limit or $limit < 0 or $limit > 100) $limit = 31;
if (!$order or !in_array($order, array('asc', 'desc'))) $order = 'desc';
if (!$sort) $sort = 'statdate';

if (!$sstart or $sstart < 0) $sstart = 0;
if (!$slimit or $slimit < 0 or $slimit > 100) $slimit = 31;
if (!$sorder or !in_array($sorder, array('asc', 'desc'))) $sorder = 'desc';
if (!$ssort) $ssort = 'sessionstart';

$totalranked  = $ps->get_total_players(array('allowall' => 0));

$player = $ps->get_player(array(
	'plrid' 	=> $id,
	'loadcounts'	=> 1,
	'loadweapons'	=> 0,
	'loadmaps'	=> 0,
	'loadvictims'	=> 0,
	'loadsessions'	=> 1,
	'loadids'	=> 1,
));

$cms->theme->page_title(' for ' . $player['name'], true);

$history = $ps->get_player_days(array(
	'plrid'		=> $id,
	'sort'		=> $sort,
	'order'		=> $order,
	'start'		=> $start,
	'limit'		=> $limit
));

$days = array();
foreach ($history as $s) {
	$days[] = $s['statdate'];
}
sort($days, SORT_STRING);

$htable = $cms->new_table($history);
$htable->if_no_data($cms->trans("No Historical Stats Found"));
$htable->attr('class', 'ps-table ps-plrhistory-table');
$htable->sort_baseurl(array( 'id' => $id ));
$htable->start_and_sort($start, $sort, $order);
$htable->columns(array(
	'statdate'		=> array( 'label' => $cms->trans("Date") ),
	'kills'			=> array( 'label' => $cms->trans("K"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Kills") ),
	'deaths'		=> array( 'label' => $cms->trans("D"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Deaths") ),
	'killsperdeath'		=> array( 'label' => $cms->trans("K:D"), 'modifier' => '%0.2f', 'tooltip' => $cms->trans("Kills Per Death") ),
	'headshotkills'		=> array( 'label' => $cms->trans("HS"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Headshot Kills") ),
	'headshotkillspct'	=> array( 'label' => $cms->trans("HS%"), 'modifier' => '%0.2f%%', 'tooltip' => $cms->trans("Headshot Kills Percentage") ),
	'accuracy'		=> array( 'label' => $cms->trans("Acc"), 'modifier' => '%0.2f%%', 'tooltip' => $cms->trans("Accuracy") ),
	'onlinetime' 		=> array( 'label' => $cms->trans("Online"), 'modifier' => 'compacttime' ),
	'dayskill' 		=> array( 'label' => $cms->trans("Skill") ),
));
$cms->filter('player_history_table_object', $htable);

// build player session table
$stable = $cms->new_table($player['sessions']);
$stable->if_no_data($cms->trans("No Sessions Found"));
$stable->attr('class', 'ps-table ps-session-table');
$stable->sort_baseurl(array( 'id' => $id, '_anchor' => 'sessions' ));
$stable->start_and_sort($sstart, $ssort, $sorder, 's');
$stable->columns(array(
	'sessionstart'		=> array( 'label' => $cms->trans("Start Time"), 'modifier' => 'ps_datetime_stamp' ),
	'online' 		=> array( 'label' => $cms->trans("Online"), 'modifier' => 'compacttime' ),
	'kills'			=> array( 'label' => $cms->trans("K"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Kills") ),
	'deaths'		=> array( 'label' => $cms->trans("D"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Deaths") ),
	'headshotkills'		=> array( 'label' => $cms->trans("HS"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Headshot Kills") ),
	'headshotkillspct'	=> array( 'label' => $cms->trans("HS%"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Headshot Kills Percentage") ),
	'accuracy'		=> array( 'label' => $cms->trans("Acc"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Accuracy") ),
	'skill' 		=> array( 'label' => $cms->trans("Skill") ),
));
$cms->filter('player_session_table_object', $stable);

$sessionpager = pagination(array(
	'baseurl'       => ps_url_wrapper(array( 'id' => $id, 'slimit' => $slimit, 'ssort' => $ssort, 'sorder' => $sorder)),
	'total'         => $player['totalsessions'],
	'start'         => $sstart,
	'startvar'      => 'sstart',
	'perpage'       => $slimit,
	'urltail'       => 'sessions',
	'separator'	=> ' ',
	'next'          => $cms->trans("Next"),
	'prev'          => $cms->trans("Previous"),
));

$cms->theme->assign_by_ref('plr', $player);
$cms->theme->assign(array(
	'history'		=> $history,
	'history_table'		=> $htable->render(),
	'sessions_table'	=> $stable->render(),
	'days'			=> $days,
	'total_days'		=> $days ? count($days) : 0,
	'totalranked'		=> $totalranked,
	'top10percentile'	=> $player['rank'] ? $player['rank'] < $totalranked * 0.10 : false,
	'top1percentile'	=> $player['rank'] ? $player['rank'] < $totalranked * 0.01 : false,
	'sessionpager'		=> $sessionpager,
));

$basename = basename(__FILE__, '.php');
if ($player['plrid']) {
	// allow mods to have their own section on the left side bar
	$ps->player_left_column_mod($player, $cms->theme);

	if ($ps->conf['main']['uniqueid'] == 'worldid') {
		$steamid = $player['ids_worldid'][0]['worldid'];
		if ($steamid) {
			include_once(PS_ROOTDIR . "/includes/class_SteamID.php");
			$v = new SteamID($steamid);
			$friendid = $v->ConvertToUInt64($steamid);
			$player['friend_id'] = $friendid;
			$player['steam_community_url'] = $v->steam_community_url($friendid);
			$player['steam_add_friend_url'] = $v->steam_add_friend_url($friendid);
		}
	}

	$cms->theme->add_css('css/2column.css');	// this page has a left column
	if ($ps->conf['theme']['map']['google_key'] and $player['latitude'] and $player['longitude']) {
		$cms->theme->add_js('http://maps.google.com/maps?file=api&amp;v=2&amp;key=' . $ps->conf['theme']['map']['google_key']);
		$cms->theme->add_js('js/player.js');
	}
	$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');
} else {
	$cms->full_page_err($basename, array(
		'message_title'	=> $cms->trans("No Player Found!"),
		'message'	=> $cms->trans("Invalid player ID specified.") . " " . $cms->trans("Please go back and try again.")
	));
}

?>
