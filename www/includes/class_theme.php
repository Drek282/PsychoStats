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
 *	Version: $Id: class_theme.php 570 2008-11-06 15:12:27Z lifo $
 *
 *	PsychoTheme class
 *
 *      Theme class that handles all HTML or text output (images are done
 *	elsewhere). Smarty is the underlining code to produce the output. The
 *	PsychoTheme class adds some new functionality like multiple language
 *	support. Also, parent/child themes are supported. A child theme can be
 *      based on a parent and only have the changed files within the child theme
 *	directory.
 *
 *      A theme is made up of template files. Almost anything can be done within
 *      a template. For security reasons PHP tags are not allowed inside a
 *      template.
 *
 *      The layout of most themes generally follow this hierarchy:
 *		overall_header
 *		page_header
 *		page_content
 *		page_footer
 *		overall_footer
 *      Plugins can hook into the different pieces of themes to apply changes
 *      that are transparent no matter what theme is being used.

***/

if (defined("CLASS_THEME_PHP")) return 1;
define("CLASS_THEME_PHP", 1);

if (!defined("SMARTY_DIR")) define("SMARTY_DIR", __DIR__ . "/smarty/");
require_once(SMARTY_DIR . 'Smarty.class.php');

class PsychoTheme extends Smarty {
var $buffer 		= '';
var $theme_url		= null;
var $theme 		= '';
var $styles 		= array();
var $parent_styles	= null;
var $language 		= 'en_US';
var $language_open	= '<#';
var $language_close	= '#>';
var $language_regex	= '/(?:<!--)?%s(.+?)%s(?:-->(.+?)<!---->)?/ms';
var $template_dir	= null;
var $css_links		= array();
var $css_compress	= true;
var $js_sources		= array();
var $js_compress	= true;
var $rel_links		= array();
var $loaded_themes	= array();
var $parent_themes	= array();
var $fetch_compile	= true;
var $_page_title	= '';

//function __construct($cms, $args = array()) { $this->PsychoTheme($cms, $args); }
function __construct(&$cms, $args = array()) {
	$this->Smarty();
	$this->cms =& $cms;

	// if args is not an array assume its the name of a theme to use
	if (!is_array($args)) $args = array( 'theme' => $args ? $args : 'default' );
	$args += array(
//		'theme'		=> '',
		'in_db' 	=> true,
		'language'	=> 'en_US',
		'fetch_compile'	=> true,
		'template_dir'	=> null,
		'theme_url'	=> null,
		'compile_dir'	=> '.',
		'compile_id'	=> '',
		'js_compress'	=> false,
		'css_compress'	=> false
	);
	if (empty($args['language'])) {
		$args['language'] = 'en_US';
	}
	$this->template_dir($args['template_dir']);
	$this->language($args['language']);
	$this->theme_url = $args['theme_url'];
	$this->fetch_compile = ($args['fetch_compile']);
	$this->js_compress = $args['js_compress'] ? true : false;
	$this->css_compress = $args['css_compress'] ? true : false;

	// Force themes to be compiled if debugging is enabled
	if (defined("PS_THEME_DEV") and PS_THEME_DEV == true) {
		$this->force_compile = PS_THEME_DEV;
	}

	// initialize some Smarty variables
	$this->error_reporting 	= E_ALL & ~E_NOTICE & ~E_DEPRECATED;
	//$this->error_reporting 	= E_ALL;
	$this->compile_id	= $args['compile_id'];
	$this->use_sub_dirs 	= false;
	$this->caching 		= false;
//	$this->cache_dir 	= $args['compile_dir'];
	$this->compile_dir 	= $args['compile_dir'];
//	$this->default_template_handler_func = array(&$this, 'no_template_found');

	// This output filter helps session support be more accurate for users w/o cookies
	if ($this->cms->session && $this->cms->session->sid_method() != 'cookie' and empty($this->cms->cookie)) {
		$this->register_outputfilter(array(&$this, 'output_filter'));
	}

	// pre-filter to automatically translate language strings
	$this->register_prefilter(array(&$this, "prefilter_language"));

	// default the theme_url to our local themes directory
	if ($this->theme_url === null) {
		// if $base is '/' then don't use it, otherwise the theme_url will start with "//"
		// and that will cause odd behavior as the client tries to do a network lookup for it
		$base = str_replace('\\', '/', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'));
		$this->theme_url = ($base != '/' ? $base : '') . '/themes';
	}

	// Define some common globals for all templates
	$this->assign_by_ref('title', $this->_page_title);
	$this->assign_by_ref('theme_name', $this->theme);
	$this->assign_by_ref('language', $this->language);
	$this->assign(array(
		'php_scnm'		=> ps_escape_html($GLOBALS['php_scnm']),
		'SELF'			=> ps_escape_html($GLOBALS['php_scnm']),
	));

	// allow theme access to a couple methods of our objects
	$this->register_object('theme', $this, array( 'css_links', 'js_sources', 'rel_links', 'url', 'parent_url' ), false);
	$this->register_object('db', $this->cms->db, array( 'totalqueries' ), false);
	
}  // end of constructor
 
function PsychoTheme(&$cms, $args = array()) {
    self::__construct($cms, $args);
}

// assigns a list of request variable names to the theme by referernce so the theme can use them 
// and the script can continue to change them before the final output.
// if $globalize is true the variables will also be injected into the global name space as references.
function assign_request_vars($list, $globalize = false) {
	if (is_array($list)) {
		foreach ($list as $var) {
			$this->assign_by_ref($var, $this->cms->input[$var]);
			if ($globalize) {
				$GLOBALS[$var] = &$this->cms->input[$var];
			}
		}
	}
}

// allows pages to insert extra CSS links within the overall_header.
// $href is a relative URL to the css file (within the current theme_url).
// $media is the media type to use (optional).
function add_css($href, $media='screen,print') {
	$this->css_links[$href] = array( 'href' => $href, 'media' => $media );
}

function add_js($src, $switch = null) {
	$this->js_sources[$src] = array( 'src' => $src, 'switch' => $switch );
}

function add_rel($values) {
        if (is_array($values)) {
                $this->rel_links[] = $values;
        }
}

// SMARTY: template routine to print out the REL links in the overall_header
function rel_links() {
        if (!is_array($this->rel_links)) return '';
        $out = '';
        foreach ($this->rel_links as $link) {
                $out .= "\t<link ";
                foreach ($link as $key => $val) {
                        $out .= "$key='" . ps_escape_html($val) . "' ";
                }
                $out .= ">\n";
        }
        return $out;
}

// returns true for '1' or 'true' and false for anything else
function is_true($val) {
	switch (strtolower($val)) {
		case '1':
		case 'true': return true;
		default: return false;
	}
}

// SMARTY: template routine to print out the CSS links in the overall_header
function css_links($theme = null) {
	if (!is_array($this->css_links)) return '';
	if (empty($theme)) $theme = $this->theme();
	$out = '';
	$first = array();
	$last = array();
	$csslist = $this->styles->val('theme.css');
	if ($csslist and (!is_array($csslist) or !$csslist[0])) $csslist = array( $csslist );
	
	// collect the css files defined in styles.xml
	if ($csslist) {
		foreach ($csslist as $c) {
			$css = array();
			if (is_array($c)) {
				if ($c['@attributes']['href']) {
					$css['href'] = $c['@attributes']['href'];
				} elseif ($c['@attributes']['@content']) {
					$css['style'] = $c['@attributes']['@content'];
				}

				// ignore block if there's no href or content
				if ($css) {
					// add media if present
					$c['@attributes']['media'] = $c['@attributes']['media'] ?? null;
					if ($c['@attributes']['media']) {
						$css['media'] = $c['@attributes']['media'];
					}
					// add loadlast if present
					$c['@attributes']['loadlast'] = $c['@attributes']['loadlast'] ?? null;
					if ($c['@attributes']['loadlast']) {
						$css['loadlast'] = $c['@attributes']['loadlast'];
					}
				}
			} elseif ($c) {
				// block is stright script, no src
				$css['style'] = trim($c);
			}
			if ($css) {
                $css['loadlast'] = $css['loadlast'] ?? null;
				if ($css['loadlast']) {
					$last[] = $css;
				} else {
					$first[] = $css;
				}
			}
		}
	}
	$list = array_merge($first, $this->css_links, $last);
	
	// output any external or embedded styles
	$files = array();
	foreach ($list as $css) {
        $css['style'] = $css['style'] ?? null;
		if (substr($css['href'], 0, 4) == 'http') {
			// ignore fully qualified sources and output them as
			// their own <link> tag, regardless.
			$out .= sprintf("<link rel='stylesheet' type='text/css' media='%s' href='%s'>\n", 
				$css['media'] ? $css['media'] : 'screen,print', $css['href']
			);
		} elseif ($css['style']) {
			// embedded styles
			$out .= "<style>" . $css['style'] . "</style>\n";
		} else {
			// css file
			$res = $this->template_found($css['href'], false, false);
			if ($res and $res['resource_name']) {
				//$files[] = $res['resource_name'];
				$css['media'] = $css['media'] ?? null;
				$out .= sprintf("<link rel='stylesheet' type='text/css' media='%s' href='%s'>\n", 
					$css['media'] ? $css['media'] : 'screen,print',
					$this->url($res['resource_theme']) . '/' . $css['href']
				);
			}
		}
	}

	return $out;
}

// SMARTY: template routine to print out the JS sources in the overall_header
function js_sources($theme = null) {
	if (!is_array($this->js_sources)) return '';
	if (empty($theme)) $theme = $this->theme();
	$out = '';
	$list = array();
	$script = $this->styles->val('theme.script');
	if ($script and (!is_array($script) or !$script[0])) $script = array( $script );

	// collect the various scripts defined in styles.xml
	if ($script) {
		foreach ($script as $s) {
			$js = array();
			if (is_array($s)) {
				// ignore block if there's no src
				if ($s['@attributes']['src']) {
					$js['src'] = $s['@attributes']['src'];
				} elseif ($s['@attributes']['@content']) {
					$js['script'] = $s['@attributes']['@content'];
				}
			} elseif ($s) {
				// block is stright script, no src
				$js['script'] = $s;
			}
			if ($js) $list[] = $js;
		}
	}
	$list = array_merge($list, $this->js_sources);

	// output any external or embedded scripts
	$files = array();
	foreach ($list as $js) {
        $js['src'] = $js['src'] ?? '';
        $js['script'] = $js['script'] ?? null;
		if (substr($js['src'], 0, 4) == 'http') {
			// ignore fully qualified sources and output them as
			// their own <script> tag, regardless.
			if (!empty($js['switch'])) {
				$out .= sprintf("<script src='%s' %s></script>\n",  $js['src'], $js['switch']);
			} else {
				$out .= sprintf("<script src='%s'></script>\n",  $js['src']);
			}
		} elseif ($js['script']) {
			// embedded JS
			$out .= "<script>" . $js['script'] . "</script>\n";
		} else {
			// javascript file
			$res = $this->template_found($js['src'], false, false);
			if ($res and $res['resource_name']) {
				$files[] = $res['resource_name'];
			}
		}
	}

	// combine and output all local scripts into a single request
	if ($files) {
		if ($this->js_compress) {
			// combine all sources into a single request
			if ($files) {
				$src = implode(',', $files);
				$out .= sprintf("<script src='%s'></script>\n",
					ps_url_wrapper(array( '_base' => 'script.php', 'src' => $src))
				);
			}
		} else {
			foreach ($files as $f) {
				$out .= sprintf("<script src='%s'></script>\n",
					$this->theme_url . '/' . $f
				);
			}
		}
	}
	return $out;
}

// returns the absolute URL for the current theme. mainly used within smarty templates
function url($theme = null) {
	if (empty($theme)) $theme = $this->theme();
	return $this->theme_url ? $this->theme_url . '/' . $theme : $theme;
}

// returns the absolute URL for the PARENT of the current theme. mainly used within smarty templates
function parent_url($theme = null) {
	if (empty($theme)) $theme = $this->theme();
	if ($this->loaded_themes[$theme]['parent']) $theme = $this->loaded_themes[$theme]['parent'];
	return $this->theme_url ? $this->theme_url . '/' . $theme : $theme;
}

// this is called by Smarty if a template file was not found.
// this allows us to output some actual useful information so the user can attempt to fix the error.
// if nothing is returned then smarty will simplay display its default warning message.
// *** NOT USED ***
/*
function no_template_found($resource_type, $resource_name, &$template_source, &$template_timestamp, &$smarty) {
	if ($resource_type == 'file') {
		if (!is_readable($resource_name)) {
			// create the template file, return contents.
			$template_source = "Template '$resource_name' not found! Do something about it!<br/><br/>\n\n";
			$template_timestamp = time();
//			$smarty->_write_file($resource_name,$template_source);
			return true;
		}
	} else {
		// not a file
		return false;
	}
//	return "Template '$tpl' not found! Do something about it!";
}
*/

// Add a new directory to search for templates in.
// New directories are added to the FRONT of the array. 
// So that each new directory added will be search first.
function template_dir($dir = null) {
	if ($dir === null) {
		return (array)$this->template_dir[0];
	} elseif (empty($this->template_dir)) {
		$this->template_dir = $dir;
	} elseif (is_array($this->template_dir)) {
		if (!in_array($dir, $this->template_dir)) {
			array_unshift($this->template_dir, $dir);
		}
	} else { // template_dir is a string 
		$this->template_dir = array_unique(array($dir, $this->template_dir));
	}
}

// remove a template directory from the search list.
// if only 1 directory is defined it can not be removed.
function remove_template_dir($dir) {
	if (is_array($this->template_dir)) {
		$newlist = array();
		for ($i=0; $i < count($this->template_dir); $i++) {
			if ($this->template_dir[$i] != $dir) {
				$newlist[] = $this->template_dir[$i];
			}
		}
		if (!count($newlist)) {			// convert it back to a string
			$this->template_dir = $this->template_dir[0];
		} elseif (count($newlist) == 1) {
			$this->template_dir = $newlist[0];
		} else {
			$this->template_dir = $newlist;
		}
	}
}

// get/set the current theme
function theme($new = null, $in_db = true) {
	global $ps;
	if (empty($new)) {
		return $this->theme;
	}
	if ($this->is_theme($new)) {
		$loaded = false;
		// load the theme from the database if possible
		$ps_installed = $this->cms->db->table_exists($this->cms->db->table('config_themes'));
		if ($ps_installed and $in_db) {
			$new = $this->cms->session->options['theme'] ??= null;
			$t = $this->cms->db->fetch_row(1, sprintf("SELECT * FROM %s WHERE name=%s and enabled <> 0", 
				$this->cms->db->table('config_themes'),
				$this->cms->db->escape($new, true)
			));
			if (!$t) {
				$new = $ps->conf['main']['theme'] ?? null;
				$t = $this->cms->db->fetch_row(1, sprintf("SELECT * FROM %s WHERE name=%s and enabled <> 0", 
					$this->cms->db->table('config_themes'),
					$this->cms->db->escape($new, true)
				));
			}
			if (!$t) {
				$new = 'default';
				$t = $this->cms->db->fetch_row(1, sprintf("SELECT * FROM %s WHERE name=%s and enabled <> 0", 
					$this->cms->db->table('config_themes'),
					$this->cms->db->escape($new, true)
				));
			}

			$this->loaded_themes[$new] = $t;
			$loaded = true;
            $t['parent'] ??= null;
			$this->loaded_themes[$t['parent']] ??= null;
			if ($t['parent'] and !$this->loaded_themes[$t['parent']]) { 
				// load the parent theme ...
				// the parent theme doesn't have to be enabled
				$p = $this->cms->db->fetch_row(1, sprintf("SELECT * FROM %s WHERE name=%s", 
					$this->cms->db->table('config_themes'),
					$this->cms->db->escape($t['parent'], true)
				));
				if ($p) {
					$this->loaded_themes[$t['parent']] = $p;
					$this->parent_themes[$new] = $t['parent'];
//					$this->child_themes[$t['parent']] = $new;
				}
			}
				
			// update the user's theme
			$this->cms->session->opt('theme', $new);
			$this->cms->session->save_session_options();
		} else {	
			// if we're not loading a theme from the DB then fudge a loaded record ...
			$this->loaded_themes[$new] ??= null;
			if (!$this->loaded_themes[$new] and !$in_db) {
				$loaded = true;
				$this->loaded_themes[$new] = array(
					'name' => $new,
					'parent' => null,
					'enabled' => 1, 
					'title' => $new,
					'description' => ''
				);
			}
		}

		// load the language for the theme
		if ($loaded) {
			$class = "PsychoLanguage_" . $new . "_" . $this->language();
			$file = catfile($this->language_dir($new), $this->language() . '.php');
			// if the language file doesn't exist in the current theme and there is a parent, check it instead.
			if (!file_exists($file) and isset($this->parent_themes[$new])) {
				$class = "PsychoLanguage_" . $this->parent_themes[$new] . "_" . $this->language();
				$file = catfile($this->language_dir($this->parent_themes[$new]), $this->language() . '.php');
			}
			
			// load the language file if it's available
			$ok = false;
			if (is_readable($file) and !class_exists($class)) {
				ob_start();
				$ok = (include_once $file);
				$err = ob_get_clean();
				if ($ok and !$err) {
					$this->lang = new $class();
				}
			} elseif (class_exists($class)) {
				$ok = true;
			}
			
			// if no language file can be loaded then create an
			// empty language instance to avoid undefined errors.
			if (!$ok) {
				if (defined("PS_THEME_DEV") and PS_THEME_DEV == true) {
					trigger_error("Error loading language class $class. <strong>Using default instead.</strong> See the errors and/or warnings below for more information", E_USER_WARNING);
					print $err ?? null;
				}
				$this->lang = new PsychoLanguage();
			}
		}

		$this->theme = $new;
		return $new;
	} else {
		trigger_error("Error loading theme $new. <strong>Using default instead.</strong> See the errors and/or warnings below for more information", E_USER_WARNING);
		print $err;

		$new = 'default';
		$this->theme = $new;
		return $new;
	}
}

// returns true if the theme is a child of another parent
function is_child($theme = null) {
	if (!isset($theme)) $theme = $this->theme();
	return isset($this->parent_themes[$theme]) ? $this->parent_themes[$theme] : false;
}

// returns true if the theme is a parent
function is_parent($theme = null) {
	if (!isset($theme)) $theme = $this->theme();
	return isset($this->loaded_themes[$theme]['parent']) ? false : $this->loaded_themes[$theme];
}

// returns the full path to the theme(=true) if the theme name specified is a valid directory within our template_dir
// if $enabled is true then it must also be enabled in the database
function is_theme($theme, $is_enabled = false) {
	if (empty($theme)) return false;
	foreach ((array)$this->template_dir as $path) {
		if (is_dir($path . DIRECTORY_SEPARATOR . $theme)) {
			if ($is_enabled) {
				list($ok) = $this->cms->db->fetch_list("SELECT 1 FROM " . $this->cms->db->table("config_themes") . " WHERE name LIKE " . $this->cms->db->escape($theme, true) . " AND enabled <> 0");
				if (!$ok) return false;
			}
			return $path . DIRECTORY_SEPARATOR . $theme;
		}
	}
	return false;
}

// get/set the current language
function language($new = null) {
	if ($new === null) {
		return $this->language;
	} else { //if ($this->is_language($new)) {
		$old = $this->language;
		$this->language = $new;
		return $old;
	}
}

// returns the path to the language dir of the theme
function language_dir($theme = null) {
	if (empty($theme)) $theme = $this->theme();
	return catfile($this->template_dir, $theme, 'language');
}

// Translate a string phrase, or return the original string if no translation is available.
function trans($str, $args = array()) {
    if (isset($str) && !empty($str)) return $this->lang->gettrans($str, $args);
    return $str;
}

// Returns true if the specified language is actually available in the current theme.
// This does not check languages from parent themes if the current theme is a child of another.
function is_language($language, $force = false) {
	static $list = array();
	if (!$this->loaded_themes) {
		return false;
	}
	foreach (array_keys($this->loaded_themes) as $theme) {
		if (!isset($list[$theme])) {
			$list[$theme] = $this->get_language_list($theme);
		}
		if (in_array($language, $list[$theme])) {
			return true;
		}
	}
	return false;
}

// returns a list of all languages found in the language directory of the theme.
function get_language_list($theme = null) {
	$theme_list = (array)($theme ? $theme : array_keys($this->loaded_themes));
	$langs = array();
	foreach ($theme_list as $t) {
		if (empty($t)) continue;
		$path = $this->language_dir($t);
		$dh = @opendir($path);
		if ($dh) {
			while (($file = readdir($dh)) !== false) {
				if (!is_file(catfile($path,$file)) or substr($file,0,1) == '.') continue;
				if (substr($file, -3) != 'php') continue;
				$langs[] = basename($file, '.php');
			}
		}
	}
	sort($langs);
	return array_unique($langs);
}

function get_theme_list() {
	$list = $this->cms->db->fetch_rows(1, "SELECT * FROM " . $this->cms->db->table('config_themes') . " WHERE enabled <> 0 ORDER BY title,name");
	return $list;
}

// override Smarty function so {include} continues to work with our directories
/**/
function _smarty_include($params) {
	$params['smarty_include_tpl_file'] = catfile($this->theme, $params['smarty_include_tpl_file']); 		///
	if ($this->debugging) {
		$_params = array();
		require_once(SMARTY_CORE_DIR . 'core.get_microtime.php');
		$debug_start_time = smarty_core_get_microtime($_params, $this);
		$this->_smarty_debug_info[] = array('type'      => 'template',
						'filename'  => $params['smarty_include_tpl_file'],
						'depth'     => ++$this->_inclusion_depth);
		$included_tpls_idx = count($this->_smarty_debug_info) - 1;
	}

	$this->_tpl_vars = array_merge($this->_tpl_vars, $params['smarty_include_vars']);

	// config vars are treated as local, so push a copy of the
	// current ones onto the front of the stack
	array_unshift($this->_config, $this->_config[0]);

	$_smarty_compile_path = $this->_get_compile_path($params['smarty_include_tpl_file']);

	if ($this->_is_compiled($params['smarty_include_tpl_file'], $_smarty_compile_path)
		|| $this->_compile_resource($params['smarty_include_tpl_file'], $_smarty_compile_path))
	{
		if ($this->fetch_compile) {										///
			include($_smarty_compile_path);
		} else {												///
			ob_start();
			$this->_eval('?>' . $this->_last_compiled);
			$_contents = ob_get_contents();
			ob_end_clean();
			print $_contents;
		}
	}

	// pop the local vars off the front of the stack
	array_shift($this->_config);

	$this->_inclusion_depth--;

	if ($this->debugging) {
		// capture time for debugging info
		$_params = array();
		require_once(SMARTY_CORE_DIR . 'core.get_microtime.php');
		$this->_smarty_debug_info[$included_tpls_idx]['exec_time'] = smarty_core_get_microtime($_params, $this) - $debug_start_time;
	}

	if ($this->caching) {
		$this->_cache_info['template'][$params['smarty_include_tpl_file']] = true;
	}
}

function _is_compiled($resource_name, $compile_path) {
	if ($this->fetch_compile) {
		return parent::_is_compiled($resource_name, $compile_path);
	} 
	return false;
}

function _compile_resource($resource_name, $compile_path) {
	$_params = array('resource_name' => $resource_name);
	if (!$this->_fetch_resource_info($_params)) {
		return false;
	}

	$_source_content = $_params['source_content'];
	$_cache_include = substr($compile_path, 0, -4).'.inc';

	if ($this->_compile_source($resource_name, $_source_content, $_compiled_content, $_cache_include)) {
		// if a _cache_serial was set, we also have to write an include-file:
		if ($this->_cache_include_info) {
			require_once(SMARTY_CORE_DIR . 'core.write_compiled_include.php');
			smarty_core_write_compiled_include(array_merge($this->_cache_include_info, array('compiled_content'=>$_compiled_content, 'resource_name'=>$resource_name)),  $this);
		}

		if ($this->fetch_compile) {
			$_params = array('compile_path'=>$compile_path, 'compiled_content' => $_compiled_content);
			require_once(SMARTY_CORE_DIR . 'core.write_compiled_resource.php');
			smarty_core_write_compiled_resource($_params, $this);
			$this->_last_compiled = '';
		} else {
			$this->_last_compiled = $_compiled_content;
		}

		return true;
	} else {
		return false;
	}
}

// returns the relative template filename if the template file is found within a loaded theme 
function template_found($filename, $update_theme = true, $get_source = false, $theme_name = null) {
	// if a dot is near the end of the file then assume an extension is present
	// and its not just part of the filename (ie: class.filename.ext)
	$pos = strrpos($filename, '.');
	$len = strlen($filename);
	if (!$pos || $len - $pos > 5) $filename .= ".html";
	$params = array('quiet' => true, 'get_source' => $get_source);
	foreach ($this->loaded_themes as $name => $theme) {
		if ($theme_name and $name != $theme_name) {
			continue;
		}
		$params['resource_name'] = $name . '/' . $filename;
		if ($this->_fetch_resource_info($params)) {
			$params['resource_theme'] = $name;
			if ($update_theme) $this->theme = $name;
			return $params;
		}
	}
	return false;
}

// fetch a template. Compiles the template if needed first.
function fetch($tpl, $cache_id = null, $compile_id = null, $display = false) {
	$res = is_array($tpl) ? $tpl : $this->template_found($tpl);
	$compile_id = $this->language() . '-' . $this->compile_id; 
	if ($res) {
		$tpl_file = $res['resource_name'];
		$compile_id = $this->theme . '-' . $compile_id;
	} else {
		$tpl_file = is_array($tpl) ? $tpl['resource_name'] : $tpl;
	}
	return parent::fetch($tpl_file, $cache_id, $compile_id, $display);
}

// fetch a template without saving to disk.
// this is not usually recommended due to performance issues.
function fetch_eval($tpl) {
	$res = is_array($tpl) ? $tpl : $this->template_found($tpl, true, true);
	if (!$res) {
		return '';
	}

	$source = $res['source_content'] ?? '';
	$compiled = '';

	$this->_compile_source('eval-template', $source, $compiled);

	ob_start();
	$this->_eval('?>' . $compiled);
	$contents = ob_get_contents();
	ob_end_clean();

	return $contents;
}

// Parses the template filename and appends it to the current buffer for output
// returns the output from the parsed template.
function parse($filename, $append_buffer = true) {
	// if a dot is near the end of the file then assume an extension is present
	// and its not just part of the filename (ie: class.filename.ext)
	$pos = strrpos($filename, '.');
	$len = strlen($filename);
	if (!$pos || $len - $pos > 5) $filename .= ".html";
	$orig = $this->theme();
//	print "parse($filename) orig=$orig\n";
	$out = $this->fetch_compile ? $this->fetch($filename) : $this->fetch_eval($filename);
	//$this->theme($orig);
	if ($append_buffer) $this->buffer .= $out;
	return $out;
}

// outputs the page to the user. Adds the timer, if $showtimer is true
function showpage($output = null, $showtimer = true) {
	global $TIMER;
	if ($output === null) $output = $this->buffer;
	if ($TIMER and $showtimer) {
		$output = str_replace('<!--PAGE_BENCHMARK-->', $TIMER->timediff(), $output);
	}

	// show debugging information if enabled... 
	if (defined('PS_DEBUG') and PS_DEBUG) {
		ps_debug($output);
	}

	print $output;
}

// changes the open and closing tags for filtering language strings in a template.
// @return array original values
function language_tags($open, $close = null) {
	$orig = array( $this->language_open, $this->language_close);
	if (is_array($open)) {
		list($open, $close) = $open;
	}
	if ($open) $this->language_open = $open;
	if ($close) $this->language_close = $close;
	return $orig;
}

function prefilter_language($tpl_source, &$smarty) {
	$regex = sprintf($this->language_regex, $this->language_open, $this->language_close);
	return preg_replace_callback($regex, array(&$this, "_compile_lang"), $tpl_source);
}

function _compile_lang($key) {
    $key['2'] = $key['2'] ?? null;
	if ($key[2]) {	// <!--<#KEYWORD#>-->english phrase here<!---->
		$text = $this->trans($key[1]);
		// if the translated text equals the key, then there is no translation 
		// and we should use the english phrase as-is.
		return $text == $key[1] ? $key[2] : $text;
	} else {	// <#english phrase here#>
		return $this->trans($key[1]);
	}
}

/**
 * output_filter
 * Called by smarty's output filter routines when loading a compiled theme.
 * This will add the current session ID to all relative links in the output.
 * But only if there was no session ID specified in the $_COOKIE array already
 *
 */
function output_filter($output, &$smarty) {
	// cookie was used for SID, so we don't need to do anything
	// or if the user client was detected as a bot the sid is not appended to urls
	if ($this->cms->session->sid_method() == 'cookie' or $this->cms->session->is_bot()) return $output;
	$sidname = $this->cms->session->sid_name(); 
	$sid = $this->cms->session->sid();
	$search = array();
	$replace = array();
	$amp = '&amp;';

	$tags = array(
		'a' => 'href', 
		'input' => 'src', 
		'form' => 'action', 
		'frame' => 'src', 
		'area' => 'href',
		'iframe' => 'src'
	);

	foreach ($tags as $tag => $attr) {
		if (!preg_match_all("'<" . $tag . "[^>]+>'si", $output, $matchlist, PREG_PATTERN_ORDER)) continue;
		foreach ($matchlist as $matches) {
			foreach ($matches as $match) {
				if (preg_match("/" . $attr . "\s*=\s*(([\"'])(.*?)\\2)/i", $match, $innermatch)) {
//					$match = <a href="team.php?id=7350" class="example">
//					$innermatch = Array(
//						[0] => href="team.php?id=7350"
//						[1] => "team.php?id=7350"
//						[2] => "
//						[3] => team.php?id=7350
//					)

					$url = $innermatch[3];

					if ($url == '') continue;	// don't append SID if the url is blank
					$quote = $innermatch[2];
					$oldattr = $innermatch[0];
					$newattr = "$attr=$quote$url";

					$query = parse_url($url);
					if (is_array($query)) {
						parse_str($query['query'], $urlargs);
						if (array_key_exists($sidname, $urlargs)) continue; 	// do not duplicate SID
					}

					if (strpos($url, '://') !== FALSE) continue;			// ignore absolute URLS
					if (strpos($url, 'javascript:') !== FALSE) continue;		// ignore javascript links

					$newattr .= (strpos($url, '?') === FALSE) ? '?' : $amp;	// append proper query separator
					$newattr .= "$sidname=$sid$quote";

					$search[] = $oldattr;
					$replace[] = $newattr;
				}
			}
		}
	}
	if (count($search)) $output = str_replace($search, $replace, $output);

	$this->cms->filter('session_output_filter', $output);
	return $output;
}

// sets the current page title. If $append is true, the $str provided will be appended to the current value.
// this can be enhanced later to provide interpolation of {$token} variables inside the string
function page_title($str, $append = false) {
	if ($append) {
		$this->_page_title .= $str;
	} else {
		$this->_page_title = $str;
	}
}

// loads a styles.xml file relative to the child and parent themes.
function load_styles($file = 'styles.xml', $theme = null) {
	if ($theme === null) {
		$theme = $this->theme();
	}
	$s = new PsychoThemeStyles();
	if (!$file) {
		$this->styles = $s;
		return $s;
	}
	
	$res = $this->template_found($file, false, false, $theme);
	if (!$res) {
		$this->styles = $s;
		return $s;
	}

	$orig = $this->language_tags('{#', '#}');
	//$orig_theme = $this->theme($theme);
	$s->load($this->fetch_compile ? $this->fetch($res) : $this->fetch_eval($res), 'styles');
	//$this->theme($orig_theme);
	$this->language_tags($orig);

	$this->styles = $s;
	//if ($this->is_parent($theme)) {
	//	$this->styles['parent'] = $s;
	//} else {
	//	$this->styles['child'] = $s;
	//	if ($this->is_child($theme)) {
	//		// load the parent theme if its present
	//		$this->load_styles($file, $this->is_child($theme));
	//	}
	//}
	
	return $s;
}

} // end of PsychoTheme

// basic styles class for loaded theme styles.
// allows for easy navigation and defaults for styles.
#[AllowDynamicProperties]
class PsychoThemeStyles {
var $xml = '';
var $styles = array();
var $def = null;

function __construct($xml = null, $root = 'styles') {
	if ($xml) {
		return $this->load($xml, $root);
	}
	return false;
}
 
function PsychoThemeStyles($xml = null, $root = 'styles') {
        self::__construct($xml, $root);
}

function load($xml, $root = 'styles') {
	$this->xml = $xml;
	require_once(PS_ROOTDIR . '/includes/class_simplexml.php');
	$this->sxml = new simplexml;
	$this->styles = $this->sxml->xml_load_string($xml, 'array');
	return $this->styles;
}

// specifies the default key to use when searching for a default value
function default_val($key) {
	if (!$this->styles) {
		return false;
	}
	$this->def = $this->_key($key);
	return $this->def;
}

// returns the value of a key (scalar or array). If the key is not found
// the $default is returned instead.
function val($key, $default = null, $literal = false, $ignore_content = false) {
	$k = $this->_key($key);
	if (is_null($k) || $k == '') {
		// if no default was given but a global default is available use it...
		if (is_null($default) and !is_null($this->def)) {
			$default = $this->def;
			$literal = true;
		}
		$k = $literal ? $default : $this->_key($default);
	}
	if (is_array($k) and !$ignore_content) {
		if (array_key_exists('@content', $k)) {
			$k = $k['@content'];
		}
	}
	return $k == '' ? null : $k;
}

// returns the attribute of a key (scalar or array). If the key is not found
// the $default is returned instead.
function attr($key, $default = null, $literal = false) {
	$k = $this->val($key, $default, $literal, true);
	$attr = null;
	//if (!$k) {
	//	$parts = explode('.', $key);
	//	$attr = array_pop($parts);
	//	$key = implode('.', $parts);
	//	$k = $this->val($key, $default, $literal, true);
	//}
	if ($k) {
		// make a numerically indexed array of elements
		$list = (array_key_exists(0, $k)) ? $k : array( $k );
		$newk = array();
		for ($i=0, $j=count($list); $i<$j; $i++) {
			if (array_key_exists('@attributes', $list[$i])) {
				if ($attr) {
					$newk[] = $list[$i]['@attributes'][$attr];
				} else {
					$newk[] = $list[$i]['@attributes'];
				}
			}
		}
		$k = $newk ? $newk : null;
	}
	return $k == '' ? null : $k;
}

// returns an array of attribute values from a node
function attr_list($key, $attr, $default = null, $literal = false) {
	$k = $this->val($key, $default, $literal, true);
	if ($k) {
		$list = array();
		for ($i=0, $j=count($k); $i<$j; $i++) {
			if ($k[$i]['@attributes']) {
				$list[] = $k[$i]['@attributes'][$attr];
			}
		}
		$k = $list;
	}
	return $k;
}

// Private: returns a pointer to the array that the $key points to.
function _key($key, $root = null) {
	$k = null;
	if (!$key) {
		return $k;
	}
	// separate the key into nodes
	$nodes = is_array($key) ? $key : explode('.', $key);

	if (!$root) {
		$root =& $this->styles;
	}
	
	// loop through nodes to create a path to the final element
	$found = true;
	while (count($nodes) > 1) {
		$node = array_shift($nodes);
		if (is_array($root) and array_key_exists($node, $root)) {
			$root =& $root[$node];
		} else {
			$found = false;
			break;
		}
	}
	
	if ($found) {
		$var = $nodes[0];
		// if the key exists then the node is another array pointing
		// to some content (or is a scalar string)
		if (array_key_exists($var, $root)) {
			if (is_array($root[$var])) {
				$k = $root[$var];
			} else {
				$k = $root[$var];
			}
		} elseif (array_key_exists('@attributes', $root)) {
			// the var doesn't exist, so we check attributes
			if (array_key_exists($var, $root['@attributes'])) {
				$k = $root['@attributes'][$var];
			}
		} elseif (array_key_exists('@content', $root)) {
			if (array_key_exists($var, $root['@content'])) {
				$k = $root['@content'][$var];
			}
		} else {
			// ... 
		}
	}
	
	return $k;
}

} // end of class PsychoThemeStyles

?>
