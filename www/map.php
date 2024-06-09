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
 *	Version: $Id: map.php 450 2008-05-20 11:34:52Z lifo $
 */

define("PSYCHOSTATS_PAGE", true);
$basename = basename(__FILE__, '.php');
include(__DIR__ . "/includes/common.php");
include_once(PS_ROOTDIR . "/includes/PS/Heatmap.php");
$cms->theme->page_title('Map Stats—PsychoStats');

// Is PsychoStats in maintenance mode?
$maintenance = $ps->conf['main']['maintenance_mode']['enable'];

// Page cannot be viewed if the site is in maintenance mode.
if ($maintenance and !$cms->user->is_admin()) previouspage('index.php');

// how many players per stat
$DEFAULT_SORT = 'kills';
$MAX_PLAYERS = 10;

$validfields = array('id', 'sort', 'order', 'start', 'limit');
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
$sort = ($sort and strlen($sort) <= 64) ? preg_replace('/[^A-Za-z0-9_\-\.]/', '', $sort) : $DEFAULT_SORT;
$order = trim(strtolower($order ?? ''));
if (!in_array($order, array('asc','desc'))) $order = 'desc';
if (!is_numeric($start) || $start < 0) $start = 0;
if (!is_numeric($limit) || $limit < 0) $limit = 10;

// sanitize sorts
$sort = ($ps->db->column_exists(array($ps->c_map_data, $ps->t_map), $sort)) ? $sort : $DEFAULT_SORT;

$topten = array();
$totalmaps = $ps->get_total_maps();
$maps = $ps->get_map_list(array(
	'sort'		=> 'kills',
	'order'		=> 'desc',
	'start'		=> 0, //$start,
	'limit'		=> 50, //$limit,
));

// a map name was given; look up the ID for it
if (!is_numeric($id) and !empty($id)) {
	if ($ps->db->fetch_list("SELECT mapid FROM $ps->t_map WHERE uniqueid=" . $ps->db->escape($id, true))) {
		list($id) = $ps->db->fetch_list("SELECT mapid FROM $ps->t_map WHERE uniqueid=" . $ps->db->escape($id, true));
	} else {
		$fn = $id;
		$id = null;
	}
}

$map = $ps->get_map(array( 
	'mapid' => $id 
));

($id) ? $cms->theme->page_title(' for ' . $map['uniqueid'], true) : $cms->theme->page_title(' unavailable', true);

if (isset($map['mapid'])) {

	$heat = new PS_Heatmap($ps);
	$map['total_heatmaps'] = $heat->total_heatmap_images($map['mapid']);

	$setup = array(
		'mapid' 	=> $id,
		'order'		=> 'desc',
		'limit'		=> $limit,
	);

	$ps->reset_map_stats();

	// generic stats that will work for any game/mod
	$ps->add_map_player_list('kills', 	$setup + array('label' => $cms->trans("Most Kills")) );
	$ps->add_map_player_list('ffkills', 	$setup + array('label' => $cms->trans("Most FF Kills")) );
	$ps->add_map_player_list('ffdeaths', 	$setup + array('label' => $cms->trans("Most FF Deaths")) );
	$ps->add_map_player_list('onlinetime', 	$setup + array('label' => $cms->trans("Most Online Time"), 'modifier' => 'compacttime') );

	// each mod will add their own stats to the output
	$ps->add_map_player_list_mod($map, $setup);

	// allow plugins to add their own stats to the map details
	$cms->action('add_map_player_list');

	// build all topten stats
	$topten = $ps->build_map_stats();
}

// Declare shades array.
$shades = array(
	's_modactions'		=> null,
	's_mapprofile'		=> null,
	's_maplist'			=> null,
	's_map_bandage'		=> null,
	's_map_kills'		=> null,
	's_map_ffkills'		=> null,
	's_map_ffdeaths'	=> null,
	's_map_onlinetime'	=> null,
	's_map_capturepoint'	=> null,
	's_map_flagscaptured'	=> null,
);

$cms->theme->assign(array(
	'maintenance'	=> $maintenance,
	'maps'			=> $maps,
	'map'			=> $map,
	'mapimg'		=> $ps->mapimg($map, array( 'noimg' => '' )),
	'totalmaps'		=> $totalmaps,
	'topten'		=> $topten,
	'totaltopten'	=> count($topten),
	'shades'		=> $shades,
	'i_bots'		=> $ps->invisible_bots(),
	'form_key'		=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
	'cookieconsent'	=> $cookieconsent,
	'title_logo'	=> ps_title_logo(),
	'game_name'		=> ps_game_name(),
));

if (isset($map['mapid'])) {
	// allow mods to have their own section on the left side bar
	$ps->map_left_column_mod($map, $cms->theme);

	$cms->theme->add_css('css/2column.css');	// this page has a left column
	$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');
} else {
	$cms->full_page_err($basename, array(
		'message_title'	=> $cms->trans("No Map Found!"),
		'message'	=> $cms->trans("Invalid map ID specified.") . " " . $cms->trans("No stats available for $fn.")
	));
}

?>
