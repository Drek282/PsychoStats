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
 *	Version: $Id: common.php 539 2008-08-15 19:24:26Z lifo $
 */
if (!defined("PSYCHOSTATS_ADMIN_PAGE")) die("Unauthorized access to " . basename(__FILE__));

// ADMIN pages need to setup the theme a little differently than the others
$opts = array( 
	'theme_default'	=> 'acp',
	'theme_opt'	=> 'admin_theme',
	'force_theme'	=> true,
	'in_db' 	=> false,				// the admin theme is not in the database
	'template_dir' 	=> __DIR__ . '/themes', 	// force the admin theme here
	'theme_url'	=> 'themes',				// force the url here too
	'compile_id' 	=> 'admin' 				// set an id for admin pages
);
$opts = array_merge($ps->conf['theme'], $opts);

// At all costs the admin page should never break due to file permissions. If
// the compile directory is not writable we fallback to not saving compiled
// themes to disk which is slower. But shouldn't be a big problem since only a
// single person is usually accessing the admin page.
if ($opts['fetch_compile'] and !is_writable($opts['compile_dir'])) {
	$opts['fetch_compile'] = false;
}

$cms->init_theme('acp', $opts);
$ps->theme_setup($cms->theme);

$cms->crumb('Stats', dirname(dirname(SAFE_PHP_SCNM)) . '/');
$cms->crumb('Admin', 'index.php');

$file = basename($_SERVER['SCRIPT_NAME'], '.php');
if (!$cms->user->admin_logged_in()) {
	if (!defined("PSYCHOSTATS_LOGIN_PAGE")) {
		gotopage(ps_url_wrapper(array('_base' => dirname($_SERVER['SCRIPT_NAME']) . '/login.php', '_ref' => $_SERVER['REQUEST_URI'])));
	}
}

// Set flag if the install directory (go script) is still readable by the
// webserver. Admins need to remove the install directory after installation.
if (is_readable(catfile(dirname(__DIR__), 'install', 'go.php'))) {
	$cms->theme->assign(array(
		'install_dir_insecure' 	=> true,
		'install_dir'		=> catfile(dirname(__DIR__), 'install')
	));
}

?>
