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
 *	Version: $Id: clantags_edit.php 389 2008-04-18 15:04:10Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");
$cms->theme->assign('page', 'clantags');

$validfields = array('ref','id','del','submit','cancel','test');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if ($cancel) {
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'clantags.php' )));
}

// load the matching clantag if an ID was given
$clantag = array();
if (is_numeric($id)) {
	$clantag = $ps->db->fetch_row(1, "SELECT * FROM $ps->t_config_clantags WHERE id=" . $ps->db->escape($id));
	if (!$clantag['id']) {
		$data = array(
			'message' => $cms->trans("Invalid clantag ID Specified"),
		);
		$cms->full_page_err($basename, $data);
		exit();		
	}
} elseif (!empty($id)) {
	$data = array(
		'message' => $cms->trans("Invalid clantag ID Specified"),
	);
	$cms->full_page_err($basename, $data);
	exit();		
}

// delete it, if asked to
if ($del and $clantag['id'] == $id) {
	$ps->db->delete($ps->t_config_clantags, 'id', $id);
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'clantags.php' )));
}

// create the form variables
$form = $cms->new_form();
$form->default_modifier('trim');
$form->field('clantag','blank,val_regex');
$form->field('overridetag');
$form->field('pos');
$form->field('type');
$form->field('example');
//$form->field('idx');


if (isset($test) and $form->input['type'] == 'regex') { 	// test the regex, if asked to
	$test = $form->values();
	$result = 'success';
	$msg = '';

	// verify the regex is valid
	$pattern = "/" . $test['clantag'] . "/";
	ob_start();
	var_export(preg_match($pattern, '')) === false;
	$test['result'] = ob_get_clean();
	
	if ($test['result'] == 0) {
		$msg = $cms->trans("The regular expression is valid!");
	} else {
		$result = 'failure';
		$msg = $cms->trans("The regular expression is not valid! - Please check the syntax");
	}

	$message = $cms->message($result, array(
		'message_title'	=> $cms->trans("Testing Results"), 
		'message'	=> $msg
	));

	// don't let the form be submitted
	unset($test);
	unset($submit);

} elseif (isset($test) and $form->input['type'] == 'plain') {
	$result = 'failure';
	$msg = '';

	// no test for plain text clantags
	$msg = $cms->trans("You can only test regex clantags!");

	$message = $cms->message($result, array(
		'message_title'	=> $cms->trans("Testing Results"), 
		'message'	=> $msg
	));

	// don't let the form be submitted
	unset($test);
	unset($submit);
}

// process the form if submitted
$valid = true;
if (isset($submit)) {
	if ($form->input['type'] == 'plain') {
		$form->field('pos', 'blank');
		$form->field('clantag','blank');	// not a regex
	} else {
		$form->input['pos'] = 'left';
	}

	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	if ($valid) {
		$ok = false;
		if ($id) {
			$ok = $ps->db->update($ps->t_config_clantags, $input, 'id', $id);
		} else {
			$input['id'] = $ps->db->next_id($ps->t_config_clantags);
			$input['idx'] = $ps->db->max($ps->t_config_clantags, 'idx') + 10;		// last source
//			$input['idx'] = 0;							// first source
			$ok = $ps->db->insert($ps->t_config_clantags, $input);
		}
		if (!$ok) {
			$form->error('fatal', "Error updating database: " . $ps->db->errstr);
		} else {
			previouspage(ps_url_wrapper('clantags.php'));
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
		$form->input($clantag);
	}
}

$cms->crumb('Manage', ps_url_wrapper('manage.php'));
$cms->crumb('Clan Tags', ps_url_wrapper('clantags.php'));
$cms->crumb('Edit');

// save a new form key in the users session cookie
// this will also be put into a 'hidden' field in the form
if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

$cms->theme->assign(array(
	'errors'	=> $form->errors(),
	'clantag'	=> $clantag,
	'form'		=> $form->values(),
	'form_key'	=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
));

// display the output
$cms->theme->add_css('css/forms.css');
//$cms->theme->add_js('js/jquery.interface.js');
$cms->theme->add_js('js/clantags.js');
$cms->theme->add_js('js/forms.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

function val_regex($var, $value, &$form) {
	global $valid, $cms;
	if (!empty($value)) {
		if (@preg_match("/$value/", "this is a test") === false) {
			$valid = false;
			$form->error($var, $cms->trans("Invalid regex syntax; See http://php.net/pcre for details"));
		}
	}
	return $valid;
}

?>
