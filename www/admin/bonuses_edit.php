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
 *	Version: $Id: bonuses_edit.php 534 2008-08-13 18:54:34Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");
$cms->theme->assign('page', 'plrbonuses');

$validfields = array('ref','id','del','submit','cancel');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if ($cancel) {
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'bonuses.php' )));
}

// try and determine bonus ID by the id string (non-numeric)
if (!empty($id) and !is_numeric($id)) {
	list($exists) = $ps->db->fetch_list("SELECT id FROM $ps->t_config_plrbonuses WHERE eventname=" . $ps->db->escape($id, true));
	if ($exists) {
		$id = $exists;
	}
}

// load the matching bonus if an ID was given
$bonus = array();
if (is_numeric($id)) {
	$bonus = $ps->db->fetch_row(1, "SELECT * FROM $ps->t_config_plrbonuses WHERE id=" . $ps->db->escape($id));
	if (!$bonus['id']) {
		$data = array(
			'message' => $cms->trans("Invalid Bonus ID Specified"),
		);
		$cms->full_page_err($basename, $data);
		exit();		
	}
} elseif (!empty($id)) {
	$data = array(
		'message' => $cms->trans("Invalid Bonus ID Specified"),
	);
	$cms->full_page_err($basename, $data);
	exit();		
}

// delete it, if asked to
if ($del and $bonus['id'] == $id) {
	$ps->db->delete($ps->t_config_plrbonuses, 'id', $id);
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'bonuses.php' )));
}

// create the form variables
$form = $cms->new_form();
$form->default_modifier('trim');
$form->field('gametype');
$form->field('modtype');
$form->field('eventname','blank,val_word');
$form->field('enactor','numeric');
$form->field('enactor_team','numeric');
$form->field('victim','numeric');
$form->field('victim_team','numeric');
$form->field('description');

// process the form if submitted
$valid = true;
if ($submit) {
	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	// don't allow empty strings for zeros
	if (!$input['enactor']) 	$input['enactor'] = 0;
	if (!$input['enactor_team']) 	$input['enactor_team'] = 0;
	if (!$input['victim']) 		$input['victim'] = 0;
	if (!$input['victim_team']) 	$input['victim_team'] = 0;

	if ($input['modtype'] != '' and $input['gametype'] == '') {
		$form->error('gametype', $cms->trans("You must enter the game type"));
	}

	$valid = !$form->has_errors();
	if ($valid) {
		$ok = false;
		if ($id) {
			$ok = $ps->db->update($ps->t_config_plrbonuses, $input, 'id', $id);
		} else {
			$input['id'] = $ps->db->next_id($ps->t_config_plrbonuses);
			$ok = $ps->db->insert($ps->t_config_plrbonuses, $input);
		}
		if (!$ok) {
			$form->error('fatal', "Error updating database: " . $ps->db->errstr);
		} else {
			previouspage(ps_url_wrapper('bonuses.php'));
		}
	}

} else {
	// fill in defaults
	if ($id) {
		$form->input($bonus);
	}
}

$cms->crumb('Manage', ps_url_wrapper('manage.php'));
$cms->crumb('Player Bonuses', ps_url_wrapper('bonuses.php'));
$cms->crumb('Edit');

// save a new form key in the users session cookie
// this will also be put into a 'hidden' field in the form
if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

$cms->theme->assign(array(
	'errors'	=> $form->errors(),
	'bonus'		=> $bonus,
	'form'		=> $form->values(),
	'form_key'	=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
));

// display the output
$cms->theme->add_css('css/forms.css');
//$cms->theme->add_js('js/jquery.interface.js');
$cms->theme->add_js('js/forms.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

function val_word($var, $value, &$form) {
	global $valid, $cms;
	if (!empty($value)) {
		if (!preg_match('/^[a-z_][a-z0-9_]+$/', $value)) {
			$valid = false;
			$form->error($var, $cms->trans("Must be an alphanumeric word with no spaces (a-z, 0-9, _ only)"));
		}
	}
	return $valid;
}

?>
