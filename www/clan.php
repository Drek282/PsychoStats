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
 *	Version: $Id: clan.php 450 2008-05-20 11:34:52Z lifo $
 */

define("PSYCHOSTATS_PAGE", true);
$basename = basename(__FILE__, '.php');
include(__DIR__ . "/includes/common.php");
$cms->theme->page_title('Clan Stats—PsychoStats');

// Is PsychoStats in maintenance mode?
$maintenance = $ps->conf['main']['maintenance_mode']['enable'];

// Page cannot be viewed if the site is in maintenance mode.
if ($maintenance and !$cms->user->is_admin()) previouspage('index.php');

$validfields = array(
	'id', 'v', 'xml',
	'psort','porder','pstart','plimit',	// players
	'vsort','vorder','vstart','vlimit',	// victims
	'msort','morder','mstart','mlimit',	// maps
	'wsort','worder','wstart','wlimit',	// weapons
	'rsort','rorder','rstart','rlimit',	// roles
);
$cms->theme->assign_request_vars($validfields, true);

// change this if you want the default sort of the clan listing to be something else
$DEFAULT_SORT = 'kills';

// create the form variable
$form = $cms->new_form();

// Get cookie consent status from the cookie if it exists.
$cms->session->options['cookieconsent'] ??= false;
($ps->conf['main']['security']['enable_cookieconsent']) ? $cookieconsent = $cms->session->options['cookieconsent'] : $cookieconsent = 1;
if (isset($cms->input['cookieconsent'])) {
	$cookieconsent = $cms->input['cookieconsent'];

	// Update cookie consent status in the cookie if they are accepted.
	// Delete coolies if they are rejected.
	if ($cookieconsent) {
		$cms->session->opt('cookieconsent', $cms->input['cookieconsent']);
		$cms->session->save_session_options();

		// save a new form key in the users session cookie
		// this will also be put into a 'hidden' field in the form
		if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());
		
	} else {
		$cms->session->delete_cookie();
		$cms->session->delete_cookie('_id');
		$cms->session->delete_cookie('_opts');
		$cms->session->delete_cookie('_login');
	}
	previouspage($php_scnm);
}

$load_google = (bool)($ps->conf['theme']['map']['google_key'] != '');

if (!$psort) $psort = 'skill';

// SET DEFAULTS—sanitized. Since they're basically the same for each list, we do this in a loop
foreach ($validfields as $var) {
	switch (substr($var, 1)) {
		case 'sort':
			$$var = ($$var and strlen($$var) <= 64) ? preg_replace('/[^A-Za-z0-9_\-\.]/', '', $$var) : $DEFAULT_SORT;
			if ($$var != 'totalmembers') $$var = ($ps->db->column_exists(array($ps->c_plr_data, $ps->t_plr, $ps->t_clan, $ps->t_clan_profile), $$var)) ? $$var : $DEFAULT_SORT;
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

$clan = $ps->get_clan(array(
	'clanid' 	=> $id,
	'membersort'	=> $psort,
	'memberorder'	=> $porder,
	'memberstart'	=> $pstart,
	'memberlimit'	=> $plimit,
	'memberfields'	=> '',
	'weaponsort'	=> $wsort,
	'weaponorder'	=> $worder,
	'weaponstart'	=> $wstart,
	'weaponlimit'	=> $wlimit,
	'weaponfields'	=> '',
	'mapsort'	=> $msort,
	'maporder'	=> $morder,
	'mapstart'	=> $mstart,
	'maplimit'	=> $mlimit,
	'mapfields'	=> '',
	'loadvictims'	=> 0,
	'victimsort'	=> $vsort,
	'victimorder'	=> $vorder,
	'victimstart'	=> $vstart,
	'victimlimit'	=> $vlimit,
	'victimfields'	=> '',
));

$cms->theme->page_title(' for ' . ($clan['name'] != '' ? $clan['name'] : $clan['clantag']), true);

$x = substr($xml,0,1);
if ($x == 'c') {		// clan

} elseif ($xml == 'w') {	// weapons
	$ary = array();
	// re-arrange the weapons list so the uniqueid of each weapon is a key.
	// weapon uniqueid's should never have any weird characters so this should be safe.
	foreach ($clan['weapons'] as $w) {
		$ary[ $w['uniqueid'] ] = $w;
	} 
	print_xml($ary);
	exit();
}

if ($clan['clanid']) {
  $memberpager = pagination(array(
	'baseurl'	=> ps_url_wrapper(array( 'id' => $id, 'plimit' => $plimit, 'psort' => $psort, 'porder' => $porder)), 
	'total'		=> $clan['totalmembers'],
	'startvar'	=> 'pstart',
	'start'		=> $pstart,
	'perpage'	=> $plimit,
	'separator'	=> ' ',
        'next'          => $cms->trans("Next"),
        'prev'          => $cms->trans("Previous"),
  ));

  $weaponpager = pagination(array(
	'baseurl'	=> ps_url_wrapper(array( 'id' => $id, 'wlimit' => $wlimit, 'wsort' => $wsort, 'worder' => $worder)),
	'total'		=> $clan['totalweapons'],
	'startvar'	=> 'wstart',
	'start'		=> $wstart,
	'perpage'	=> $wlimit,
	'separator'	=> ' ',
	'urltail'	=> "weapons",
        'next'          => $cms->trans("Next"),
        'prev'          => $cms->trans("Previous"),
  ));

  $mappager = pagination(array(
	'baseurl'	=> ps_url_wrapper(array( 'id' => $id, 'mlimit' => $mlimit, 'msort' => $msort, 'morder' => $morder)),
	'total'		=> $clan['totalmaps'],
	'startvar'	=> 'mstart',
	'start'		=> $mstart,
	'perpage'	=> $mlimit,
	'separator'	=> ' ',
	'urltail'	=> "maps",
        'next'          => $cms->trans("Next"),
        'prev'          => $cms->trans("Previous"),
  ));

/*
  $victimpager = pagination(array(
	'baseurl'       => ps_url_wrapper(array( 'id' => $id, 'vlimit' => $vlimit, 'vsort' => $vsort , 'vorder' => $vorder)),
	'total'         => $clan['totalvictims'],
	'startvar'      => 'vstart',
	'start'         => $vstart,
	'perpage'       => $vlimit,
	'separator'	=> ' ',
	'urltail'       => 'victims',
	'next'          => $cms->trans("Next"),
	'prev'          => $cms->trans("Previous"),
  ));
*/
}

//$data['mapblockfile'] = $smarty->get_block_file('block_maps');
//$data['teamblockfile'] = $smarty->get_block_file('block_team');


$ptable = $cms->new_table($clan['members']);
$ptable->attr('class', 'ps-table ps-player-table');
$ptable->sort_baseurl(array( 'id' => $id, '_anchor' => 'members' ));
$ptable->start_and_sort($pstart, $psort, $porder, 'p');
$ptable->columns(array(
	'rank'			=> $cms->trans("Rank"),
	'name'			=> array( 'label' => $cms->trans("Player"), 'callback' => 'ps_table_plr_link' ),
	'kills'			=> array( 'label' => $cms->trans("Kills"), 'modifier' => 'commify' ),
	'deaths'		=> array( 'label' => $cms->trans("Deaths"), 'modifier' => 'commify' ),
	'killsperdeath' 	=> array( 'label' => $cms->trans("K:D"), 'tooltip' => $cms->trans("Kills Per Death") ),
	'headshotkills'		=> array( 'label' => $cms->trans("HS"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Headshot Kills") ),
	'headshotkillspct'	=> array( 'label' => $cms->trans("HS%"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Headshot Kills Percentage") ),
	'skill'			=> $cms->trans("Skill"),
));
$ptable->column_attr('rank', 'class', 'first');
$ptable->column_attr('name', 'class', 'left');
$ptable->column_attr('skill', 'class', 'right');
$ps->clan_players_table_mod($ptable);
$cms->filter('clan_members_table_object', $ptable);


$wtable = $cms->new_table($clan['weapons']);
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
$wtable->column_attr('kills', 'class', 'secondary');
$ps->clan_weapons_table_mod($wtable);
$cms->filter('clan_weapons_table_object', $wtable);


// build map table
$mtable = $cms->new_table($clan['maps']);
$mtable->if_no_data($cms->trans("No Maps Found"));
$mtable->attr('class', 'ps-table ps-map-table');
$mtable->sort_baseurl(array( 'id' => $id, '_anchor' => 'maps' ));
$mtable->start_and_sort($mstart, $msort, $morder, 'm');
$mtable->columns(array(
//	'+'		=> '#',
	'_mapimg'	=> array( 'nolabel' => true, 'callback' => 'ps_table_map_link' ),
	'uniqueid'	=> array( 'label' => $cms->trans("Map"), 'callback' => 'ps_table_map_text_link' ),
	'kills'		=> array( 'label' => $cms->trans("K"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Kills") ), 
	'deaths'	=> array( 'label' => $cms->trans("D"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Deaths") ), 
	'ffkills'	=> array( 'label' => $cms->trans("FF"), 'modifier' => 'commify', 'tooltip' => $cms->trans('Friendly Fire Kills') ),
	'killsperdeath' => array( 'label' => $cms->trans("K:D"), 'tooltip' => $cms->trans("Kills Per Death") ),
	'killsperminute'=> array( 'label' => $cms->trans("K:M"), 'tooltip' => $cms->trans("Kills Per Minute") ),
	'games'		=> array( 'label' => $cms->trans("G"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Games") ),
	'rounds'	=> array( 'label' => $cms->trans("R"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Rounds") ),
	'onlinetime'	=> array( 'label' => $cms->trans("Online"), 'modifier' => 'compacttime' ),
	'lasttime'	=> array( 'label' => $cms->trans("Last"), 'modifier' => 'ps_date_stamp' ),
));
$mtable->column_attr('uniqueid','class','left');
$mtable->header_attr('uniqueid', 'colspan', '2');
$mtable->column_attr('_mapimg', 'style', 'width: 40px;');
$ps->clan_maps_table_mod($mtable);
$cms->filter('clan_maps_table_object', $mtable);

// Declare shades array.
$shades = array(
	's_clan_rundown'		=> null,
	's_clan_killprofile'	=> null,
	's_modactions'			=> null,
	's_clanmembers'			=> null,
	's_clanweapons'			=> null,
	's_clanmaps'			=> null,
);

$totalranked ??= null;
$cms->theme->assign(array(
	'maintenance'		=> $maintenance,
	'clan'				=> $clan,
	'members_table'		=> $ptable->render(),
	'weapons_table'		=> $wtable->render(),
	'maps_table'		=> $mtable->render(),
	'totalranked'		=> $totalranked,
	'weaponpager'		=> $weaponpager,
	'memberpager'		=> $memberpager,
	'mappager'			=> $mappager,
//	'victimpager'		=> $victimpager,
	'shades'			=> $shades,
	'i_bots'			=> $ps->invisible_bots(),
	'form_key'			=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
	'cookieconsent'		=> $cookieconsent,
	'title_logo'		=> ps_title_logo(),
	'game_name'			=> ps_game_name(),
));

// allow mods to have their own section on the left side bar
$ps->clan_left_column_mod($clan, $cms->theme);

if ($clan['clanid']) {
	$cms->theme->add_css('css/2column.css');	// this page has a left column
	$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');
} else {
	$cms->full_page_err($basename, array(
		'message_title'	=> $cms->trans("No Clan Found!"),
		'message'	=> $cms->trans("Invalid clan ID specified.") . " " . $cms->trans("Please go back and try again.")
	));
}

function dmg($dmg) {
	return "<abbr title='" . commify($dmg) . "'>" . abbrnum0($dmg) . "</abbr>";
}

?>
