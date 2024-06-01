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
 *	Version: $Id: roles_edit.php 389 2008-04-18 15:04:10Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");
$cms->theme->assign('page', 'roles');

$validfields = array('ref','id','del','submit','cancel');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if ($cancel) {
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'roles.php' )));
}

// load the matching role if an ID was given
$role = array();
if (is_numeric($id)) {
	$role = $ps->db->fetch_row(1, "SELECT * FROM $ps->t_role WHERE roleid=" . $ps->db->escape($id, true));
	if (!$role['roleid']) {
		$data = array('message' => $cms->trans("Invalid role ID Specified"));
		$cms->full_page_err($basename, $data);
		exit();		
	}
} elseif (!empty($id)) {
	$data = array('message' => $cms->trans("Invalid role ID Specified"));
	$cms->full_page_err($basename, $data);
	exit();		
}

// delete it, if asked to
if ($del and $role['roleid'] == $id) {
	$ps->db->delete($ps->t_role, 'roleid', $id);
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'roles.php' )));
}

// create the form variables
$form = $cms->new_form();
$form->default_modifier('trim');
$form->field('uniqueid','blank');
$form->field('name');
$form->field('team');

// process the form if submitted
$valid = true;
if ($submit) {
	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	list($exists) = $ps->db->fetch_list("SELECT roleid FROM $ps->t_role WHERE uniqueid='" . $ps->db->escape($input['uniqueid']) . "'");
	if (($id and $exists != $id) or (!$id and $exists)) {
		$form->error('uniqueid', $cms->trans("A role already exists with this identifier!"));
	}

	$valid = ($valid and !$form->has_errors());
	if ($valid) {
		$ok = false;
		if (empty($input['name'])) $input['name'] = null;
		if (empty($input['team'])) $input['team'] = null;
		if ($id) {
			$ok = $ps->db->update($ps->t_role, $input, 'roleid', $id);
		} else {
			$input['roleid'] = $ps->db->next_id($ps->t_role, 'roleid');
			$ok = $ps->db->insert($ps->t_role, $input);
		}
		if (!$ok) {
			$form->error('fatal', "Error updating database: " . $ps->db->errstr);
		} else {
			previouspage(ps_url_wrapper('roles.php'));
		}
	}

} else {
	// fill in defaults
	if ($id) {
		$form->input($role);
	}
}

$cms->crumb('Manage', ps_url_wrapper('manage.php'));
$cms->crumb('Roles', ps_url_wrapper('roles.php'));
$cms->crumb('Edit');

// save a new form key in the users session cookie
// this will also be put into a 'hidden' field in the form
if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

$cms->theme->assign(array(
	'errors'	=> $form->errors(),
	'role'		=> $role,
	'form'		=> $form->values(),
	'form_key'	=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
));

// display the output
$cms->theme->add_css('css/forms.css');
$cms->theme->add_js('js/forms.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
