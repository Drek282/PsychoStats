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
 *	Version: $Id$
 */

/***
	CMS_functions.php
	Functions that can be overridden by plugins.

	All functions here can be overridden by a plugin. 
	Only the FIRST plugin to override a function will actually succeed. 
	These functions provide features that mainly deal with how users are authenticated
	and how sessions are maintained. Other utility methods are also available. 
	A plugin could override functions to authenticate against a 3rd party software 
	suite (ie: integrate PsychoStats into your forum database). But for more advanced
	overrides a plugin will have to override the base 'user' and 'session' classes too.

***/

if (!function_exists('ps_auto_login')) { 
	/** 
	Used to automatically authenticate a user who is not logged in 
	but has a saved login cookie in their browser. The password will
	already be hashed.
	*/
	function ps_auto_login($id, $password) {
		global $cms;
		list($userid,$acl,$confirmed) = $cms->db->fetch_row(0, 
			"SELECT userid,accesslevel,confirmed " . 
			"FROM " . $cms->db->table('user') . " " . 
			"WHERE userid=" . $cms->db->escape($id,true) . " AND password=" . $cms->db->escape($password,true));
		if ($acl < 1 or !$confirmed) $userid = 0;
		return $userid;
	}
}

if (!function_exists('ps_session_start')) {
	/** 
	Starts up the session for the current page request
	*/
	require_once(rtrim(dirname(__DIR__), '/\\') . "/class_session.php");
	function ps_session_start(&$cms) {
		global $ps;
		$time = $ps->conf['main']['security']['cookie_life'];
		// do not allow less than 60 seconds for cookies; or users may lock themselves out of the site.
		if (!is_numeric($time) or $time < 60) $time = 60;
		$cms->session = new PsychoSession(array(
			'cms'			=> &$cms,
			'dbhandle'		=> &$cms->db,
			'db_session_table'	=> $cms->db->table('sessions'),
			'db_user_table'		=> $cms->db->table('user'),
			'db_user_session_last'	=> 'session_last',
			'db_user_login_key'	=> 'session_login_key',
			'db_user_last_visit'	=> 'lastvisit',
			'login_callback_func'	=> 'ps_auto_login',
			'cookiesalt'		=> $ps->conf['main']['security']['cookie_salt'],
			'cookiename'		=> 'ps_sess',
			'cookiepath'		=> '/',
			'cookiedomain'		=> '', 
			'cookielife'		=> $time ? $time : 60*60,
			'cookiecompress'	=> $ps->conf['main']['security']['cookie_compress'],
			'cookieencode'		=> $ps->conf['main']['security']['cookie_encode']
		));
	}
}

if (!function_exists('ps_user_is_admin')) {
	/** 
	Returns true if the current user has admin privileges
	*/
	function ps_user_is_admin() {
		global $cms;
		return $cms->user->is_admin();
	}
}

if (!function_exists('ps_user_logged_in')) {
	/** 
	Returns true if the current user is logged in
	*/
	function ps_user_logged_in() {
		global $cms;
		return $cms->session->logged_in();
	}
}

if (!function_exists('ps_url_wrapper')) {
	/**
	All url's within a theme pass through this wrapper.
	$url is an array of key=>value pairs for parameters. 
	See the url() function for more details.
	*/
	function ps_url_wrapper($url = array()) {
		return url($url);
	}
}

if (!function_exists('ps_date')) {
	/** 
	Returns a formatted date using the timestamp given. 
	The date will be offset by the configuration setting $theme.format.time_offset;
	*/
	function ps_date($fmt, $epoch = null, $ignore_ofs = false) {
		global $ps;
		static $ofs = null;
		// calculate the offset once...
		if (is_null($ofs)) {
			$ofs = $ps->conf['theme']['format']['time_offset'];
			if ($ofs) {
				$sign = substr($ofs,0,1);
				$neg = (bool)($sign == '-');
				if ($neg || $sign == '+') $ofs = substr($ofs,1);
	
				list($h,$m) = explode(':', $ofs);
				$h = (int)$h;
				$m = (int)$m;
				$ofs = 60*60*$h + 60*$m;
				if ($neg) $ofs *= -1;
			} else {
				$ofs = 0;	// make sure it's not null if time_offset is empty
			}
		}
		if (is_null($epoch)) $epoch = time();
		return date($fmt, $ignore_ofs ? $epoch : $epoch + $ofs);
	}
}

if (!function_exists('ps_datetime_stamp')) {
	/** 
	Used to return a quick date and time stamp. Used from certain theme routines.
	*/
	function ps_datetime_stamp($epoch, $fmt = null) {
		global $ps;
		if (!$fmt) $fmt = $ps->conf['theme']['format']['datetime'];
		if (empty($fmt)) $fmt = "Y-m-d H:i:s";
		return ps_date($fmt, $epoch);
	}
}

if (!function_exists('ps_date_stamp')) {
	/** 
	Used to return a quick date stamp. Used from certain theme routines.
	*/
	function ps_date_stamp($epoch, $fmt = null) {
		global $ps;
		if (!$fmt) $fmt = $ps->conf['theme']['format']['date'];
		if (empty($fmt)) $fmt = "Y-m-d";
		return ps_date($fmt, $epoch);
	}
}

if (!function_exists('ps_time_stamp')) {
	/** 
	Used to return a quick time stamp. Used from certain theme routines.
	*/
	function ps_time_stamp($epoch, $fmt = null) {
		global $ps;
		if (!$fmt) $fmt = $ps->conf['theme']['format']['time'];
		if (empty($fmt)) $fmt = "H:i:s";
		return ps_date($fmt, $epoch);
	}
}

if (!function_exists('ps_game_name')) {
	/**
	Returns the name of the installed game.
	*/
	function ps_game_name() {
		global $ps;
		$prefix = ($ps->conf['main']['modtype']) ? $ps->conf['main']['modtype'] : $ps->conf['main']['gametype'];
		if ($ps->conf['main']['modtype']) {
			$options = $ps->db->fetch_row(1, "SELECT options FROM $ps->t_config WHERE var='modtype' AND value='$prefix'");
			$options = implode($options);
			$options = explode("\n",$options);
			array_shift($options);
			foreach ($options as $o) {
				$oa = explode(':',$o);
				if ($oa[0] == $prefix) return $oa[1];
			}
			return false;
		}
		$options = $ps->db->fetch_row(1, "SELECT options FROM $ps->t_config WHERE var='gametype' AND value='$prefix'");
		$options = implode($options);
		$options = explode("\n",$options);
		array_shift($options);
		foreach ($options as $o) {
			$oa = explode(':',$o);
			if ($oa[0] == $prefix) return $oa[1];
		}
		return false;
	}
}

if (!function_exists('ps_title_logo')) {
	/**
	Returns the image name if the option to show title logos on primary stat pages has
	been enabled, and the image exists in the theme img folder in the correct format.
	*/
	function ps_title_logo($img = null) {
		global $cms, $ps;
		$bdary = array_reverse(explode('/', dirname(__FILE__)));
		array_shift($bdary);
		array_shift($bdary);
		array_shift($bdary);
		$basedir = implode('/', array_reverse($bdary));
		$basedir = $basedir . catfile($cms->theme->url(), 'img');

		// First check if logo file exists.
		if (!$img) {
			if ($ps->conf['theme']['permissions']['show_titlelogo'] and $ps->conf['theme']['titlelogo_name']) {
				$img = trim($ps->conf['theme']['titlelogo_name']);
				$file = catfile($basedir, $img);
				if (!file_exists($file)) return false;
			} elseif ($ps->conf['theme']['permissions']['show_titlelogo']) {
				$prefix = ($ps->conf['main']['modtype']) ? $ps->conf['main']['modtype'] : $ps->conf['main']['gametype'];
				$prefix = $prefix . '_logo';
				$ext = array_map('trim', explode(',', str_replace('.','', $ps->conf['theme']['images']['search_ext'])));
				$file = "";
				foreach ($ext as $e) {
					$img = $prefix . '.' . $e;
					$file = catfile($basedir, $img);
//					print "$file<br>";
					if (@file_exists($file)) break;
					$file = "";
				}
				if (!$file) return false;
			} else {
				return false;
			}
		} else {
			$img = trim($img);
			$file = catfile($basedir, $img);
			if (!file_exists($file)) return false;
		}

		// Check if logo file is the right dimensions.
		$dim = @getimagesize($file);
		return ($dim[0] === 64 && $dim[1] === 64) ? $img : false;
	}
}

if (!function_exists('ps_table_map_link')) {
	/**
	Called from the dynamic table class when creating a table that has a map <a> link.
	@param: $map contains stats for the current map. But mainly the $id is only needed.
	*/
	function ps_table_map_link($name, $map) {
		global $ps;
		$url = ps_url_wrapper(array( '_base' => 'map.php', 'id' => $map['mapid'] ));
		$img = $ps->mapimg($map, array( 'width' => 32, 'noimg' => '' ));
		return "<a class='map' href='$url'>$img</a>";
	}
}

if (!function_exists('ps_table_map_text_link')) {
	/**
	Called from the dynamic table class when creating a table that has a map <a> link.
	@param: $map contains stats for the current map. But mainly the $id is only needed.
	*/
	function ps_table_map_text_link($name, $map) {
		global $ps;
		$url = ps_url_wrapper(array( '_base' => 'map.php', 'id' => $map['mapid'] ));
//		$img = $ps->mapimg($map, array( 'width' => 32, 'height' => 24, 'noimg' => ''));
		return "<a class='map' href='$url'>" . ps_escape_html($name) . "</a>";
	}
}

if (!function_exists('ps_table_session_map_link')) {
	/**
	Called from the dynamic table class when creating a player session table that has a 
	map <a> link.
	@param: $plr contains stats for the current player. But mainly the $name is only needed.
	*/
	function ps_table_session_map_link($name, $sess) {
		global $ps;
		$url = ps_url_wrapper(array( '_base' => 'map.php', 'id' => $sess['mapid'] ));
//		$img = $ps->mapimg($map, array( 'width' => 32, 'height' => 24, 'noimg' => ''));
		return "<a class='map' href='$url'>" . ps_escape_html($name) . "</a>";
	}
}

if (!function_exists('ps_table_session_time_link')) {
	/**
	Called from the dynamic table class when creating a player session table that has a timestamp
	@param: $sess contains stats for the current player session.
	*/
	function ps_table_session_time_link($time, $sess) {
		global $ps;
		$time = ps_date_stamp($sess['sessionstart']);
		$time .= " @ " . ps_time_stamp($sess['sessionstart'],'H:i') . " - " . ps_time_stamp($sess['sessionend'],'H:i');
		return $time;
	}
}

if (!function_exists('ps_table_plr_link')) {
	/**
	Called from the dynamic table class when creating a table that has a plr <a> link.
	@param: $plr contains stats for the current plr. But mainly the $id is only needed.
	*/
	function ps_table_plr_link($name, $plr, $inc_icon = true, $inc_flag = true) {
		global $ps;
		$url = ps_url_wrapper(array( '_base' => 'player.php', 'id' => $plr['plrid'] ));
		$icons = ($inc_icon and $ps->conf['theme']['permissions']['show_plr_icons']) ? $ps->iconimg($plr['icon']) . ' ' : '';
		$flags = ($inc_flag and $ps->conf['theme']['permissions']['show_plr_flags']) ? $ps->flagimg($plr['cc']) . ' ' : '';
		return "<a class='plr' href='$url'>$flags$icons" . ps_escape_html($name) . "</a>";
	}
}

if (!function_exists('ps_table_victim_link')) {
	/**
	Called from the dynamic table class when creating a table that has a plr <a> link 
	for the players 'victim' table.
	@param: $plr contains stats for the current plr. But mainly the $id is only needed.
	*/
	function ps_table_victim_link($name, $plr, $inc_icon = true, $inc_flag = true) {
		global $ps;
		$url = ps_url_wrapper(array( '_base' => 'player.php', 'id' => $plr['victimid'] ));
		$icons = ($inc_icon and $ps->conf['theme']['permissions']['show_plr_icons']) ? $ps->iconimg($plr['icon']) . ' ' : '';
		$flags = ($inc_flag and $ps->conf['theme']['permissions']['show_plr_flags']) ? $ps->flagimg($plr['cc']) . ' ' : '';
		return "<a class='plr' href='$url'>$flags$icons" . ps_escape_html($name) . "</a>";
	}
}

if (!function_exists('ps_table_clan_link')) {
	/**
	Called from the dynamic table class when creating a table that has a clan <a> link.
	@param: $clan contains stats for the current clan. But mainly the $id is only needed.
	*/
	function ps_table_clan_link($name, $clan, $inc_icon = true, $inc_flag = true) {
		global $ps;
		$url = ps_url_wrapper(array( '_base' => 'clan.php', 'id' => $clan['clanid'] ));
		$icons = ($inc_icon and $ps->conf['theme']['permissions']['show_clan_icons']) ? $ps->iconimg($clan['icon']) . ' ' : '';
		$flags = ($inc_flag and $ps->conf['theme']['permissions']['show_clan_flags']) ? $ps->flagimg($clan['cc']) . ' ' : '';
		return "<a class='clan' href='$url'>$flags$icons" . ps_escape_html($name != '' ? $name : '-') . "</a>";
	}
}

if (!function_exists('ps_table_weapon_link')) {
	/**
	Called from the dynamic table class when creating a table that has a weapon <a> link.
	@param: $weapon contains stats for the current weapon. But mainly the $id is only needed.
	*/
	function ps_table_weapon_link($name, $weapon) {
		global $ps;
		$weapon['weaponid'] ??= null;
		$url = ps_url_wrapper(array( '_base' => 'weapon.php', 'id' => $weapon['weaponid'] ));
		$img = $ps->weaponimg($weapon);
		return "<a class='weapon' href='$url'>$img</a>";
	}
}

if (!function_exists('ps_table_weapon_text_link')) {
	/**
	Called from the dynamic table class when creating a table that has a weapon <a> link.
	@param: $weapon contains stats for the current weapon. But mainly the $id is only needed.
	*/
	function ps_table_weapon_text_link($name, $weapon) {
		global $ps;
		$url = ps_url_wrapper(array( '_base' => 'weapon.php', 'id' => $weapon['weaponid'] ));
		$name = $weapon['label'] ? $weapon['label'] : $name;
		return "<a class='weapon' href='$url'>" . ps_escape_html($name) . "</a>";
	}
}

if (!function_exists('ps_table_role_link')) {
	/**
	Called from the dynamic table class when creating a table that has a role <a> link.
	@param: $role contains stats for the current role. But mainly the $id is only needed.
	*/
	function ps_table_role_link($name, $role) {
		global $ps;
		$url = ps_url_wrapper(array( '_base' => 'role.php', 'id' => $role['roleid'] ));
		$img = $ps->roleimg($role);
		return "<a class='role' href='$url'>$img</a>";
	}
}

if (!function_exists('ps_escape_html')) {
	/**
	Escapes a string for output within the HTML themes. This should be used instead of 
	htmlentities() as that can mess up certain characters with UT8 encoded names.
	@param: $str contains the plain string to escape
	@param: $quote_style defines how to handle single and double quotes. Default is ENT_QUOTES
	which will escape both quotes.
	*/
	function ps_escape_html($str,$quote_style = ENT_QUOTES) {
		return htmlspecialchars($str ?? '', $quote_style, 'UTF-8');	// PHP >= 4.3.0
	}
}

if (!function_exists('ps_strip_tags')) {
	/** 
	Strips html tags from a string. Will also remove certain keywords like 'onmouseover', 'onclick', etc...
	By default the allowed_html_tags configuration option is used if $allowed is not specified.
	@param: $html contains the HTML string to strip
	@param: $allowed (optional) is a space separated list of tag names to NOT strip. By default 'allowed_html_tags' 
		configuration option is used. Set this to an empty string to not allow any tags.
	*/
	function ps_strip_tags($html, $allowed = null) {
		global $ps;
		if ($allowed == null) $allowed = $ps->conf['theme']['format']['allowed_html_tags'];
		if (!empty($allowed)) {
			$allowed = '<' . str_replace(',', '><', preg_replace('/\\s+/m', ',', $allowed)) . '>';
		} else {
			$allowed = '';
		}
		// repeat loop incase embedded tags are attempted (ie: <di<div>v>malicious</div>)
		// I'm not convinced this is needed ... but does not hurt at the moment.
		while ($html != strip_tags($html, $allowed)) {
			$html = strip_tags($html, $allowed);
		}
		return preg_replace_callback('/<(.*?)>/i', function($m) { return '<' . ps_strip_attribs($m[1]) . '>'; }, $html);
//		return preg_replace('/<(.*?)>/ie', "'<' . ps_strip_attribs('\\1') . '>'", $html);
	}
}

if (!function_exists('ps_strip_attribs')) {
	/**
	Disables harmful attributes from an HTML tag (eq: onclick, etc).
	See ps_strip_tags.
	@param $html is the text inside a tag w/o the angled brackets (ie: <...>)
	*/
	function ps_strip_attribs($html) {
		$attribs = 'javascript|on(?:dbl)?click|onmouse(?:click|over|out)|onkey(?:press|up|down)';
		$html = stripslashes(preg_replace("/([^\w](?:$attribs))(?!_disabled)/i", '\\1_disabled', $html));
		return $html;
	}
}

if (!function_exists('ps_user_can_edit_player')) {
	/**
	Returns true if the user can edit the player ID specified
	@param $plr is either an array of player info or a numeric ID to check against
	@param $user (optional) specifies the user object to check against, uses the logged in user if null
	*/
	function ps_user_can_edit_player($plr, $user = null) {
		global $cms, $ps;
		if ($user == null) $user =& $cms->user;
		if ($user->is_admin()) return true;
		if (!is_array($plr)) {
			$plrid = $plr;
			$plr = $ps->get_player_profile($plrid);
		}
		return ($user->logged_in() and $plr['userid'] == $user->userid());
	}
}

if (!function_exists('ps_user_can_edit_clan')) {
	/**
	Returns true if the user can edit the clan ID specified
	@param $clanid is the clanid to check against
	@param $plr is either an array of plr info (including clanid) or a numeric plr ID to check against
	@param $user (optional) specifies the user object to check against, uses the logged in user if null
	*/
	function ps_user_can_edit_clan($clanid, $plr = null, $user = null) {
		global $cms, $ps;
		if ($user == null) $user =& $cms->user;
		if ($user->is_admin()) return true;
		if (is_array($clanid)) {
			$clanid = $clanid['clanid'];
		}
		if (!is_array($plr)) {
			if ($plr == null) $plr = ps_user_plrid($user);
			$plrid = $plr;
			$plr = $ps->get_player_profile($plrid);
		}
		return ($user->logged_in() and $plr['userid'] == $user->userid() and $plr['clanid'] == $clanid);
	}
}

if (!function_exists('ps_user_plrid')) {
	/**
	Returns the plrid associated with the user provided.
	@param $user (optional) the user to match a player against, if no user is given the currently logged in user is used.
	*/
	function ps_user_plrid($user = null) {
		global $cms, $ps;
		if ($user == null) $user =& $cms->user;
		$plrid = $ps->db->fetch_item("SELECT p.plrid FROM $ps->t_plr p, $ps->t_plr_profile pp WHERE pp.uniqueid=p.uniqueid AND pp.userid=" . $ps->db->escape($user->userid(), true));
		return $plrid ? $plrid : 0;
	}
}

if (!function_exists('ps_ob_gzhandler')) {
	/**
	 * Output handler for all HTML based content. Default behavior will
	 * detect GZIP or DEFLATE support on the client and encode the HTML
	 * accordingly. A proper 'content-length' header is also sent to allow
	 * for persistent HTTP connections (PHP's built in ob_gzhandler does
	 * not send a content-length).
	 */
	function ps_ob_gzhandler($buffer, $flags) {
		// don't do anything if the buffer isn't being closed or if
		// headers were already sent.
		if (($flags & PHP_OUTPUT_HANDLER_END != PHP_OUTPUT_HANDLER_END) || headers_sent() || empty($buffer)) {
			return false;
		}

		$zipped = '';
		$original_length = strlen($buffer);
		$encoding = false;
		// build an array of accepted encodings
		$accept = (array)explode(",", str_replace(" ", "", strtolower($_SERVER['HTTP_ACCEPT_ENCODING'])));
		if (in_array("gzip", $accept)) {		// GZIP
			$zipped = gzencode($buffer);
			$encoding = 'gzip';
		} elseif (in_array('deflate', $accept)) {	// DEFLATE
			$zipped = gzcompress($buffer);
			$encoding = 'deflate';
		} else {
			$zipped =& $buffer;
		}
		$length = strlen($zipped);
		
		// don't send compressed output if the zipped length is
		// greater than the original (only occurs on small output)
		if ($length > $original_length) {
			$zipped = false;
			$length = strlen($buffer);
		}
		
		// provide content-length to allow HTTP persistent connections.
		// PHP's built in ob_gzhandler does not send this header
		header("Content-Length: " . $length, true);
		
		if ($zipped) {
			header("Vary: Accept-Encoding", true); 	// handle proxies
			header("Content-Encoding: " . $encoding, true);
			// add an information value showing how much compression we actually achieved.
			header("X-Compression: " . sprintf("%d/%d (%.02f%%)", $length, $original_length, abs($length / $original_length * 100 - 100)), true);
			return $zipped;
		} else {
			return $buffer;
		}
	}
}

if (!function_exists('ps_ob_handler')) {
	/**
	 * Output handler for all HTML based content. No compression.
	 * A proper 'content-length' header is also sent to allow
	 * for persistent HTTP connections.
	 */
	function ps_ob_handler($buffer, $flags) {
		// don't do anything if the buffer isn't being closed or if
		// headers were already sent.
		if (($flags & PHP_OUTPUT_HANDLER_END != PHP_OUTPUT_HANDLER_END) || headers_sent() || empty($buffer)) {
			return false;
		}

		// provide content-length to allow HTTP persistent connections.
		// PHP's built in ob_gzhandler does not send this header
		header("Content-Length: " . strlen($buffer), true);
		header("Vary: Accept-Encoding", true); 	// handle proxies

		return false; //$buffer;
	}
}

if (!function_exists('ps_debug')) {
	/**
	 * DEBUG handler. Outputs as much debugging information possible
	 * somewhere within the current theme output. 
	 */
	function ps_debug(&$buffer) {
		global $cms, $ps;
		$str = '';
		
		// output all queries sent
		$str .= "<ul class='ps-debug'>\n";
		foreach ($ps->db->queries as $q) {
			$str .= "<li>" . ps_escape_html($q) . "</li>\n";
		}
		$str .= "</ul>\n";
		
		// output any errors that occured
		if ($ps->db->errors) {
			$str .= "<ul class='ps-debug'>\n";
			foreach ($ps->db->errors as $e) {
				$str .= "\t<li><span class='error'>" . $e['error'] . "</span>\n\t<span class='query'>" . $e['query'] . "</span></li>\n";
			}
			$str .= "</ul>\n";
		}
		$buffer = str_replace('</body>', $str . '</body>', $buffer);
	}
}

?>
