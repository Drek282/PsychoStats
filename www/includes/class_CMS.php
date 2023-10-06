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
 *	Version: $Id: class_CMS.php 527 2008-08-03 16:06:41Z lifo $
 *
 *	PsychoStats CMS class
 *	First conceived on March 20th, 2007 by Stormtrooper
 *
 *      This is not a full Content Management System. It's "just enough" to be
 *      useful for PsychoStats but powerful enough to have some nifty features.
 *
 *      Depends: CMS/functions.php
 *      Optional Depends: class_DB.php, class_session.php, class_theme.php,
 *      class_table.php
 *      
 *      The CMS class attempts to make all input and output streams within
 *	PsychoStats easy to access.
 *      A plugin system is built into the IO stream to allow for 3rd party
 *      plugins. Plugins can also override some core functionality (ie: add
 *      different session support). Plugins use a class hierarchy to help avoid
 *	namespace collisions.
 *      Smarty templates are used for output and supports multi-language
 *	<#text#> strings.
 *      Using classes also opens up new posibilities in the future.
**/

if (defined("CLASS_CMS_PHP")) return 1;
define("CLASS_CMS_PHP", 1);

#[AllowDynamicProperties]
class PsychoCMS {
// I have to stay PHP4 compatible; so don't use public/private variables
var $user_class		= 'PsychoUser';
var $table_class	= 'PsychoTable';
var $form_class		= 'PsychoForm';
var $user 		= null;
var $session 		= null;
var $smarty		= null;
var $cookie		= array();	// _COOKIE
var $input 		= array();	// all input from GET/POST (not _REQUEST)
var $file		= array();	// _FILES
var $filters		= array();
var $postfilters	= array();
var $actions		= array();
var $plugins		= array();
var $plugin_errors 	= array();
var $plugin_warnings	= array();
var $breadcrumbs	= array();
var $plugin_dir;

function __construct($conf=array()) {
//    return $this->PsychoCMS($conf);
//}	// PHP5
//function PsychoCMS($conf=array()) {						// PHP4
	$conf += array(
		'dbhandle'	=> null,
		'session'	=> null,
		'plugin_dir'	=> '', 
	);
	if (empty($conf['plugin_dir'])) {
		$conf['plugin_dir'] = catfile(PS_ROOTDIR, 'plugins');
	}
	$this->conf = $conf;

	// initialize input; strip those annoying slashes if needed.
	// but lets be nice to the environment and not actually touch the global arrays.
    $this->input  = array_merge($_GET, $_POST);
    $this->file   = $_FILES;
    $this->cookie = $_COOKIE;

	// initialize the database connection
	if (isset($conf['dbhandle']) and is_object($conf['dbhandle'])) {
		$this->db =& $conf['dbhandle'];
	} else {
		require_once(__DIR__ . "/class_DB.php");
		$this->db = DB::create($conf);
	}

	// where are our plugins?
	$this->plugin_dir = $conf['plugin_dir'];
}
 
function PsychoCMS($conf=array()) {
        self::__construct($conf);
}

// must be called after 'new'
function init($quick = false) {
//	ob_start();

	if (!$quick) {
		// initialize the plugin system.
		// This must be done before we attempt to do any IO since everything past this point can use hooks
		$this->load_active_plugins();

		// import functions that haven't been overridden by plugins
		include_once(__DIR__ . '/CMS/functions.php');

		// this just creates the user instance. It does not attempt to auto login, etc.
		// this needs to be created before the session is started otherwise the auto_login will not work.
		// the session isn't valid yet but will be assigned in the init_user function
		$this->user =& $this->new_user();

		// start up the session. Pluggable function.
		if ($this->session == NULL) {
			ps_session_start($this);
		}

		// initialize the user. If we're logged in fetch the proper information, etc.
		$this->init_user();
	} else {
		include_once(__DIR__ . '/CMS/functions.php');
	}
}

// if the session has a user that is logged in, fetch their info from the database.
// called automatically from the init() method
function init_user() {
	// update the session 
	$this->user->session =& $this->session;
//	$this->filter('init_user', $this->user);

	$userid = $this->session->logged_in();
	if ($userid) {
		$this->user->load($userid);
	} else {
		// setup a guest/anonymous user
		$this->user->info(array(
			'userid'	=> 0,
			'username'	=> 'Guest', 	// $cms->trans("Guest"); theme isn't initialized yet, cant do this
			'password'	=> '',
			'confirmed'	=> 0,
			'lastvisit'	=> $this->session->session_start(),
			'session_last'	=> time(),
			'accesslevel'	=> 1, //PU_ACL_GUEST,
		));
	}
	$this->user->load_session_options();
}

// Initialize theme object.
// $theme is the name of the theme to use.
// $conf should be passed with an array of theme options.
function init_theme($theme, $opts = array()) {
	global $cookieconsent;
	require_once(__DIR__ . '/class_theme.php');
	require_once(__DIR__ . '/class_lang.php');
	$conf = array( 'theme' => $theme );
	if (is_array($opts)) {
		$conf = array_merge($conf, $opts);
	}
	// provide defaults (will not overwrite existing keys)
	$conf += array(
		'theme'			=> 'default',
		'theme_opt'		=> 'theme',	// option name of the cookie
		'theme_default'		=> 'default',
		'in_db'			=> true,
		'language'		=> 'en_US',
		'language_opt'		=> 'language',
		'compile_id'		=> '',
		'allow_user_change'	=> true,
		'fetch_compile'		=> true,
		'compile_dir'		=> '', 
		'template_dir'		=> null,
		'theme_url'		=> null,
		'cookieconsent'	=> $cookieconsent,
	);
	
	if (empty($conf['theme_url'])) $conf['theme_url'] = null;
	if (empty($conf['language'])) $conf['language'] = 'en_US';

//	print "<pre>"; print_r($conf); print "</pre>";

	if ($conf['fetch_compile'] and (empty($conf['compile_dir']) || !@is_writable($conf['compile_dir']))) {
		$temp = sys_get_temp_dir();
		if (php_sapi_name() == 'isapi') {
			$dir = $temp;
		} else {
			$dir = $temp ? catfile($temp, 'ps_themes_compiled') : 'ps_themes_compiled';
		}
		$conf['compile_dir'] = $dir;
	}

	if (!$conf['template_dir']) {
		$conf['template_dir'] = catfile(PS_ROOTDIR, 'themes');
	}

	if ($conf['allow_user_change'] && $this->session) {
		// use theme cookie if it's set
		$opt = $this->session->opt($conf['theme_opt']);
		if ($opt) $conf['theme'] = $opt;
	}

	if ($this->session) {
		// use language cookie if it's set
		$opt = $this->session->opt($conf['language_opt']);
		if ($opt) $conf['language'] = $opt;
	}

	$this->theme = new PsychoTheme($this, $conf);
	if ($this->theme->is_theme($conf['theme'], false)) {
		$this->theme->theme($conf['theme'], $conf['in_db']);
	} else {
		$this->theme->theme($conf['theme_default'], $conf['in_db']);
	}

	// initialize any post filters that were registered.
//	if (count($this->postfilters)) { }

	$this->theme->assign_by_ref('breadcrumbs', $this->breadcrumbs);

	if ($this->user) {
		// allow templates to access the user object but do not allow 
		// access to certain methods or properties (like password)
		$this->theme->register_object('user', $this->user, 
			array(	'userid', 'username', 'accesslevel', 'confirmed', 'options', 
				'lastvisit', 'has_access', 'is_admin', 'logged_in', 'hash', 'acl_str'
			), 
			false
		);

		// we also assign a basic $user variable to the template for easy access, since using an object
		// in the theme templates is not always 'neat' or even possible (inside {if} blocks)
		$this->theme->assign('user', $this->user->to_form_input());
	}


	// allow templates to access the session object.
	if ($this->session) {
		$this->theme->assign_by_ref('session_options', $this->session->options);
		$this->theme->register_object('session', $this->session, 
			array( 'sid', 'sid_method', 'is_bot', 'is_new', 'is_sid', 'opt' ),
			false
		);
	}
}

// loads a list of plugin files that were found but not installed
// this requires the active plugins to be loaded already
function load_pending_plugins($ignore_installed = false) {
	if (!$this->plugins and !$ignore_installed) $this->load_active_plugins();
	if (!is_dir($this->plugin_dir)) return array();

	$list = array();
	if ($dh = @opendir($this->plugin_dir)) {
		while (($file = readdir($dh)) !== false) {
			if (substr($file,0,1) == '.') continue;		// ignore hidden files
			if (substr($file,0,6) == 'index.') continue;
			$fullfile = catfile($this->plugin_dir, $file);
			$f = array();
			if (is_file($fullfile)) {
				$f = array(
					'base'	=> basename($file, '.php'),
					'file'	=> $file,
					'fullfile' => $fullfile,
					'path'	=> ''
				);
			} elseif (is_dir($fullfile)) {
				$fullfile = catfile($fullfile, $file . ".php");
				if (is_file($fullfile)) {
					$f = array(
						'base' => $file,
						'file'	=> $file . ".php",
						'fullfile' => $fullfile,
						'path'	=> catfile($file, '')
					);
				}
			} 
			if ($f) {
				$table = $this->db->table('plugins');
				$exists = $this->db->exists($this->db->table('plugins'), 'plugin', $f['base']);
				if (!$exists and !array_key_exists($f['base'], $this->plugins)) {
					$list[$f['base']] = $f;
				}
			}
		}
		closedir($dh);
	}

	return $list;
}

// load active plugins
function load_active_plugins() {
        $disabled = defined("PSYCHOSTATS_DISABLE_PLUGINS") ? PSYCHOSTATS_DISABLE_PLUGINS : false;
	$table = $this->db->table('plugins');
	$plugins = $this->db->fetch_rows(1, "SELECT plugin,version FROM $table WHERE enabled <> 0 ORDER BY idx");
	// load each plugin in order. plugins can be a single file or a sub-directory, 
	// either way the plugin class file will be its name with .php appended.
	foreach ($plugins as $p) {
		$plugin = $p['plugin'];
		$file = $this->plugin_dir . '/' . $plugin;
		if (is_dir($file)) {
			$file .= '/' . $plugin . '.php';
		} else {
			$file .= '.php';
		}

		if (!$disabled) {
			$ok = $this->include_plugin_file($file, $err);
			if ($ok) {	// even if there was an error $ok can still be true
				// create an object for the plugin and load it.
				$obj = new $plugin();
				$loaded = $obj->load($this);
				if ($loaded) {
					$this->plugins[ $plugin ] = $obj;
				} elseif (!empty($obj->errstr)) {
					$this->plugin_errors[ $plugin ][] = $obj->errstr;
				}
			} else {
				$this->plugin_errors[ $plugin ][] = "Error in include($plugin); Unable to load plugin $plugin.";
			}
			// this is done separately from above since an include can fail w/o an actual error string
			if ($err) {
				$this->plugin_errors[ $plugin ][] = $err;
			}
		} else {
			// even when plugins are not loaded we still need to track which ones would be loaded
			// so the plugins.php page can tell which ones are actually installed.
			$this->plugins[ $plugin ] = new PsychoPlugin();
		}
	}

	// sort all plugin hooks by priority
	$this->sort_filters();
	$this->sort_postfilters();
	$this->sort_actions();
}

function include_plugin_file($file, &$err) { 
	// trap all runtime errors in an output buffer
	ob_start();
	$ok = include_once($file);
	$err = trim(ob_get_contents());
	ob_end_clean();
	return $ok;
}

// uninstalls the plugin
function uninstall_plugin($plugin) {
	$exists = $this->db->exists($this->db->table('plugins'), 'plugin', $plugin);
	if (!$exists) return false;
	$p = null;
	if (array_key_exists($plugin, $this->plugins)) {
		$p =& $this->plugins[$plugin];
	} else {
		$file = catfile($this->plugin_dir,$plugin);
		if (is_dir($file)) {
			$file .= '/' . $plugin . '.php';
		} else {
			$file .= '.php';
		}

		$ok = $this->include_plugin_file($file, $err);
		$p = $ok ? new $plugin() : false;
		// do not $p->load() the plugin
	}
	$ok = $p ? $p->uninstall($this) : false;
	if ($ok) {
		$this->db->delete($this->db->table('plugins'), 'plugin', $plugin);
	}
	return $ok;
}

// installs a new plugin. If a plugin of the same name already exists in the database
// it will not be updated unless $overwrite is true (false by default).
function install_plugin($plugin, $info = array(), $overwrite = false) {
	$set = array(
		'plugin' 	=> $plugin,
		'version'	=> $info['version'],
		'description'	=> $info['description'],
		'installdate'	=> time(),
		'enabled'	=> 1,
		'idx'		=> $this->db->max($this->db->table('plugins'), 'idx') + 10
	);
	$exists = $this->db->exists($this->db->table('plugins'), 'plugin', $plugin);
	$ok = true;
	if (!$exists) {
		$ok = $this->db->insert($this->db->table('plugins'), $set);
	} elseif ($exists and $overwrite) {
		$ok = $this->db->update($this->db->table('plugins'), $set, 'plugin', $plugin);
	}
	return $ok;
}

function sort_filters() {
	foreach (array_keys($this->filters) as $hook) {
		ksort($this->filters[$hook], SORT_NUMERIC);
	}
}

function sort_postfilters() {
	foreach (array_keys($this->postfilters) as $hook) {
		ksort($this->postfilters[$hook], SORT_NUMERIC);
	}
}

function sort_actions() {
	foreach (array_keys($this->actions) as $hook) {
		ksort($this->actions[$hook], SORT_NUMERIC);
	}
}

function get_plugin_list() {
	return $this->plugins;
}

function get_filter_list() {
	return $this->filters;
}

function get_action_list() {
	return $this->actions;
}

// plugins call this to hook into a filter
//function register_filter(&$plugin, $hook, $postfilter = true, $priority = 100) {
function register_filter(&$plugin, $hook, $priority = 100) {
	$name = get_class($plugin);
	$priority = intval($priority);
	$filters =& $this->filters;
/*
	$filters = array();
	if ($postfilter) {
		$filters =& $this->postfilters;
	} else {
		$filters =& $this->filters;
	}
*/
	// verify if a filter for this hook was already defined by the plugin
	if (isset($filters[$hook][$priority])) {
		foreach($filters[$hook][$priority] as $filter) {
		     	if ($filter['name'] == get_class($plugin)) {
				return false;
			}
		}
	}
	if (!method_exists($plugin, 'filter_' . $hook)) {
		$this->plugin_warnings[$name][] = "Filter::$hook registration failed; No filter_$hook method available.";
		return false;
	}

	// add the filter to the specifed hook and priority
	$filters[$hook][$priority][] = array(
		'name'		=> $name,
		'plugin' 	=> $plugin,
	);
}

// plugins call this to hook into an action
function register_action(&$plugin, $hook, $final = false, $priority = 100) {
	$name = get_class($plugin);
	$priority = intval($priority);
	// verify if a action for this hook was already defined by the plugin
	if (isset($this->actions[$hook][$priority])) {
		foreach($this->actions[$hook][$priority] as $action) {
		     	if ($action['name'] == $name) {
				return false;
			}
		}
	}
	if (!method_exists($plugin, 'action_' . $hook)) {
		$this->plugin_warnings[$name][] = "Action::$hook registration failed; No action_$hook method available.";
		return false;
	}

	// add the action to the specifed hook and priority
	$this->actions[$hook][$priority][] = array(
		'name'		=> $name,
		'plugin' 	=> $plugin,
	);
}


// perform a filter hook
function filter($hook, &$string) {
	if (!array_key_exists($hook, $this->filters)) {	// no hooks are defined
		return false;
	}
	$args = array_slice(func_get_args(), 2);	// get any extra arguments

	$func = 'filter_' . $hook;
	foreach (array_keys($this->filters[$hook]) as $pri) {
		foreach ($this->filters[$hook][$pri] as $p) {
			$obj = $p['plugin'];
			$result = $obj->$func($string, $this, $args);
		}
	}
}

// perform an action hook
function action($hook) {
	if (!array_key_exists($hook, $this->actions)) {	// no hooks are defined
		return false;
	}
	$args = array_slice(func_get_args(), 1);	// get any extra arguments

	$func = 'action_' . $hook;
	foreach (array_keys($this->actions[$hook]) as $pri) {
		foreach ($this->actions[$hook][$pri] as $p) {
			$obj = $p['plugin'];
			$obj->$func($this, $args);
		}
	}
}

// output a full page which consists of overall header/footer and page content/header/footer.
function full_page($pagename, $content, $page_header = null, $page_footer = null, $prefix = '') { 
	$page = "";

	$out = $this->theme->parse($prefix . 'overall_header');
	$this->filter('overall_header', $out);
	$this->filter('overall_header_' . $pagename, $out);
	$page .= $out;

	if ($page_header and $this->theme->template_found($prefix . $page_header, false)) {
		$out = $this->theme->parse($prefix . $page_header);
		$this->filter('page_header', $out);
		$this->filter('page_header_' . $pagename, $out);
		$page .= $out;
	}

	$out = $this->theme->parse($prefix . $content);
	$this->filter('page_content', $out);
	$this->filter('page_content_' . $pagename, $out);
	$page .= $out;

	if ($page_footer and $this->theme->template_found($prefix . $page_footer, false)) {
		$out = $this->theme->parse($prefix . $page_footer);
		$this->filter('page_footer', $out);
		$this->filter('page_footer_' . $pagename, $out);
		$page .= $out;
	}

	$out = $this->theme->parse($prefix . 'overall_footer');
	$this->filter('overall_footer', $out);
	$this->filter('overall_footer_' . $pagename, $out);
	$page .= $out;

	$this->theme->showpage($page);
}

function full_page_err($pagename, $data = array()) {
	$this->theme->assign($data);
	$this->full_page($pagename, 'msg/error');
}

// output a lite page which consists of no headers and has a smaller footer.
// useful for pages that get embedded into iframes or popup windows.
function lite_page($pagename, $content, $prefix = '') {
	$page = "";

	$out = $this->theme->parse($prefix . $content);
	$this->filter('page_content', $out);
	$this->filter('page_content_' . $pagename, $out);
	$page .= $out;

	$out = $this->theme->parse($prefix . 'overall_footer_small');
	$this->filter('overall_footer', $out);
	$this->filter('overall_footer_' . $pagename, $out);
	$page .= $out;

	$this->theme->showpage($page);
}

// output a simple page which has no header or footer. Just a single content block.
// useful for very small embedded pages or ajax response pages.
function tiny_page($pagename, $content, $prefix = '') {
	$page = "";

	$out = $this->theme->parse($prefix . $content);
	$this->filter('page_content', $out);
	$this->filter('page_content_' . $pagename, $out);
	$page .= $out;

	$this->theme->showpage($page);
}

// returns a templated message block
function message($msgname, $data = array()) {
	$page = "";

	if ($data) $this->theme->assign($data);
	$page = $this->theme->parse('msg/' . $msgname);
//	$out = $this->theme->parse('msg/' . $msgname);
//	$this->filter('message', $out);
//	$this->filter('message_' . $pagename, $out);
//	$page .= $out;

	return $page;
}

// translate a string, allows for printf formating
function trans() {
	$args = func_get_args();
	$str = array_shift($args);
	return $this->theme->trans($str, $args);
}

// returns a new user object 
function & new_user() {
	if ($this->user_class == 'PsychoUser') {
		include_once(__DIR__ . '/class_user.php');
	}
	$class = $this->user_class;
	$u = new $class($this->session, $this->db);
	return $u;	// do not return new $class directly
}

// returns a new table object 
function & new_table($data = array()) {
	if ($this->table_class == 'PsychoTable') {
		include_once(__DIR__ . '/class_table.php');
	}
	$class = $this->table_class;
	$t = new $class($data);
	return $t;	// do not return new $class directly
}

// returns a new form object 
function & new_form($input = null) {
	if ($this->form_class == 'PsychoForm') {
		include_once(__DIR__ . '/class_form.php');
	}
	$class = $this->form_class;
	$f = new $class($input !== null ? $input : $this->input);
	return $f;	 // do not return new $class directly
}

// returns a list input values and optionally sets them in the global namespace as references
function input_vars($vars = array(), $globalize = false) {
	if (!is_array($vars)) return array();
	$list = array();
	foreach ($vars as $v) {
		if (array_key_exists($v, $this->input)) {
			$list[$v] = $this->input[$v];
			if ($globalize) {
				$GLOBALS[$v] = &$this->input[$v];
			}
		} 
	}
	return $list;
}

// Add a breadcrumb to the end of the list
function crumb($label, $link = false) {
	$this->breadcrumbs[] = $link ? array('label' => $label, 'link' => $link) : $label;
	return count($this->breadcrumbs);
}

// globalizes the request vars specified
function globalize_request_vars($list) {
	if (is_array($list)) {
		foreach ($list as $var) {
			$GLOBALS[$var] = &$this->input[$var];
		}
	}
}

} // end of PsychoCMS

// ------------------------------------------------------------------------------------------
// Parent class for PsychoStats plugins. All plugins must inherit this.
class PsychoPlugin {
	var $errstr = '';

//function __construct() { $this->PsychoPlugin(); }
function __construct() { /* nop */ }

function PsychoPlugin() { self::__construct(); }

// called when the plugin is loaded. This is called on every page request.
function load(&$cms) {
	/*
		abstract; children must override this to do something useful
		like registering hooks to plug in to.
		return FALSE if the plugin failed to load for some reason
	*/
	return true;
}

// called when a plugin is installed from the ACP.
// anything that has to be setup before the plugin can be used (like creating tables)
// should be done here. All plugins should overload this.
// if install was successful an array of information is returned containing the version
// and description for the plugin. returns false if there's an error.
function install(&$cms) { 
	$this->errstr = "No installation method available."; 
	return false; 
}

// called when a plugin is uninstalled from the ACP.
// plugins should always cleanup when being uninstalled. remove tables created, etc.
// return TRUE if uninstall was successful
function uninstall(&$cms) { return true; }

// called when a plugin needs to be upgraded.
// this provides a way for plugin authors to allow users to upgrade w/o having to reinstall
// return TRUE if upgrade was successful
function upgrade(&$cms) { return true; }

// return the current version of the plugin. 
// it's recommended that version strings stick with the PHP format: X.x or X.x.x-blah
function version() { return ''; }

function __toString() {	// PHP5 only
	return '[Plugin ' . get_class($this) . ']';
}

} // end of PsychoPlugin

// ------
?>
