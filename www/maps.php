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
 *	Version: $Id: maps.php 450 2008-05-20 11:34:52Z lifo $
 */

define("PSYCHOSTATS_PAGE", true);
$basename = basename(__FILE__, '.php');
include(__DIR__ . "/includes/common.php");
$cms->theme->page_title('Maps Played—PsychoStats');

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
	$cms->full_page_err('maps', array(
		'maintenance'	=> $maintenance,
		'message_title'	=> $cms->trans("No Stats Found"),
		'message'		=> $cms->trans("You must run stats.pl before you will see any stats."),
		'form_key'		=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
		'cookieconsent'	=> $cookieconsent,
	));
	exit();
}
unset ($results);

// Default sort for the maps listing.
$DEFAULT_SORT = 'kills';

$validfields = array('sort','order','start','limit','xml');
$cms->theme->assign_request_vars($validfields, true);

// SET DEFAULTS—santized
$sort = ($sort and strlen($sort) <= 64) ? preg_replace('/[^A-Za-z0-9_\-\.]/', '', $sort) : $DEFAULT_SORT;
$order = trim(strtolower($order ?? ''));
if (!in_array($order, array('asc','desc'))) $order = 'desc';
if (!is_numeric($start) || $start < 0) $start = 0;
if (!is_numeric($limit) || $limit < 0 || $limit > 500) $limit = 100;

// sanitize sorts
$sort = ($ps->db->column_exists(array($ps->c_map_data, $ps->t_map), $sort)) ? $sort : $DEFAULT_SORT;

$totalmaps = $ps->get_total_maps();
$maps = $ps->get_map_list(array(
	'sort'		=> $sort,
	'order'		=> $order,
	'start'		=> $start,
	'limit'		=> $limit,
));

$pager = pagination(array(
	'baseurl'			=> ps_url_wrapper(array( 'limit' => $limit, 'sort' => $sort, 'order' => $order )),
	'total'				=> $totalmaps,
	'start'				=> $start,
	'perpage'			=> $limit,
	'pergroup'			=> 5,
	'separator'			=> ' ', 
	'force_prev_next'	=> true,
    'next'          	=> $cms->trans("Next"),
    'prev'          	=> $cms->trans("Previous"),
));

// build a dynamic table that plugins can use to add custom columns of data
$table = $cms->new_table($maps);
$table->if_no_data($cms->trans("No Maps Found"));
$table->attr('class', 'ps-table ps-map-table');
$table->sortable(true);
//$table->sort_baseurl(array( '_base' => 'maps.php' ));
$table->start_and_sort($start, $sort, $order);
$table->columns(array(
	'+'				=> '#',
	'_mapimg'		=> array( 'nolabel' => true, 'callback' => 'ps_table_map_link' ),
	'uniqueid'		=> array( 'label' => $cms->trans("Map"), 'callback' => 'ps_table_map_text_link' ),
	'kills'			=> array( 'label' => $cms->trans("Kills"), 'modifier' => 'commify' ), 
	'ffkills'		=> array( 'label' => $cms->trans("FF Kills"), 'tooltip' => $cms->trans('Friendly Fire Kills') ),
//	'headshotkills'	=> 'Headshots',
	'games'			=> $cms->trans("Games"),
	'rounds'		=> $cms->trans("Rounds"),
	'onlinetime'	=> array( 'label' => $cms->trans("Online"), 'modifier' => 'compacttime' ),
	'lasttime'		=> array( 'label' => $cms->trans("Last"), 'modifier' => 'ps_date_stamp' ),
));
$table->column_attr('uniqueid', 'class', 'left');
$table->header_attr('uniqueid', 'colspan', '2');
$table->column_attr('_mapimg', 'class', 'mapimg');
$ps->maps_table_mod($table);
$cms->filter('maps_table_object', $table);

// assign variables to the theme
$cms->theme->assign(array(
	'maintenance'	=> $maintenance,
	'maps'			=> $maps,
	'maps_table'	=> $table->render(),
	'totalmaps'		=> $totalmaps,
	'pager'			=> $pager,
	'i_bots'		=> $ps->invisible_bots(),
	'form_key'		=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
	'cookieconsent'	=> $cookieconsent,
	'title_logo'	=> ps_title_logo(),
	'game_name'		=> ps_game_name(),
));

// display the output
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

?>
