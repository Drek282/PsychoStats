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
 *	Version: $Id: overlays_edit.php 549 2008-08-24 23:54:06Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");
$cms->theme->assign('page', 'overlays');

$validfields = array('ref','id','del','submit','cancel');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if ($cancel) {
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'overlays.php' )));
}

// try and determine bonus ID by the id string (non-numeric)
if (!empty($id) and !is_numeric($id)) {
	list($exists) = $ps->db->fetch_list("SELECT id FROM $ps->t_config_overlays WHERE map=" . $ps->db->escape($id, true) . " LIMIT 1");
	// only returns the first map found, which will usually suffice
	if ($exists) {
		$id = $exists;
	}
}

// load the matching overlay if an ID was given
$overlay = array();
if (is_numeric($id) and $id > 0) {
	$overlay = $ps->db->fetch_row(1, "SELECT * FROM $ps->t_config_overlays WHERE id=" . $ps->db->escape($id));
}
if ($id and (!$overlay or !$overlay['id'])) {
	$data = array(
		'message' => $cms->trans("Invalid Overlay Specified"),
	);
	$cms->full_page_err($basename, $data);
	exit();		
}

// delete it, if asked to
if ($del and $overlay['id'] == $id) {
	$ps->db->delete($ps->t_config_overlays, 'id', $id);
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'overlays.php' )));
}

// create the form variables
$form = $cms->new_form();
$form->default_modifier('trim');
$form->field('gametype','blank');
$form->field('modtype','blank');
$form->field('map','blank');
$form->field('minx','blank,numeric');
$form->field('miny','blank,numeric');
$form->field('maxx','blank,numeric');
$form->field('maxy','blank,numeric');
$form->field('width','blank,numeric');
$form->field('height','blank,numeric');
$form->field('flipv','numeric');
$form->field('fliph','numeric');
//$form->field('rotate','numeric');

// process the form if submitted
$valid = true;
if ($submit) {
	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	$input['flipv'] = $input['flipv'] ? 1 : 0;
	$input['fliph'] = $input['fliph'] ? 1 : 0;

	$valid = !$form->has_errors();
	if ($valid) {
		$ok = false;
		if ($id) {
			$ok = $ps->db->update($ps->t_config_overlays, $input, 'id', $id);
		} else {
			$input['id'] = $ps->db->next_id($ps->t_config_overlays);
			$ok = $ps->db->insert($ps->t_config_overlays, $input);
			print $ps->db->lastcmd;
		}
		if (!$ok) {
			$form->error('fatal', "Error updating database: " . $ps->db->errstr);
		} else {
			previouspage(ps_url_wrapper('overlays.php'));
		}
	}

} else {
	// fill in defaults
	if ($id) {
		$form->input($overlay);
	} else {
		// default game:mod to currently configured values
		$form->input(array(
			'gametype'	=> $ps->conf['main']['gametype'],
			'modtype'	=> $ps->conf['main']['modtype']
		));
	}
}

$cms->crumb('Manage', ps_url_wrapper('manage.php'));
$cms->crumb('Overlays', ps_url_wrapper('overlays.php'));
$cms->crumb('Edit');

// save a new form key in the users session cookie
// this will also be put into a 'hidden' field in the form
if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

$cms->theme->assign(array(
	'errors'	=> $form->errors(),
	'overlay'	=> $overlay,
	'form'		=> $form->values(),
	'form_key'	=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
));

// display the output
$cms->theme->add_css('css/forms.css');
$cms->theme->add_js('js/forms.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
