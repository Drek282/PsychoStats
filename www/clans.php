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
 *	Version: $Id: clans.php 450 2008-05-20 11:34:52Z lifo $
 */

define("PSYCHOSTATS_PAGE", true);
$basename = basename(__FILE__, '.php');
include(__DIR__ . "/includes/common.php");
$cms->theme->page_title('Clan Stats—PsychoStats');

// Is PsychoStats in maintenance mode?
$maintenance = $ps->conf['main']['maintenance_mode']['enable'];

// Page cannot be viewed if the site is in maintenance mode.
if ($maintenance and !$cms->user->is_admin()) previouspage('index.php');

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
	$cms->full_page_err('clans', array(
		'maintenance'	=> $maintenance,
		'message_title'	=> $cms->trans("No Stats Found"),
		'message'	=> $cms->trans("You must run stats.pl before you will see any stats."),
		'form_key'		=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
		'cookieconsent'	=> $cookieconsent,
	));
	exit();
}
unset ($results);

// change this if you want the default sort of the clan listing to be something else like 'kills'
$DEFAULT_SORT = 'skill';

// collect url parameters ...
$validfields = array('sort','order','start','limit','xml');
$cms->theme->assign_request_vars($validfields, true);

// SET DEFAULTS—sanitized.
$sort = ($sort and strlen($sort) <= 64) ? preg_replace('/[^A-Za-z0-9_\-\.]/', '', $sort) : $DEFAULT_SORT;
$order = trim(strtolower($order));
if (!in_array($order, array('asc','desc'))) $order = 'desc';
if (!is_numeric($start) || $start < 0) $start = 0;
if (!is_numeric($limit) || $limit < 0 || $limit > 500) $limit = 100;

// sanitize sorts
if ($sort != 'totalmembers') $sort = ($ps->db->column_exists(array($ps->t_clan, $ps->t_plr, $ps->c_plr_data, $ps->t_clan_profile), $sort)) ? $sort : $DEFAULT_SORT;

// fetch stats, etc...
$totalclans  = $ps->get_total_clans(array('allowall' => 1));
$totalranked = $ps->get_total_clans(array('allowall' => 0));

$clans = $ps->get_clan_list(array(
	'sort'		=> $sort,
	'order'		=> $order,
	'start'		=> $start,
	'limit'		=> $limit,
//	'fields'	=> "kills,deaths,killsperdeath",
));

$pager = pagination(array(
	'baseurl'			=> ps_url_wrapper(array('limit' => $limit, 'sort' => $sort, 'order' => $order)),
	'total'				=> $totalranked,
	'start'				=> $start,
	'perpage'			=> $limit,
	'separator'			=> ' ', 
	'force_prev_next'	=> true,
    'next'          	=> $cms->trans("Next"),
    'prev'          	=> $cms->trans("Previous"),
));

// build a dynamic table that plugins can use to add custom columns of data
$table = $cms->new_table($clans);
$table->if_no_data($cms->trans("No Clans Found"));
$table->attr('class', 'ps-table ps-clan-table');
$table->start_and_sort($start, $sort, $order);
$table->columns(array(
	'+'			=> '#',
	'clantag'		=> array( 'label' => $cms->trans("Clan Tag"), 'callback' => 'ps_table_clan_link' ), 
	'name'			=> array( 'label' => $cms->trans("Clan Name"), 'callback' => 'ps_table_clan_link2' ),
	'totalmembers'		=> array( 'label' => $cms->trans("Members"), 'modifier' => 'commify' ),
	'kills'			=> array( 'label' => $cms->trans("Kills"), 'modifier' => 'commify' ),
	'deaths'		=> array( 'label' => $cms->trans("Deaths"), 'modifier' => 'commify' ),
	'killsperdeath' 	=> array( 'label' => $cms->trans("K:D"), 'tooltip' => $cms->trans("Kills Per Death") ),
	'headshotkills'		=> array( 'label' => $cms->trans("HS"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Headshot Kills") ),
	'headshotkillspct'	=> array( 'label' => $cms->trans("HS%"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Headshot Kills Percentage") ),
	'activity'		=> array( 'label' => $cms->trans("Activity"), 'modifier' => 'activity_bar' ),
	'skill'			=> $cms->trans("Skill")
));
$table->column_attr('+', 'class', 'first');
$table->column_attr('clantag', 'class', 'left');
$table->column_attr('name', 'class', 'left');
$table->column_attr('skill', 'class', 'right');
$ps->clans_table_mod($table);
$cms->filter('clans_table_object', $table);


$cms->theme->assign(array(
	'maintenance'	=> $maintenance,
	'clans'			=> $clans,
	'clans_table'	=> $table->render(),
	'pager'			=> $pager,
	'totalclans'	=> $totalclans,
	'totalranked' 	=> $totalranked,
	'i_bots'		=> $ps->invisible_bots(),
	'form_key'		=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
	'cookieconsent'	=> $cookieconsent,
	'title_logo'	=> ps_title_logo(),
	'game_name'		=> ps_game_name(),
));


// display the output
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

function activity_bar($pct) {
	$out = pct_bar(array( 'pct' => $pct ));
	return $out;
}

function ps_table_clan_link2($name, $clan) {
	return ps_table_clan_link($name, $clan, false, false);
}


?>
