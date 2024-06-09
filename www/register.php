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
 *	Version: $Id: register.php 450 2008-05-20 11:34:52Z lifo $
 */

define("PSYCHOSTATS_PAGE", true);
$basename = basename(__FILE__, '.php');
include(__DIR__ . "/includes/common.php");
$cms->theme->page_title('Player Registration—PsychoStats');

// Is PsychoStats in maintenance mode?
$maintenance = $ps->conf['main']['maintenance_mode']['enable'];

// Page cannot be viewed if the site is in maintenance mode.
if ($maintenance and !$cms->user->is_admin()) previouspage('index.php');

$validfields = array('submit','cancel','ref');
$_GET['ref'] = htmlspecialchars($_GET['ref'] ?? null); //XSS Fix. Thanks to JS2007
$cms->theme->assign_request_vars($validfields, true);

//if ($cancel or $cms->user->logged_in()) previouspage('index.php');
if ($cancel) previouspage('index.php');

// If you are on this page $cookieconsent is assumed to be true.
$cms->session->options['cookieconsent'] = true;
$cookieconsent = $cms->session->options['cookieconsent'];

switch ($ps->conf['main']['uniqueid']) {
	case 'worldid': $uniqueid_label = $cms->trans("Steam ID"); break;
	case 'name': 	$uniqueid_label = $cms->trans("Name"); break;
	case 'ipaddr': 	$uniqueid_label = $cms->trans("IP Address"); break;
};

$form = $cms->new_form();
$form->default_modifier('trim');
$form->field('uniqueid', 'blank');
$form->field('username', 'blank');
$form->field('password', 'blank,password_match');
$form->field('password2', 'blank');
$form->field('email', 'blank, email');

if ($submit) {
	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	if ($ps->conf['main']['registration'] == 'closed') {
		$form->error('fatal', $cms->trans("Player registration is currently disabled!"));
	}

	$u =& $cms->new_user();

	$id = $input['uniqueid'];
	$plr = array();
	// lookup the worldid/uniqueid ... 
	if ($input['uniqueid'] != '') {
		if ($ps->conf['main']['uniqueid'] == 'ipaddr') {
			$id = sprintf("%u", ip2long($id));
		}
		$plr = $ps->get_player_profile($id, 'uniqueid');
		if (!$plr) {
			$form->error('uniqueid', $cms->trans("The %s does not exist!", $uniqueid_label));
		} elseif ($plr['userid']) {
			$form->error('uniqueid', $cms->trans("This player is already registered!"));
		}

		if ($u->username_exists($input['username'])) {
			$form->error('username', $cms->trans("Username already exists!"));
		}
	}

	$valid = ($valid and !$form->has_errors());
	if ($valid) {
		$userinfo = $input;
		// email is saved to profile, not user
		unset($userinfo['uniqueid'], $userinfo['password2'], $userinfo['email']);

		$userinfo['userid'] = $u->next_userid();
		$userinfo['password'] = $u->hash($userinfo['password']);
		$userinfo['accesslevel'] = $u->acl_user();
		$userinfo['confirmed'] = $ps->conf['main']['registration'] == 'open' ? 1 : 0;

		$ps->db->begin();
		$ok = $u->insert_user($userinfo);
		if ($ok) {
			$ok = $ps->db->update($ps->t_plr_profile, 
				array( 'userid' => $userinfo['userid'], 'email' => $input['email'] ? $input['email'] : null), 
				'uniqueid', $id
			);
			if (!$ok) $form->error('fatal', $cms->trans("Error updating player profile: " . $ps->db->errstr));
		} else {
			$form->error('fatal', $cms->trans("Error creating user: " . $u->db->errstr));
		}

		if ($ok and !$form->has_errors()) {
			$ps->db->commit();

			// load this player
			$plr = $ps->get_player($plr['plrid'], true);
			$cms->theme->assign(array(
				'maintenance'	=> $maintenance,
				'plr'	=> $plr,
				'reg'	=> $userinfo,
				'form_key'		=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
				'cookieconsent'	=> $cookieconsent,
			));

			// if registration is open log the user in
			if ($ps->conf['main']['registration'] == 'open') {
				$cms->session->online_status(1, $userinfo['userid']);
			}
	
			// display the registration confirmation
			$basename = $basename . '_confirmation';
			$cms->theme->add_css('css/forms.css');
			$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');
			exit;
		} else {
			$ps->db->rollback();
		}
	}

} else {
	if ($ps->conf['main']['uniqueid'] == 'ipaddr') {
		$form->set('uniqueid', remote_addr());
	}

}

if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

// assign variables to the theme
$cms->theme->assign(array(
	'maintenance'		=> $maintenance,
//	'plr'				=> $ps->get_player(6375, true),
	'errors'			=> $form->errors(),
	'form'				=> $form->values(),
	'uniqueid_label'	=> $uniqueid_label,
	'form_key'			=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
	'cookieconsent'		=> $cookieconsent,
));

// display the output
$cms->theme->add_css('css/forms.css');
$cms->theme->add_js('js/forms.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

// validator functions --------------------------------------------------------------------------

function password_match($var, $value, &$form) {
	global $valid, $cms, $ps;
	if (!empty($value)) {
		if ($value != $form->input['password2']) {
			$valid = false;
			$form->error($var, $cms->trans("Passwords do not match"));
			$form->error('password2', $cms->trans("Passwords do not match"));
		}
	}
	return $valid;
}

?>
