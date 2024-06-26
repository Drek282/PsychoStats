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
 *	Version: $Id: roles.php 450 2008-05-20 11:34:52Z lifo $
 */

define("PSYCHOSTATS_PAGE", true);
$basename = basename(__FILE__, '.php');
include(__DIR__ . "/includes/common.php");
$cms->theme->page_title('Role Stats—PsychoStats');

// Is PsychoStats in maintenance mode?
$maintenance = $ps->conf['main']['maintenance_mode']['enable'];

// Page cannot be viewed if the site is in maintenance mode.
if ($maintenance and !$cms->user->is_admin()) previouspage('index.php');

// change this if you want the default sort of the roles listing to be something else like 'ffkills'
$DEFAULT_SORT = 'kills';

$validfields = array('sort','order','xml','v');
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
	previouspage($php_scnm);
}

// Check to see if there is any data in the database before we continue.
$cmd = "SELECT * FROM $ps->t_plr_data LIMIT 1";

$results = array();
$results = $ps->db->fetch_rows(1, $cmd);

// if $results is empty then we have no data in the database
if (empty($results)) {
	$cms->full_page_err('roles', array(
		'maintenance'	=> $maintenance,
		'message_title'	=> $cms->trans("No Stats Found"),
		'message'		=> $cms->trans("You must run stats.pl before you will see any stats."),
		'form_key'		=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
		'cookieconsent'	=> $cookieconsent,
	));
	exit();
}
unset ($results);

// SET DEFAULTS—sanitized
$v = strtolower($v ?? '');
$sort = ($sort and strlen($sort) <= 64) ? preg_replace('/[^A-Za-z0-9_\-\.]/', '', $sort) : $DEFAULT_SORT;
$order = trim(strtolower($order ?? ''));
$start = 0;
$limit = 100;
if (!in_array($order, array('asc','desc'))) $order = 'desc';

// sanitize sorts
$sort = ($ps->db->column_exists(array($ps->c_role_data, $ps->t_role), $sort)) ? $sort : $DEFAULT_SORT;

$stats = $ps->get_sum(array('kills','damage'), $ps->c_plr_data);

$roles = $ps->get_role_list(array(
	'sort'		=> $sort,
	'order'		=> $order,
	'start'		=> $start,
	'limit'		=> $limit,
));
$totalroles = count($roles);

// calculate some extra percentages for each role and determine max values
$max = array();
$keys = array('kills', 'damage', 'headshotkills');
for ($i=0; $i < count($roles); $i++) {
	foreach ($keys as $k) {
        $stats[$k] ??= null;
		if ($stats[$k]) {
			$roles[$i][$k.'pct'] = ($stats[$k]) ? ceil($roles[$i][$k] / $stats[$k] * 100) : 0;
		}
		$max[$k] ??= null;
		if ($roles[$i][$k] > $max[$k]) $max[$k] = $roles[$i][$k];
	}
}
// calculate scale width of pct's based on max
$scale = 200;
$ofs   = $scale; // + 40;
for ($i=0; $i < count($roles); $i++) {
	foreach ($keys as $k) {
		if ($max[$k] == 0) {
			$roles[$i][$k.'width'] = $ofs - ceil($roles[$i][$k] / 1 * $scale);
		} else {
			$roles[$i][$k.'width'] = $ofs - ceil($roles[$i][$k] / $max[$k] * $scale);
		}
	}
}

if ($xml) {
	$ary = array();
	foreach ($roles as $r) {
		unset($r['dataid']);
		$ary[ $r['uniqueid'] ] = $r;
	} 
	print_xml($ary);
}

// build a dynamic table that plugins can use to add custom columns of data
$table = $cms->new_table($roles);
$table->if_no_data($cms->trans("No Roles Found"));
$table->attr('class', 'ps-table ps-role-table');
$table->start_and_sort($start, $sort, $order);
$table->columns(array(
	'uniqueid'		=> array( 'label' => $cms->trans("Role"), 'callback' => 'ps_table_role_link' ),
	'kills'			=> array( 'label' => $cms->trans("Kills"), 'modifier' => 'commify' ),
	'headshotkills'		=> array( 'label' => $cms->trans("HS"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Headshot Kills") ),
	'headshotkillspct'	=> array( 'label' => $cms->trans("HS%"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Headshot Kills Percentage") ),
	'ffkills'		=> array( 'label' => $cms->trans("FF"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Friendly Fire Kills") ),
	'ffkillspct'		=> array( 'label' => $cms->trans("FF%"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Friendly Fire Kills Percentage") ),
	'accuracy'		=> array( 'label' => $cms->trans("Acc"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Accuracy") ),
	'shotsperkill' 		=> array( 'label' => $cms->trans("S:K"), 'tooltip' => $cms->trans("Shots Per Kill") ),
	'damage' 		=> array( 'label' => $cms->trans("Dmg"), 'modifier' => 'abbrnum0', 'tooltip' => $cms->trans("Damage") ),
));
$table->column_attr('uniqueid', 'class', 'first');
$ps->roles_table_mod($table);
$cms->filter('roles_table_object', $table);

// assign variables to the theme
$cms->theme->assign(array(
	'maintenance'	=> $maintenance,
	'roles'			=> $roles,
	'roles_table'	=> $table->render(),
	'totalroles'	=> $totalroles,
	'totalkills'	=> $stats['kills'],
	'totaldamage'	=> $stats['damage'],
	'i_bots'		=> $ps->invisible_bots(),
	'form_key'		=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
	'cookieconsent'	=> $cookieconsent,
	'title_logo'	=> ps_title_logo(),
	'game_name'		=> ps_game_name(),
));

// display the output
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

?>
