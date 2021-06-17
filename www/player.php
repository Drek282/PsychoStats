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
 *	Version: $Id: player.php 529 2008-08-08 16:10:01Z lifo $
 */

define("PSYCHOSTATS_PAGE", true);
include(__DIR__ . "/includes/common.php");
$cms->init_theme($ps->conf['main']['theme'], $ps->conf['theme']);
$ps->theme_setup($cms->theme);
$cms->theme->page_title('PsychoStats - Player Stats');

// maximum player ID's to load for ipaddr, name, and worldid
$MAX_PLR_IDS = 10;

$validfields = array(
	'id', 'ofc', 
	'vsort','vorder','vstart','vlimit',	// victims
	'msort','morder','mstart','mlimit',	// maps
	'wsort','worder','wstart','wlimit',	// weapons
	'rsort','rorder','rstart','rlimit',	// roles
	'ssort','sorder','sstart','slimit',	// sessions
	'xml'
);
$cms->theme->assign_request_vars($validfields, true);

if ($cms->input['ofc']) {
	$styles = $cms->theme->load_styles();
	return_ofc_data($styles);
	exit;
}

if (!$msort) $msort = 'kills';
if (!$ssort) $ssort = 'sessionstart';
if (!$slimit) $slimit = '10';

// SET DEFAULTS. Since they're basically the same for each list, we do this in a loop
foreach ($validfields as $var) {
	switch (substr($var, 1)) {
		case 'sort':
			if (!$$var) $$var = 'kills';
			break;
		case 'order':
			if (!$$var or !in_array($$var, array('asc', 'desc'))) $$var = 'desc';
			break;
		case 'start':
			if (!is_numeric($$var) || $$var < 0) $$var = 0;
			break;
		case 'limit':
			if (!is_numeric($$var) || $$var < 0 || $$var > 100) $$var = 20;
			break;
		default:
		        break;
	}
}

$totalranked  = $ps->get_total_players(array('allowall' => 0));

$player = $ps->get_player(array(
	'plrid' 	=> $id,
	'loadsessions'	=> 1,
	'loadnames'	=> 1, 
	'loadipaddrs'	=> $ps->conf['theme']['permissions']['show_ips'] || $cms->user->is_admin(),
	'loadworldids'	=> $ps->conf['theme']['permissions']['show_worldids'] || $cms->user->is_admin(),
	'loadgeoinfo'	=> !empty($ps->conf['theme']['map']['google_key']),
//	'loadawards'	=> 1,
	'weaponsort'	=> $wsort,
	'weaponorder'	=> $worder,
	'sessionsort'	=> $ssort,
	'sessionorder'	=> $sorder,
	'sessionstart'	=> $sstart,
	'sessionlimit'	=> $slimit,
	'mapsort'	=> $msort,
	'maporder'	=> $morder,
	'mapstart'	=> $mstart,
	'maplimit'	=> $mlimit,
	'rolesort'	=> $rsort,
	'roleorder'	=> $rorder,
	'rolestart'	=> $rstart,
	'rolelimit'	=> $rlimit,
	'victimsort'	=> $vsort,
	'victimorder'	=> $vorder,
	'victimstart'	=> $vstart,
	'victimlimit'	=> $vlimit,
	'idstart'	=> 0,
	'idlimit'	=> $MAX_PLR_IDS,
	'idsort'	=> 'totaluses',
	'idorder'	=> 'desc',
));

$cms->theme->page_title(' for ' . $player['name'], true);

$x = substr($xml,0,1);
if ($x == 'p') {	// player
	// we have to alter some of the data for player arrays otherwise we'll end up with invalid or strange keys
	$ary = $player;
	$ary['weapons'] = array();
	$ary['maps'] = array();
	foreach ($player['weapons'] as $w) {
		$ary['weapons'][ $w['uniqueid'] ] = $w;
	} 
	foreach ($player['maps'] as $m) {
		$ary['maps'][ $m['uniqueid'] ] = $m;
	} 
	print_xml($ary);

} elseif ($x == 'w') {	// weapons
	// re-arrange the weapons list so the uniqueid of each weapon is a key.
	// weapon uniqueid's should never have any weird characters so this should be safe.
	$ary = array();
	foreach ($player['weapons'] as $w) {
		$ary[ $w['uniqueid'] ] = $w;
	} 
	print_xml($ary);
}


$rolepager = "";
if ($ps->use_roles) {
	$rolepager = pagination(array(
		'baseurl'       => ps_url_wrapper(array( 'id' => $id, 'rlimit' => $rlimit, 'rsort' => $rsort, 'rorder' => $rorder)),
		'total'         => $player['totalroles'],
		'start'         => $rstart,
		'startvar'      => 'rstart',
		'perpage'       => $rlimit,
		'urltail'       => 'roles',
		'separator'	=> ' ',
		'next'          => $cms->trans("Next"),
		'prev'          => $cms->trans("Previous"),
		'pergroup'	=> 5,
	));
}

$victimpager = pagination(array(
	'baseurl'       => ps_url_wrapper(array( 'id' => $id, 'vlimit' => $vlimit, 'vsort' => $vsort, 'vorder' => $vorder)),
	'total'         => $player['totalvictims'],
	'start'         => $vstart,
	'startvar'      => 'vstart',
	'perpage'       => $vlimit,
	'urltail'       => 'victims',
	'separator'	=> ' ',
	'next'          => $cms->trans("Next"),
	'prev'          => $cms->trans("Previous"),
	'pergroup'	=> 5,
));

$mappager = pagination(array(
	'baseurl'       => ps_url_wrapper(array( 'id' => $id, 'mlimit' => $mlimit, 'msort' => $msort, 'morder' => $morder)),
	'total'         => $player['totalmaps'],
	'start'         => $mstart,
	'startvar'      => 'mstart',
	'perpage'       => $mlimit,
	'urltail'       => 'maps',
	'separator'	=> ' ',
	'next'          => $cms->trans("Next"),
	'prev'          => $cms->trans("Previous"),
));

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

// build a dynamic table that plugins can use to add custom columns of data
$wtable = $cms->new_table($player['weapons']);
$wtable->if_no_data($cms->trans("No Weapons Found"));
$wtable->attr('class', 'ps-table ps-weapon-table');
$wtable->sort_baseurl(array( 'id' => $id, '_anchor' => 'weapons' ));
$wtable->start_and_sort($wstart, $wsort, $worder, 'w');
$wtable->columns(array(
	'uniqueid'		=> array( 'label' => $cms->trans("Weapon"), 'callback' => 'ps_table_weapon_link' ),
	'kills'			=> array( 'label' => $cms->trans("Kills"), 'modifier' => 'commify' ),
	'deaths'		=> array( 'label' => $cms->trans("Deaths"), 'modifier' => 'commify' ),
	'headshotkills'		=> array( 'label' => $cms->trans("HS"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Headshot Kills") ),
	'headshotkillspct'	=> array( 'label' => $cms->trans("HS%"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Headshot Kills Percentage") ),
	'accuracy'		=> array( 'label' => $cms->trans("Acc"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Accuracy") ),
	'shotsperkill' 		=> array( 'label' => $cms->trans("S:K"), 'tooltip' => $cms->trans("Shots Per Kill") ),
	'damage' 		=> array( 'label' => $cms->trans("Dmg"), 'callback' => 'dmg', 'tooltip' => $cms->trans("Damage") ),
));
$wtable->column_attr('uniqueid', 'class', 'first');
$ps->player_weapons_table_mod($wtable);
$cms->filter('player_weapon_table_object', $wtable);


// build player session table
$stable = $cms->new_table($player['sessions']);
$stable->if_no_data($cms->trans("No Sessions Found"));
$stable->attr('class', 'ps-table ps-session-table');
$stable->sort_baseurl(array( 'id' => $id, '_anchor' => 'sessions' ));
$stable->start_and_sort($sstart, $ssort, $sorder, 's');
$stable->columns(array(
	'sessionstart'		=> array( 'label' => $cms->trans("Session Time"), 'callback' => 'ps_table_session_time_link' ),
	'mapname' 		=> array( 'label' => $cms->trans("Map"), 'callback' => 'ps_table_session_map_link' ),
	'online' 		=> array( 'label' => $cms->trans("Online"), 'modifier' => 'compacttime' ),
	'kills'			=> array( 'label' => $cms->trans("K"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Kills") ),
	'deaths'		=> array( 'label' => $cms->trans("D"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Deaths") ),
	'headshotkills'		=> array( 'label' => $cms->trans("HS"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Headshot Kills") ),
	'headshotkillspct'	=> array( 'label' => $cms->trans("HS%"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Headshot Kills Percentage") ),
	'accuracy'		=> array( 'label' => $cms->trans("Acc"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Accuracy") ),
	'skill' 		=> array( 'label' => $cms->trans("Skill"), 'callback' => 'session_skill' ),
));
$stable->column_attr('skill','class','right');
$ps->player_sessions_table_mod($stable);
$cms->filter('player_session_table_object', $stable);


// build player map table
$mtable = $cms->new_table($player['maps']);
$mtable->if_no_data($cms->trans("No Maps Found"));
$mtable->attr('class', 'ps-table ps-map-table');
$mtable->sort_baseurl(array( 'id' => $id, '_anchor' => 'maps' ));
$mtable->start_and_sort($mstart, $msort, $morder, 'm');
$mtable->columns(array(
//	'+'		=> '#',
	'_mapimg'	=> array( 'nolabel' => true, 'callback' => 'ps_table_map_link' ),
	'uniqueid'	=> array( 'label' => $cms->trans("Map"), 'callback' => 'ps_table_map_text_link' ),
	'kills'		=> array( 'label' => $cms->trans("K"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Kills") ), 
	'ffkills'	=> array( 'label' => $cms->trans("FF"), 'modifier' => 'commify', 'tooltip' => $cms->trans('Friendly Fire Kills') ),
	'killsperdeath' => array( 'label' => $cms->trans("K:D"), 'tooltip' => $cms->trans("Kills Per Death") ),
	'killsperminute'=> array( 'label' => $cms->trans("K:M"), 'tooltip' => $cms->trans("Kills Per Minute") ),
	'games'		=> array( 'label' => $cms->trans("G"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Games") ),
	'rounds'	=> array( 'label' => $cms->trans("R"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Rounds") ),
	'onlinetime'	=> array( 'label' => $cms->trans("Online"), 'modifier' => 'compacttime' ),
	'lasttime'	=> array( 'label' => $cms->trans("Last"), 'modifier' => 'ps_date_stamp' ),
));
$mtable->column_attr('uniqueid','class','first left');
$mtable->header_attr('uniqueid', 'colspan', '2');
$mtable->column_attr('_mapimg', 'style', 'width: 40px;');
$ps->player_maps_table_mod($mtable);
$cms->filter('player_map_table_object', $mtable);


// build player role table
$rtable = null;
if ($ps->use_roles) {
	$rtable = $cms->new_table($player['roles']);
	$rtable->if_no_data($cms->trans("No Roles Found"));
	$rtable->attr('class', 'ps-table ps-role-table');
	$rtable->sort_baseurl(array( 'id' => $id, '_anchor' => 'roles' ));
	$rtable->start_and_sort($rstart, $rsort, $rorder, 'r');
	$rtable->columns(array(
		'uniqueid'		=> array( 'label' => $cms->trans("Role"), 'callback' => 'ps_table_role_link' ),
//		'team'			=> $cms->trans("Team"),
		'kills'			=> array( 'label' => $cms->trans("Kills"), 'modifier' => 'commify' ),
		'deaths'		=> array( 'label' => $cms->trans("Deaths"), 'modifier' => 'commify' ),
		'headshotkills'		=> array( 'label' => $cms->trans("HS"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Headshot Kills") ),
		'headshotkillspct'	=> array( 'label' => $cms->trans("HS%"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Headshot Kills Percentage") ),
		'accuracy'		=> array( 'label' => $cms->trans("Acc"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Accuracy") ),
		'shotsperkill' 		=> array( 'label' => $cms->trans("S:K"), 'tooltip' => $cms->trans("Shots Per Kill") ),
		'damage' 		=> array( 'label' => $cms->trans("Dmg"), 'modifier' => 'abbrnum0', 'tooltip' => $cms->trans("Damage") ),
	));
	$rtable->column_attr('uniqueid','class','first');
	$ps->player_roles_table_mod($rtable);
	$cms->filter('player_role_table_object', $rtable);
}


// build player victim table
$vtable = $cms->new_table($player['victims']);
$vtable->if_no_data($cms->trans("No Victims Found"));
$vtable->attr('class', 'ps-table ps-player-table');
$vtable->sort_baseurl(array( 'id' => $id, '_anchor' => 'victims' ));
$vtable->start_and_sort($vstart, $vsort, $vorder, 'v');
$vtable->columns(array(
	'+'		=> '#',
	'rank'		=> array( 'label' => $cms->trans("Rank"), 'callback' => 'dash_if_empty' ),
	'name'		=> array( 'label' => $cms->trans("Victim"), 'callback' => 'ps_table_victim_link' ),
	'kills'		=> array( 'label' => $cms->trans("Kills"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Kills") ), 
	'deaths'	=> array( 'label' => $cms->trans("Deaths"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Deaths") ), 
	'killsperdeath' => array( 'label' => $cms->trans("K:D"), 'tooltip' => $cms->trans("Kills Per Death") ),
	'skill'		=> $cms->trans("Skill"),
));
$vtable->column_attr('name', 'class', 'left');
$vtable->column_attr('skill', 'class', 'right');
$ps->player_victims_table_mod($wtable);
$cms->filter('player_victim_table_object', $vtable);

$cms->theme->assign_by_ref('plr', $player);
$cms->theme->assign(array(
//	'hitbox_url'		=> ps_escape_html("weaponxml=$PHP_SELF?id=$id&xml=w") . '&' . ps_escape_html("imgpath=" . dirname($PHP_SELF) . '/img/weapons/' . $ps->conf['main']['gametype'] . '/' . $ps->conf['main']['modtype']),
    'hitbox_url'		=> 'weaponxml=' . ps_escape_html(PHP_SCNM) . "&amp;id=$id&amp;imgpath=" . ps_escape_html(rtrim(dirname(PHP_SCNM), '/\\') . '/img/weapons/' . $ps->conf['main']['gametype'] . '/' . $ps->conf['main']['modtype']) . '&amp;confxml=' . $cms->theme->parent_url() . '/hitbox/config.xml',
	'weapons_table'		=> $wtable->render(),
	'sessions_table'	=> $stable->render(),
	'maps_table'		=> $mtable->render(),
	'roles_table'		=> $rtable ? $rtable->render() : '',
	'victims_table'		=> $vtable->render(),
	'sessionpager'		=> $sessionpager,
	'mappager'		=> $mappager,
	'rolepager'		=> $rolepager,
	'victimpager'		=> $victimpager,
	'totalranked'		=> $totalranked,
	'max_plr_ids'		=> $MAX_PLR_IDS,
	'top10percentile'	=> $player['rank'] ? $player['rank'] < $totalranked * 0.10 : false,
	'top1percentile'	=> $player['rank'] ? $player['rank'] < $totalranked * 0.01 : false,
));

$basename = basename(__FILE__, '.php');
if ($player['plrid']) {
	// allow mods to have their own section on the left side bar
	$ps->player_left_column_mod($player, $cms->theme);

	if ($ps->conf['main']['gametype'] == 'halflife' and $ps->conf['main']['uniqueid'] == 'worldid') {
		$steamid = $player['ids_worldid'][0]['worldid'];
		if ($steamid and strtoupper(substr($steamid, 0, 5)) == 'STEAM') {
			include_once(PS_ROOTDIR . "/includes/class_valve.php");
			$v = new Valve_AuthId();
			$friendid = $v->get_friend_id($steamid);
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

function dash_if_empty($val) {
	return !empty($val) ? $val : '-';
}

function dmg($dmg) {
	return "<abbr title='" . commify($dmg) . "'>" . abbrnum0($dmg) . "</abbr>";
}

function session_skill($val, $sess) {
	return $val . " " . skill_change(array(
		'skill'		=> $val,
		'prevskill'	=> $sess['prevskill'],
	));
}


function return_ofc_data($styles) {
	global $cms, $ps;

	$ofc = $cms->input['ofc'];
	$data = array();
	$data_avg = array();
	$avg = 0;
	$interval = 1000;
	$maxlimit = 1000;
	$minlimit = 0;
	$max = 21;
	$field = $ofc == 'skill' ? 'dayskill' : $ofc;
	if (!in_array($field, array('skill','kills','onlinetime'))) $field = 'dayskill';
	$plrid = $cms->input['id'];

	$ps->db->query("SELECT statdate,$field FROM $ps->t_plr_data WHERE plrid=" . $ps->db->escape($plrid, true) . " ORDER BY statdate DESC LIMIT $max");
	$i = 1;
	while (list($statdate,$skill) = $ps->db->fetch_row(0)) {
		$skill = round($skill);
		$sum += $skill;
		$data[] = $skill;
		$labels[] = $statdate;
	}

	if ($data) {
		$data = array_reverse($data);
		$labels = array_reverse($labels);

		$avg = $sum / count($data);
		$data_avg[] = $avg;
		$data_avg = array_pad($data_avg, count($data)-1, 'null');	// yes, 'null' is a string
		$data_avg[] = $avg;
		$minlimit = floor(min($data) / $interval) * $interval;
		$maxlimit = ceil(max($data) / $interval) * $interval;
	}

	include_once(PS_ROOTDIR . '/includes/ofc/open-flash-chart.php');
	$g = new graph();
	$g->bg_colour = $styles->val('flash.plrskill.bgcolor', 'flash.bgcolor');
	$g->title(
		$styles->val('flash.plrskill.title'),
		'{' . $styles->val('flash.plrskill.title.style', 'font-size: 12px', true) . '}'
	);

	$g->set_data($data_avg);
	$g->set_data($data);

	$lines = $styles->attr('flash.plrskill.lines.line');

	$g->line(
		coalesce($lines[0]['width'], 1),
		coalesce($lines[0]['color'], '#9999ee'),
		coalesce($lines[0]['key'], $cms->trans('Average')), 
		coalesce($lines[0]['key_size'], $styles->val('flash.plrskill.lines.key_size'), 9)
	);
	$g->line(
		coalesce($lines[1]['width'], 1),
		coalesce($lines[1]['color'], '#9999ee'),
		coalesce($lines[1]['key'], $cms->trans('Skill')), 
		coalesce($lines[1]['key_size'], $styles->val('flash.plrskill.lines.key_size'), 9)
	);

	// label each point with its value
	$g->set_x_labels($labels);
//	$g->set_x_axis_steps(count($labels) / 3 + 1);
//	$g->set_x_tick_size(1);

//	$g->set_x_label_style( 10, '0x000000', 0, 2 );

//	$g->set_x_label_style('none');
	$g->set_x_label_style( 8, '#000000', 2 );
	$g->set_inner_background(
		coalesce($styles->val('flash.plrskill.bg_inner1', 'flash.bg_inner1'), '#E3F0FD'),
		coalesce($styles->val('flash.plrskill.bg_inner2', 'flash.bg_inner2'), '#CBD7E6'),
		coalesce($styles->val('flash.plrskill.bg_inner_angle', 'flash.bg_inner_angle'), 90)
	);
	$g->x_axis_colour( '#eeeeee', '#eeeeee' );
	$g->y_axis_colour( '#eeeeee', '#eeeeee' );
//	$g->set_x_offset( false );

	// set the Y max
	$g->set_y_max($maxlimit);
	$g->set_y_min($minlimit);
	// label every 20 (0,20,40,60)
//	$g->x_label_steps( 2 );

	// display the data
	print $g->render();
}

?>
