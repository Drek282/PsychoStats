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
 *	Version: $Id: index.php 506 2008-07-02 14:29:49Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
$basename = basename(__FILE__, '.php');
include(__DIR__ . "/includes/common.php");
$cms->theme->page_title('Player Stats—PsychoStats');

// create the form variable
$form = $cms->new_form();

// Get cookie consent status from the cookie if it exists.
$cms->session->options['cookieconsent'] ??= false;
($ps->conf['main']['security']['enable_cookieconsent']) ? $cookieconsent = $cms->session->options['cookieconsent'] : $cookieconsent = 1;
if (isset($cms->input['cookieconsent'])) {
	$cookieconsent = $cms->input['cookieconsent'];

	// Update cookie consent status in the cookie if they are accepted.
	// Delete cookies if they are rejected.
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

// Is PsychoStats in maintenance mode?
$maintenance = $ps->conf['main']['maintenance_mode']['enable'];

// If PschoStats is in maintenance mode display a message
if ($maintenance and !$cms->user->is_admin()) {
	$cms->full_page_err('index', array(
		'maintenance'	=> $maintenance,
		'message_title'	=> $cms->trans("PsychoStats Maintenance"),
		'message'		=> $cms->trans("Please try again later."),
		'form_key'		=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
		'cookieconsent'	=> $cookieconsent,
	));
	exit();
}

// Check to see if there is any data in the database before we continue.
$cmd = "SELECT * FROM $ps->t_plr_data LIMIT 1";

$results = array();
$results = $ps->db->fetch_rows(1, $cmd);

// if $results is empty then we have no data in the database
if (empty($results)) {
	$cms->full_page_err('index', array(
		'maintenance'	=> $maintenance,
		'message_title'	=> $cms->trans("No Stats Found"),
		'message'		=> $cms->trans("You must run stats.pl before you will see any stats."),
		'form_key'		=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
		'cookieconsent'	=> $cookieconsent,
	));
	exit();
}
unset ($results);

// change this if you want the default sort of the player listing to be something else like 'kills'
$DEFAULT_SORT = 'skill';
$DEFAULT_LIMIT = 100;

// collect url parameters ...
$validfields = array('sort','order','start','limit','q','search');
$cms->theme->assign_request_vars($validfields, true);

// SET DEFAULTS—santized
$sort = ($sort and strlen($sort) <= 64) ? preg_replace('/[^A-Za-z0-9_\-\.]/', '', $sort) : $DEFAULT_SORT;
$order = trim(strtolower($order ?? ''));
if (!in_array($order, array('asc','desc'))) $order = 'desc';
if (!is_numeric($start) || $start < 0) $start = 0;
if (!is_numeric($limit) || $limit < 0 || $limit > 500) $limit = $DEFAULT_LIMIT;
$q = trim($q ?? '');

// sanitize sorts
$sort = ($ps->db->column_exists(array($ps->t_plr, $ps->t_plr_profile, $ps->c_plr_data), $sort)) ? $sort : $DEFAULT_SORT;

// if $q is longer than 50 characters we have a problem
if (strlen($q) > 50) {
	$cms->full_page_err('index', array(
		'maintenance'	=> $maintenance,
		'message_title'	=> $cms->trans("Invalid Search String"),
		'message'	=> $cms->trans("Searches are limited to 50 characters in length."),
		'form_key'		=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
		'cookieconsent'	=> $cookieconsent,
	));
	exit();
}

// If a language is passed from GET/POST update the user's cookie. 
if (isset($cms->input['language'])) {
	if ($cms->theme->is_language($cms->input['language'])) {
		$cms->session->opt('language', $cms->input['language']);
		$cms->session->save_session_options();
	} else {
		// report an error?
		// na... just silently ignore the language
//		trigger_error("Invalid theme specified!", E_USER_WARNING);
	}
	previouspage($php_scnm);
}

$total = array();
$results = array();
if ($q != '') {
	// a new search was requested (a query string was given)
	$search = $ps->init_search();
	$matched = $ps->search_players($search, array(
		'phrase'	=> $q,
		'mode'		=> 'contains',
		'status'	=> 'ranked',
	));
	$results = $ps->get_search($search);
	
} else if ($ps->is_search($search)) {
	// an existing search was requested (new page or sort)
	$results = $ps->get_search($search);
	
} else {
	// no search, just fetch a list players
	$search = '';
}

// determine the total players found
$total['all'] = $ps->get_total_players(array('allowall' => 1));
if ($results) {
	$total['ranked'] = $results['result_total'];
	$total['absolute'] = $results['abs_total'];
} else {
	$total['ranked']   = $ps->get_total_players(array('allowall' => 0));
	$total['absolute'] = $total['all'];
}

// auto-redirect to the exact player matched in the search
// if a single player was found.
if ($search and $results['abs_total'] == 1 and is_numeric($results['results'])) {
	gotopage(ps_url_wrapper(array(
		'_amp' => '&',
		'_base' => 'player.php',
		'id' => $results['results']
	)));
}

// fetch stats, etc...
$players = $ps->get_player_list(array(
	'results'	=> $results,
	'sort'		=> $sort,
	'order'		=> $order,
	'start'		=> $start,
	'limit'		=> $limit,
	'joinclaninfo' 	=> false,
));

$baseurl = array('sort' => $sort, 'order' => $order, 'limit' => $limit);
if ($search) {
	$baseurl['search'] = $search;
} else if ($q != '') {
	$baseurl['q'] = $q;
}
$pager = pagination(array(
	'baseurl'	=> ps_url_wrapper($baseurl),
	'total'		=> $total['ranked'],
	'start'		=> $start,
	'perpage'	=> $limit, 
	'pergroup'	=> 5,
	'separator'	=> ' ', 
	'force_prev_next' => true,
	'next'		=> $cms->trans("Next"),
	'prev'		=> $cms->trans("Previous"),
));

// build a dynamic table that plugins can use to add custom columns of data
$table = $cms->new_table($players);
$table->if_no_data($cms->trans("No Players Found"));
$table->attr('class', 'ps-table ps-player-table');
$table->sort_baseurl($search ? array( 'search' => $search ) : array( 'q' => $q ));
$table->start_and_sort($start, $sort, $order);
$table->columns(array(
	'rank'			=> array( 'label' => $cms->trans("Rank"), 'callback' => 'dash_if_empty' ),
	'prevrank'		=> array( 'nolabel' => true, 'callback' => 'rankchange' ),
	'name'			=> array( 'label' => $cms->trans("Player"), 'callback' => 'ps_table_plr_link' ),
	'kills'			=> array( 'label' => $cms->trans("Kills"), 'modifier' => 'commify' ),
	'deaths'		=> array( 'label' => $cms->trans("Deaths"), 'modifier' => 'commify' ),
	'killsperdeath' 	=> array( 'label' => $cms->trans("K:D"), 'tooltip' => $cms->trans("Kills Per Death") ),
	'headshotkills'		=> array( 'label' => $cms->trans("HS"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Headshot Kills") ),
	'headshotkillspct'	=> array( 'label' => $cms->trans("HS%"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Headshot Kills Percentage") ),
	'onlinetime'		=> array( 'label' => $cms->trans("Online"), 'modifier' => 'compacttime' ),
	'activity'		=> array( 'label' => $cms->trans("Activity"), 'modifier' => 'activity_bar' ),
	'skill'			=> array( 'label' => $cms->trans("Skill"), 'callback' => 'plr_skill' ),
));
$table->column_attr('rank', 'class', 'first');
$table->column_attr('skill', 'class', 'right');
$table->column_attr('name', 'class', 'left');
$table->header_attr('rank', 'colspan', '2');
$ps->index_table_mod($table);
$cms->filter('players_table_object', $table);

// assign variables to the theme
$cms->theme->assign(array(
	'maintenance'	=> $maintenance,
	'q'				=> $q,
	'search'		=> $search,
	'results'		=> $results,
	'search_blurb'	=> $cms->trans('Search criteria "<em>%s</em>" matched %d ranked players out of %d total',
		ps_escape_html($q), $total['ranked'], $total['absolute']
	),
	'players'		=> $players,
	'players_table'	=> $table->render(),
	'total'			=> $total,
	'pager'			=> $pager,
	'i_bots'		=> $ps->invisible_bots(),
	'language_list'	=> $cms->theme->get_language_list(),
	'theme_list'	=> $cms->theme->get_theme_list(),
	'language'		=> $cms->theme->language,
	'form_key'		=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
	'cookieconsent'	=> $cookieconsent,
	'title_logo'	=> ps_title_logo(),
	'game_name'		=> ps_game_name(),
));

// display the output
//$cms->theme->add_js('js/index.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

function activity_bar($pct) {
	$out = pct_bar(array( 'pct' => $pct ));
	return $out;
}

function dash_if_empty($val) {
	return !empty($val) ? $val : '-';
}

function rankchange($val, $plr) {
	return rank_change($plr);
}

function skillchange($val, $plr) {
	return skill_change($plr);
}

function plr_skill($val, $plr) {
	return $val . " " . skill_change($plr);
}

function plr_rank($val, $plr) {
	return rank_change($plr) . " " . $val;
}

?>
