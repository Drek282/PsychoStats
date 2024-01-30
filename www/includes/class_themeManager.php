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
 *	Version: $Id: class_themeManager.php 549 2008-08-24 23:54:06Z lifo $
 */

if (defined("CLASS_THEMEINSTALLER_PHP")) return 1;
define("CLASS_THEMEINSTALLER_PHP", 1);

define("PSTHEME_ERR_NOTFOUND", 1);
define("PSTHEME_ERR_XML", 2);
define("PSTHEME_ERR_WRITE", 3);
define("PSTHEME_ERR_VALUE", 4);
define("PSTHEME_ERR_CONTENT", 5);

include_once(PS_ROOTDIR . "/includes/class_XML.php");
//require_once(PS_ROOTDIR . '/includes/class_simplexml.php');


#[AllowDynamicProperties]
class PsychoThemeManager {
var $ps = null;			// PsychoStats object handle
var $db = null;			// PsychoDB object handle
var $template_dir = null;	// where are the templates stored
var $xml = array();		// current theme XML from load_theme()
var $error = null;		// keeps track of the last error reported
var $code = null;
var $invalid = array();		// keeps track of each invalid xml variable

function __construct(&$ps, $dir = null) {
	$this->ps =& $ps;
	$this->db =& $ps->db;
	$this->template_dir(is_null($dir) ? $ps->conf['theme']['template_dir'] : $dir);
}

function PsychoThemeManager(&$ps, $dir = null) {
    self::__construct($ps, $dir);
}

// fetches the theme.xml from the location specified
function load_theme($url, $skip_file = false) {
	$this->error(false);
	ob_start();
	$xml = file_get_contents($url); //, false, null, 0, 4*1024); // 5 paramater format only works in PHP5
	$err = ob_get_contents();
	if (!empty($err)) {	// cleanup the error a little bit so it's a little easier to tell what happened
		$err = str_replace(' [function.file-get-contents]', '', strip_tags($err));
	}
	ob_end_clean();
	
	$http_response_header ??= null;
	$this->headers = $this->parse_headers($http_response_header);
	$this->xml = array();
	$data = array();
	if ($xml !== false) {
		// make sure the content-type returned is something reasonable (and not an image, etc).
		if ($this->is_xml()) {
			$data = XML_unserialize($xml);
			if ($data and is_array($data['theme'])) {
				$xml2 = simple_interpolate($xml, $data['theme'], true);
				if ($xml2 != $xml) {	// unserialize the data once more since it had $vars in the XML
					$data = XML_unserialize($xml2);
				}
				$this->xml = $data['theme'];
				array_walk($this->xml, array(&$this, '_fix_xml_attrib'));
			} else {
				$this->error("Invalid theme XML format loaded from $url", PSTHEME_ERR_XML);
			}
		} else {
			$this->error("Invalid content-type returned for XML (" . $this->headers['content-type'] . ")", PSTHEME_ERR_CONTENT);
		}
	} else {
		$this->error($err, PSTHEME_ERR_NOTFOUND);
	}

	$ok = false;
	if (!$this->error() and $this->xml) {
		$ok = $this->validate_theme($skip_file);
		if ($ok and file_exists(catfile($this->template_dir, $this->xml['name']))) {
			$this->xml['theme_exists'] = true;
		}
	}

	return $ok;
}

// loads the specified theme from the database, not an xml file
function load_theme_db($name) {
	$ok = true;
	$t = $this->db->fetch_row(1, "SELECT * FROM {$this->ps->t_config_themes} WHERE name=" . $this->db->escape($name, true));
	if ($t) {
		$this->xml = $t;
		$this->xml_name($t['name']);
		$this->xml_parent($t['parent']);
		$this->xml_version($t['version']);
		$this->xml_title($t['title']);
		$this->xml_author($t['author']);
		$this->xml_website($t['website']);
		$this->xml_source($t['source']);
		$this->xml_image($t['image']);
		$this->xml_description($t['description']);
	} else {
		$this->error("Theme '$name' not found in database", PSTHEME_ERR_NOTFOUND);
		$ok = false;
	}
	return $ok;
}

// returns the current $theme data as an XML string
function serialize() {
	if (!$this->xml) return '';
	return XML_serialize($this->xml, 'theme');
}

// attempts to install the current theme that was loaded
function install() {
	$ok = false;
	if (!$this->xml or $this->error()) {	// nothing to install
		return false;
	}

	// temporary file for download
	$localfile = tempnam(getcwd(),'ps3theme');
	$local = fopen($localfile,'wb');
	if (!$local) {
		$this->error("Error creating temporary file for download");
		return false;
	}

	// download the file (could also be a local file)
	$remote = fopen($this->xml_file(), "rb");
	if ($remote) {
		while (!feof($remote)) {
			$str = fread($remote, 8192);
			fwrite($local, $str);
		}
		fclose($remote);
		fclose($local);
	} else {
		$this->error("Error opening XML file");
		return false;
	}

	// try to read the downloaded file. It must be a zip file
	$ok = $this->open_zip($localfile);
	if ($ok) {
		$created = array();
		// loop through each file in the archive and save it to our local theme directory.
		// every file in the zip must have the theme 'name' as the root directory, or ignore it.
		while ($zip_entry = zip_read($this->zip)) {
			zip_entry_open($this->zip, $zip_entry);
			$name = zip_entry_name($zip_entry);
			if (strpos($name, $this->xml_name().'/') !== 0) {
				$this->error("Invalid directory structure in theme archive. ABORTING INSTALLATION");
				$ok = false;
				break;
			}
			// do not allow script files! these can (and will most likely) be malicous!!
			if (preg_match('/\.(php\d?|inc|pl|cgi)$/', $name)) {
				$this->error("Invalid script file found in theme archive: $name. ABORTING INSTALLATION");
				$ok = false;
				break;
			}
			if (substr($name, -1) == '/') {			// directory
				$dir = catfile($this->template_dir, substr($name,0,-1));
				if (!file_exists($dir)) {
					mkdir_recursive($dir);
					$created[] = $dir;
				}
			} else {					// file
				$file = catfile($this->template_dir, $name);
				$fh = fopen($file,'wb');
				if ($fh) {
					fwrite($fh, zip_entry_read($zip_entry, zip_entry_filesize($zip_entry)), zip_entry_filesize($zip_entry));
					fclose($fh);
					@chmod($file, 0664);
					$created[] = $file;
				} else {
					$this->error("Error writing file $name from archive. File permissions are probably incorrect! ABORTING INSTALLATION");
					$ok = false;
					break;
				}
			}
			zip_entry_close($zip_entry);
		}
		$this->close_zip();

		// cleanup the installed theme if we failed!
		if (!$ok and $created) {
			foreach ($created as $file) { // we're not really concerned if this cleanup fails
				if (is_dir($file) and !is_link($file)) {
					@rmdir($file);
				} else {
					@unlink($file);
				}
			}
		}

		// local files were installed, now add it to the database
		if ($ok) {
			$xml = $this->save_db();
			if ($xml) {
				$this->xml = $xml;
			} else {
				$ok = false;
				$this->error("Error adding theme to database: " . $this->db->errstr);
			}
		}
	}

	// cleanup!
	@unlink($localfile);

	return $ok;
}

// reinstall a theme that is already in the template_dir
function reinstall($name) {
	$t = new PsychoThemeManager($this->ps, $this->template_dir);
	$ok = $t->load_theme(catfile($this->template_dir, $name, 'theme.xml'), true);
	if ($ok) {
		$ok = $t->save_db();
	}
	if (!$ok) {
		$this->error($t->error());
	}
	return $ok ? $t : false;
}

// uninstalls the theme.
// this does not delete the theme directory (TODO: implement $delete option)
function uninstall($delete = false) {
	$ok = $this->delete_db();
	if ($ok and $delete) {
		// not implemented ...	
	}
	return $ok;
}

// removes the theme from the database.
function delete_db($name = null) {
	if ($name === null) {
		$name = $this->xml_name();
	}
	if (!$name) return false;
	$ok = $this->db->delete($this->ps->t_config_themes, 'name', $name);
	if (!$ok) {
		$this->error($this->db->errstr);
	}
	return $ok;
}

// saves the theme to the database.
function save_db() {
	if (!$this->theme_xml()) return false;
	$exists = $this->db->fetch_row(1, "SELECT * FROM {$this->ps->t_config_themes} WHERE name LIKE " . $this->db->escape($this->xml_name(), true));
	$set = array();
	$set['name']		= $this->xml_name();
	$set['parent']		= $this->xml_parent() ? $this->xml_parent() : null;
	$set['enabled'] 	= $exists ? ($exists['enabled']?1:0) : 1;
	$set['version']		= $this->xml_version();
	$set['title']		= $this->xml_title();
	$set['author']		= $this->xml_author();
	$set['website']		= $this->xml_website();
	$set['source']		= $this->xml_source();
	$set['image']		= $this->xml_image();
	$set['description']	= $this->xml_description();

	if ($exists) {
		$ok = $this->db->update($this->ps->t_config_themes, $set, 'name', $set['name']);
	} else {
		$ok = $this->db->insert($this->ps->t_config_themes, $set);
	}
	if (!$ok) {
		$this->error($this->db->errstr);
	}
	return $ok ? $set : false;
}


// enables or disables the theme
function toggle($enabled) {
	$ok = $this->db->update($this->ps->t_config_themes, array( 'enabled' => $enabled ), 'name', $this->xml_name());
	return $ok;
}


// attempts to open the file for reading.
function open_zip($file) {
	$res = false;
	if (!function_exists('zip_open')) {
		$this->error("Error processing downloaded file. ZIP support not fully enabled in your PHP installation.");
		return false;
	}
	$res = zip_open($file);
	$this->zip = $res;
	return $res ? true : false;
}

function close_zip() {
	if ($this->zip) {
		zip_close($this->zip);
	}
	$this->zip = null;
}

// returns true if we have an XML file according to the content-type header.
// text/xml, application/xml, plain/xml, text/plain, text/html
function is_xml($hdr = null) {
	if ($hdr === null) {
		$hdr =& $this->headers;
	}
	$hdr['content-type'] ??= null;
	$ct = $hdr['content-type'];
	// if we don't have a content-type we assume the best and return true
	if (!$ct) return true;

	$ok = preg_match('@^\w+/xml|text/(plain|html)@', $ct);
	return $ok;

}

// parses the response headers from a $http_response_header array
function parse_headers($fields) {
	$res = array( 'response' => 'Invalid Request', 'response_code' => '404' );
	if (!is_array($fields)) return $res;
	foreach( $fields as $field ) {
		if( preg_match('/([^:]+): (.+)/m', $field, $match) ) {
			$match[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./', function($m) { return strtoupper($m[0]); }, strtolower(trim($match[1])));
			$match[1] = strtolower($match[1]);
			if( isset($res[$match[1]]) ) {
				$res[$match[1]] = array($res[$match[1]], $match[2]);
			} else {
				$res[$match[1]] = trim($match[2]);
			}
		} else if (preg_match('/^HTTP\/\d.\d\s(\d+)/', $field, $match)) {
			$res['response'] = $field;
			$res['response_code'] = $match[1];
		}
        }
	return $res;
}

// replace XML @attributes with _attributes so the variables can be used in the theme output
function _fix_xml_attrib(&$ary, $key) {
//	print "$key = "; var_dump($ary); print "\n";
	if (substr($key,0,1) == '@') {
		$this->xml['_' . substr($key,1)] = $this->xml[$key];
		unset($this->xml[$key]);
	}
}

// returns true if the loaded theme XML has all the proper information
function validate_theme($skip_file = false) {
	global $cms;	// this is bad.. i know...
	if (!$this->xml) {
		return false;
	}
	$this->invalid(false);
	$this->error('');

	// make sure the name exists
	if ($this->xml_name() == '') {
		$this->error($cms->trans("No name defined"), PSTHEME_ERR_VALUE);
		$this->invalid('name', $cms->trans("A name must be defined"));
	}

	// make sure the name is valid
	if ($this->xml_name() != '' and !$this->re_match('/^[\w\d_\.-]+$/', $this->xml_name())) {
		$this->error($cms->trans("Invalid name defined"), PSTHEME_ERR_VALUE);
		$this->invalid('name', $cms->trans("Invalid characters found in name"));
	}

	if (!$skip_file) {
		// make sure the file exists
		if (!$this->xml_file()) {
			$this->error($cms->trans("No file defined"), PSTHEME_ERR_VALUE);
			$this->invalid('file', $cms->trans("A file location must be defined to download the theme from"));
		}

		// make sure the file exists on the remote server (but don't download it yet)
		if ($this->xml_file() != '' and !$this->test_file()) {
			$this->error($cms->trans("Theme download file not found or invalid type (" . $this->xml_file() . ")"), PSTHEME_ERR_VALUE);
			$this->invalid('file', $cms->trans("Unable to download theme file from " . $this->xml_file()));
		}
	}

	// make sure the parent is valid
	if (!$this->re_match('/^[\w\d_\.-]+$/', $this->xml_parent())) {
		$this->error($cms->trans("Invalid parent defined"), PSTHEME_ERR_VALUE);
		$this->invalid('parent', $cms->trans("Invalid characters found in parent"));
	}

	// make sure the website is valid
	if (!$this->re_match('|^https?:/\/|', $this->xml_website())) {
		$this->error($cms->trans("Invalid website defined"), PSTHEME_ERR_VALUE);
		$this->invalid('website', $cms->trans("Website must start with http:// or https://"));
	}

	// make sure the source is valid
	if (!$this->xml_source()) {
		$this->error($cms->trans("No source defined"), PSTHEME_ERR_VALUE);
		$this->invalid('source', $cms->trans("A source location must be defined to download the theme from"));
	}

	// make sure the image is valid
	if (!$this->re_match('/.(?:jpg|png|webp)$/', $this->xml_image())) {
		$this->error($cms->trans("Invalid image defined"), PSTHEME_ERR_VALUE);
		$this->invalid('image', $cms->trans("Image must be in jpg, png or webp format."));
	}

	// if there's a parent defined make sure we have the appropriate parent already installed
	if (!$this->error() and $this->xml_parent()) {
		list($exists) = $this->db->fetch_list("SELECT 1 FROM {$this->ps->t_config_themes} WHERE name LIKE " . $this->db->escape($this->xml_parent(), true));
		if (!$exists) {
			$this->error("Child theme '" . $this->xml_name() . "' requires the parent '" . $this->xml_parent() . "' to be installed.", PSTHEME_ERR_VALUE);
			$this->invalid('parent', "Child theme '" . $this->xml_name() . "' requires the parent '" . $this->xml_parent() . "' to be installed.");
		}
	}
	
	$err = $this->error();
	return empty($err);
}

// helper function to check a string against a regex patten.
// returns true if the str is in a valid format
function re_match($regex, $str) {
	if ($str == '') return true;
	return preg_match($regex, $str);
}

// readonly accessor functions for loaded XML theme values
function theme_xml() 		{ return $this->xml ? $this->xml : ''; }
function xml_name() 		{ return $this->xml ? trim($this->xml['name'] ?? '') : ''; }
function xml_parent() 		{ return $this->xml ? trim($this->xml['parent']) : ''; }
function xml_website() 		{ return $this->xml ? trim($this->xml['website']) : ''; }
function xml_version() 		{ return $this->xml ? trim($this->xml['version']) : ''; }
function xml_title() 		{ return $this->xml ? trim($this->xml['title']) : ''; }
function xml_author() 		{ return $this->xml ? trim($this->xml['author']) : ''; }
function xml_source() 		{ return $this->xml ? trim($this->xml['source']) : ''; }
function xml_image() 		{ return $this->xml ? trim($this->xml['image']) : ''; }
function xml_file() 		{ return $this->xml ? trim($this->xml['file']) : ''; }
function xml_description() 	{ return $this->xml ? trim($this->xml['description']) : ''; }

// returns a list of themes within the template_dir (or the directory specified)
function theme_dirs($dir = null) {
	if ($dir === null) {
		$dir = $this->template_dir;
	}
	if (!is_dir($dir)) return false;
	$list = array();
	$dh = opendir($dir);
	if (!$dh) return false;
	while (($file = readdir($dh)) !== false) {
		if (!is_dir(catfile($dir, $file)) or substr($file,0,1) == '.') continue;	// ignore non-directories and special
		@list($installed) = $this->db->fetch_list("SELECT 1 FROM {$this->ps->t_config_themes} WHERE name LIKE " . $this->db->escape($file, true));
		$xml = catfile($dir, $file, 'theme.xml');
		if ($installed or !file_exists($xml)) continue;		// ignore installed themes, or directories w/o a theme.xml
		$t = new PsychoThemeManager($this->ps, $this->template_dir);
		$t->load_theme($xml);
		$list[] = array(
			'directory'	=> $file,
			'installed'	=> $installed,
			'title'		=> $t->xml_title() ? $t->xml_title() : $file,
			'xml'		=> $t->theme_xml(),
		);
		unset($t);
	}
	closedir($dh);
	return $list;
}

function enabled($toggle = null) { 
	if (!$this->xml) return false;
	if ($toggle === null) {
		return $this->xml['enabled'] ? 1 : 0;
	} else {
		return $this->xml['enabled'] = $toggle ? 1 : 0;
	}
}

function test_file($file = null) {
	if ($file === null) {
		$file = $this->xml_file();
	}

	ob_start();
	$fh = fopen($file, 'rb');
	$err = strip_tags(ob_get_contents());
	ob_end_clean();
	$hdr = $this->parse_headers($http_response_header);

	$ok = ($hdr['response_code'] == 200);
	if ($ok) {
		$this->xml['_file']['headers'] = $hdr;
		// if the content-length is returned keep track of it
		$this->xml['_file']['size'] = intval($hdr['content-length']);
		// try to determine the type of file. must be a ZIP
		// we can't rely on file extension since that will not always be available
		if ($hdr['content-type']) {
			$type = explode('/', $hdr['content-type']);
			$ct = array_pop($type);	// just look at the last part of 'application/zip'
			$this->xml['_file']['type'] = $ct;
			$ok = ($ct == 'zip'); // || $ct == 'rar');
		}
	}
	return $ok;
}

function template_dir($dir = null) {
	if ($dir === null) {
		$this->template_dir = catfile(PS_ROOTDIR, 'themes');
	} else {
		$this->template_dir = $dir;
	}
}

function invalid($key, $str = null) {
	if (empty($key)) {
		$this->invalid = array();
	} else {
		$this->invalid[$key] = $str;
	}
}
function invalid_list() {
	return $this->invalid;
}

function error($str = null, $code = null) {
	if ($str !== null) {
		$this->error = $str;
		$this->code($code);
	}
	return $this->error;
}

function code($code = null) {
	if ($code !== null) {
		$this->code = $code;
	}
	return $this->code;
}

} // end of PsychoThemeManager

?>
