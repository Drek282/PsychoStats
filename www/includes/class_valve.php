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
 *	Version: $Id: class_valve.php 367 2008-03-17 17:47:45Z lifo $
 */

/**
	class_valve.php

	Basic Valve "Steam ID" to "Friend ID" translator.

	This class will allow you to translate a players Steamid into a Friend ID that the 
	Valve community website uses to identify players.

	Example of a valve player profile page: http://steamcommunity.com/profiles/123

	This class requires the BCMath routines to be enabled in PHP.

**/

class Valve_AuthId {

/**
* @var string $steam_community_url	Specifies the base URL to the steam community website.
* @access protected
*/
var $steam_community_url = "http://steamcommunity.com/profiles/";

function __construct() {
	// nothing to do
}

function Valve_AuthId() {
    self::__construct();
}

/**
* Converts the "Steam ID" given into a "Friend ID".
*
* @param string $steamid	A Steam ID.
*/
function get_friend_id($steamid) {
	if (!function_exists('bcmul')) {
//		trigger_error("BCMath extension is not available. Unable to create friend ID.", E_USER_WARNING);
		return false;
	}

	$parts = explode(':', $steamid);
	if (!$parts or strtoupper(substr($parts[0], 0, 5)) != 'STEAM') {
		trigger_error("Invalid STEAM ID passed to " . get_class($this) . "::get_friend_id($steamid)", E_USER_WARNING);
		return false;
	}

	// STEAM_0:<SERVER_ID>:<AUTH_ID>
	$server = $parts[1];
	$auth = $parts[2];

	// an Auth ID of 0 is invalid
	if ($auth == "0") {
		return "0";
	}

	$friend = bcmul($auth, "2");
	$friend = bcadd($friend, bcadd("76561197960265728", $server)); 
	
	return $friend;
}

/**
* Converts the "Friend ID" given into a "Steam ID".
*
* @param string $friendid	A Friend ID.
*/
function get_steam_id($friendid) {
	if (!function_exists('bcmod')) {
//		trigger_error("BCMath extension is not available. Unable to create friend ID.", E_USER_WARNING);
		return false;
	}
	$server = bcmod($friendid, "2") == "0" ? "0" : "1";
	$friendid = bcsub($friendid, $server);
	if (bccomp("76561197960265728",$friendid) == -1) {
		$friendid = bcsub($friendid, "76561197960265728");
	}
	$authid = bcdiv($friendid, "2");
	return "STEAM_0:" . $server . ":" . $authid;
}

/**
* Returns an fully qualified URL to the steamcommunity.com website for adding a friend
* to you profile.
*
* @param string $id	A Steam ID, or Friend ID. The type of ID is auto-discovered.
*/
function steam_add_friend_url($id) {
	if (!is_numeric($id)) {
		$id = @$this->get_friend_id($id);
		if (!$id) {
			return false;
		}
	}
	return "steam://friends/add/$id";
}

/**
* Returns an fully qualified URL to the steamcommunity.com website for the 
* steam_id or friend_id given.
*
* @param string $id	A Steam ID, or Friend ID. The type of ID is auto-discovered.
*/
function steam_community_url($id) {
	if (!preg_match('/^\d+$/', $id)) {
		$id = @$this->get_friend_id($id);
		if (!$id) {
			return false;
		}
	}
	return $this->get_steam_community_url() . $id;
}

/**
* Set's the $steam_community_url base URL.
*
* @param string $url	A fully qualified URL.
*/
function set_steam_community_url($url) {
	$this->steam_community_url = $url;
}

/**
* Returns the $steam_community_url base URL.
*/
function get_steam_community_url() {
	return $this->steam_community_url;
}

} // End of Valve_AuthId

?>
