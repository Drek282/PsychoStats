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
 *	Version: $Id: overlays_import.php 537 2008-08-14 12:54:28Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("../includes/class_simplexml.php");
include("./common.php");
$cms->theme->assign('page', 'overlays');

$validfields = array('ref','id','del','submit','cancel');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if ($cancel) {
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'overlays.php' )));
}


// create the form variables
$form = $cms->new_form();
$form->default_modifier('trim');
$form->field('xml','blank');
$form->field('overwrite');

// process the form if submitted
$valid = true;
if ($submit) {
	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	$list = array();
	$valid = !$form->has_errors();
	// process the XML and make sure it has valid data in it
	if ($valid) {
		$str = trim($input['xml']);
		$str = preg_replace('/^<\?xml.+\?>\s*/', '', $str);
		if (substr($str,0,6) != '<maps>') {
			$str = "<maps>\n" . $str . "\n</maps>";
		}
		$xml = new simplexml();
		$ary = $xml->xml_load_string($str, 'array');
		if (is_array($ary)) {
			if ($ary['map']) {
				$list = $ary['map'][0] ? $ary['map'] : array( $ary['map']);
			} else {
				$form->error('fatal', $cms->trans("No valid 'map' nodes found! Make sure all 'map' nodes are contained with a root 'maps' node."));
			}
		} else {
			if (substr($ary, 0, 15) == 'XML parse error') {
				// ary is an error string
				$form->error('fatal', $ary);
			} else {
				$form->error('fatal', "Invalid XML syntax!");
			}
		}
	}

	// convert $list into a list of maps for insertion into the DB
	$maps = array();
	$bad = array();
	if ($list) {
		$columns = array('minx', 'miny', 'maxx', 'maxy', 'res', 'flipv', 'fliph');
		foreach ($list as $m) {
			$attr = $m['@attributes'];
			$name = $attr['name'];
			if (!$name) {
				continue;
			}
			$map = array();
			// make sure each column is valid before using it
			foreach ($columns as $c) {
				if ($c == 'res') {
					list($w,$h) = explode('x', $m[$c]);
					if (is_numeric($w) and $w > 0 and is_numeric($h) and $h > 0) {
						$map['width'] = $w;
						$map['height'] = $h;
					} else {
						$bad[] = $name;
						$map = false;
						break;
					}
				} else {
					if (is_numeric($m[$c])) {
						$map[$c] = $m[$c];
					} else {
						$bad[] = $name;
						$map = false;
						break;
					}
				}
			}
			if ($map) {
				$map['map'] = $name;
				$map['gametype'] = $attr['gametype'] ? $attr['gametype'] : $ps->conf['main']['gametype'];
				$map['modtype'] = $attr['modtype'] ? $attr['modtype'] : $ps->conf['main']['modtype'];
				$maps[] = $map;
			}
		}
		unset($list);
		
		// only show the warning message if we have at least 1 valid map
		if ($bad and $maps) {
			$message = $cms->message('warning', array(
				'message_title'	=> $cms->trans("Invalid maps ignored"), 
				'message'	=> '<b>' . $cms->trans("The following maps were ignored due to bad values:") . '</b><br/>' . implode(', ', $bad)
			));
		}
		
		if (!$maps) {
			$form->error('fatal', $cms->trans("No valid maps found to import. Make sure the maps entered have valid data."));
		}
	}
	
	$valid = !$form->has_errors();
	if ($valid) {
		$ok = false;
		$ignored = array();
		// insert or update each map 
		foreach ($maps as $m) {
			$exists = $ps->db->fetch_item(sprintf(
				"SELECT id FROM $ps->t_config_overlays " .
				"WHERE gametype=%s AND modtype=%s AND map=%s",
				$ps->db->escape($m['gametype'], true),
				$ps->db->escape($m['modtype'], true),
				$ps->db->escape($m['map'], true)
			));
			if (!$exists) {
				$m['id'] = $ps->db->next_id($ps->t_config_overlays);
				$ok = $ps->db->insert($ps->t_config_overlays, $m);
			} elseif ($exists and $input['overwrite']) {
				$ok = $ps->db->update($ps->t_config_overlays, $m, 'id', $exists);
			} else {
				// the map exists and overwrite is false
				$ignored[] = $m['map'];
				continue;
			}
			if (!$ok) {
				$form->error('fatal', "Error updating database: " . $ps->db->errstr);
			}
		}

		if ($ignored) {
			$message = $cms->message('warning', array(
				'message_title'	=> $cms->trans("Duplicate maps ignored"), 
				'message'	=> '<b>' . $cms->trans("The following maps were ignored since they exist already:") . '</b><br/>' . implode(', ', $ignored)
			));
		}

		if (!$form->has_errors() and !$message) {
			previouspage('overlays.php');
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
