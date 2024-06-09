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
 *	Version: $Id: weapon.php 507 2008-07-02 18:50:45Z lifo $
 */

define("PSYCHOSTATS_PAGE", true);
$basename = basename(__FILE__, '.php');
include(__DIR__ . "/includes/common.php");
$cms->theme->page_title('Weapon Stats—PsychoStats');

// Is PsychoStats in maintenance mode?
$maintenance = $ps->conf['main']['maintenance_mode']['enable'];

// Page cannot be viewed if the site is in maintenance mode.
if ($maintenance and !$cms->user->is_admin()) previouspage('index.php');

// default sort for the weapons listing
$DEFAULT_SORT = 'kills';

$validfields = array('id','order','sort','xml');
$cms->theme->assign_request_vars($validfields, true);

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
}

// SET DEFAULTS—santized
$limit = 25;
$sort = ($sort and strlen($sort) <= 64) ? preg_replace('/[^A-Za-z0-9_\-\.]/', '', $sort) : $DEFAULT_SORT;
$order = trim(strtolower($order ?? ''));
if (!in_array($order, array('asc','desc'))) $order = 'desc';

// sanitize sorts
$sort = ($ps->db->column_exists(array($ps->c_weapon_data, $ps->t_weapon), $sort)) ? $sort : $DEFAULT_SORT;

$totalweapons = $ps->get_total_weapons();
$weapons = $ps->get_weapon_list(array(
	'sort'		=> 'kills',
	'order'		=> 'desc',
	'start'		=> 0,
	'limit'		=> 100		// there's never more than about 25-30 weapons
));

// a weapon name was given; look up the ID for it
if (!is_numeric($id) and !empty($id)) {
	list($id) = $ps->db->fetch_list("SELECT weaponid FROM $ps->t_weapon WHERE uniqueid=" . $ps->db->escape($id, true));
}

$weapon = $ps->get_weapon(array(
	'weaponid' 	=> $id
));

$cms->theme->page_title(' for ' . $weapon['label'], true);

// calculate the hitbox zone percentages
$zone = array('head','chest','leftarm','rightarm','stomach','leftleg','rightleg');
$hits = $weapon['hits'] ? $weapon['hits'] : 0;
$max = 0;
foreach ($zone as $z) {
	if ($weapon['shot_'.$z] > $max) $max = $weapon['shot_'.$z];
}
foreach ($zone as $z) {
	$weapon['shot_'.$z.'pct'] = $max ? ceil($weapon['shot_'.$z] / $max * 100) : 0;
	$weapon['real_shot_'.$z.'pct'] = $hits ? ceil($weapon['shot_'.$z] / $hits * 100) : 0;
}

// Setup the xml for the hitbox.
$x = substr($xml ?? '',0,1);
if ($x == 'w') {
	// re-arrange the weapon array so the uniqueid of the weapon is the key.
	$ary = array();
	$ary[ $weapon['uniqueid'] ] = $weapon;
	print_xml($ary);
}

// get top10 players .....
$players = array();
if ($weapon['weaponid']) {
	$players = $ps->get_weapon_player_list(array(
		'weaponid' 	=> $id,
		'sort'		=> $sort,
		'order'		=> $order,
		'limit'		=> $limit,
	));
}

// build a dynamic table that plugins can use to add custom columns of data
$table = $cms->new_table($players);
$table->if_no_data($cms->trans("No Players Found"));
$table->attr('class', 'ps-table ps-player-table');
$table->sort_baseurl(array( 'id' => $id ));
$table->start_and_sort(0, $sort, $order);
$table->columns(array(
	'+'			=> array( 'label' => '#' ),
	'name'			=> array( 'label' => $cms->trans("Player"), 'callback' => 'ps_table_plr_link' ),
	'kills'			=> array( 'label' => $cms->trans("Kills"), 'modifier' => 'commify' ),
	'deaths'		=> array( 'label' => $cms->trans("Deaths"), 'modifier' => 'commify' ),
	'headshotkills'		=> array( 'label' => $cms->trans("HS"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Headshot Kills") ),
	'headshotkillspct'	=> array( 'label' => $cms->trans("HS%"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Headshot Kills Percentage") ),
//	'ffkills'		=> array( 'label' => $cms->trans("FF"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Friendly Fire Kills") ),
//	'ffkillspct'		=> array( 'label' => $cms->trans("FF%"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Friendly Fire Kills Percentage") ),
	'accuracy'		=> array( 'label' => $cms->trans("Acc"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Accuracy") ),
	'shotsperkill' 		=> array( 'label' => $cms->trans("S:K"), 'tooltip' => $cms->trans("Shots Per Kill") ),
	'damage' 		=> array( 'label' => $cms->trans("Dmg"), 'callback' => 'dmg', 'tooltip' => $cms->trans("Damage") ),
));
$table->column_attr('name', 'class', 'left');
//$table->column_attr('+', 'class', 'first');
$ps->weapon_players_table_mod($table);
$cms->filter('players_table_object', $table); // same as index.php players table

# handle games with no mods
if (empty($ps->conf['main']['modtype'])) {
    $moddir = "";
} else {
    $moddir = '/' . $ps->conf['main']['modtype'];
}

// Declare shades array.
$shades = array(
	's_hitbox'				=> null,
	's_weapon_killprofile'	=> null,
	's_weaponlist'			=> null,
	's_weapon_plrlist'		=> null,
);

$cms->theme->assign(array(
	'maintenance'	=> $maintenance,
    'hitbox_url'	=> 'weaponxml=' . ps_escape_html($php_scnm) . "&amp;id=$id&amp;imgpath=" . ps_escape_html(rtrim(dirname($php_scnm), '/\\') . '/img/weapons/' . $ps->conf['main']['gametype'] . $moddir) . '&amp;confxml=' . $cms->theme->parent_url() . '/hitbox/config.xml',
	'weapons'		=> $weapons,
	'weapon'		=> $weapon,
	'weaponimg'		=> $ps->weaponimg($weapon, array('path' => 'large', 'noimg' => '') ),
	'totalweapons'	=> $totalweapons,
	'players'		=> $players,
	'players_table'	=> $table->render(),
	'totalplayers'	=> count($players),
	'i_bots'		=> $ps->invisible_bots(),
	'shades'		=> $shades,
	'form_key'		=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
	'cookieconsent'	=> $cookieconsent,
	'title_logo'	=> ps_title_logo(),
	'game_name'		=> ps_game_name(),
));

if ($weapon['weaponid']) {
	$cms->theme->add_css('css/2column.css');	// this page has a left column
	$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');
} else {
	$cms->full_page_err($basename, array(
		'message_title'	=> $cms->trans("No Weapon Found!"),
		'message'	=> $cms->trans("Invalid weapon ID specified.") . " " . $cms->trans("Please go back and try again.")
	));
}

function dmg($dmg) {
	return "<abbr title='" . commify($dmg) . "'>" . abbrnum0($dmg) . "</abbr>";
}

?>
