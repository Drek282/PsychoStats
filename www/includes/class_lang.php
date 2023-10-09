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
 *	Version: $Id: class_lang.php 506 2008-07-02 14:29:49Z lifo $
 */

/*
	class_lang.php

	PsychoLanguage base class.
	All theme language classes must extend this class for basic functionality.
*/
if (!defined("PSYCHOSTATS_PAGE")) die("Unauthorized access to " . basename(__FILE__));

class PsychoLanguage {
// array that holds all language mappings (from english to something else)
var $map = array();

// Constructor
function __construct() { 
	$this->map = array(
		// language map would go here (in sub-classes)
	);
}
 
function PsychoLanguage() {
        self::__construct();
}

/** 
 * @return A translated string, or the original string if no mapping is available.
 * @param string $str String to translate. May contain sprintf format characters.
 * @param array $args optional array of sprintf variables to interpolate into the string.
 **/
function gettrans($str = null, $args = array()) {
	if (isset($this->map[$str])) {
		if ($this->map[$str] != '') {
			$str = $this->map[$str];
		}
	} elseif (method_exists($this, $str) and preg_match('/^[A-Z_]+[A-Z_0-9]+$/', $str)) {
		// only allow method calls if the string only contains UPPER, NUMERIC and _ characters
		$str = $this->$str($str);
	}

	if (count($args)) {
		$str = vsprintf($str, $args);
	} 
	return $str;
}

// serialize the language map and return it as a large string.
// This is used for the pslang.pl interface to dump all available language strings.
function serialize() {
	$map = $this->map;

	// get all translation methods within the object
	$methods = get_class_methods($this);
	if (is_array($methods)) {
		foreach ($methods as $method) {
			$method = strtoupper($method);	// PHP4 lowercases all methods... stupid.
			if (
				preg_match('/^[A-Z_]+$/', $method) and 			// only match FUNC_NAME
				substr($method,0,10) != 'PSYCHOLANG' and		// do not match PSYCHOLANG*
				!in_array($method, array('GETTEXT','SERIALIZE'))	// ignore some non-translation methods
			) {
				$map[$method] = $this->$method();
			}
		}
	}
	print serialize($map);
}

} // END: class PsychoLanguage

?>
