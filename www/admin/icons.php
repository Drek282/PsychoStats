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
 *	Version: $Id: icons.php 389 2008-04-18 15:04:10Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");

$validfields = array('ref','delete','upload','ajax');
$cms->theme->assign_request_vars($validfields, true);

$msg_not_writable = '';
$action_result = '';
$uploaded_icon = '';
$cms->theme->assign_by_ref('msg_not_writable', $msg_not_writable);
$cms->theme->assign_by_ref('result', $action_result);
$cms->theme->assign_by_ref('uploaded_icon', $uploaded_icon);

// delete an icon, if specified
if ($delete) {
	$res = 'success';
	$file = catfile($ps->conf['theme']['icons_dir'], basename($delete));
	if (@file_exists($file)) {
		if (!@unlink($file)) {
			$res = !is_writable($file) ? $cms->trans("Permission denied") : $cms->trans("Unknown error while deleting file");
		}
	} else {
		$res = $cms->trans("Icon '%s' does not exist", basename($file));
	}

	// if $ajax is true this was an AJAX request.
	if ($ajax) {
		print $res;
		exit();
	} else {
		$action_result = $res == 'success' ? $cms->trans("Icon '%s' deleted successfully", basename($file)) : $res;
	}
}

// process an icon upload request (either from a file or a remote URL)
$form = null;
$errors = array();
$file = array();
if ($upload) {
	$form = $cms->new_form();
	$form->default_modifier('trim');
//	$form->field('file');
	$form->field('url');
	$input = $form->values();

	// first determine where we're fetching the url from 
	$from = null;
	if ($cms->file['file']['size']) {
		$from = $cms->file['file'];
	} elseif ($input['url'] and $input['url'] != 'http://') {
		$from = $input['url'];
	}

	$err = '';
	if (is_array($from)) {	// upload file
		$file = $from;
		// Sanitize $file['tmp_name'].
		$file['tmp_name'] = preg_replace('/(?:\.\.|%2e%2e)(?:\/|\\)/','',$file['tmp_name']);
		if (!is_uploaded_file($file['tmp_name'])) {
			$err = $cms->trans("Uploaded icon is invalid");
		}
	} elseif ($from) {	// fetch file from URL
		$file = array();
		// Sanitize $from
		$from = filter_var($from, FILTER_SANITIZE_URL);
		$from = preg_replace('/(?:\.\.|%2e%2e)(?:\/|\\)/','',$from);
		if (!preg_match('|^\w+://|', $from)) {	// make sure a http:// prefex is present
			$from = "http://" . $from;
		}

		if (($tmpname = @tempnam('/tmp', 'iconimg_')) === FALSE) {
			$err = $cms->trans("Unable to create temporary file for download");
		} else {
			$file['tmp_name'] = $tmpname;
			$url = parse_url(rawurldecode($from));
			$file['name'] = basename($url['path']);
			if (empty($file['name'])) $file['name'] = $url['host'];
			$file['size'] = 0;
			// open the URL for reading ... 
			if (!($dl = @fopen($from, 'rb'))) {
				$err = $cms->trans("Unable to download file from server");
//				if (isset($php_errormsg)) $err .= "<br/>\n" . $php_errormsg;
			}
			// open the tmp file for writting ... 
			if ($dl and !($fh = @fopen($file['tmp_name'], 'wb'))) {
				$err = $cms->trans("Unable to process download");
//				if (isset($php_errormsg)) $err .= "<br/>\n" . $php_errormsg;
			}

			// get the headers from the request
			$hdr = $http_response_header;	// built in PHP variable (hardly documented, php 4 and 5)

			// find the Content-Type and Size
			foreach ($hdr as $h) {
				if (preg_match('/:/', $h)) {
					list($key, $str) = explode(":", $h, 2);
					$str = trim($str);
					if ($key == 'Content-Length') {
						if ($str > $ps->conf['theme']['icons']['max_size']) {
							$err = $cms->trans("File download is too large") . " (" . abbrnum($str) . " > " . abbrnum($ps->conf['theme']['icons']['max_size']) . ")";
						}
						break;
					}
				}
			}

			// read the contents of the URL into the tmp file ... 
			if (!$err and $dl and $fh) {
				// make sure the URL file is a valid image type before we download it
				$match ??= null;
				if (!preg_match("/$match/", $file['name'])) {
					$err = $cms->trans("Image type of URL must be one of the following:") . " <b>" . $ps->conf['theme']['image']['search_ext'] . "</b>";
				} else {
					$total = 0;
					while (!feof($dl) and $total < $ps->conf['theme']['icons']['max_size']) {
						$total += fwrite($fh, fread($dl, 8192));
					}
					// if it's not the EOF then the file was too large ... 
					if (!feof($dl)) {
						$err = $cms->trans("File download is too large") . " (" . abbrnum($file['size']) . " > " . abbrnum($ps->conf['theme']['icons']['max_size']) . ")";
					}
				}
				fclose($dl);
				fclose($fh);
				$file['size'] = filesize($file['tmp_name']);
			}
		}
	}
	$file['info'] = array();
	if ($file['tmp_name']) $file['info'] = @getimagesize($file['tmp_name']);
	if (!$err) {
		$res = validate_img($file);
		if ($res !== true) {
			$err = $res;
		}
	}

	// still no error? we can now try and copy the file from the tmp location to the icon dir
	if (!$err) {
		$newfile = catfile($ps->conf['theme']['icons_dir'], $file['name']);
		$overwrote = file_exists($newfile);
		$ok = @rename_file($file['tmp_name'], $newfile);
		if (!$ok) {
			$err = $cms->trans("Error copying new image to icon directory!");
//			$err .= is_writable(dirname($newfile)) ? "<br/>" . $cms->trans("Permission Denied") : '';
//			if (isset($php_errormsg)) $err .= "<br/>\n" . $php_errormsg;
		} else {
			$action_result = $cms->trans("File '%s' uploaded successfully!", $file['name']);
			if ($overwrote) $action_result .= " (" . $cms->trans("Original file was overwritten") . ")";
			$uploaded_icon = $file['name'];
			@chmod(catfile($ps->conf['theme']['icons_dir'], $file['name']), 0644);
		}
	}

	if ($err) {
		$form->error('fatal',$err);
	}

	// don't care if this fails
	@unlink($file['tmp_name']);
}

// load the icons
$icons = array();
$ext = $ps->conf['theme']['images']['search_ext'];
if (empty($ext)) $ext = 'png, jpg, gif, webp';
$list = explode(',',$ext);
$list = array_map('trim', $list);
$match = '\\.(' . implode('|', $list) . ')$';
$dir = $ps->conf['theme']['icons_dir'];
if (is_dir($dir)) {
	if ($dh = opendir($dir)) {
		while (($file = readdir($dh)) !== false) {
			if (substr($file,0,1) == '.') continue;		// ignore dot and hidden files
			if (!preg_match("/$match/", $file)) continue;	// ignore files not matching the search_ext
			$full = catfile($dir, $file);
			$icons[] = array(
				'filename' 	=> $file,
				'fullfile' 	=> $full,
				'size'		=> @filesize($full),
				// if the file is not writable but the directory is, we should still be able
				// to delete the file (since deleting a file writes to the directory and not the actual file).
				// unless the STICKY bit is set on the directory and someone other than the webserver user
				// owns the file.
				'is_writable'	=> is_writable($full) || is_writable(rtrim(dirname($full), '/\\')),
				'basename'	=> basename($file),
				'path'		=> $dir
			);
        	}
		closedir($dh);
	}
}

if (!is_writable($dir)) {
	$msg_not_writable = $cms->message('not_writable', array(
		'message_title'	=> $cms->trans("Permissions Error!"),
		'message'	=> $cms->trans("The icons directory is not writable.") . ' ' . $cms->trans("You can not upload any new icons until the permissions are corrected."),
	));
}

$cms->crumb('Manage', ps_url_wrapper(array('_base' => 'manage.php' )));
$cms->crumb('Icon Avatars', ps_url_wrapper(array('_base' => 'icons.php' )));

$message ??= null;
// assign variables to the theme
$cms->theme->assign(array(
	'page'		=> $basename, 
	'icons'		=> $icons,
	'message'	=> $message,
	'icons_url'	=> $ps->conf['theme']['icons_url'],
	'form'		=> $form ? $form->values() : array('url' => null,),
	'errors'	=> $form ? $form->errors() : array('fatal' => null,),
));

// display the output
$cms->theme->add_css('css/2column.css');
$cms->theme->add_css('css/forms.css');
$cms->theme->add_css('css/icons.css');
//$cms->theme->add_js('js/jquery.interface.js');
$cms->theme->add_js('js/icons.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

function validate_img($file) {
	global $form, $cms, $ps;
	$c = $ps->conf['theme']['icons'];
	$ext = $ps->conf['theme']['images']['search_ext'];
	if (empty($ext)) $ext = 'png, jpg, gif, webp';
	$list = explode(',',$ext);
	$list = array_map('trim', $list);
	$match = '\\.(' . implode('|', $list) . ')$';
	$res = true;
	if (!preg_match("/$match/", $file['name'])) {
		return $cms->trans("Image type must be one of the following:") . ' <b>' . implode(', ', $list) . '</b>';
#	} elseif ($file['info'][2] > 3) {
#		return $cms->trans("Image type is invalid");		
	} elseif ($c['max_size'] and $file['size'] > $c['max_size']) {
		return $cms->trans("Image size is too large") . " (" . abbrnum($file['size']) . " > " . abbrnum($c['max_size']) . ")";
	} elseif ($file['info'][0] > $c['max_width'] or $file['info'][1] > $c['max_height']) {
		return $cms->trans("Image dimensions are too big") . " ({$file['info'][0]}x{$file['info'][1]} > " . $c['max_width'] . "x" . $c['max_height'] . ")";
	} elseif (substr($file['name'], 0, 1) == '.') { 
		return $cms->trans("Image name can not start with a period");
	}
	return $res;
}

// shuwdown function; delete temp file
function sd_del_file($file) {
//	global $file;
	print "unlink(" . $file['tmp_name'] . ")";
	if ($file['tmp_name'] and @is_file($file['tmp_name'])) {
		@unlink($file['tmp_name']);
	}
}
?>
