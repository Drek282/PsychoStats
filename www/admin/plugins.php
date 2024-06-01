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
 *	Version: $Id: plugins.php 389 2008-04-18 15:04:10Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
define("PSYCHOSTATS_DISABLE_PLUGINS", true);	// we don't want plugins to function on this page
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");

$validfields = array('start','limit','order','move','id','enable','disable','install','uninstall');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if (!is_numeric($start) or $start < 0) $start = 0;
if (!is_numeric($limit) or $limit < 0) $limit = 25;
if (!in_array($order, array('asc','desc'))) $order = 'asc';
$sort = 'idx';

$_order = array(
	'start'	=> $start,
	'limit'	=> $limit,
	'order' => $order, 
	'sort'	=> $sort
);

// set the ID to the proper value
if ($enable or $disable) {
	$id = $enable ? $enable : $disable;
} elseif ($uninstall) {
	$id = $uninstall;
}

// load the matching plugin if an ID was given
$plugin = array();
if (!empty($id)) {
	$plugin = $ps->db->fetch_row(1, "SELECT * FROM $ps->t_plugins WHERE plugin=" . $ps->db->escape($id, true));
	if (!isset($plugin['plugin'])) {
		previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'plugins.php' )));	
	}
}

// dis/enable the plugin
if (($enable or $disable) and $id) {
	$on = $enable ? 1 : 0;
	if ($plugin['enabled'] != $on) {
		$ok = $ps->db->update($ps->t_plugins, array( 'enabled' => $on ), 'plugin', $id);
		if ($ok) {
			$message = $cms->message('success', array(
				'message_title'	=> $on ? $cms->trans("Plugin was enabled") : $cms->trans("Plugin was disabled"),
				'message'	=> $plugin['plugin'] . " " . 
					($on ? $cms->trans("was enabled successfully!") : $cms->trans("was disabled successfully!"))
					. "<br/>\n" . $cms->trans("Changes will not be applied until the next page request.")
			));
		} else {
			$message = $cms->message('failure', array(
				'message_title'	=> $cms->trans("Database Error"),
				'message'	=> $cms->trans("Error writting to database") . ": " . $ps->db->errstr
			));
		}
	}
} elseif ($uninstall and $id) {
	$ok = $cms->uninstall_plugin($id);
	if ($ok) {
		$message = $cms->message('success', array(
			'message_title'	=> $cms->trans("Plugin was uninstalled"),
			'message'	=> "$id " . $cms->trans("was successfully uninstalled. It will no longer load after this current page request.")
		));
	} else {
		$message = $cms->message('failure', array(
			'message_title'	=> $cms->trans("Plugin Uninstall Error"),
			'message'	=> $cms->trans("There was an error uninstalling the plugin") . ": " . $ps->db->errstr
		));
	}
}

// re-order plugins
if ($move and $id) {
	$list = $ps->db->fetch_rows(1, "SELECT plugin,idx FROM $ps->t_plugins ORDER BY idx");
	$inc = $move == 'up' ? -15 : 15;
	$idx = 0;
	// loop through all and set idx linearly
	for ($i=0; $i < count($list); $i++) {
		$list[$i]['idx'] = ++$idx * 10;
		if ($list[$i]['plugin'] == $id) $list[$i]['idx'] += $inc;
		$ps->db->update($ps->t_plugins, array( 'idx' => $list[$i]['idx'] ), 'plugin', $list[$i]['plugin']);
	}
	unset($submit);
}

// load plugins
$list = $ps->db->fetch_rows(1, "SELECT * FROM $ps->t_plugins " . $ps->getsortorder($_order));
$plugins = array();

// determine sorting directions
$first = $list ? $list[0]['plugin'] : '';
$last  = $list ? $list[ count($list) - 1]['plugin'] : '';
$list2 = array();
foreach ($list as $p) {
    $p['down'] ??= null;
    $p['up'] ??= null;
	if ($p['plugin'] == $first and $p['plugin'] != $last) {
		$p['down'] = 1;
	} elseif ($p['plugin'] == $last and $p['plugin'] != $first) {
		$p['up'] = 1;
	} elseif ($p['plugin'] != $first and $p['plugin'] != $last) {
		$p['down'] = 1;
		$p['up'] = 1;
	}
	$list2[] = $p;
}
$list = $list2;
unset($list2);

// I want the plugins array to be keyed by the plugin name
foreach ($list as $p) {
	$plugins[ $p['plugin'] ] = $p;
}
unset($list);
$cms->filter('admin_plugins_list', $plugins);

// load new/pending plugins
$pending = array();
$pending = $cms->load_pending_plugins();

if ($install) {
	if (!array_key_exists($install, $pending)) {
		$message = $cms->message('failure', array(
			'message_title'	=> $cms->trans("Plugin Installation Error"),
			'message'	=> $cms->trans("Invalid plugin was specified! Only plugins in the pending list can be installed.")
		));
	} else {
		// install the plugin!
		$err = '';
		$ok = $cms->include_plugin_file($pending[$install]['fullfile'], $err);
		if ($ok and !$err) {	// even if there was an error $ok can still be true
			// create an object for the plugin and load it.
			$plugin = $pending[$install]['base'];
			$obj = new $plugin();
			if ($info = $obj->install($cms)) {
				// plugin successfully installed whatever it needed ...
				// now we install it in the database.
				if ($cms->install_plugin($plugin, $info)) {
					gotopage(ps_url_wrapper($php_scnm));
				} else {
					$message = $cms->message('failure', array(
						'message_title'	=> $cms->trans("Plugin Installation Error"),
						'message'	=> $cms->trans("Error installing plugin:") . " " . $obj->errstr
					));
				}
			} else {
				$message = $cms->message('failure', array(
					'message_title'	=> $cms->trans("Plugin Installation Error"),
					'message'	=> $obj->errstr 
						? $obj->errstr 
						: $cms->trans("Plugin failed to install but did not give a reason why. Contact the plugin author for help.")
				));
			}
		} else {
			$message = $cms->message('failure', array(
				'message_title'	=> $cms->trans("Plugin Installation Error"),
				'message'	=> $cms->trans("Error loading plugin code!") . $err ? "<br/>\n$err" : ''
			));
		}
	}
}

$total = $ps->db->count($ps->t_plugins);
$pager = pagination(array(
	'baseurl'			=> ps_url_wrapper(array('sort' => $sort, 'order' => $order, 'limit' => $limit)),
	'total'				=> $total,
	'start'				=> $start,
	'perpage'			=> $limit, 
	'pergroup'			=> 5,
	'separator'			=> ' ', 
	'force_prev_next'	=> true,
	'next'				=> $cms->trans("Next"),
	'prev'				=> $cms->trans("Previous"),
));

$cms->crumb("Plugins", $php_scnm);

// assign variables to the theme
$cms->theme->assign(array(
	'page'				=> $basename, 
	'installed_plugins'	=> $plugins,
	'pending_plugins'	=> $pending,
	'total_installed'	=> count($plugins),
	'total_pending'		=> count($pending),
	'pager'				=> $pager,
	'total'				=> $total,
));

// display the output
$cms->theme->add_css('css/2column.css');
$cms->theme->add_css('css/forms.css');
//$cms->theme->add_js('js/jquery.interface.js');
$cms->theme->add_js('js/message.js');
$cms->theme->add_js('js/plugins.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
