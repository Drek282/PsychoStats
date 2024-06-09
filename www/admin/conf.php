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
 *	Version: $Id: conf.php 389 2008-04-18 15:04:10Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");
$cms->theme->assign('page', $basename);

/* 
	ct = conftype we're currently editing. Which can have multiple sections within it
	q  = search query. if spcified, only conf variables that match will be displayed
*/

$validfields = array('submit', 'cancel', 'new', 'ct', 's', 'q', 'adv');
$cms->theme->assign_request_vars($validfields, true);

$form = $cms->new_form();
$form->default_modifier('trim');

$message = '';
$cms->theme->assign_by_ref('message', $message);

if ($new) {
	gotopage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'var.php', 'ct' => $ct, 's' => $s )));
}
if ($cancel) {
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'conf.php', 'ct' => $ct, 's' => $s)));
}

$where = "";
if ($q != '') {
	$_q = '%' . $ps->db->escape($q) . '%';
	$where = "(var LIKE '$_q' OR label LIKE '$_q' OR help LIKE '$_q')";
}

// get a list of conftype's available. Ignoring those that only have locked variables within them
$list = $ps->db->fetch_rows(1,
	"SELECT conftype,section " .
	"FROM $ps->t_config " .
	"WHERE conftype <> 'info' AND locked <> 1 AND var IS NOT NULL " . 
	($where ? "AND $where " : "") . 
	"GROUP BY conftype,section " . 
	"HAVING COUNT(*) > 0 " .
	"ORDER BY conftype"
);

// if $list is empty no results have been returned on a search
if (!$list) {
	$message = $cms->message('success', array(
		'message_title' => $cms->trans("No Matching Configuration Options"),
		'message'	=> $cms->trans("No results returned for search query")
	));
}

$sections = array();
foreach ($list as $c) {
    $sections[ $c['conftype'] ] = $sections[ $c['conftype'] ] ?? null;
	if (!$sections[ $c['conftype'] ]) {
		$sections[ $c['conftype'] ] = array();
	}
	$c['section'] = $c['section'] ?? null;
	if ($c['section']) $sections[$c['conftype']][] = $c['section'];
}
//print "<pre>"; print_r($sections); print "</pre>";

// make sure we're trying to edit a valid conftype
$sec_keys = array_keys($sections);
if (!array_key_exists($ct, $sections)) {
	$ct = $sec_keys[0] ?? null;
	if (!$ct) $ct = 'main';
}
unset($sec_keys);

// get a list of section labels
$list = $ps->db->fetch_rows(1, "SELECT conftype,section,label,value FROM $ps->t_config WHERE var IS NULL");
$section_labels = array();
foreach ($list as $l) {
	$section_labels[ $l['conftype'] ][ $l['section'] ? $l['section'] : 'general' ] = array(
		'label'	=> $cms->trans($l['label']),
		'value'	=> $l['value'],
	);
}

// load the full config for the current conftype.
// but we need to massage it into a slightly different format for easier use.
$list = $ps->load_config_layout($ct, $where);
$config_layout = array( 'general' => array() );	// we want 'general' first
if (is_array($sections[$ct] ?? null)) {
	foreach ($sections[$ct] as $sec) {
        $list[$ct][$sec] = $list[$ct][$sec] ?? null;
		$config_layout[$sec] = $list[$ct][$sec];
		unset($list[$ct][$sec]);
	}
}
if ($list[$ct]) {
	$config_layout['general'] = $list[$ct];		// any remaining keys are options w/o a section
} else {
	unset($config_layout['general']);
}
unset($list);

//print "<pre>"; print_r($config_layout); print "</pre>";
// make sure the section is valid
if (empty($s)) $s = 'general';
if (!is_array($config_layout[$s] ?? null)) {
	$s = $sections[$ct][0] ?? null;		// default to first section found
}


$cms->crumb("Config", ps_url_wrapper(array( '_base' => $php_scnm, 'ct' => $ct )));
$cms->crumb($ct);

// setup form fields. each field is actually the ID of the variable so I don't have to worry about
// naming conflicts between sections (eg: maxdays and errlogs.maxdays)
foreach ($config_layout as $sec => $list) {
	if (!is_array($list)) continue;
	foreach ($list as $o) {
        $o['id'] ??= null;
        $o['verifycodes'] ??= null;
		$form->field($o['id'], $o['verifycodes']);
	}
}

// we want the credits at the end of the theme section
if (isset($sections['theme']) and is_array($sections['theme'])) {
	foreach ($sections['theme'] as $st => $val) {
		if ($val != 'credits') continue;
		$mv = $val;
		unset($sections['theme'][$st]);
		$sections['theme'] = array_values($sections['theme']);
		array_push($sections['theme'],$mv);
		unset($mv);
		break;
	}
}
//print "<pre>"; print_r($sections['theme']); print "</pre>";

$section_errors = array();
$section_errors['general'] = false;

// NOW we can process the form if it was submitted
if ($submit) {
	// get all options from the form; we have to fudge this a little
	// since all options are passed in via an opts[] array first
	$key = $form->value('key');	// save the key
	$opts = $form->value('opts');
	$form->input($opts);

	$form->validate();
	$input = $form->values();
	$form->input['key'] = $key;
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	// find out what sections had errors
	if ($form->errors()) {
		$orig_conf = $ps->load_config_by_id('id,section,var,value');
		foreach (array_keys($form->errors()) as $id) {
            $form->errors[$id] ??= null;
            $orig_conf[$id]['section'] ??= null;
			if ($id == 'fatal') continue;
			$key = $orig_conf[$id]['section'];
			if ($key == '') $key = 'general';
			$section_errors[$key] = true;
		}
	}

	if ($valid) {
		// get a copy of the original config
		$orig_conf = $ps->load_config_by_id('id,section,var,value');

		$ps->db->begin();
		$err = false;
		$updated = array();
		foreach ($input as $id => $val) {
            $orig_conf[$id]['value'] = $orig_conf[$id]['value'] ?? null;
			if ($orig_conf[$id]['value'] != $val) {
				if (!$ps->db->update($ps->t_config, array( 'value' => $val ), 'id', $id)) {
					$form->error('fatal', "Error updating config variable ID '$id': " . $ps->db->errstr);
					$err = true;
					break;
				} else {
					$updated[] = $orig_conf[$id]['section'] ? $orig_conf[$id]['section'] . '.' . $orig_conf[$id]['var'] : $orig_conf[$id]['var'];
				}
			}
		}
		$ps->db->commit();

		if (!$err) {
			if (count($updated)) {
				$message = $cms->message('success', array(
					'message_title'	=> $cms->trans("Configuration Updated Successfully"),
					'message'	=> $cms->trans("%d options updated: %s", count($updated), implode(', ', $updated))
				));
			} else {
				$message = $cms->message('success', array(
					'message_title' => $cms->trans("No Changes"),
					'message'	=> $cms->trans("No config changes were made")
				));
			}
		}
	}

} else {
	// fill in the form with default values since nothing has been submitted yet
	foreach ($config_layout as $sec => $list) {
		if (!is_array($list)) continue;
		foreach ($list as $o) {
            $o['value'] = $o['value'] ?? null;
			$form->input[ $o['id'] ] = $o['value'];
		}
	}
}

// generate a new CSRF key
if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

// turn on 'advanced mode'
if ($adv) {
	$cms->session->opt('advconfig', 1);
	$cms->session->save_session_options();
}

// assign variables to the theme
$cms->theme->assign(array(
	'conftypes'				=> array_keys($sections),
	'ct'					=> $ct,
	'sections'				=> $sections,
	'section_labels'		=> $section_labels,
	'section'				=> $sections[$ct] ?? null,
	's'						=> $s,
	'conf'					=> $config_layout,
	'errors'				=> $form->errors(),
	'section_errors'		=> $section_errors['general'],
	'form'					=> $form->values(),
	'form_key'				=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
	'advanced_config'		=> $cms->session->opt('advconfig') ? true : false,
	'q'						=> $q,
	'install_dir_insecure'	=> false
));

// display the output
$cms->theme->add_css('css/2column.css');
$cms->theme->add_css('css/forms.css');
$cms->theme->add_js('js/jquery.interface.js'); // needed for color animation
$cms->theme->add_js('js/conf.js');
$cms->theme->add_js('js/message.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
