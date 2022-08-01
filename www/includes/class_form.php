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
 *	Version: $Id: class_form.php 367 2008-03-17 17:47:45Z lifo $
 */

/***
	PsychoStats FORM class (used with CMS class)
	First conceived on May 2nd, 2007 by Stormtrooper

	Form processing and validation. This class tries to wrap all form logic	in 
	an easy to use API to help simplify error checking when processing user forms.

***/

if (defined("CLASS_PSYCHOFORM_PHP")) return 1;
define("CLASS_PSYCHOFORM_PHP", 1);

class PsychoForm {
var $input = array();
var $fields = array();
var $errors = array();
var $modifiers = array();	// default modifiers for all fields
var $validators = array();	// default validators ...
var $valid_errors = array();	// default error strings for validators
var $validated = false;

function __construct($input, $do_strip = false) {
	$this->input($input, $do_strip);
}

function PsychoForm($input, $do_strip = false) {
    self::__construct($input, $do_strip);
}

// adds a new field to be validated. Call this once for each field on the form.
function field($name, $validator = null, $modifier = null, $error = '') {
// allow fields to be overridden ... 
//	if (array_key_exists($name, $this->fields)) return false;

	$this->fields[$name] = array( 'm' => array(), 'v' => array() );

	if (!empty($validator)) {
		$list = array_map('trim', explode(',', $validator));
		foreach ($list as $v) {
			$this->add_validator($name, $v);
		}
	}

	if (!empty($modifier)) {
		$list = array_map('trim', explode(',', $modifier));
		foreach ($list as $m) {
			$this->add_modifier($name, $m);
		}
	}

	return true;
}

// validate 1 or more fields (or all fields if no $name is given).
// $name can be an array of field names or a string name.
// returns true if there are no errors
function validate($name = null) {
	if ($this->validated) return count($this->errors) ? false : true;
	$fields = ($name === null) ? array_keys($this->fields) : (array)$name;
	foreach ($fields as $f) {
		if (empty($f)) continue;
		$this->check($f);
	}
	$this->validated = true;
	return count($this->errors) ? false : true;
}

// check the field for errors. returns true/false on outcome and sets $errors accordingly
function check($name) {
	if (!array_key_exists($name, $this->fields)) return true;
	$value = $this->modify($name);
	$this->input[$name] = $value;

	$validators = array_merge($this->validators, $this->fields[$name]['v']);
	foreach ($validators as $func => $args) {
		$ok = true;
		array_unshift($args, $name, $value);
		$args[] = &$this;
		if (substr($func, 0, 1) == '!') {
			$func = substr($func, 1);
			$ok = call_user_func_array(array(&$this, $func), $args);
		} else {
			$ok = call_user_func_array($func, $args);
		}

		if (!$ok) {
			if ($this->fields[$name]['e']) $this->error($name,$this->fields[$name]['e']);
			break;
		}
	}	
}

// return a field value modified with any modifiers enabled on it
function modify($name) {
	$value = $this->input[$name];
	$modifiers = array_merge($this->modifiers, $this->fields[$name]['m']);
	foreach ($modifiers as $func => $args) {
		array_unshift($args, $value);
		if (substr($func, 0, 1) == '!') {
			$func = substr($func, 1);
			$value = call_user_func_array(array(&$this, $func), $args);
		} else {
			$value = call_user_func_array($func, $args);
		}
	}
	return $value;
}

// adds a validator for a field, if the function specified does not exist then nothing is added.
function add_validator($name, $v) {
	$args = explode(':', $v);
	$func = array_shift($args);
	// check if a method in the current object matches first
	if (method_exists($this, 'val_' . $func)) {
		$this->fields[$name]['v']['!val_'.$func] = $args;
	} elseif (function_exists($func)) {
		$this->fields[$name]['v'][$func] = $args;
	}
}

// adds a modifier for a field, if the function specified does not exist then nothing is added.
function add_modifier($name, $m) {
	$args = explode(':', $m);
	$func = array_shift($args);
	// check if a method in the current object matches first
	if (method_exists($this, 'mod_' . $func)) {
		$this->fields[$name]['m']['!mod_'.$func] = $args;
	} elseif (function_exists($func)) {
		$this->fields[$name]['m'][$func] = $args;
	} else {
		return false;
	}
	return true;
}

// adds a modifier for all fields (ie: 'trim')
function default_modifier($m) {
	$this->add_modifier('', $m);
	if (is_array($this->fields['']['m'])) $this->modifiers = $this->fields['']['m'];
}

// sets a default validator for all fields (ie: 'blank')
// an optional error message can be given as well.
function default_validator($v, $e = null) {
	$this->add_validator('', $v);
	if (is_array($this->fields['']['v'])) {
		$this->validators = $this->fields['']['v'];
		if ($e !== null) {
			$this->valid_errors[$v] = $e;
		}
	}
}

// returns all errors
function errors() {
    $this->errors['1'] = $this->errors['1'] ?? null;
    $this->errors['95'] = $this->errors['95'] ?? null;
    $this->errors['97'] = $this->errors['97'] ?? null;
    $this->errors['98'] = $this->errors['98'] ?? null;
    $this->errors['99'] = $this->errors['99'] ?? null;
    $this->errors['100'] = $this->errors['100'] ?? null;
    $this->errors['101'] = $this->errors['101'] ?? null;
    $this->errors['102'] = $this->errors['102'] ?? null;
    $this->errors['103'] = $this->errors['103'] ?? null;
    $this->errors['104'] = $this->errors['104'] ?? null;
    $this->errors['105'] = $this->errors['105'] ?? null;
    $this->errors['106'] = $this->errors['106'] ?? null;
    $this->errors['107'] = $this->errors['107'] ?? null;
    $this->errors['108'] = $this->errors['108'] ?? null;
    $this->errors['109'] = $this->errors['109'] ?? null;
    $this->errors['110'] = $this->errors['110'] ?? null;
    $this->errors['111'] = $this->errors['111'] ?? null;
    $this->errors['112'] = $this->errors['112'] ?? null;
    $this->errors['113'] = $this->errors['113'] ?? null;
    $this->errors['114'] = $this->errors['114'] ?? null;
    $this->errors['115'] = $this->errors['115'] ?? null;
    $this->errors['116'] = $this->errors['116'] ?? null;
    $this->errors['117'] = $this->errors['117'] ?? null;
    $this->errors['118'] = $this->errors['118'] ?? null;
    $this->errors['119'] = $this->errors['119'] ?? null;
    $this->errors['120'] = $this->errors['120'] ?? null;
    $this->errors['121'] = $this->errors['121'] ?? null;
    $this->errors['300'] = $this->errors['300'] ?? null;
    $this->errors['301'] = $this->errors['301'] ?? null;
    $this->errors['302'] = $this->errors['302'] ?? null;
    $this->errors['302'] = $this->errors['302'] ?? null;
    $this->errors['391'] = $this->errors['391'] ?? null;
    $this->errors['393'] = $this->errors['393'] ?? null;
    $this->errors['394'] = $this->errors['394'] ?? null;
    $this->errors['395'] = $this->errors['395'] ?? null;
    $this->errors['396'] = $this->errors['396'] ?? null;
    $this->errors['397'] = $this->errors['397'] ?? null;
    $this->errors['398'] = $this->errors['398'] ?? null;
    $this->errors['399'] = $this->errors['399'] ?? null;
    $this->errors['400'] = $this->errors['400'] ?? null;
    $this->errors['401'] = $this->errors['401'] ?? null;
    $this->errors['402'] = $this->errors['402'] ?? null;
    $this->errors['405'] = $this->errors['405'] ?? null;
    $this->errors['406'] = $this->errors['406'] ?? null;
    $this->errors['407'] = $this->errors['407'] ?? null;
    $this->errors['408'] = $this->errors['408'] ?? null;
    $this->errors['1000'] = $this->errors['1000'] ?? null;
    $this->errors['1001'] = $this->errors['1001'] ?? null;
    $this->errors['1002'] = $this->errors['1002'] ?? null;
    $this->errors['1003'] = $this->errors['1003'] ?? null;
    $this->errors['1004'] = $this->errors['1004'] ?? null;
    $this->errors['1005'] = $this->errors['1005'] ?? null;
    $this->errors['2514'] = $this->errors['2514'] ?? null;
    $this->errors['2515'] = $this->errors['2515'] ?? null;
    $this->errors['2516'] = $this->errors['2516'] ?? null;
    $this->errors['2517'] = $this->errors['2517'] ?? null;
    $this->errors['2518'] = $this->errors['2518'] ?? null;
    $this->errors['2520'] = $this->errors['2520'] ?? null;
    $this->errors['2521'] = $this->errors['2521'] ?? null;
    $this->errors['2525'] = $this->errors['2525'] ?? null;
    $this->errors['2526'] = $this->errors['2526'] ?? null;
    $this->errors['2527'] = $this->errors['2527'] ?? null;
    $this->errors['2528'] = $this->errors['2528'] ?? null;
    $this->errors['2529'] = $this->errors['2529'] ?? null;
    $this->errors['2530'] = $this->errors['2530'] ?? null;
    $this->errors['2535'] = $this->errors['2535'] ?? null;
    $this->errors['2545'] = $this->errors['2545'] ?? null;
    $this->errors['2542'] = $this->errors['2542'] ?? null;
    $this->errors['2543'] = $this->errors['2543'] ?? null;
    $this->errors['2544'] = $this->errors['2544'] ?? null;
    $this->errors['2545'] = $this->errors['2545'] ?? null;
    $this->errors['2546'] = $this->errors['2546'] ?? null;
    $this->errors['2547'] = $this->errors['2547'] ?? null;
    $this->errors['2548'] = $this->errors['2548'] ?? null;
    $this->errors['2552'] = $this->errors['2552'] ?? null;
    $this->errors['2556'] = $this->errors['2556'] ?? null;
    $this->errors['2557'] = $this->errors['2557'] ?? null;
    $this->errors['2563'] = $this->errors['2563'] ?? null;
    $this->errors['2565'] = $this->errors['2565'] ?? null;
    $this->errors['2566'] = $this->errors['2566'] ?? null;
    $this->errors['2567'] = $this->errors['2567'] ?? null;
    $this->errors['2568'] = $this->errors['2568'] ?? null;
    $this->errors['2569'] = $this->errors['2569'] ?? null;
    $this->errors['2572'] = $this->errors['2572'] ?? null;
    $this->errors['2583'] = $this->errors['2583'] ?? null;
    $this->errors['2584'] = $this->errors['2584'] ?? null;
    $this->errors['2585'] = $this->errors['2585'] ?? null;
    $this->errors['2586'] = $this->errors['2586'] ?? null;
    $this->errors['2587'] = $this->errors['2587'] ?? null;
    $this->errors['2588'] = $this->errors['2588'] ?? null;
    $this->errors['2589'] = $this->errors['2589'] ?? null;
    $this->errors['2590'] = $this->errors['2590'] ?? null;
    $this->errors['2591'] = $this->errors['2591'] ?? null;
    $this->errors['2592'] = $this->errors['2592'] ?? null;
    $this->errors['2593'] = $this->errors['2593'] ?? null;
    $this->errors['2594'] = $this->errors['2594'] ?? null;
    $this->errors['2595'] = $this->errors['2595'] ?? null;
    $this->errors['2596'] = $this->errors['2596'] ?? null;
    $this->errors['2597'] = $this->errors['2597'] ?? null;
    $this->errors['2598'] = $this->errors['2598'] ?? null;
    $this->errors['2599'] = $this->errors['2599'] ?? null;
    $this->errors['2600'] = $this->errors['2600'] ?? null;
    $this->errors['2601'] = $this->errors['2601'] ?? null;
    $this->errors['2602'] = $this->errors['2602'] ?? null;
    $this->errors['2604'] = $this->errors['2604'] ?? null;
    $this->errors['2605'] = $this->errors['2605'] ?? null;
    $this->errors['2606'] = $this->errors['2606'] ?? null;
    $this->errors['2607'] = $this->errors['2607'] ?? null;
    $this->errors['2608'] = $this->errors['2608'] ?? null;
    $this->errors['2609'] = $this->errors['2609'] ?? null;
    $this->errors['2610'] = $this->errors['2610'] ?? null;
    $this->errors['2611'] = $this->errors['2611'] ?? null;
    $this->errors['2612'] = $this->errors['2612'] ?? null;
    $this->errors['2613'] = $this->errors['2613'] ?? null;
    $this->errors['2614'] = $this->errors['2614'] ?? null;
    $this->errors['2615'] = $this->errors['2615'] ?? null;
    $this->errors['2616'] = $this->errors['2616'] ?? null;
    $this->errors['2617'] = $this->errors['2617'] ?? null;
    $this->errors['2618'] = $this->errors['2618'] ?? null;
    $this->errors['2619'] = $this->errors['2619'] ?? null;
    $this->errors['2620'] = $this->errors['2620'] ?? null;
    $this->errors['2621'] = $this->errors['2621'] ?? null;
    $this->errors['2622'] = $this->errors['2622'] ?? null;
    $this->errors['2623'] = $this->errors['2623'] ?? null;
    $this->errors['2624'] = $this->errors['2624'] ?? null;
    $this->errors['2625'] = $this->errors['2625'] ?? null;
    $this->errors['2626'] = $this->errors['2626'] ?? null;
    $this->errors['2627'] = $this->errors['2627'] ?? null;
    $this->errors['2628'] = $this->errors['2628'] ?? null;
    $this->errors['2629'] = $this->errors['2629'] ?? null;
    $this->errors['2630'] = $this->errors['2630'] ?? null;
    $this->errors['2631'] = $this->errors['2631'] ?? null;
    $this->errors['2700'] = $this->errors['2700'] ?? null;
    $this->errors['2701'] = $this->errors['2701'] ?? null;
    $this->errors['5000'] = $this->errors['5000'] ?? null;
    $this->errors['5001'] = $this->errors['5001'] ?? null;
    $this->errors['5002'] = $this->errors['5002'] ?? null;
    $this->errors['5003'] = $this->errors['5003'] ?? null;
    $this->errors['5004'] = $this->errors['5004'] ?? null;
    $this->errors['5005'] = $this->errors['5005'] ?? null;
    $this->errors['5006'] = $this->errors['5006'] ?? null;
    $this->errors['5007'] = $this->errors['5007'] ?? null;
    $this->errors['5008'] = $this->errors['5008'] ?? null;
    $this->errors['5009'] = $this->errors['5009'] ?? null;
    $this->errors['5010'] = $this->errors['5010'] ?? null;
    $this->errors['5011'] = $this->errors['5011'] ?? null;
    $this->errors['5012'] = $this->errors['5012'] ?? null;
    $this->errors['5013'] = $this->errors['5013'] ?? null;
    $this->errors['5014'] = $this->errors['5014'] ?? null;
    $this->errors['5015'] = $this->errors['5015'] ?? null;
    $this->errors['5016'] = $this->errors['5016'] ?? null;
    $this->errors['6000'] = $this->errors['6000'] ?? null;
    $this->errors['6001'] = $this->errors['6001'] ?? null;
    $this->errors['6002'] = $this->errors['6002'] ?? null;
    $this->errors['6003'] = $this->errors['6003'] ?? null;
    $this->errors['6004'] = $this->errors['6004'] ?? null;
    $this->errors['6005'] = $this->errors['6005'] ?? null;
    $this->errors['6006'] = $this->errors['6006'] ?? null;
    $this->errors['6007'] = $this->errors['6007'] ?? null;
    $this->errors['6008'] = $this->errors['6008'] ?? null;
    $this->errors['6009'] = $this->errors['6009'] ?? null;
    $this->errors['6010'] = $this->errors['6010'] ?? null;
    $this->errors['6011'] = $this->errors['6011'] ?? null;
    $this->errors['6012'] = $this->errors['6012'] ?? null;
    $this->errors['6013'] = $this->errors['6013'] ?? null;
    $this->errors['6014'] = $this->errors['6014'] ?? null;
    $this->errors['6015'] = $this->errors['6015'] ?? null;
    $this->errors['6016'] = $this->errors['6016'] ?? null;
    $this->errors['6017'] = $this->errors['6017'] ?? null;
    $this->errors['6018'] = $this->errors['6018'] ?? null;
    $this->errors['10000'] = $this->errors['10000'] ?? null;
    $this->errors['10001'] = $this->errors['10001'] ?? null;
    $this->errors['10002'] = $this->errors['10002'] ?? null;
    $this->errors['10003'] = $this->errors['10003'] ?? null;
    $this->errors['10100'] = $this->errors['10100'] ?? null;
    $this->errors['10101'] = $this->errors['10101'] ?? null;
    $this->errors['10102'] = $this->errors['10102'] ?? null;
    $this->errors['10103'] = $this->errors['10103'] ?? null;
    $this->errors['10104'] = $this->errors['10104'] ?? null;
    $this->errors['10105'] = $this->errors['10105'] ?? null;
    $this->errors['10106'] = $this->errors['10106'] ?? null;
    $this->errors['10107'] = $this->errors['10107'] ?? null;
    $this->errors['10108'] = $this->errors['10108'] ?? null;
    $this->errors['10109'] = $this->errors['10109'] ?? null;
    $this->errors['10110'] = $this->errors['10110'] ?? null;
    $this->errors['10111'] = $this->errors['10111'] ?? null;
    $this->errors['10112'] = $this->errors['10112'] ?? null;
    $this->errors['10113'] = $this->errors['10113'] ?? null;
    $this->errors['10114'] = $this->errors['10114'] ?? null;
    $this->errors['10115'] = $this->errors['10115'] ?? null;
    $this->errors['10116'] = $this->errors['10116'] ?? null;
    $this->errors['10117'] = $this->errors['10117'] ?? null;
    $this->errors['10118'] = $this->errors['10118'] ?? null;
    $this->errors['10119'] = $this->errors['10119'] ?? null;
    $this->errors['10120'] = $this->errors['10120'] ?? null;
    $this->errors['10200'] = $this->errors['10200'] ?? null;
    $this->errors['10201'] = $this->errors['10201'] ?? null;
    $this->errors['10202'] = $this->errors['10202'] ?? null;
    $this->errors['10203'] = $this->errors['10203'] ?? null;
    $this->errors['10204'] = $this->errors['10204'] ?? null;
    $this->errors['10205'] = $this->errors['10205'] ?? null;
    $this->errors['10206'] = $this->errors['10206'] ?? null;
    $this->errors['10207'] = $this->errors['10207'] ?? null;
    $this->errors['10208'] = $this->errors['10208'] ?? null;
    $this->errors['10209'] = $this->errors['10209'] ?? null;
    $this->errors['10210'] = $this->errors['10210'] ?? null;
    $this->errors['10211'] = $this->errors['10211'] ?? null;
    $this->errors['10212'] = $this->errors['10212'] ?? null;
    $this->errors['10213'] = $this->errors['10213'] ?? null;
    $this->errors['10214'] = $this->errors['10214'] ?? null;
    $this->errors['10215'] = $this->errors['10215'] ?? null;
    $this->errors['10216'] = $this->errors['10216'] ?? null;
    $this->errors['10217'] = $this->errors['10217'] ?? null;
    $this->errors['10218'] = $this->errors['10218'] ?? null;
    $this->errors['10219'] = $this->errors['10219'] ?? null;
    $this->errors['10220'] = $this->errors['10220'] ?? null;
    $this->errors['10221'] = $this->errors['10221'] ?? null;
    $this->errors['10222'] = $this->errors['10222'] ?? null;
    $this->errors['fatal'] = $this->errors['fatal'] ?? null;
    $this->errors['username'] = $this->errors['username'] ?? null;
    $this->errors['password'] = $this->errors['password'] ?? null;
	return $this->errors;
}

// returns true if there are errors present in the current form.
// only call after validate().
function has_errors() {
	return count($this->errors) ? true : false;
}

// returns a key=>value array for all current values in the form
// (after modifiers are applied)
function values() {
	$form = array();
	foreach (array_keys($this->fields) as $var) {
		if (empty($var)) continue;
//		$form[$var] = $this->modify($var);
        $this->input[$var] = $this->input[$var] ?? null;
		$form[$var] = $this->input[$var];
	}
	return $form;
}

function value($var, $modified = false) {
	return $modified ? $this->modify($var) : $this->input[$var];
}

// get/set an error string for a field
function error($name, $err = null) {
	if ($err !== null) {
		$this->errors[$name] = $err;
	} else {
		return $this->errors[$name];
	}
}

// set an input field to the value specified
function set($var, $value) {
	$this->input[$var] = $value;
}

// removes the variable from the form (and all associated validators and modifiers
function clear($var) {
	unset($this->input[$var]);
	unset($this->fields[$var]);
}


// sets the input array of form data
function input($input, $do_strip = false) {
	if ($do_strip) {
		$this->input = array_map_recursive('stripslashes', $this->input);
	} else {
		$this->input = $input;
	}
}

// reset the validation state (rarely useful)
function reset($input = null, $do_strip = false) {
	$this->errors = array();
	$this->validated = false;
	if ($intput !== null) {
		$this->input($input, $do_strip);
	}
}

// Validator: returns true if the value is NOT blank
function val_blank($var, $value, &$form) {
	if ($value == '') {
		$form->error($var, $this->valid_errors['blank'] ? $this->valid_errors['blank'] : "Field can not be blank");
		return false;
	}
	return true;
}

// Validator: returns true if the value is numeric (or if it's blank)
function val_numeric($var, $value, &$form) {
	if (!is_numeric($value) and $value != '') {
		$form->error($var, $this->valid_errors['numeric'] ? $this->valid_errors['numeric'] : "Field must be a number");
		return false;
	} 
	return true;
}

// Validator: returns true if the value is a positive number
function val_positive($var, $value, &$form) {
	if ($value != '' and (!is_numeric($value) or $value < 0)) {
		$form->error($var, $this->valid_errors['positive'] ? $this->valid_errors['positive'] : "Field must be a positive number");
		return false;
	} 
	return true;
}

// Validator: returns true if the value is (losely) an email address (or if it's blank)
function val_email($var, $value, &$form) {
	if (!empty($value)) {
//		$pattern = '/^([a-zA-Z0-9])+([a-zA-Z0-9\._-])*@([a-zA-Z0-9_-])+([a-zA-Z0-9\._-]+)+$/';
		$pattern = '/^(.+)\@(.+\..+)$/';
		if (!preg_match($pattern, $value)) {
			$form->error($var, $this->valid_errors['email'] ? $this->valid_errors['email'] : "Field must be a valid email address");
			return false;
		}
	}
	return true;
}

// Validator: returns true if the value is a date in YYYY-MM-DD format, with valid ranges.
function val_ymd($var, $value, &$form) {
	if (!empty($value)) {
		list($year,$mon,$day) = explode('-',$value, 3);
		if (!(preg_match("/^\d+$/", $mon) and $mon > 0 and $mon < 13)) $err = 1;
		if (!(preg_match("/^\d+$/", $day) and $day > 0 and $day < 32)) $err = 1;
		if (!(preg_match("/^\d+$/", $year) and $year > 1900 and $year < (date('Y')-10))) $err = 1;
		if ($err) {
			$form->error($var, $this->valid_errors['ymd'] ? $this->valid_errors['ymd'] : "Field must be a valid date (YYYY-MM-DD)");
			return false;
		}
	} 
	return true;
}

// Validator: returns true if the value is a valid english date (as defined by strtotime)
function val_strtotime($var, $value, &$form) {
	if (!empty($value)) {
		$time = strtotime($value);
		if ($time === false or $time == -1) {
			$form->error($var, $this->valid_errors['strtotime'] ? $this->valid_errors['strtotime'] : "Field must be a valid date (YYYY-MM-DD)");
			return false;
		}
	}
	return true;
}

// Validator: returns true if the value is a valid hostname (or IP address)
function val_hostname($var, $value, &$form) {
	if (!empty($value)) {
		$ip = gethostbyname($value);
		if ($ip != $value) return true;		// hostname was resolved successfully
		if (!preg_match('/^(\\d{1,3}\\.){3}\\d{1,3}$/', $value)) {
			$form->error($var, $this->valid_errors['hostname'] ? $this->valid_errors['hostname'] : "Unknown hostname or IP Address");
			return false;
		}
	}
	return true;
}

// return a new randomly generated key for use in the form.
// this should be saved in the session table and the web form.
// before processing a form submit verify the session key matches the key found in the form.
// this will help prevent CSRF (Cross-Site Request Forgeries) attacks.
function key() {
	return md5(uniqid(rand(), true));
}

// returns true if the key found in the form matches the one passed in (from a session)
// form keys are NOT checked automatically by this form class. You must do this yourself.
// if $error is true then a fatal error will be automatically triggered on the form if invalid.
function key_is_valid(&$session, $key_name = 'key', $error = true) {
	$key = array_key_exists($key_name, $this->input) ? $this->input[$key_name] : null;
	$valid = $session->verify_key($key);
	if ($error and !$valid) {
		$this->error('fatal', 
			"Invalid key token! Using the 'refresh' button in your browser or waiting too long to submit " .
			"a request can cause problems on these forms. " . 
			"If you continue to encounter problems then go back to the previous page and try again."
		);
	}
	return $valid;
}

} // end of PsychoForm

?>
