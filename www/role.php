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
 *	Version: $Id: role.php 450 2008-05-20 11:34:52Z lifo $
 */

define("PSYCHOSTATS_PAGE", true);
$basename = basename(__FILE__, '.php');
include(__DIR__ . "/includes/common.php");
$cms->theme->page_title('Role Stats—PsychoStats');

// Is PsychoStats in maintenance mode?
$maintenance = $ps->conf['main']['maintenance_mode']['enable'];

// Page cannot be viewed if the site is in maintenance mode.
if ($maintenance and !$cms->user->is_admin()) previouspage('index.php');

// default sort for the roles listing
$DEFAULT_SORT = 'kills';

$validfields = array('id','order','sort');
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

// SET DEFAULTS—sanitized
$limit = 25;
$sort = ($sort and strlen($sort) <= 64) ? preg_replace('/[^A-Za-z0-9_\-\.]/', '', $sort) : $DEFAULT_SORT;
$order = trim(strtolower($order ?? ''));
if (!in_array($order, array('asc','desc'))) $order = 'desc';

// sanitize sorts
$sort = ($ps->db->column_exists(array($ps->c_role_data, $ps->t_role), $sort)) ? $sort : $DEFAULT_SORT;

$totalroles = $ps->get_total_roles();
$roles = $ps->get_role_list(array(
	'sort'		=> 'kills',
	'order'		=> 'desc',
	'start'		=> 0,
	'limit'		=> 100		// there's never more than about 25-30 roles
));

// a role name was given; look up the ID for it
if (!is_numeric($id) and !empty($id)) {
	list($id) = $ps->db->fetch_list("SELECT roleid FROM $ps->t_role WHERE uniqueid=" . $ps->db->escape($id, true));
}

$role = $ps->get_role(array(
	'roleid' 	=> $id
));

$cms->theme->page_title(' for ' . $role['label'], true);

// calculate the hitbox zone percentages
/*
$zone = array('head','chest','leftarm','rightarm','stomach','leftleg','rightleg');
$hits = $role['hits'] ? $role['hits'] : 0;
foreach ($zone as $z) {
	$role['shot_'.$z.'pct'] = $hits ? ceil($role['shot_'.$z] / $hits * 100) : 0;
}
*/

// get top10 players .....
$players = array();
if ($role['roleid']) {
	$players = $ps->get_role_player_list(array(
		'roleid' 	=> $id,
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
$ps->role_players_table_mod($table);
$cms->filter('players_table_object', $table); // same as index.php players table

$cms->theme->assign(array(
	'maintenance'	=> $maintenance,
	'roles'			=> $roles,
	'role'			=> $role,
	'roleimg'		=> $ps->roleimg($role, array('path' => 'large', 'noimg' => '') ),
	'totalroles'	=> $totalroles,
	'players'		=> $players,
	'players_table'	=> $table->render(),
	'totalplayers'	=> count($players),
	'i_bots'		=> $ps->invisible_bots(),
	'form_key'		=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
	'cookieconsent'	=> $cookieconsent,
));

if ($role['roleid']) {
	$cms->theme->add_css('css/2column.css');	// this page has a left column
	$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');
} else {
	$cms->full_page_err($basename, array(
		'message_title'	=> $cms->trans("No Role Found!"),
		'message'	=> $cms->trans("Invalid role ID specified.") . " " . $cms->trans("Please go back and try again.")
	));
}

function dmg($dmg) {
	return "<abbr title='" . commify($dmg) . "'>" . abbrnum0($dmg) . "</abbr>";
}

?>
