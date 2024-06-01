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
 *	Version: $Id: login.php 389 2008-04-18 15:04:10Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
define("PSYCHOSTATS_LOGIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");
$cms->theme->assign('page', $basename);
$validfields = array('submit','cancel','ref');
$_GET['ref'] = htmlspecialchars($_GET['ref'] ?? null); //XSS Fix. Thanks to JS2007
$cms->theme->assign_request_vars($validfields, true);

// If you are on this page $cookieconsent is assumed to be true.
$cms->session->options['cookieconsent'] = true;
$cookieconsent = $cms->session->options['cookieconsent'];

if ($cancel) {
	gotopage("../index.php");
} elseif ($cms->user->admin_logged_in()) {
	previouspage('index.php');
}

$bad_pw_error = $cms->trans('Invalid username or password');

$form = $cms->new_form();
$form->default_modifier('trim');
$form->default_validator('blank', $cms->trans("This field can not be blank"));
$form->field('username', 'user_exists');
$form->field('password');

if ($submit) {
	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	if ($valid) {
		// attempt to re-authenticate
		$id = $cms->user->auth($input['username'], $input['password']);
		if ($id) {
			// load the authenticated user and override the preivous user for this session
			if ($id != $cms->user->userid()) {
				$_u =& $cms->new_user();
				if (!$_u->load($id)) {
					$form->error('fatal', $cms->trans("Error retreiving user from database") . ":" . $_u->loaderr);
					$valid = false;
				} else {
					$cms->user =& $_u;
				}
			}

			if (!$cms->user->is_admin()) {
				$form->error('fatal', "Insufficient Privileges");
				$ps->errlog(sprintf("Failed admin login attempt for user '%s' (bad privs) from IP [%s]", $input['username'], remote_addr()));
				$valid = false;
			}
		} else { // auth failed
			$form->error('fatal', $bad_pw_error);
			$ps->errlog(sprintf("Failed admin login attempt for user '%s' (bad password) from IP [%s]", $input['username'], remote_addr()));
			$valid = false;
		}
	}

	// If authenetication was valid then we'll set the users admin flag and redirect to the previous page
	if ($valid and !$form->has_errors()) {
		// assign the session a new SID
		$cms->session->delete_session();
		$cms->session->sid($cms->session->generate_sid());
		$cms->session->send_cookie($cms->session->sid());
		$cms->session->key('');
//		header("Cache-Control: no-cache, must-revalidate");
		// enable the session admin flag
		$cms->session->is_admin(1);
		// make sure the user is actually marked online as well
		$cms->session->online_status(1, $cms->user->userid());
		previouspage('index.php');
	}
}

// save a new form key in the users session cookie
// this will also be put into a 'hidden' field in the form
if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

// assign variables to the theme
$cms->theme->assign(array(
	'errors'	=> $form->errors(),
	'form'		=> $form->values(),
	'form_key'	=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
	'cookieconsent'	=> $cookieconsent,
));

// display the output
$cms->theme->add_css('css/forms.css');
$cms->theme->add_js('js/forms.js');
$cms->theme->add_js('js/login.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

// validator functions --------------------------------------------------------------------------

function user_exists($var, $value, &$form) {
	global $cms, $ps, $bad_pw_error;
	if (!$cms->user->username_exists($value)) {
		$ps->errlog(sprintf("Failed login attempt for unknown user '%s' from IP [%s]", $value, remote_addr()));
		$form->error('fatal', $bad_pw_error);
		return false;
	}
	return true;
}

function password_match($var, $value, &$form) {
	global $valid, $cms, $ps;
	if (!empty($value)) {
		if ($value != $form->input['password2']) {
			$valid = false;
			$form->error($var, $cms->trans("Passwords do not match"));
		}
	}
	return $valid;
}

?>
