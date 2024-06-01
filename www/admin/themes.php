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
 *	Version: $Id: themes.php 389 2008-04-18 15:04:10Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");
include_once(PS_ROOTDIR . "/includes/class_themeManager.php");

$validfields = array('id','start','limit','action','submit','confirm','url','reinstall','dir','cancel');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if (!is_numeric($start) or $start < 0) $start = 0;
if (!is_numeric($limit) or $limit < 0) $limit = 1000;
$sort = 'name';
$order = 'asc';

$_order = array(
	'start'	=> $start,
	'limit'	=> $limit,
	'order' => $order, 
	'sort'	=> $sort
);

$theme_dir = $ps->conf['theme']['template_dir'];

// make sure the server environment will allow themes to be installed 
$allow = array();
$allow['url'] = (bool)ini_get('allow_url_fopen');
$allow['write'] = is_writable($theme_dir);

$allow['install'] = ($allow['url'] && $allow['write']);

ini_set('user_agent', "PsychoStats Theme Installer");
$newtheme = new PsychoThemeManager($ps);

if ($cancel) {
	$submit = false;
}

if ($reinstall and $dir) {			// reinstall a local theme already on the hard drive
	$dir = basename($dir);			// remove any potentially malicous paths
	if (file_exists(catfile($newtheme->template_dir, $dir))) {
		$t = $newtheme->reinstall($dir);
		if ($t) {
			$message = $cms->message('success', array( 
				'message_title'	=> "Theme reinstalled successfully!",
				'message'	=> "Theme \"" . $t->xml_title() . "\" was installed successfully and is now available for use."
			));
		} else {
			$message = $cms->message('theme-failure', array( 
				'message_title'	=> "Error reinstalling theme",
				'message'	=> $newtheme->error()
			));
		}
	} else {
		$message = $cms->message('theme-failure', array( 
			'message_title'	=> "Error installing theme",
			'message'	=> "Theme not found!"
		));
	}
} elseif ($submit and $url and $allow['install']) {	// attempt to install new theme if one is submitted
	$newtheme->load_theme($url);
	if ($newtheme->error()) {
		$submit = false;
		if ($newtheme->code() != PSTHEME_ERR_XML) {
			$message = $cms->message('theme-failure', array( 
				'message_title'	=> "Error installing theme",
				'message'	=> $newtheme->error()
			));
		} else {
			$message = $cms->message('theme-failure', array( 
				'url' => $url,
				'invalid' => $newtheme->invalid_list()
			));
		}
	}

	// if there are no errors and 'confirm' was specified then install the theme
	if ($submit and $confirm) {
		if ($newtheme->install()) {
			$message = $cms->message('success', array( 
				'message_title'	=> "Theme installed successfully!",
				'message'	=> "Theme \"" . $newtheme->xml_title() . "\" was installed successfully and is now available for use."
			));
		} else { // theme did not install properly
			$message = $cms->message('failure', array( 
				'message_title'	=> "Error installing theme",
				'message'	=> $newtheme->error()
			));
		}
	}
} else {
	$submit = false;
}

// do something with an installed theme
if (!empty($action)) $action = strtolower($action);
if ($id and in_array($action, array('default','disable','enable','uninstall'))) {
	$t = new PsychoThemeManager($ps);
	if (!$t->load_theme_db($id)) {
		previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'themes.php' )));			
	}

	$res = 'success';
	$msg = '';
	$title = $cms->trans("Operation was successful!");
	if ($action == 'default')  {
		if ($ps->conf['main']['theme'] != $t->xml_name()) {	// don't do it if the current default matches the name
			$ok = $ps->db->query("UPDATE $ps->t_config SET value=" . $ps->db->escape($t->xml_name(), true) . " WHERE conftype='main' AND (section='' OR ISNULL(section)) AND var='theme'");
			if ($ok) {
				$msg = $cms->trans("Theme '%s' is now the default theme", $t->xml_name());
				$ps->conf['main']['theme'] = $t->xml_name();
				// always make sure the new default theme is enabled
				if (!$t->enabled()) {
					$ps->db->update($ps->t_config_themes, array( 'enabled' => 1 ), 'name', $t->xml_name());
				}
			} else {
				$res = 'failure';
				$msg = $cms->trans("Error writting to database: %s", $ps->db->errstr);
			}
		}

	} elseif ($action == 'uninstall') {
		// do not uninstall the current theme, or any theme named 'default'
		if ($ps->conf['main']['theme'] == $t->xml_name() or $t->xml_name() == 'default') {
			$res = 'failure';
			$msg = $cms->trans("You can not uninstall the default or currently active theme!");
		} else {
			if (!$t->uninstall()) {
				$res = 'failure';
				$msg = $cms->trans("Error writting to database: %s", $ps->db->errstr);
			} else {
				$msg = $cms->trans("Theme '%s' was uninstalled successfully (note: directory was not deleted)", $t->xml_title());
			}
		}
	} else {
		$enabled = ($action == 'enable') ? 1 : 0;
		if ($ps->conf['main']['theme'] == $t->xml_name() and !$enabled) {
			$res = 'failure';
			$title = $cms->trans("Operation Failed!");
			$msg = $cms->trans('You can not disable the active theme');
		} elseif ($t->enabled() != $enabled) {
			if ($t->toggle($enabled)) {
				$msg = $enabled ? $cms->trans("Theme '%s' was enabled", $t->xml_name()) 
						: $cms->trans("Theme '%s' was disabled", $t->xml_name());
			} else {
				$res = 'failure';
				$msg = $cms->trans("Error writting to database: %s", $ps->db->errstr);
			}
		}
	}

	if ($msg) $message = $cms->message($res, array(
		'message_title'	=> $title,
		'message'	=> $msg
	));
}

// load the themes
$list = $ps->db->fetch_rows(1, "SELECT * FROM $ps->t_config_themes " . $ps->getsortorder($_order));
$total = $ps->db->count($ps->t_config_themes);
$themes = array();
foreach ($list as $t) {
	if ($t['parent']) {
		$themes[ $t['parent'] ]['children'][] = $t;
	} else {
		$themes[ $t['name'] ] = $t;
		$themes[ $t['name'] ]['children'] = array();
	}
}

$pager = pagination(array(
	'baseurl'	=> ps_url_wrapper(array('sort' => $sort, 'order' => $order, 'limit' => $limit)),
	'total'		=> $total,
	'start'		=> $start,
	'perpage'	=> $limit, 
	'pergroup'	=> 5,
	'separator'	=> ' ', 
	'force_prev_next' => true,
	'next'		=> $cms->trans("Next"),
	'prev'		=> $cms->trans("Previous"),
));

$cms->crumb("Themes", ps_url_wrapper());

// assign variables to the theme
$cms->theme->assign(array(
	'url'		=> $url,
	'allow'		=> $allow,
	'newtheme'	=> $newtheme->theme_xml(),
	'theme_dirs'	=> $newtheme->theme_dirs(),
	'themes'	=> $themes,
	'total_themes'	=> $total,
	'submit'	=> $submit,
	'confirm'	=> $confirm,
	'page'		=> basename(__FILE__, '.php'), 
	'child'		=> null, 
));

// display the output
$cms->theme->add_css('css/forms.css');
$cms->theme->add_js('js/themes.js');
$cms->theme->add_js('js/message.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
