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
 *	Version: $Id: events_edit.php 389 2008-04-18 15:04:10Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");
$cms->theme->assign('page', 'events');

$validfields = array('ref','id','del','submit','cancel');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if ($cancel) {
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'events.php' )));
}

// load the matching event if an ID was given
$event = array();
if (is_numeric($id)) {
	$event = $ps->db->fetch_row(1, "SELECT * FROM $ps->t_config_events WHERE id=" . $ps->db->escape($id));
	if (!$event['id']) {
		$data = array(
			'message' => $cms->trans("Invalid Event ID Specified"),
		);
		$cms->full_page_err($basename, $data);
		exit();		
	}
} elseif (!empty($id)) {
	$data = array(
		'message' => $cms->trans("Invalid Event ID Specified"),
	);
	$cms->full_page_err($basename, $data);
	exit();		
}

// delete it, if asked to
if ($del and $event['id'] == $id) {
	$ps->db->delete($ps->t_config_events, 'id', $id);
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'events.php' )));
}

// create the form variables
$form = $cms->new_form();
$form->default_modifier('trim');
$form->field('gametype');
$form->field('modtype');
$form->field('eventname','blank,val_word');
$form->field('alias','val_word');
$form->field('regex','blank,val_regex');
$form->field('ignore', 'blank,numeric');
$form->field('codefile','val_filename');
//$form->field('idx');

// process the form if submitted
$valid = true;
if ($submit) {
	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	if ($valid) {
		$ok = false;
		if ($id) {
			$ok = $ps->db->update($ps->t_config_events, $input, 'id', $id);
		} else {
			$input['id'] = $ps->db->next_id($ps->t_config_events);
			$input['idx'] = $ps->db->max($ps->t_config_events, 'idx') + 10;		// last source
//			$input['idx'] = 0;							// first source
			$ok = $ps->db->insert($ps->t_config_events, $input);
		}
		if (!$ok) {
			$form->error('fatal', "Error updating database: " . $ps->db->errstr);
		} else {
			previouspage(ps_url_wrapper('events.php'));
		}
/*
		$message = $cms->message('success', array(
			'message_title'	=> $cms->trans("Update Successfull"),
			'message'	=> $cms->trans("Log Source has been updated"))
		));
*/

	}

} else {
	// fill in defaults
	if ($id) {
		$form->input($event);
	} else {
		// new events should default to being enabled
		$form->input['ignore'] = 1;
	}
}

$cms->crumb('Manage', ps_url_wrapper('manage.php'));
$cms->crumb('Events', ps_url_wrapper('events.php'));
$cms->crumb('Edit');

// save a new form key in the users session cookie
// this will also be put into a 'hidden' field in the form
if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

$cms->theme->assign(array(
	'errors'	=> $form->errors(),
	'event'		=> $event,
	'form'		=> $form->values(),
	'form_key'	=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
));

// display the output
$cms->theme->add_css('css/forms.css');
//$cms->theme->add_js('js/jquery.interface.js');
$cms->theme->add_js('js/forms.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

function val_regex($var, $value, &$form) {
	global $valid, $cms;
	if (!empty($value)) {
		if (@preg_match($value, "this is a test") === false) {
			$valid = false;
			$form->error($var, $cms->trans("Invalid regex syntax; See http://php.net/pcre for details"));
		}
	}
	return $valid;
}

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

function val_filename($var, $value, &$form) {
	global $valid, $cms;
	if (!empty($value)) {
		if (!preg_match('/^[\w\d\.]+$/', $value)) {
			$valid = false;
			$form->error($var, $cms->trans("Filename must have no spaces or path"));
		}
	}
	return $valid;
}

?>
