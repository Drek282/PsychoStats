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
 *	Version: $Id: mapheat.php 450 2008-05-20 11:34:52Z lifo $
 */

define("PSYCHOSTATS_PAGE", true);
$basename = basename(__FILE__, '.php');
include(__DIR__ . "/includes/common.php");
include_once(PS_ROOTDIR . "/includes/PS/Heatmap.php");
$cms->theme->page_title('Heatmap—PsychoStats');

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
		$cms->session->delete_cookie('_opts');
	}
	previouspage($php_scnm);
}

// Check to see if there is any data in the database before we continue.
$cmd = "SELECT * FROM $ps->t_plr_data LIMIT 1";

$results = array();
$results = $ps->db->fetch_rows(1, $cmd);

// if $results is empty then we have no data in the database
if (empty($results)) {
	$cms->full_page_err('mapheat', array(
		'message_title'	=> $cms->trans("No Stats Found"),
		'message'	=> $cms->trans("You must run stats.pl before you will see any stats."),
		'form_key'		=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
		'cookieconsent'	=> $cookieconsent,
	));
	exit();
}
unset ($results);

$validfields = array('id', 'heatid', 'sort', 'order', 'start', 'limit');
$cms->theme->assign_request_vars($validfields, true);

$sort = isset($sort) ? $sort = strtolower($sort) : '';
if (isset($order)) $order = strtolower($order);
if (!preg_match('/^\w+$/', $sort)) $sort = 'kills';
if (!in_array($order, array('asc','desc'))) $order = 'desc';
if (!is_numeric($start) || $start < 0) $start = 0;
if (!is_numeric($limit) || $limit < 0) $limit = 10;

$totalmaps = $ps->get_total_maps();
$maps = $ps->get_map_list(array(
	'sort'		=> 'kills',
	'order'		=> 'desc',
	'start'		=> 0, //$start,
	'limit'		=> 50, //$limit,
));

// a map name was given; look up the ID for it
if (!is_numeric($id) and !empty($id)) {
	list($id) = $ps->db->fetch_list("SELECT mapid FROM $ps->t_map WHERE uniqueid=" . $ps->db->escape($id, true));
}

$map = $ps->get_map(array( 
	'mapid' => $id 
));

$cms->theme->page_title(' for ' . $map['uniqueid'], true);

$heatmap_list = array();
if ($map['mapid']) {
	$heat = new PS_Heatmap($ps);
	$heatmap_list = $heat->get_map_heatmaps($id);
	uasort($heatmap_list,'sort_heatmaps');

	if ($heatmap_list) {
		// default to the first heatmap type available
		if (!$heatid or !isset($heatmap_list[$heatid])) {
			reset($heatmap_list);
			$m = current($heatmap_list);
			$heatid = $m['heatid'];
		}
//		print_r($heatmap_list[$heatid]);
		$map['overlay'] = $ps->overlayimg($map['uniqueid']);
		$map['heatmap_images'] = $heat->get_heatmap_images($map['mapid'], $heatmap_list[$heatid]);
	}
}

// Declare shades array.
$shades = array(
	's_modactions'		=> null,
	's_mapprofile'		=> null,
	's_maplist'			=> null,
	's_map_kills'		=> null,
	's_map_ffkills'		=> null,
	's_map_ffdeaths'	=> null,
	's_map_onlinetime'	=> null,
);

$cms->theme->assign(array(
	'maintenance'	=> $maintenance,
	'heatid'		=> $heatid,
	'maps'			=> $maps,
	'map'			=> $map,
	'mapimg'		=> $ps->mapimg($map, array( 'noimg' => '' )),
	'totalmaps'		=> $totalmaps,
	'heatmap_list'	=> $heatmap_list,
	'shades'		=> $shades,
	'form_key'		=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
	'cookieconsent'	=> $cookieconsent,
	'title_logo'	=> ps_title_logo(),
	'game_name'		=> ps_game_name(),
));

if ($map['mapid']) {
	// allow mods to have their own section on the left side bar
	$ps->map_left_column_mod($map, $cms->theme);

	$cms->theme->add_css('css/2column.css');	// this page has a left column
	$cms->theme->add_js('js/heatmap.js');
	$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');
} else {
	$cms->full_page_err($basename, array(
		'message_title'	=> $cms->trans("No Map Found!"),
		'message'	=> $cms->trans("Invalid map ID specified.") . " " . $cms->trans("Please go back and try again.")
	));
}

function sort_heatmaps($a,$b) {
	return strcasecmp($a['label'], $b['label']);
}

?>
