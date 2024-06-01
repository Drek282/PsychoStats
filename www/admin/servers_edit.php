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
 *	Version: $Id: servers_edit.php 389 2008-04-18 15:04:10Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");
include("../includes/class_PQ.php");
$cms->theme->assign('page', 'servers');

$validfields = array('ref','id','del','submit','cancel','test');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if ($cancel) {
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'servers.php' )));
}

// load the matching server if an ID was given
$server = array();
if (is_numeric($id)) {
	$server = $ps->db->fetch_row(1, "SELECT * FROM $ps->t_config_servers WHERE id=" . $ps->db->escape($id));
	if (!$server['id']) {
		$data = array(
			'message' => $cms->trans("Invalid Server ID Specified"),
		);
		$cms->full_page_err(basename(__FILE__, '.php'), $data);
		exit();		
	}
} elseif (!empty($id)) {
	$data = array(
		'message' => $cms->trans("Invalid Server ID Specified"),
	);
	$cms->full_page_err(basename(__FILE__, '.php'), $data);
	exit();		
}

// delete it, if asked to
if ($del and $server['id'] == $id) {
	$ps->db->delete($ps->t_config_servers, 'id', $id);
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'servers.php' )));
}

// create the form variables
$form = $cms->new_form();
$form->default_modifier('trim');
$form->field('enabled');
$form->field('host','blank,hostname');
$form->field('port','blank,numeric');
$form->field('alt');
$form->field('querytype');
$form->field('rcon');
$form->field('cc');
//$form->field('idx');

if (isset($test)) { 	// test the server, if asked to
	$test = $form->values();
	$result = 'success';
	$msg = '';

	// verify we can query the server
	$pq = PQ::create(array(
		'ip'		=> $test['host'],
		'port'		=> $test['port'],
		'querytype'	=> $test['querytype'],
		'timeout'	=> 3,
		'retries'	=> 1,
	));
//	$pq->DEBUG = true;
	$in = @$pq->query_info();

	if (!$in) {
		$result = 'failure';
		$msg = $cms->trans("Unable to query server");
		if ($pq->errstr) $msg .= "<br/>\n" . $pq->errstr;
	} else {
		$msg = $cms->trans("Server queried successfully!");
		$msg .= "<br/>\n" . $cms->trans("Server Name") . ": " . $in['name'];
	}

	$message = $cms->message($result, array(
		'message_title'	=> $cms->trans("Testing Results"), 
		'message'	=> $msg
	));
	// don't let the form be submitted
	unset($submit);
}

// process the form if submitted
$valid = true;
if (isset($submit)) {
	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	if ($valid) {
		// default port; avoid mysql error: Incorrect integer value: '' for column 'port'
		if (!$input['port']) $input['port'] = null;

		$ok = false;
		if ($id) {
			$ok = $ps->db->update($ps->t_config_servers, $input, 'id', $id);
		} else {
			$input['id'] = $ps->db->next_id($ps->t_config_servers);
			$input['idx'] = $ps->db->max($ps->t_config_servers, 'idx') + 10;		// last source
//			$input['idx'] = 0;							// first source
			$ok = $ps->db->insert($ps->t_config_servers, $input);
		}
		if (!$ok) {
			$form->error('fatal', "Error updating database: " . $ps->db->errstr);
		} else {
			previouspage(ps_url_wrapper('servers.php'));
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
	if (!$test) {
		if ($id) {
			$form->input($server);
		} else {
			// new servers should default to being enabled
			$form->input['enabled'] = 1;
		}
	}
}

$cms->crumb('Manage', ps_url_wrapper('manage.php'));
$cms->crumb('Servers', ps_url_wrapper('servers.php'));
$cms->crumb('Edit');

// save a new form key in the users session cookie
// this will also be put into a 'hidden' field in the form
if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

$cms->theme->assign(array(
	'errors'	=> $form->errors(),
	'server'	=> $server,
	'querytypes'	=> pq_query_types(),
	'form'		=> $form->values(),
	'form_key'	=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
));

// display the output
$cms->theme->add_css('css/forms.css');
//$cms->theme->add_js('js/jquery.interface.js');
$cms->theme->add_js('js/forms.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
