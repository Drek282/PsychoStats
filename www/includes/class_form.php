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
			if (isset($this->fields[$name]['e'])) $this->error($name,$this->fields[$name]['e']);
			break;
		}
	}	
}

// return a field value modified with any modifiers enabled on it
function modify($name) {
    $this->input[$name] ??= '';
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
	foreach (array_keys($this->fields) as $var) {
        $this->errors[$var] ??= null;
	}
	$this->errors['fatal'] ??= null;
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
        $this->input[$var] ??= null;
		$form[$var] = $this->input[$var];
	}
	return $form;
}

function value($var, $modified = false) {
	return $modified ? $this->modify($var) : $this->input[$var] ?? null;
}

// get/set an error string for a field
function error($name, $err = null) {
	$this->errors[$name] ??= null;
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
		$form->error($var, isset($this->valid_errors['blank']) ? $this->valid_errors['blank'] : "Field can not be blank");
		return false;
	}
	return true;
}

// Validator: returns true if the value is numeric (or if it's blank)
function val_numeric($var, $value, &$form) {
	if (!is_numeric($value) and $value != '') {
		$form->error($var, isset($this->valid_errors['numeric']) ? $this->valid_errors['numeric'] : "Field must be a number");
		return false;
	} 
	return true;
}

// Validator: returns true if the value is a positive number
function val_positive($var, $value, &$form) {
	if ($value != '' and (!is_numeric($value) or $value < 0)) {
		$form->error($var, isset($this->valid_errors['positive']) ? $this->valid_errors['positive'] : "Field must be a positive number");
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
			$form->error($var, isset($this->valid_errors['email']) ? $this->valid_errors['email'] : "Field must be a valid email address");
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
			$form->error($var, isset($this->valid_errors['ymd']) ? $this->valid_errors['ymd'] : "Field must be a valid date (YYYY-MM-DD)");
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
			$form->error($var, isset($this->valid_errors['strtotime']) ? $this->valid_errors['strtotime'] : "Field must be a valid date (YYYY-MM-DD)");
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
			$form->error($var, isset($this->valid_errors['hostname']) ? $this->valid_errors['hostname'] : "Unknown hostname or IP Address");
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
			"Your session has expired." .
			"Please try again."
		);
		// assign the session a new SID
		$session->delete_session();
		$session->sid($session->generate_sid());
		$session->send_cookie($session->sid());
		$session->key('');
	}
	return $valid;
}

} // end of PsychoForm

?>
