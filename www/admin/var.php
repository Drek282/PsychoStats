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
 *	Version: $Id: var.php 389 2008-04-18 15:04:10Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");
$cms->theme->assign('page', basename(__FILE__, '.php'));

$input_types = array( 'boolean', 'checkbox', 'select', 'text', 'textarea' );

$validfields = array('submit', 'cancel', 'del', 'ct', 's', 'id');
$cms->theme->assign_request_vars($validfields, true);

if ($cancel) {
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'conf.php', 'ct' => $ct, 's' => $s)));
}

$conf = array();		// config variable

// Load the config variable if we were given an ID
if (is_numeric($id)) {
	$conf = $ps->load_conf_var($id);
	if (!$conf['id']) {
		$data = array(
			'message' => $cms->trans("Invalid Conf ID Specified"),
		);
		$cms->full_page_err(basename(__FILE__, '.php'), $data);
		exit();
	}
	if (empty($ct)) $ct = $conf['conftype'];
	if (empty($s)) $s = $conf['section'];
}
if (empty($ct)) $ct = 'main';

// delete the variable
if ($del and $conf['id'] == $id) {
	$ps->db->delete($ps->t_config, 'id', $id);
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'conf.php', 'ct' => $conf['conftype'], 's' => $conf['section'])));
}

$form = $cms->new_form();
$form->default_modifier('trim');
$form->field('conftype', 'blank');
$form->field('section');
$form->field('var', 'blank');
$form->field('value');
$form->field('label');
$form->field('type', 'val_type,blank');
$form->field('verifycodes');
$form->field('options');
$form->field('help');

$valid = true;
if ($submit) {
	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	// verify the variable name doesn't already exist in the same conftype.section
	list($exists) = $ps->db->fetch_list(sprintf("SELECT id FROM $ps->t_config WHERE conftype=% AND section=%s AND var=%s", 
		$ps->db->escape($input['conftype'], true),
		$ps->db->escape($input['section'], true),
		$ps->db->escape($input['var'], true)
	));
	if (($id and $exists and $exists != $id) or (!$id and $exists)) {
		$form->error('var', "Variable name already exists");
		$valid = false;
	}
/*
	if ($id) {	// if we're editing a current option then make sure the ID matches if it exists
		if (($id and $exists and $exists != $id) or (!$id and $exists)) {
			$form->error('var', "Variable name already exists");
			$valid = false;
		}
	} else {	// if we're creating a new option then make sure the ID does not exist
		if ($exists) {
			$form->error('var', "Variable name already exists");
			$valid = false;
		}
	}
*/

	// if the form is valid then we update the database
	if ($valid) {
		$ok = false;
		if ($id) {
			$ok = $ps->db->update($ps->t_config, $input, 'id', $id);
			if (!$ok) {
				$form->error('fatal', "Error updating database: " . $ps->db->errstr);
			}
		} else {
			$set = $input;
			$set['id'] = $ps->db->next_id($ps->t_config);
			if (strtolower($set['section']) == 'general') $set['section'] = '';
			$ok = $ps->db->insert($ps->t_config, $set);
			if (!$ok) {
				$form->error('fatal', "Error inserting into database: " . $ps->db->errstr);
			}
		}
		if ($ok) {
			previouspage(ps_url_wrapper(array('_amp' => '&', '_base' => 'conf.php', 'ct' => $input['conftype'], 's' => $input['section'])));
		}
	}

} else {
	if ($id) {
		foreach ($conf as $key => $v) {
			$form->input[$key] = $v;
		}
	} else {
		$form->input['conftype'] = $ct;
		$form->input['section'] = strtolower($s) == 'general' ? '' : $s;
	}
}

$cms->crumb("Config", ps_url_wrapper(array( '_base' => 'conf.php', 'ct' => $ct )));
$cms->crumb("Edit Option");

// save a new form key in the users session cookie
// this will also be put into a 'hidden' field in the form
if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

$cms->theme->assign(array(
	'input_types' 	=> $input_types,
	'errors'	=> $form->errors(),
	'form'		=> $form->values(),
	'form_key'	=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
));

// display the output
//$cms->theme->add_css('css/2column.css');
$cms->theme->add_css('css/forms.css');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

// validate: input type
function val_type($var, $value, &$form) {
	global $valid, $cms, $input_types;
	if (!empty($value)) {
		if (!in_array($value, $input_types)) {
			$form->error($var, $cms->trans("Passwords do not match"));
			$valid = false;
		}
	}
	return $valid;
}

?>
