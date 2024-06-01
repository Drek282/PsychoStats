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
 *	Version: $Id: aliases_edit.php 389 2008-04-18 15:04:10Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");
$cms->theme->assign('page', 'aliases');

$validfields = array('ref','id','del','submit','cancel');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if ($cancel) {
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'aliases.php' )));
}

// load the matching aliases if an ID was given
$alias = array();
if (!empty($id)) {
	$key = 'uniqueid'; //is_numeric($id) ? 'id' : 'uniqueid';
	$alias = $ps->db->fetch_row(1, "SELECT * FROM $ps->t_plr_aliases WHERE $key=" . $ps->db->escape($id, true) . " LIMIT 1");
	if (!$alias['id']) {
		$data = array('message' => $cms->trans("Unknown player alias specified"));
		$cms->full_page_err($basename, $data);
		exit();	
	}
	// load all matching aliases for this uniqueid
	$list = $ps->db->fetch_list("SELECT alias FROM $ps->t_plr_aliases WHERE uniqueid=" . $ps->db->escape($alias['uniqueid'], true) . " ORDER BY alias");
	$alias['aliases'] = implode("\n", $list) . "\n";
	$alias['alias_list'] = $list;
}

// delete alias. If $id is numeric then only a single alias is removed, 
// if its a string then all aliases pointing to the uniqueid will be deleted.
if ($del and $alias) {
	$key = 'uniqueid'; // is_numeric($id) ? 'id' : 'uniqueid';
	$ps->db->delete($ps->t_plr_aliases, $key, $id);
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'aliases.php' )));
}

// create the form variables
$form = $cms->new_form();
$form->default_modifier('trim');
$form->default_validator('blank');
$form->field('uniqueid');
$form->field('aliases');

// process the form if submitted
$valid = true;
if ($submit) {
	if ($id) {
		// force the uniqueid if we're editing an alias (read-only)
		$form->input['uniqueid'] = $alias['uniqueid'];
	}

	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	// separate the alias list into pieces, remove duplicates (case-insensitive) and verify
	$list = array_map('trim', explode("\n", $input['aliases']));
	$uniq = array();
	foreach ($list as $a) {
		if ($a == '') continue;		// ignore blank lines
		$uniq[ strtolower($a) ] = $a;
	}
	$list = array_values($uniq);
	$input['aliases'] = $list;
	$form->input['aliases'] = implode("\n", $list) . "\n";		// update the form so it'll redisplay properly

	// verify the entries in the alias list are valid
	foreach ($list as $a) {
		// don't allow an alias to equal the uniqueid
		if (strtolower($a) == strtolower($input['uniqueid'])) {
			$form->error('aliases', $cms->trans("No alias can be the same as the unique ID"));
			$valid = false;
			break;
		}

		// verify the same alias is not assigned to another uniqueid
		$exists = $ps->db->fetch_list(sprintf("SELECT 1 FROM $ps->t_plr_aliases WHERE uniqueid <> %s AND alias = %s", 
			$ps->db->escape($input['uniqueid'], true),
			$ps->db->escape($a, true)
		));
		if ($exists) { 
			$form->error('aliases', $cms->trans("'%s' is already defined to another unique ID", $a));
			$valid = false;
			break;
		}

	}

	// if we're adding a new alias make sure the uniqueid is ... unique
	if (!$id) {
		$exists = $ps->db->fetch_list(sprintf("SELECT 1 FROM $ps->t_plr_aliases WHERE uniqueid = %s LIMIT 1", 
			$ps->db->escape($input['uniqueid'], true)
		));
		if ($exists) { 
			$form->error('uniqueid', $cms->trans("This unique ID is already defined"));
			$valid = false;
		}
	}

	if ($valid) {
		$ok = true;

		// start a new transaction in case something goes wrong...
		$ps->db->begin();

		if ($id) {
			// delete aliases that were removed
			// deleting entries before inserting new ones allows the SQL server to fill in 'holes' in the table
			$del = array_diff($alias['alias_list'], $input['aliases']);
			if ($del) {
				$a_list = '';
				foreach ($del as $a) {
					$a_list[] = $ps->db->escape($a, true);
				}
				$a = implode(', ', $a_list);
				$uid = $ps->db->escape($alias['uniqueid'], true);
				$ok = $ps->db->query("DELETE FROM $ps->t_plr_aliases WHERE uniqueid=$uid AND alias IN ($a)");
			}			
		}

		// add new aliases for this uniqueid
		$new = $id ? array_diff($input['aliases'], $alias['alias_list']) : $input['aliases'];
		if ($ok and $new) {
			foreach ($new as $a) {
				$ok = $ps->db->insert($ps->t_plr_aliases, array(
					'id'		=> $ps->db->next_id($ps->t_plr_aliases, 'id'),
					'uniqueid' 	=> $input['uniqueid'],
					'alias'		=> $a
				));
				if (!$ok) break;
			}
		}

		if (!$ok) {
			$form->error('fatal', "Error updating database: " . $ps->db->errstr);
			$ps->db->rollback();	// cancel any changes made
		} else {
			$ps->db->commit();	// commit changes and stop transaction
			previouspage(ps_url_wrapper('aliases.php'));
		}
/*
		$message = $cms->message('success', array(
			'message_title'	=> $cms->trans("Update Successfull"),
			'message'	=> $cms->trans("Aliases have been updated"))
		));
*/

	}

} else {
	// fill in defaults
	if ($id) {
		$form->input($alias);
	}
}

$cms->crumb('Manage', ps_url_wrapper('manage.php'));
$cms->crumb('Player Aliases', ps_url_wrapper('aliases.php'));
$cms->crumb('Edit');

// save a new form key in the users session cookie
// this will also be put into a 'hidden' field in the form
if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

$cms->theme->assign(array(
	'errors'	=> $form->errors(),
	'alias'		=> $alias,
	'form'		=> $form->values(),
	'form_key'	=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
));

// display the output
$cms->theme->add_css('css/forms.css');
//$cms->theme->add_js('js/jquery.interface.js');
$cms->theme->add_js('js/forms.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
