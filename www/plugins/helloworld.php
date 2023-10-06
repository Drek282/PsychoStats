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
 *	Version: $Id$
 *
 *	"Hello World" plugin.
 *
 *	This is not a useful plugin. It's simply for educational purposes only. 
 *	Use this as a baseline when creating your own plugins for PsychoStats.
 */

// The class name must be the same as the plugin directory or filename.
// all plugins must inherhit PsychoPlugin
class helloworld extends PsychoPlugin {
	var $version = '1.0';
	var $errstr = '';

// called when the plugin is loaded. This is called on every page request.
// You'll want to register all your hooks here.
function load(&$cms) {
	// an example of registering a hook. In this case we register a filter
	// on the 'overall_header' hook. Our class needs a 'filter_overall_header' method
	// that will be called automatically when the hook triggers.
	$cms->register_filter($this, 'overall_header');

	// If loading fails, a plugin should set the error string $errstr and return false
	if ('something broke' and false) {
		$this->errstr = "Error loading plugin";
		return false;
	}

	// return true if everything is loaded ok
	return true;
}

// The install method is called when a plugin is installed by an admin in the ACP. 
// This is only called once. This is a good place to initialize 
// things like database tables, etc.
// This should return an array of metadata that describes your plugin or
// FALSE if the install failed.
function install(&$cms) {
	$info = array();
	$info['version'] = $this->version;
	$info['description'] = "This is an example plugin that does nothing useful. "."View the plugin code to see how to make your own plugins!";
	return $info;
}

// The uninstall method is called when a plugin in UNinstalled by an admin in the ACP.
// this is only called once. This is a good place to remove anything you initialized
// from the install method originally.
function uninstall(&$cms) {
	return true;
}

// our filter hook. This is called automatically when the 'overall_header' hook is 
// triggered. This is a filter which means we're given a reference to a string (or 
// other object). Any changes to the $output will be permanent.
function filter_overall_header(&$output, &$cms, $args = array()) {
//	$output = strtoupper($output);
	$output .= "<b>$this</b> updated the overall header!<br>";
}

} // END of helloworld


?>
