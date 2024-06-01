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
 *	Version: $Id: reset.php 389 2008-04-18 15:04:10Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");

$validfields = array('ref','cancel','submit');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if ($cancel) {
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'manage.php' )));
}

$form = $cms->new_form();
$form->default_modifier('trim');
$form->field('player_profiles');
$form->field('player_aliases');
$form->field('player_bans');
$form->field('clan_profiles');
$form->field('users');

// process the form if submitted
$valid = true;
$db_errors = array();
if ($submit) {
	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	if ($valid) {
		$ok = $ps->reset_stats($input);

		if ($ok !== true) {
			$db_errors = $ok;	// $ok is an array of errors
			$form->error('warning', "The following data tables could not be reset because they contain no data:");
			
			$message = $cms->message('success', array(
				'message_title'	=> $cms->trans("Database was reset!"), 
				'message'	=> $cms->trans("The database has been reset. Stats will be empty until your next stats update."),
			));
		} else {
			$message = $cms->message('success', array(
				'message_title'	=> $cms->trans("Database was reset!"), 
				'message'	=> $cms->trans("The database has been reset. Stats will be empty until your next stats update."),
			));
//			previouspage(ps_url_wrapper('manage.php'));
		}
	}
} else {
	// default all options to keep
	$form->input(array(
		'player_profiles'	=> true,
		'player_aliases'	=> true,
		'player_bans'		=> true,
		'clan_profiles'		=> true,
		'users'			=> true
	));
}

$cms->crumb('Manage', ps_url_wrapper($_SERVER['REQUEST_URI']));
$cms->crumb('Reset Stats', ps_url_wrapper($php_scnm));

// save a new form key in the users session cookie
// this will also be put into a 'hidden' field in the form
if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

// assign variables to the theme
$cms->theme->assign(array(
	'errors'	=> $form->errors(),
	'db_errors'	=> $db_errors,
	'form'		=> $form->values(),
	'form_key'	=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
	'page'		=> $basename, 
));

// display the output
$cms->theme->add_css('css/2column.css');
$cms->theme->add_css('css/forms.css');
//$cms->theme->add_js('js/jquery.interface.js');
$cms->theme->add_js('js/forms.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
