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
 *	Version: $Id: logsources_edit.php 530 2008-08-08 17:53:35Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");
$cms->theme->assign('page', 'logsources');

$protocols = array( 'ftp', 'sftp', 'stream' );

$validfields = array('ref','id','del','submit','cancel','test');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if ($cancel) {
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'logsources.php' )));
}

// load the matching logsource if an ID was given
$log = array();
if (is_numeric($id)) {
	$log = $ps->db->fetch_row(1, "SELECT * FROM $ps->t_config_logsources WHERE id=" . $ps->db->escape($id));
	if (!$log['id']) {
		$data = array(
			'message' => $cms->trans("Invalid Log Source ID Specified"),
		);
		$cms->full_page_err($basename, $data);
		exit();		
	}
} elseif (!empty($id)) {
	$data = array(
		'message' => $cms->trans("Invalid Log Source ID Specified"),
	);
	$cms->full_page_err($basename, $data);
	exit();		
}

// Declarations.
$log['id'] ??= null;
$log['username'] ??= null;

// delete it, if asked to
if ($del and $log['id'] == $id) {
	$ps->db->delete($ps->t_config_logsources, 'id', $id);
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'logsources.php' )));
/*
	$message = $cms->message('success', array(
		'message_title'	=> $cms->trans("Log Source Deleted"),
		'message'	=> $cms->trans("Log source '%s' deleted", $ps->parse_logsource($log))
	));
*/
}

// create the form variables
$form = $cms->new_form();
$form->default_modifier('trim');
$form->field('type', 'val_type');
$form->field('path');
$form->field('host', 'hostname');
$form->field('port', 'numeric');
$form->field('passive', 'numeric');
$form->field('username');
$form->field('blank');				// does not get saved to logsource record
$form->field('password', 'password_match');
$form->field('password2');			// does not get saved ...
$form->field('recursive', 'numeric');
$form->field('depth', 'positive');
$form->field('skiplast', 'numeric');
$form->field('skiplastline', 'numeric');
$form->field('delete', 'numeric');
$form->field('options');
$form->field('defaultmap', 'blank');
$form->field('enabled', 'numeric');
//$form->field('idx');

if ($test and $log['id'] == $id) { 	// test the log source, if asked to
	$test = $form->values();
	$result = 'success';
	$msg = '';
	if ($test['type'] == 'file') {
		// Check if the directory is readable and has logs in it
		if (@is_dir($test['path']) and @is_readable($test['path'])) {
			$msg = $cms->trans("Log source path was found on the local server and is readable!");
		} else {
			$result = 'failure';
			$msg = $cms->trans("Log source path was not found or is not readable! - Please verify the path");
		}
	} elseif ($test['type'] == 'ftp') {
		// Fill in a default log path.
		if (empty($test['path'])) $test['path'] = $log['path'] ?? './';

		// Check if we can connect to the FTP server and fetch a list of logs
		if (!function_exists('ftp_connect')) {
			$result = 'failure';
			$msg = $cms->trans("FTP support not available in this installation of PHP") . "<br/>\n" . 
				$cms->trans("See %s for more information", "<a href='http://php.net/ftp'>http://php.net/ftp</a>");
		} else {
			if (!isset($test['password']) or empty($test['password'])) $test['password'] = $log['password'] ?? null;
			$ftp = @ftp_connect($test['host'], $test['port'] ? $test['port'] : 21);
			$res = @ftp_login($ftp, $test['username'], $test['password']);
			$result = 'failure';
			if (!$res) {
				$msg = $cms->trans("Unable to connect to ftp://%s@%s<br/>\n", $test['username'], $test['host']);
				$msg .= !$ftp ? $cms->trans("Verify the host and port are correct") : $cms->trans("Authentication Failed");
			} else {
				@ftp_set_option($ftp, FTP_TIMEOUT_SEC, 10);
				$pasv = $test['passive'] ? true : false;
				ftp_pasv($ftp, $pasv);
				$isdir = @ftp_chdir($ftp, $test['path']);		// chdir first, because Windows sucks
				if (!$isdir) {
					$msg = $cms->trans("Connected to FTP server, however the path entered does not exist");
				} else {
					// Use empty string instead of "." due to some Windows FTP servers (e.g.: TCAdmin)
					$list = ftp_nlist($ftp, "");
					if (!$list) {
						$msg = $cms->trans("No files exist! Please verify the path entered");
						if (!$pasv) $msg .= "<br/>\n" . $cms->trans("If you know logs exist then try enabling 'Passive Mode' and test again");
					} else {
						$result = 'success';
						$msg = $cms->trans("Successfully connected to the FTP server and verified the path exists!");
						$msg .= "<br/>" . $cms->trans("Note: If a new password was entered you will have to re-enter it and save the log source now.");
					}
				}
			}
			if ($ftp) ftp_close($ftp);
		}
	} elseif ($test['type'] == 'sftp') {
		// Fill in a default log path.
		if (empty($test['path'])) $test['path'] = $log['path'] ?? './';

		// Check if we can connect to the SFTP server
		if (!function_exists('ssh2_connect')) {
			$result = 'failure';
			$msg = $cms->trans("SFTP support not available in this installation of PHP") . "<br/>\n" . 
				$cms->trans("See %s for more information", "<a href='http://php.net/manual/en/ref.ssh2.php'>http://php.net/manual/en/ref.ssh2.php</a>");
		} else {
			if (!isset($test['password']) or empty($test['password'])) $test['password'] = $log['password'] ?? null;
			$ssh = @ssh2_connect($test['host'], $test['port'] ? $test['port'] : 22);
			$finger = @ssh2_fingerprint($ssh);
			// in order for this to work PasswordAuthentication must be set to 'yes' in 
			// the remote server's sshd_config file. I think a lot of distros might disable it.
			$res = @ssh2_auth_password($ssh, $test['username'], $test['password']);
			$result = 'failure';
			if (!$res) {
				$msg = $cms->trans("Unable to connect to ssh://%s@%s" . "<br/>\n", $test['username'], $test['host']);
				$msg .= !$ssh 
					? $cms->trans("Verify the host and port are correct") 
					: $cms->trans("Authentication Failed! Note: the remote server must have PasswordAuthentication set to 'yes' in the sshd_config file.");
				$msg .= "<br/>" . $cms->trans("Note: This test mechanism does not support public key authentication.");
			} else {
				$sftp = ssh2_sftp($ssh);
				$stat = @ssh2_sftp_stat($sftp, $test['path']);
				if (!$stat) {
					$msg = $cms->trans("Connected to SFTP server, however the path entered does not exist");
				} else {
					$result = 'success';
					$msg = $cms->trans("Successfully connected to the SSH server and verified the path exists!");
					$msg .= "<br/>" . $cms->trans("Note: If a new password was entered you will have to re-enter it and save the log source now.");
				}
			}
		}
	} elseif ($test['type'] == 'stream') {
		// We can't test streams
		$msg = $cms->trans("Testing 'stream' sources is not possible.");
	}

	$message = $cms->message($result, array(
		'message_title'	=> $cms->trans("Testing Results"), 
		'message'	=> $msg
	));
	// don't let the form be submitted
	unset($test);
	unset($submit);
}

// process the form if submitted
$valid = true;
$submit ??= null;
if ($submit) {
	// do some special error checking and correction depending on the logsource type
	$type = $form->input['type'];
	$form->input['blank'] ??= null;
	if ($type == '' or $type == 'file' or $type == 'stream' or $form->input['blank']) {
		$form->input['password'] = '';
		$form->input['password2'] = '';
	}
	if ($type == 'stream') {
		$form->input['path'] = '';
		$form->input['username'] = '';
	}

	// update some fields so they're required if the log source is remote
	if ($type == 'ftp' or $type == 'sftp') {
		$form->field('host', 'blank,hostname');
		$form->field('port', 'numeric');
		// Fill in a default log path.
		if (empty($form->input['path'])) $form->input['path'] = $log['path'] ?? './';
	} elseif ($type == 'stream') {
		$form->field('host', 'blank,hostname');
		$form->field('port', 'blank,numeric');
		$form->field('path',null);
	}

	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	// convert certain blank fields to nulls, so mysql 'strict' mode won't complain
	$nulls = array( 'host', 'port', 'passive', 'username', 'password', 'recursive', 'depth', 'skiplast', 'delete', 'options' );
	foreach ($nulls as $n) {
		if ($input[$n] == '') {
			$input[$n] = null;
		}
	}

	if ($input['blank']) {			// if blank is true, set password to null
		$input['password'] = null;
	} elseif ($input['password'] == '') {	// don't save blank passwords
		unset($input['password']);
	}

	if ($valid) {
		unset($input['blank'], $input['password2']);
		$ok = false;
		if ($id) {
			$ok = $ps->db->update($ps->t_config_logsources, $input, 'id', $id);
		} else {
			$input['id'] = $ps->db->next_id($ps->t_config_logsources);
//			$input['idx'] = $ps->db->max($ps->t_config_logsources, 'idx') + 10;	// last source
			$input['idx'] = 0;							// first source
			$ok = $ps->db->insert($ps->t_config_logsources, $input);
		}
		if (!$ok) {
			$form->error('fatal', "Error updating database: " . $ps->db->errstr);
		} else {
			previouspage(ps_url_wrapper('logsources.php'));
		}
/*
		$message = $cms->message('success', array(
			'message_title'	=> $cms->trans("Update Successfull"),
			'message'	=> $cms->trans("Log Source has been updated"))
		));
*/

	}

} else {
	// fill in defaults
	if (!isset($test)) {
		if ($id) {
			$form->input($log);
			if (empty($log['password'])) $form->input['blank'] = 1;
		} else {
			// new logsources should default to being enabled
			$form->input['enabled'] = 1;
			$form->input['defaultmap'] = 'unknown';
		}
	}
}

$cms->crumb('Manage', ps_url_wrapper('manage.php'));
$cms->crumb('Log Sources', ps_url_wrapper('logsources.php'));
$cms->crumb('Edit');

// save a new form key in the users session cookie
// this will also be put into a 'hidden' field in the form
if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

$cms->theme->assign(array(
	'protocols'	=> $protocols,
	'errors'	=> $form->errors(),
	'log'		=> $log,
	'form'		=> $form->values(),
	'form_key'	=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
));

// display the output
$cms->theme->add_css('css/forms.css');
//$cms->theme->add_js('js/jquery.interface.js');
$cms->theme->add_js('js/forms.js');
$cms->theme->add_js('js/logsources.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

function val_type($var, $value, &$form) {
	global $valid, $cms, $protocols;
	if (!empty($value)) {
		if (!in_array($value, $protocols) and $value != 'file') {
			$valid = false;
			$form->error($var, $cms->trans("Invalid protocol selected"));
		}
	}
	return $valid;
}

function password_match($var, $value, &$form) {
	global $valid, $cms;
	if (!empty($value)) {
		if ($value != $form->input['password2']) {
			$valid = false;
			$form->error('password', $cms->trans("Passwords do not match"));
			$form->error('password2', $cms->trans("Re-enter password"));
		}
	}
	return $valid;
}

?>
