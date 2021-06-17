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
 *	Version: $Id: PS.php 568 2008-10-16 18:38:19Z lifo $
 *	
 *	PsychoStats base class
 *
 *	Depends: class_DB.php
 *	Optional Depends: class_HTTP.php
 *
 *      PsychoStats class. This is a self contained API class for PsychoStats.
 *      It can be included almost anywhere to fetch stats from a PsychoStats
 *      database. The API is simple and does not require the user to know how
 *      stats are stored in the database. No other libraries (except the DB
 *      class) are needed.
 *	
 *      Sub-classes will override this base class to provide some extra
 *      functionality based on game::mod.
 *      
 *	Example:
 *		include("class_PS.php");
 *		$dbconf = array( ... DB settings ... );
 *		$ps = PsychoStats::create($dbconf);
 *
 *		$top100 = $ps->get_player_list(array( ... params ... ));
 *		print_r($top100);
 *
 *		$clans = $ps->get_clan_list(array( ... params ... ));
 *		print_r($clans);
 *		
 * @package PsychoStats
 * 
 */

if (defined("CLASS_PS_PHP")) return 1;
define("CLASS_PS_PHP", 1);

class PS {
var $use_roles = false;

/**
 *	The *_TYPES arrays are used for calculating clan statistics. 
 *	Each array generally matches the same array found in the back-end perl 
 *	arrays in lib/PS/Player.pm
 */
var $CLAN_TYPES = array(
	'onlinetime'		=> '+',
	'kills'			=> '+',
	'deaths'		=> '+', 
	'killsperdeath'		=> array( 'ratio', 'kills', 'deaths' ),
	'killsperminute'	=> array( 'ratio_minutes', 'kills', 'onlinetime' ),
	'headshotkills'		=> '+',
	'headshotkillspct'	=> array( 'percent', 'headshotkills', 'kills' ),
	'ffkills'		=> '+',
	'ffkillspct'		=> array( 'percent', 'ffkills', 'kills' ),
	'ffdeaths'		=> '+',
	'ffdeathspct'		=> array( 'percent', 'ffdeaths', 'deaths' ),
	'kills_streak'		=> '>',
	'deaths_streak'		=> '>',
	'damage'		=> '+',
	'shots'			=> '+',
	'hits'			=> '+',
	'shotsperkill'		=> array( 'ratio', 'shots', 'kills' ),
	'accuracy'		=> array( 'percent', 'hits', 'shots' ),
	'suicides'		=> '+', 
	'games'			=> '+',
	'rounds'		=> '+',
	'kicked'		=> '+',
	'banned'		=> '+',
	'cheated'		=> '+',
	'connections'		=> '+',
	'totalbonus'		=> '+',
	'lasttime'		=> '>',
);

var $CLAN_WEAPON_TYPES = array(
	'kills'			=> '+',
	'deaths'		=> '+',
	'ffkills'		=> '+',
	'ffkillspct'		=> array( 'percent', 'ffkills', 'kills' ),
	'headshotkills'		=> '+',
	'headshotkillspct'	=> array( 'percent', 'headshotkills', 'kills' ),
	'damage'		=> '+',
	'hits'			=> '+',
	'shots'			=> '+',
	'shot_chest'		=> '+',
	'shot_head'		=> '+',
	'shot_leftarm'		=> '+',
	'shot_leftleg'		=> '+',
	'shot_rightarm'		=> '+',
	'shot_rightleg'		=> '+',
	'shot_stomach'		=> '+',
	'accuracy'		=> array( 'percent', 'hits', 'shots' ),
	'shotsperkill'		=> array( 'ratio', 'shots', 'kills' ),
);

var $CLAN_ROLE_TYPES = array(
	'kills'			=> '+',
	'deaths'		=> '+',
	'ffkills'		=> '+',
	'ffkillspct'		=> array( 'percent', 'ffkills', 'kills' ),
	'headshotkills'		=> '+',
	'headshotkillspct'	=> array( 'percent', 'headshotkills', 'kills' ),
	'damage'		=> '+',
	'hits'			=> '+',
	'shots'			=> '+',
	'shot_chest'		=> '+',
	'shot_head'		=> '+',
	'shot_leftarm'		=> '+',
	'shot_leftleg'		=> '+',
	'shot_rightarm'		=> '+',
	'shot_rightleg'		=> '+',
	'shot_stomach'		=> '+',
	'accuracy'		=> array( 'percent', 'hits', 'shots' ),
	'shotsperkill'		=> array( 'ratio', 'shots', 'kills' ),
	'joined'		=> '+',
);

var $CLAN_MAP_TYPES = array(
	'games'			=> '+',
	'rounds'		=> '+',
	'kills'			=> '+',
	'deaths'		=> '+', 
	'killsperdeath'		=> array( 'ratio', 'kills', 'deaths' ),
	'killsperminute'	=> array( 'ratio_minutes', 'kills', 'onlinetime' ),
	'ffkills'		=> '+',
	'ffkillspct'		=> array( 'percent', 'ffkills', 'kills' ),
	'ffdeaths'		=> '+',
	'ffdeathspct'		=> array( 'percent', 'ffdeaths', 'deaths' ),
	'connections'		=> '+',
	'onlinetime'		=> '+',
	'lasttime'		=> '>',
);

var $CLAN_VICTIM_TYPES = array(
	'kills'			=> '+',
	'deaths'		=> '+', 
	'killsperdeath'		=> array( 'ratio', 'kills', 'deaths' ),
	'headshotkills'		=> '+',
	'headshotkillspct'	=> array( 'percent', 'headshotkills', 'kills' ),
);

var $PLR_SESSIONS_TYPES = array( 
#	plrid			=> '=',
	'mapid'			=> '=',
	'sessionstart'		=> '=',
	'sessionend'		=> '=',
	'skill'			=> '=',
	'prevskill'		=> '=',
	'kills'			=> '+',
	'deaths'		=> '+', 
	'killsperdeath'		=> array( 'ratio', 'kills', 'deaths' ),
#	'killsperminute'	=> array( 'ratio_minutes', 'kills', 'onlinetime' ),
	'headshotkills'		=> '+',
	'headshotkillspct'	=> array( 'percent', 'headshotkills', 'kills' ),
	'ffkills'		=> '+',
	'ffkillspct'		=> array( 'percent', 'ffkills', 'kills' ),
	'ffdeaths'		=> '+',
	'ffdeathspct'		=> array( 'percent', 'ffdeaths', 'deaths' ),
	'damage'		=> '+',
	'shots'			=> '+',
	'hits'			=> '+',
	'shotsperkill'		=> array( 'ratio', 'shots', 'kills' ),
	'accuracy'		=> array( 'percent', 'hits', 'shots' ),
	'suicides'		=> '+', 
);

var $db = null;
var $tblprefix = '';

var $explained = array();
var $conf = array();
var $conf_layout = array();

var $class = 'PS';

function PS(&$db) {
	$this->db =& $db;
	$this->tblprefix = $this->db->dbtblprefix;

	// normal tables ...
	$this->t_awards			= $this->tblprefix . 'awards';
	$this->t_awards_plrs		= $this->tblprefix . 'awards_plrs';
	$this->t_clan 			= $this->tblprefix . 'clan';
	$this->t_clan_profile 		= $this->tblprefix . 'clan_profile';
	$this->t_config 		= $this->tblprefix . 'config';
	$this->t_config_awards 		= $this->tblprefix . 'config_awards';
	$this->t_config_clantags 	= $this->tblprefix . 'config_clantags';
	$this->t_config_events 		= $this->tblprefix . 'config_events';
	$this->t_config_logsources 	= $this->tblprefix . 'config_logsources';
	$this->t_config_overlays 	= $this->tblprefix . 'config_overlays';
	$this->t_config_plrbans 	= $this->tblprefix . 'config_plrbans';
	$this->t_config_plrbonuses 	= $this->tblprefix . 'config_plrbonuses';
	$this->t_config_servers 	= $this->tblprefix . 'config_servers';
	$this->t_config_themes 		= $this->tblprefix . 'config_themes';
	$this->t_errlog 		= $this->tblprefix . 'errlog';
	$this->t_geoip_cc		= $this->tblprefix . 'geoip_cc';
	$this->t_geoip_ip		= $this->tblprefix . 'geoip_ip';
	$this->t_heatmaps 		= $this->tblprefix . 'heatmaps';
	$this->t_live_entities		= $this->tblprefix . 'live_entities';
	$this->t_live_events		= $this->tblprefix . 'live_events';
	$this->t_live_games		= $this->tblprefix . 'live_games';
	$this->t_map 			= $this->tblprefix . 'map';
	$this->t_map_data 		= $this->tblprefix . 'map_data';
	$this->t_map_hourly 		= $this->tblprefix . 'map_hourly';
	$this->t_map_spatial 		= $this->tblprefix . 'map_spatial';
	$this->t_plr 			= $this->tblprefix . 'plr';
	$this->t_plr_aliases 		= $this->tblprefix . 'plr_aliases';
	$this->t_plr_bans 		= $this->tblprefix . 'plr_bans';
	$this->t_plr_data 		= $this->tblprefix . 'plr_data';
	$this->t_plr_ids_name 		= $this->tblprefix . 'plr_ids_name';
	$this->t_plr_ids_ipaddr 	= $this->tblprefix . 'plr_ids_ipaddr';
	$this->t_plr_ids_worldid 	= $this->tblprefix . 'plr_ids_worldid';
	$this->t_plr_maps 		= $this->tblprefix . 'plr_maps';
	$this->t_plr_profile 		= $this->tblprefix . 'plr_profile';
	$this->t_plr_roles 		= $this->tblprefix . 'plr_roles';
	$this->t_plr_sessions 		= $this->tblprefix . 'plr_sessions';
	$this->t_plr_victims 		= $this->tblprefix . 'plr_victims';
	$this->t_plr_weapons 		= $this->tblprefix . 'plr_weapons';
	$this->t_plugins 		= $this->tblprefix . 'plugins';
	$this->t_role 			= $this->tblprefix . 'role';
	$this->t_role_data		= $this->tblprefix . 'role_data';
	$this->t_search_results		= $this->tblprefix . 'search_results';
	$this->t_sessions 		= $this->tblprefix . 'sessions';
	$this->t_state 			= $this->tblprefix . 'state';
	$this->t_user 			= $this->tblprefix . 'user';
	$this->t_weapon 		= $this->tblprefix . 'weapon';
	$this->t_weapon_data 		= $this->tblprefix . 'weapon_data';

	// load our main config ...
	$this->load_config(array('main','theme','info'));

	$this->tblsuffix = '_' . $this->conf['main']['gametype'] . '_' . $this->conf['main']['modtype'];

	// compiled player/game tables
	$this->c_map_data	= $this->tblprefix . 'c_map_data';
	$this->c_plr_data	= $this->tblprefix . 'c_plr_data';
	$this->c_plr_maps	= $this->tblprefix . 'c_plr_maps';
	$this->c_plr_roles	= $this->tblprefix . 'c_plr_roles';
	$this->c_plr_victims	= $this->tblprefix . 'c_plr_victims';
	$this->c_plr_weapons	= $this->tblprefix . 'c_plr_weapons';
	$this->c_weapon_data	= $this->tblprefix . 'c_weapon_data';
	$this->c_map_data	= $this->tblprefix . 'c_map_data';
	$this->c_role_data	= $this->tblprefix . 'c_role_data';
} // constructor

/*
    * function init_search
    * Generates a new unique search string (to be used with search_players())
    *
    * @return  string  A new unique search ID.
*/
function init_search() {
	$id = md5(uniqid(rand(), true));	
	return $id;
}

/*
    * function search_players
    * Performs a search on the DB for players matching the criteria specified.
    * 
    * @param  string  $search_id  The search ID to use for this search.
    * @param  string/array  $criteria  Array of options allows to change
    * the criteria very specifically. A string will be used as the text to
    * search for.
    * 
    * @return integer Total matches found.
*/
function search_players($search_id, $criteria) {
	global $cms;
	$plrids = array();
	
	// convert criteria string to an array
	if (!is_array($criteria)) {
		$criteria = array( 'phrase' => $criteria );
	}

	// assign criteria defaults
	$criteria += array(
		'phrase'	=> null,
		'mode'		=> 'contains', 	// 'contains', 'begins', 'ends', 'exact'
		'status'	=> '',		// empty, 'ranked', 'unranked'
	);
	// 'limit' is forced based on current configuration
	$criteria['limit'] = coalesce($this->conf['main']['security']['search_limit'], 1000);
	if (!$criteria['limit']) $criteria['limit'] = 1000;

	// do not allow blank phrases to be searched
	$criteria['phrase'] = trim($criteria['phrase']);
	if (is_null($criteria['phrase']) or $criteria['phrase'] == '') {
		return false;
	}

	// sanitize 'mode'
	$criteria['mode'] = strtolower($criteria['mode']);
	if (!in_array($criteria['mode'], array('contains', 'begins', 'ends', 'exact'))) {
		$criteria['mode'] = 'contains';
	}

	// sanitize 'status'
	$criteria['status'] = strtolower($criteria['status']);
	if (!in_array($criteria['status'], array('ranked', 'unranked'))) {
		$criteria['status'] = '';
	}

	// tokenize our search phrase
	$tokens = array();
	if ($criteria['mode'] == 'exact') {
		$tokens = array( $criteria['phrase'] );
	} else {
		$tokens = query_to_tokens($criteria['phrase']);
	}

	// build our WHERE clause
	$where = "";
	$inner = array();
	$outer = array();
	
	// loop through each field and add it to the 'where' clause.
	// Search plr, profile and ids
	foreach (array('p.uniqueid', 'pp.name', 'pp.email', 'n.name', 'w.worldid', 'ip.ipaddr') as $field) {
		foreach ($tokens as $t) {
			$token = $this->token_to_sql($t, $criteria['mode']);
			if ($field == 'ip.ipaddr') {
				// Does the token phrase look like an IPv4 IP address?
				// The IP is not verified to be a valid IP... I don't care.
				if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $t)) {
					$inner[] = "$field = INET_ATON('" . $this->db->escape($t) . "')";
				}
			} else {
				$inner[] = "$field LIKE '$token'";
			}
		}
		if ($inner) {
			$outer[] = $inner;
		}
		$inner = array();
	}

	// combine the outer and inner clauses into a where clause
	foreach ($outer as $in) {
		$where .= " (" . join(" AND ", $in) . ") OR ";
	}
	$where = substr($where, 0, -4);		// remove the trailing " OR "

	// perform search and find Jimmy Hoffa!
	// NOTE: SQL_CALC_FOUND_ROWS is MYSQL specific and would need to be
	// changed for other databases.
	$cmd  = "SELECT SQL_CALC_FOUND_ROWS DISTINCT p.plrid " .
		"FROM $this->t_plr p, $this->t_plr_profile pp, " .
		"$this->t_plr_ids_name n, $this->t_plr_ids_worldid w," .
		"$this->t_plr_ids_ipaddr ip " . 
		"WHERE p.uniqueid=pp.uniqueid AND p.plrid=n.plrid AND " .
		"p.plrid=w.plrid AND p.plrid=ip.plrid ";
	if ($criteria['status'] == 'ranked') {
		$cmd .= "AND p.allowrank=1 ";
	} elseif ($criteria['status' == 'unranked']) {
		$cmd .= "AND p.allowrank=0 ";
	} 
	$cmd .= "AND ($where) ";
	$cmd .= "LIMIT " . $criteria['limit'];
	$plrids = $this->db->fetch_list($cmd);
	$total = $this->db->fetch_item("SELECT FOUND_ROWS()");

	// delete any searches that are more than a few hours old
	$this->delete_stale_searches();

	// ps_search_results record for insertion
	$search = array(
		'search_id'	=> $search_id,
		'session_id'	=> $cms->session->sid(),
		'phrase'	=> $criteria['phrase'],
		'result_total'	=> count($plrids),
		'abs_total'	=> $total,
		'results'	=> join(',', $plrids),
		'query'		=> $cmd,
		'updated'	=> date('Y-m-d H:i:s'),
		
	);
	$ok = $this->save_search($search);
	
	return $ok ? count($plrids) : false;
}

/*
    * function save_search
    * Saves the results of a search done with search_players
    * 
    * @param  array  $search  Search paramters to save
    * 
    * @return string  Returns true if the search was saved, false otherwise.
*/
function save_search($search) {
	return $this->db->insert($this->t_search_results, $search);
}

/*
    * function get_search
    * Returns a saved search result
    * 
    * @param  string  $search  Search paramters to save
    * 
    * @return string  Returns true if the search was saved, false otherwise.
*/
function get_search($search) {
	if ($this->is_search($search)) {
		return $this->db->fetch_row(1, "SELECT * FROM $this->t_search_results WHERE search_id=" . $this->db->escape($search, true));
	}
	return array();
}

/*
    * function is_search
    * Determines if the search id given is an active search
    * 
    * @param  string  $search  Search ID string to validate.
    * 
    * @return boolean Returns true if the search is valid.
*/
function is_search($search) {
	if (!$search) return false;
	return $this->db->exists($this->t_search_results, 'search_id', $search);
	
}

/*
    * function delete_search
    * Deletes the search results assoicated with the search ID given.
    * 
    * @param  string  $search  Search ID to delete
    * 
    * @return boolean  True if successful
*/
function delete_search($search) {
	if ($this->is_search($search)) {
		return $this->db->delete($this->t_search_results, 'search_id', $search);
	}
	return false;
}

/*
    * function delete_stale_searches
    * Deletes stale searches more than a few hours old
    * 
    * @param  integer  $hours  Maximum hours allowed to be stale (Optional)
    * 
    * @return void
*/
function delete_stale_searches($hours = 4) {
	if (!is_numeric($hours) or $hours < 0) $hours = 4;
	$this->db->query("DELETE FROM $this->t_search_results WHERE updated < NOW() - INTERVAL $hours HOUR");
}

/*
    * function token_to_sql
    * Converts the token string into a SQL string based on the $mode given.
    * 
    * @param  string  $str  The token string
    * @param  string  $mode Token mode (contains, begins, ends, exact)
    * 
    * @return string  Returns the string ready to be used in a SQL statement.
*/
function token_to_sql($str, $mode) {
	$token = $this->db->escape($str);
	switch ($mode) {
		case 'begins': 	return $token . '%'; break;
		case 'ends': 	return '%' . $token; break;
		case 'exact': 	return $token; break;
		case 'contains':
		default:	return '%' . $token . '%'; break;
	}
}

// load a player's profile only. does not load any extra statistics.
// if a plrid doesn't have a matching profile then nulls are returned for each column except plrid.
// @param $key is either 'plrid' or 'uniqueid'
function get_player_profile($plrid, $key = 'plrid') {
	$plr = array();
	$cmd = "SELECT p.*,pp.* FROM ";
	if ($key == 'plrid') {
		$cmd .= "$this->t_plr p LEFT JOIN $this->t_plr_profile pp USING(uniqueid) WHERE p.plrid=";
	} else {
		$_key = $this->db->quote_identifier($key);
		$cmd .= "$this->t_plr_profile pp LEFT JOIN $this->t_plr p USING(uniqueid) WHERE pp.$_key=";
	}
	$cmd .= $this->db->escape($plrid, true);
	$plr = $this->db->fetch_row(1, $cmd);
	return $plr ? $plr : false;
}

// $args can be a player ID, or an array of arguments
function get_player($args = array(), $minimal = false) {
	if (!is_array($args)) {
		$id = $args;
		$args = array( 'plrid' => $id );
	}
	$args += array(
		'plrid'		=> 0,
		'minimal'	=> false, // if true, overrides all 'load...' options to false (or use $minimal parameter)
		'loadsessions'	=> 0,			// do not want sessions by default
		'loadmaps'	=> 1,
		'loadroles'	=> $this->use_roles,	// default is based on the MODTYPE
		'loadweapons'	=> 1,
		'loadvictims'	=> 1,
		'loadclan'	=> 1,
		'loadawards'	=> 0,			// no awards by default
		'loadnames'	=> 1,
		'loadipaddrs'	=> 1,
		'loadworldids'	=> 1,
		'loadgeoinfo'	=> 1,			// IP to city lookups? (requires an HTTP request)
		'loadcounts'	=> 1,
		'sessionsort'	=> 'kills',
		'sessionorder'	=> 'desc',
		'sessionstart'	=> 0,
		'sessionlimit'	=> 50,
		'weaponsort'	=> 'kills',
		'weaponorder'	=> 'desc',
		'weaponstart'	=> 0,
		'weaponlimit'	=> 50,
		'mapsort'	=> 'kills',
		'maporder'	=> 'desc',
		'mapstart'	=> 0,
		'maplimit'	=> 50,
		'rolesort'	=> 'kills',
		'roleorder'	=> 'desc',
		'rolestart'	=> 0,
		'rolelimit'	=> 50,
		'victimsort'	=> 'kills',
		'victimorder'	=> 'desc',
		'victimstart'	=> 0,
		'victimlimit'	=> 25,
		'idsort'	=> 'totaluses',
		'idorder'	=> 'desc',
		'idstart'	=> 0,
		'idlimit'	=> 10,
	);
	$plr = array();
	$id = $this->db->escape($args['plrid']);
	if (!is_numeric($id)) $id = 0;

	if ($minimal) $args['minimal'] = true;

	// Load overall player information
	$cmd  = "SELECT data.*,plr.*,pp.*,c.cn FROM ($this->c_plr_data as data, $this->t_plr as plr, $this->t_plr_profile pp) ";
	$cmd .= "LEFT JOIN $this->t_geoip_cc c ON c.cc=pp.cc ";
	$cmd .= "WHERE plr.plrid=data.plrid AND plr.plrid='$id' AND plr.uniqueid=pp.uniqueid ";
	$cmd .= "LIMIT 1 ";
	$plr = $this->db->fetch_row(1, $cmd);

	// Load player clan information
	if (!$args['minimal'] and $args['loadclan'] and $plr['clanid']) {
		$cmd  = "SELECT clan.*,cp.* FROM $this->t_clan clan, $this->t_clan_profile cp ";
		$cmd .= "WHERE clanid='" . $this->db->escape($plr['clanid']) . "' AND clan.clantag=cp.clantag ";
		$cmd .= "LIMIT 1";
		$plr['clan'] = $this->db->fetch_row(1, $cmd);
		$plr['clan']['totalmembers'] = $this->db->count($this->t_plr, '*', "clanid='" . $this->db->escape($plr['clanid']) . "'");
	} else {
		$plr['clan'] = array();
	}

	if (!$args['minimal'] and $args['loadcounts']) {
		if ($this->conf['main']['plr_save_victims']) {
			$plr['totalvictims'] 	= $this->db->count($this->c_plr_victims, '*', "plrid='$id'");
		}
		$plr['totalmaps'] 	= $this->db->count($this->c_plr_maps, '*', "plrid='$id'");
		$plr['totalweapons'] 	= $this->db->count($this->c_plr_weapons, '*', "plrid='$id'");
		$plr['totalroles'] 	= $this->db->count($this->c_plr_roles, '*', "plrid='$id'");
		//$plr['totalids'] 	= $this->db->count($this->t_plr_ids, '*', "plrid='$id'");
		$plr['totalsessions'] 	= $this->db->count($this->t_plr_sessions, '*', "plrid='$id'");
		$plr['totalawards'] 	= $this->db->count($this->t_awards, '*', "topplrid='$id'");
	}

	if (!$args['minimal'] and $args['loadsessions']) {
		$plr['sessions'] = $this->get_player_sessions(array(
			'plrid'		=> $args['plrid'],
			'start'		=> $args['sessionstart'],
			'limit'		=> $args['sessionlimit'],
			'sort'		=> $args['sessionsort'],
			'order'		=> $args['sessionorder'],
		), $s);
	}

	// Load weapons for the player
	if (!$args['minimal'] and $args['loadweapons']) {
		$cmd  = "SELECT data.*,w.*,COALESCE(w.name,w.uniqueid) label FROM $this->c_plr_weapons AS data, $this->t_weapon AS w ";
		$cmd .= "WHERE data.plrid='$id' AND w.weaponid=data.weaponid ";
		$cmd .= $this->getsortorder($args, 'weapon');
		$plr['weapons'] = $this->db->fetch_rows(1, $cmd);
	}

	// Load roles for the player
	if (!$args['minimal'] and $args['loadroles']) {
		$cmd  = "SELECT data.*,r.* FROM $this->c_plr_roles AS data, $this->t_role AS r ";
		$cmd .= "WHERE data.plrid='$id' AND r.roleid=data.roleid ";
		$cmd .= $this->getsortorder($args, 'role');
		$plr['roles'] = $this->db->fetch_rows(1, $cmd);
	}

	// Load maps for the player
	if (!$args['minimal'] and $args['loadmaps']) {
		$cmd  = "SELECT data.*,m.* FROM $this->c_plr_maps AS data, $this->t_map AS m ";
		$cmd .= "WHERE data.plrid='$id' AND m.mapid=data.mapid ";
		$cmd .= $this->getsortorder($args, 'map');
		$plr['maps'] = $this->db->fetch_rows(1, $cmd);
	}

	// Load victim for the player
	if (!$args['minimal'] and $args['loadvictims'] and $this->conf['main']['plr_save_victims']) {
		$cmd  = "SELECT plr.*,pp.*,v.* FROM $this->c_plr_victims AS v, $this->t_plr as plr, $this->t_plr_profile pp ";
		$cmd .= "WHERE v.plrid='$id' AND v.victimid=plr.plrid AND pp.uniqueid=plr.uniqueid ";
		$cmd .= $this->getsortorder($args, 'victim');
		$plr['victims'] = $this->db->fetch_rows(1, $cmd);
	}

	// Load player identities.
	if (!$args['minimal']) {
		$loadlist = array();
		if ($args['loadnames']) $loadlist[] = 'name';
		if ($args['loadipaddrs']) $loadlist[] = 'ipaddr';
		if ($args['loadworldids']) $loadlist[] = 'worldid';
		if ($loadlist) {
			foreach ($loadlist as $v) {
				$tbl = $this->{'t_plr_ids_' . $v};
				$ip = $v == 'ipaddr' ? 'INET_NTOA(ipaddr) ipstr,' : '';
				$cmd  = "SELECT $ip$v,$v id,totaluses FROM $tbl WHERE plrid='$id' ";
				$cmd .= $this->getsortorder($args, 'id');
				$plr['ids_' . $v] = $this->db->fetch_rows(1, $cmd);
#				print "<pre>"; print_r($plr['ids_'.$v]); print "</pre>";
			}
		}
	}

	// geocode IP addresses. Only does the first 10 IP's. Lets not overload my server with lookups.
/*
	// this is disabled until ip_lookup is recoded to allow a csv return result
	if (!$args['minimal'] and $this->conf['theme']['map']['google_key'] and $args['loadipaddrs'] and $args['loadgeoinfo']) {
		$list = array_slice($plr['ids_ipaddr'], 0, 10);
		$ipstr = key_join(',', $list, 'ipstr');
		$csv = explode("\n", $this->ip_lookup($ipstr));
		$hdr = explode(',',array_shift($csv));
		$i = 0;
		foreach ($csv as $data) {
			$data = trim($data);
			if (!$data) continue;
			$info = explode(',',$data);
			foreach ($hdr as $key => $var) {
				if ($var == 'requested_ip') continue;
				$plr['ids_ipaddr'][$i][$var] = $info[$key];
			}
			$plr['ids_ipaddr'][$i]['flagimg'] = $this->flagimg($plr['ids_ipaddr'][$i]['country_code']);
			$i++;
		}
		print_r($plr['ids_ipaddr']);
	}
*/

	return $plr;
}
function not0($a) { return ($a != '0.0.0.0'); }

// returns a list of per-day stats for a player. Each element in the list is a single day.
function get_player_days($args = array()) {
	$args += array(
		'plrid'		=> $id,
		'sort'		=> 'statdate',
		'order'		=> 'desc',
		'start'		=> 0,
		'limit'		=> 30
	);

	$list = array();
	$cmd  = "SELECT * ";
	if ($this->db->type() == 'mysql') {
		$cmd .= ", " . 
			"IFNULL(headshotkills / kills * 100, 0.00) headshotkillspct, " .
			"IFNULL(hits / shots * 100, 0.00) accuracy, " .
			"IFNULL(kills / deaths, kills) killsperdeath, " .
			"IFNULL(kills / (onlinetime / 60), kills) killsperminute ";
	} elseif ($this->db->type == 'sqlite') {
		$cmd .= " ";
	}
	$cmd .= "FROM $this->t_plr_data ";
	$cmd .= "WHERE plrid='" . $this->db->escape($args['plrid']) . "'";
	$cmd .= $this->getsortorder($args);
	$list = $this->db->fetch_rows(1, $cmd);

	return $list;
}

function get_player_sessions($args = array()) {
	$args += array(
		'plrid' 	=> 0,
		'sort'		=> 'sessionstart',
		'order'		=> 'desc',
		'start'		=> 0,
		'limit'		=> 10,
		'fields'	=> '',
	);

	$fields = !empty($args['fields']) ? explode(',',$args['fields']) : array_keys($this->PLR_SESSIONS_TYPES);
	$key = array_search('mapid', $fields);
	if ($key !== false) $fields[$key] = 's.mapid';	// mapid will be ambiguous if we don't prefix it
	$values = $this->_calcvalues($fields, $this->PLR_SESSIONS_TYPES);

	$cmd  = "SELECT $values, sessionend-sessionstart AS online, COALESCE(m.name,m.uniqueid,'unknown') mapname ";
	$cmd .= "FROM $this->t_plr_sessions s ";
	$cmd .= "LEFT JOIN $this->t_map m USING(mapid) ";
	$cmd .= "WHERE plrid='" . $this->db->escape($args['plrid']) . "'";
	$cmd .= $this->getsortorder($args);
	$list = array();
	$list = $this->db->fetch_rows(1, $cmd);
	return $list;
}

function get_player_awards($args = array()) {
	$args += array(
		'plrid' 	=> 0,
		'sort'		=> 'awardname',
		'order'		=> 'asc',
	);
	$cmd  = "SELECT ap.plrid,a.awardname,ap.value,a.awarddate FROM $this->t_awards_plrs ap, $this->t_awards a ";
	$cmd .= "WHERE a.id=ap.awardid AND ap.plrid='" . $this->db->escape($args['plrid']) . "'";
	$cmd .= $this->getsortorder($args);
	$list = array();
	$list = $this->db->fetch_rows(1, $cmd);
	return $list;
}

function get_clan($args = array(), $minimal = false) {
	if (!is_array($args)) {
		$id = $args;
		$args = array( 'clanid' => $id );
	}
	$args += array(
		'clanid'	=> 0,
		'minimal'	=> false, // if true, overrides all 'load...' options to false (or use $minimal parameter)
		'fields'	=> '',
		'allowall'	=> 0,
		'loadmaps'	=> 1,
		'loadroles'	=> 1,
		'loadweapons'	=> 1,
		'loadmembers'	=> 1,
		'loadvictims'	=> 1,
		'loadcounts'	=> 1,
		'membersort'	=> 'kills',
		'memberorder'	=> 'desc',
		'memberstart'	=> 0,
		'memberlimit'	=> 25,
		'memberfields'	=> '',
		'weaponsort'	=> 'kills',
		'weaponorder'	=> 'desc',
		'weaponstart'	=> 0,
		'weaponlimit'	=> 50,
		'weaponfields'	=> '',
		'mapsort'	=> 'kills',
		'maporder'	=> 'desc',
		'mapstart'	=> 0,
		'maplimit'	=> 50,
		'mapfields'	=> '',
		'rolesort'	=> 'kills',
		'roleorder'	=> 'desc',
		'rolestart'	=> 0,
		'rolelimit'	=> 50,
		'rolefields'	=> '',
		'victimsort'	=> 'kills',
		'victimorder'	=> 'desc',
		'victimstart'	=> 0,
		'victimlimit'	=> 25,
		'victimfields'	=> '',
	);
	$clan = array();
	$id = $this->db->escape($args['clanid']);
	if (!is_numeric($id)) $id = 0;

	if ($minimal) $args['minimal'] = true;

	$values = "clan.clanid,clan.locked,clan.allowrank,cp.*,COUNT(distinct plr.plrid) totalmembers, " . 
		"ROUND(AVG(skill),0) skill, ROUND(AVG(activity)) activity, ";

	$types = $this->get_types('CLAN');
	$fields = !empty($args['fields']) ? explode(',',$args['fields']) : array_keys($types);
	$values .= $this->_values($fields, $types);

	$cmd  = "SELECT $values ";
	$cmd .= "FROM $this->c_plr_data data, $this->t_plr plr, $this->t_clan clan, $this->t_clan_profile cp ";
	$cmd .= "WHERE clan.clanid=$id AND plr.clanid=clan.clanid AND clan.clantag=cp.clantag AND data.plrid=plr.plrid ";
	if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
	if (trim($args['where']) != '') $cmd .= "AND (" . $args['where'] . ") ";
	$cmd .= "GROUP BY plr.clanid ";
	$cmd .= $this->getsortorder($args);
	$clan = $this->db->fetch_row(1, $cmd);

	if (!$args['minimal'] and $args['loadcounts']) {
		$cmd  = "SELECT COUNT(DISTINCT mapid) FROM $this->c_plr_maps data, $this->t_plr plr ";
		$cmd .= "WHERE plr.clanid='$id' AND plr.plrid=data.plrid ";
		if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
		$clan['totalmaps'] = $this->db->fetch_item($cmd);

		$cmd  = "SELECT COUNT(DISTINCT weaponid) FROM $this->c_plr_weapons data, $this->t_plr plr ";
		$cmd .= "WHERE plr.clanid='$id' AND plr.plrid=data.plrid ";
		if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
		$clan['totalweapons'] = $this->db->fetch_item($cmd);

		$cmd  = "SELECT COUNT(DISTINCT victimid) FROM $this->c_plr_victims data, $this->t_plr plr ";
		$cmd .= "WHERE plr.clanid='$id' AND plr.plrid=data.plrid ";
		if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
		$clan['totalvictims'] = $this->db->fetch_item($cmd);
	}

	if (!$args['minimal'] and $args['loadmembers']) {
		$clan['members'] = $this->get_player_list(array(
			'where' => "plr.clanid='$id'",
			'sort'	=> $args['membersort'],
			'order' => $args['memberorder'],
			'start' => $args['memberstart'],
			'limit' => $args['memberlimit'],
			'fields'=> $args['memberfields'],
//			'allowall' => 1,
			'allowall' => $args['allowall'],
		),$s);
	}

	// Load weapons for the clan
	if (!$args['minimal'] and $args['loadweapons']) {
		$values = "w.*,";
		$fields = !empty($args['weaponfields']) ? explode(',',$args['weaponfields']) : array_keys($this->CLAN_WEAPON_TYPES);
		$values .= $this->_values($fields, $this->CLAN_WEAPON_TYPES);
		$cmd  = "SELECT $values FROM $this->c_plr_weapons data, $this->t_weapon w, $this->t_plr plr ";
		$cmd .= "WHERE plr.plrid=data.plrid AND plr.clanid='$id' AND w.weaponid=data.weaponid ";
		if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
		$cmd .= "GROUP BY data.weaponid ";
		$cmd .= $this->getsortorder($args, 'weapon');
		$clan['weapons'] = $this->db->fetch_rows(1, $cmd);
	}

	// Load maps for the clan
	if (!$args['minimal'] and $args['loadmaps']) {
		$values = "m.*,";
		$map_types = $this->get_types("CLAN_MAP");
		$fields = !empty($args['mapfields']) ? explode(',',$args['mapfields']) : array_keys($map_types);
		$values .= $this->_values($fields, $map_types);
		$cmd  = "SELECT $values FROM $this->c_plr_maps data, $this->t_map m, $this->t_plr plr ";
		$cmd .= "WHERE plr.plrid=data.plrid AND plr.clanid='$id' AND m.mapid=data.mapid ";
		if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
		$cmd .= "GROUP BY data.mapid ";
		$cmd .= $this->getsortorder($args, 'map');
		$clan['maps'] = $this->db->fetch_rows(1, $cmd);
	}

	// Load victim for the clan
	if (!$args['minimal'] and $args['loadvictims']) {
		$values = "v.*,vp.*,";
		$fields = !empty($args['victimfields']) ? explode(',',$args['victimfields']) : array_keys($this->CLAN_VICTIM_TYPES);
		$values .= $this->_values($fields, $this->CLAN_VICTIM_TYPES);
		$cmd  = "SELECT $values FROM $this->c_plr_victims data, $this->t_plr v, $this->t_plr plr, $this->t_plr_profile vp ";
		$cmd .= "WHERE plr.plrid=data.plrid AND plr.clanid='$id' AND v.plrid=data.victimid AND vp.uniqueid=v.uniqueid ";
		if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
		$cmd .= "GROUP BY data.victimid ";
		$cmd .= $this->getsortorder($args, 'victim');
		$clan['victims'] = $this->db->fetch_rows(1, $cmd);
//		print "explain " . $this->db->lastcmd . ";";
	}

/*
    // Load roles for the player
    if (!$args['minimal'] and $args['loadroles']) {
      $cmd  = "SELECT data.*,roleid,def.name,def.desc FROM ($this->tblplrroles AS data) ";
      $cmd .= "LEFT JOIN $this->tbldefroles AS def ON def.id=data.roleid ";
      $cmd .= "WHERE data.plrid='$id' ";
      $cmd .= $this->getsortorder($args, 'role');
      $plr['roles'] = $this->db->fetch_rows(1, $cmd);
    }
*/

	return $clan;
}

// load a clan's profile only. does not load any extra statistics.
// if a clanid doesn't have a matching profile then nulls are returned for each column except clanid.
// @param $key is either 'clanid' or 'clantag'
function get_clan_profile($clanid, $key = 'clanid') {
	$clan = array();
	// the clantag from the profile is returned as 'profile_clantag' is calling routines can determine
	// if a profile actually existed for a clanid there's no other way to determine if a profile actually matched
	// since I'm using LEFT JOINs here.
	// so if 'profile_clantag' is not null then a profile was found
	$cmd = "SELECT c.*,cp.*,cp.clantag profile_clantag FROM ";
	if ($key == 'clanid') {
		$cmd .= "$this->t_clan c LEFT JOIN $this->t_clan_profile cp USING(clantag) WHERE c.clanid=";
	} else {
		$_key = $this->db->quote_identifier($key);
		$cmd .= "$this->t_clan_profile cp LEFT JOIN $this->t_clan c USING(clantag) WHERE cp.$_key=";
	}
	$cmd .= $this->db->escape($clanid, true);
	$clan = $this->db->fetch_row(1, $cmd);
	return $clan ? $clan : false;
}

// Returns an array of player profiles that are members of the clan specified, regardless of rank.
function get_clan_members($clanid) {
	$cmd = "SELECT p.*,pp.* FROM $this->t_plr p, $this->t_plr_profile pp WHERE pp.uniqueid=p.uniqueid AND p.clanid=" . 
		$this->db->escape($clanid, true) . " ORDER BY name ASC";
	$list = $this->db->fetch_rows(1, $cmd);
	return $list;
}

function get_weapon($args = array()) {
	$args += array(
		'weaponid'	=> 0,
		'fields'	=> '',
	);
	$id = $args['weaponid'];
	if (!is_numeric($id)) $id = 0;

	$fields = $args['fields'] ? $args['fields'] : "data.*,COALESCE(w.name,w.uniqueid) label";

	$cmd  = "SELECT $fields, w.* ";
	$cmd .= "FROM $this->c_weapon_data data, $this->t_weapon w ";
	$cmd .= "WHERE data.weaponid=w.weaponid AND data.weaponid=" . $this->db->escape($id) . " ";
	$cmd .= "LIMIT 1";
	$weapon = $this->db->fetch_row(1, $cmd);

	return $weapon;
}

function get_role($args = array()) {
	$args += array(
		'roleid'	=> 0,
		'fields'	=> '',
	);
	$id = $args['roleid'];
	if (!is_numeric($id)) $id = 0;

	$fields = $args['fields'] ? $args['fields'] : "data.*,COALESCE(r.name,r.uniqueid) label";

	$cmd  = "SELECT $fields, r.* ";
	$cmd .= "FROM $this->c_role_data data, $this->t_role r ";
	$cmd .= "WHERE data.roleid=r.roleid AND data.roleid=" . $this->db->escape($id) . " ";
	$cmd .= "LIMIT 1";
	$role = $this->db->fetch_row(1, $cmd);

	return $role;
}

function get_award($args = array()) {
	$args += array(
		'id'		=> 0,
//		'fields'	=> '',
	);
	$id = $args['id'];
	if (!is_numeric($id)) $id = 0;
//	$fields = $args['fields'] ? $args['fields'] : "data.*";

	$cmd  = "SELECT a.*, ac.enabled, ac.type, ac.class, ac.expr, ac.order, ac.limit, ac.format, ac.desc, plr.*, pp.* ";
	$cmd .= "FROM ($this->t_awards a, $this->t_config_awards ac) ";
	$cmd .= "LEFT JOIN $this->t_plr plr ON plr.plrid=a.topplrid ";
	$cmd .= "LEFT JOIN $this->t_plr_profile pp ON pp.uniqueid=plr.uniqueid ";
	$cmd .= "WHERE a.awardid=ac.id AND a.id='" . $this->db->escape($id) . "' ";
	$cmd .= "LIMIT 1";
	$award = $this->db->fetch_row(1, $cmd);
//	print $this->db->lastcmd;

	return $award;
}

function get_map($args = array()) {
	$args += array(
		'mapid'		=> 0,
		'fields'	=> '',
	);
	$id = $args['mapid'];
	if (!is_numeric($id)) $id = 0;

	$fields = $args['fields'] ? $args['fields'] : "data.*";

	$cmd  = "SELECT $fields, m.* ";
	$cmd .= "FROM $this->c_map_data data, $this->t_map m ";
	$cmd .= "WHERE data.mapid=m.mapid AND data.mapid=" . $this->db->escape($id) . " ";
	$cmd .= "LIMIT 1";
	$map = $this->db->fetch_row(1, $cmd);

	return $map;
}

function get_award_player_list($args = array()) {
	$args += array(
		'id'		=> 0,
		'fields'	=> '',
		'where'		=> '',
		'sort'		=> 'idx',
		'order'		=> 'desc',
		'start'		=> 0,
		'limit'		=> 10,
	);
	$id = $args['id'];
	if (!is_numeric($id)) $id = 0;
	$fields = $args['fields'] ? $args['fields'] : "ap.*, ac.format, ac.desc, plr.*, pp.*";

	$cmd  = "SELECT $fields ";
	$cmd .= "FROM ($this->t_awards_plrs ap, $this->t_awards a, $this->t_config_awards ac) ";
	$cmd .= "LEFT JOIN $this->t_plr plr ON plr.plrid=ap.plrid ";
	$cmd .= "LEFT JOIN $this->t_plr_profile pp ON pp.uniqueid=plr.uniqueid ";
	$cmd .= "WHERE ap.awardid=a.id AND a.awardid=ac.id AND ap.awardid=" . $this->db->escape($id) . " ";
	if ($args['where'] != '') $cmd .= "AND (" . $args['where'] . ") ";
	$cmd .= $this->getsortorder($args);
	$list = array();
	$list = $this->db->fetch_rows(1, $cmd);
//	print $this->db->lastcmd;

	return $list;
}

function get_weapon_player_list($args = array()) {
	$args += array(
		'weaponid'	=> 0,
		'fields'	=> '',
		'where'		=> '',
		'allowall'	=> 0,
		'sort'		=> '',
		'order'		=> 'desc',
		'start'		=> 0,
		'limit'		=> 10,
	);
	$id = $this->db->escape($args['weaponid']);
	if (!is_numeric($id)) $id = 0;
	$fields = $args['fields'] ? $args['fields'] : "data.*, plr.*, pp.*, c.cn";

	$cmd  = "SELECT $fields ";
	$cmd .= "FROM ($this->c_plr_weapons data, $this->t_plr plr, $this->t_plr_profile pp) ";
	$cmd .= "LEFT JOIN $this->t_geoip_cc c ON c.cc=pp.cc ";
	$cmd .= "WHERE plr.plrid=data.plrid AND data.weaponid=$id AND pp.uniqueid=plr.uniqueid ";
	if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
	if ($args['where'] != '') $cmd .= "AND (" . $args['where'] . ") ";
	$cmd .= $this->getsortorder($args);
	$list = array();
	$list = $this->db->fetch_rows(1, $cmd);

	return $list;
}

function get_role_player_list($args = array()) {
	$args += array(
		'roleid'	=> 0,
		'fields'	=> '',
		'where'		=> '',
		'allowall'	=> 0,
		'sort'		=> '',
		'order'		=> 'desc',
		'start'		=> 0,
		'limit'		=> 10,
	);
	$id = $this->db->escape($args['roleid']);
	if (!is_numeric($id)) $id = 0;
	$fields = $args['fields'] ? $args['fields'] : "data.*, plr.*, pp.*, c.cn";

	$cmd  = "SELECT $fields ";
	$cmd .= "FROM ($this->c_plr_roles data, $this->t_plr plr, $this->t_plr_profile pp) ";
	$cmd .= "LEFT JOIN $this->t_geoip_cc c ON c.cc=pp.cc ";
	$cmd .= "WHERE plr.plrid=data.plrid AND data.roleid=$id AND pp.uniqueid=plr.uniqueid ";
	if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
	if ($args['where'] != '') $cmd .= "AND (" . $args['where'] . ") ";
	$cmd .= $this->getsortorder($args);
	$list = array();
	$list = $this->db->fetch_rows(1, $cmd);

	return $list;
}

function get_map_player_list($args = array()) {
	$args += array(
		'mapid'		=> 0,
		'fields'	=> '',
		'where'		=> '',
		'allowall'	=> 0,
		'sort'		=> '',
		'order'		=> 'desc',
		'start'		=> 0,
		'limit'		=> 10,
	);
	$id = $this->db->escape($args['mapid']);
	if (!is_numeric($id)) $id = 0;
//	$fields = $args['fields'] ? $args['fields'] : "data.*, plr.*, pp.*, c.cn";
	$fields = $args['fields'] ? $args['fields'] : "data.*";

	$cmd  = "SELECT $fields, plr.*, pp.*, c.cn ";
	$cmd .= "FROM ($this->c_plr_maps data, $this->t_plr plr, $this->t_plr_profile pp) ";
	$cmd .= "LEFT JOIN $this->t_geoip_cc c ON c.cc=pp.cc ";
	$cmd .= "WHERE plr.plrid=data.plrid AND data.mapid=$id AND pp.uniqueid=plr.uniqueid ";
	if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
	if ($args['where'] != '') $cmd .= "AND (" . $args['where'] . ") ";
	$cmd .= $this->getsortorder($args);
	$list = array();
	$list = $this->db->fetch_rows(1, $cmd);

	return $list;
}

function get_player_list($args = array()) {
	global $cms;
	$args += array(
		'allowall'	=> false,
		'start'		=> 0,
		'limit'		=> 100,
		'sort'		=> 'skill',
		'order'		=> 'desc',
		'fields'	=> '',
		'where'		=> '',
		'filter'	=> '',
		'joinclaninfo'	=> false,
		'joinccinfo'	=> true,
		'results'	=> null,
		'search'	=> null
	);
	$values = "";
	if (trim($args['fields']) == '') {
		if ($args['joinclaninfo']) $values .= "clan.*, ";
		$values .= "data.*,plr.*,pp.* ";
		if ($args['joinccinfo']) $values .= ",c.* ";
	} else {
		$values = $args['fields'];
	}

	$cmd  = "SELECT $values FROM ($this->t_plr plr, $this->t_plr_profile pp, $this->c_plr_data data) ";
	if ($args['joinccinfo']) {
		$cmd .= "LEFT JOIN $this->t_geoip_cc c ON c.cc=pp.cc ";
	}
	if ($args['joinclaninfo']) {
		$cmd .= "LEFT JOIN $this->t_clan clan ON clan.clanid=plr.clanid ";
	}
	$cmd .= "WHERE pp.uniqueid=plr.uniqueid AND data.plrid=plr.plrid ";
	if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
	if (trim($args['where']) != '') $cmd .= "AND (" . $args['where'] . ") ";

	$filter = trim($args['filter']);
	if ($filter != '') {
		$f = '%' . $this->db->escape($filter) . '%';
		$cmd .= "AND (pp.name LIKE '$f') ";
	}
	
	$list = array();
	// limit list to search results
	$results = $args['results'];
	if ($args['search']) {
		$results = $this->get_search($args['search']);
	}
	if ($results) {
//		$args['start'] = 0;	// override start since we sliced the array
//		$plrids = array_slice(explode(',',$results['results']), $args['start'], $args['limit']);
		$plrids = explode(',',$results['results']);
		if (count($plrids)) {
			$cmd .= "AND plr.plrid IN (" . join(',', $plrids) . ") ";
		}
	}

	// only do a query if we are not searching or if our current search
	// actually has some data to return.
	if (!$results or $results['results']) {
		$cmd .= $this->getsortorder($args);
		$list = $this->db->fetch_rows(1, $cmd);
	}
	return $list;
}

// Loads a list of player information (no stats) including their profile and assoicated user information
function get_basic_player_list($args = array()) {
	$args += array(
		'start'			=> 0,
		'limit'			=> 100,
		'sort'			=> 'name',
		'order'			=> 'asc',
		'fields'		=> '',
		'filter'		=> '',
		'where'			=> '',
		'joinclaninfo'		=> 0,
		'joinccinfo'		=> 0,
		'joinuserinfo'		=> 1,
		'results'		=> null,
		'search'		=> null
	);
	$values = "";
	if (trim($args['fields']) == '') {
		$values .= "plr.*,pp.* ";
		if ($args['joinclaninfo']) $values .= "clan.*, ";
		if ($args['joinccinfo']) $values .= ",c.* ";
		if ($args['joinuserinfo']) $values .= ",u.* ";
	} else {
		$values = $args['fields'];
	}

	$cmd  = "SELECT $values ";
	$cmd .= "FROM ($this->t_plr plr, $this->t_plr_profile pp) ";
	if ($args['joinccinfo']) {
		$cmd .= "LEFT JOIN $this->t_geoip_cc c ON c.cc=pp.cc ";
	}
	if ($args['joinclaninfo']) {
		$cmd .= "LEFT JOIN $this->t_clan clan ON clan.clanid=plr.clanid ";
	}
	if ($args['joinuserinfo']) {
		$cmd .= "LEFT JOIN $this->t_user u ON u.userid=pp.userid ";
	}
	$cmd .= "WHERE pp.uniqueid=plr.uniqueid ";
	if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
	if (trim($args['where']) != '') $cmd .= "AND (" . $args['where'] . ") ";
	// basic filter
	if (trim($args['filter']) != '') {
		$cmd .= " AND (pp.name LIKE '%" . $this->db->escape(trim($args['filter'])) . "%') ";		
	}

	$list = array();	
	// limit list to search results
	$results = $args['results'];
	if ($args['search']) {
		$results = $this->get_search($args['search']);
	}
	if ($results) {
//		$args['start'] = 0;	// override start since we sliced the array
//		$plrids = array_slice(explode(',',$results['results']), $args['start'], $args['limit']);
		$plrids = explode(',',$results['results']);
		if (count($plrids)) {
			$cmd .= "AND plr.plrid IN (" . join(',', $plrids) . ") ";
		}
	}

	// only do a query if we are not searching or if our current search
	// actually has some data to return.
	if (!$results or $results['results']) {
		$cmd .= $this->getsortorder($args);
		$list = $this->db->fetch_rows(1, $cmd);
	}
	return $list;
}

function get_clan_list($args = array()) {
	$args += array(
		'start'		=> 0,
		'limit'		=> 100,
		'sort'		=> 'skill',
		'order'		=> 'desc',
		'fields'	=> '',
		'where'		=> '',
		'allowall'	=> 0,
	);
	$values = "clan.clanid,clan.locked,clan.allowrank,cp.*,COUNT(*) totalmembers, ROUND(AVG(skill),0) skill, ROUND(AVG(activity)) activity, ";

	$types = $this->get_types("CLAN");
	$fields = !empty($args['fields']) ? explode(',',$args['fields']) : array_keys($types);
	$values .= $this->_values($fields, $types);

	$cmd  = "SELECT $values ";
	$cmd .= "FROM $this->t_clan clan, $this->t_plr plr, $this->c_plr_data data, $this->t_clan_profile cp ";
	$cmd .= "WHERE (plr.clanid=clan.clanid AND plr.allowrank=1) AND clan.clantag=cp.clantag AND data.plrid=plr.plrid ";
	if (!$args['allowall']) $cmd .= "AND clan.allowrank=1 ";
	if (trim($args['where']) != '') $cmd .= "AND (" . $args['where'] . ") ";
	$cmd .= "GROUP BY clan.clanid ";
//	$cmd .= "HAVING totalmembers > " . $this->conf['main']['clans']['min_members'] . " ";
	$cmd .= $this->getsortorder($args);
	$list = array();
	$list = $this->db->fetch_rows(1, $cmd);

//	print "explain " . $this->db->lastcmd;

	return $list;
}


function get_weapon_list($args = array()) {
	$args += array(
		'start'		=> 0,
		'limit'		=> 100,
		'sort'		=> 'skill',
		'order'		=> 'desc',
		'fields'	=> '',
		'where'		=> '',
	);

	$values = "";
	if (trim($args['fields']) == '') {
		$values .= "data.*,w.*,COALESCE(w.name,w.uniqueid) label";
	} else {
		$values = $args['fields'];
	}

	$cmd  = "SELECT $values FROM $this->c_weapon_data data, $this->t_weapon w ";
	$cmd .= "WHERE data.weaponid=w.weaponid ";
	if ($args['where'] != '') {
		$cmd .= "AND (" . $args['where'] . ") ";
	}
	$cmd .= $this->getsortorder($args);

	$list = $this->db->fetch_rows(1, $cmd);

	return $list;
}

function get_role_list($args = array()) {
	$args += array(
		'start'		=> 0,
		'limit'		=> 100,
		'sort'		=> 'skill',
		'order'		=> 'desc',
		'fields'	=> '',
		'where'		=> '',
	);

	$values = "";
	if (trim($args['fields']) == '') {
		$values .= "data.*,r.*,COALESCE(r.name,r.uniqueid) label";
	} else {
		$values = $args['fields'];
	}

	$cmd  = "SELECT $values FROM $this->c_role_data data, $this->t_role r ";
	$cmd .= "WHERE data.roleid=r.roleid ";
	if ($args['where'] != '') {
		$cmd .= "AND (" . $args['where'] . ") ";
	}
	$cmd .= $this->getsortorder($args);

	$list = $this->db->fetch_rows(1, $cmd);

	return $list;
}

function get_map_list($args = array()) {
	$args += array(
		'start'		=> 0,
		'limit'		=> 100,
		'sort'		=> 'skill',
		'order'		=> 'desc',
		'fields'	=> '',
		'where'		=> '',
	);

	$values = "";
	if (trim($args['fields']) == '') {
		$values .= "data.*,m.*";
	} else {
		$values = $args['fields'];
	}

	$cmd  = "SELECT $values FROM $this->c_map_data data ";
	$cmd .= "LEFT JOIN $this->t_map m ON m.mapid=data.mapid ";
	if ($args['where'] != '') {
		$cmd .= "WHERE " . $args['where'] . " ";
	}
	$cmd .= $this->getsortorder($args);
	$list = $this->db->fetch_rows(1, $cmd);
//	print "explain " . $this->db->lastcmd . ";";

	return $list;
}

// returns some basic summarized stats from the table
function get_sum($args = array(), $table = null) {
	if ($table === null) $table = $this->c_map_data;	// best table to summarize from
	$cmd = "SELECT ";
	foreach ($args as $key) {
		$key = $this->db->qi($key);
		$cmd .= "SUM($key) $key,";
	}
	$cmd = substr($cmd,0,-1);
	$cmd .= " FROM " . $this->db->qi($table);
	return $this->db->fetch_row(1,$cmd);
}

function get_total_players($args = array()) {
	$args += array(
		'allowall'	=> false,
		'filter'	=> '',
	);
	$cmd = "";
	$filter = trim($args['filter']);
	if ($filter == '') {
		$cmd = "SELECT count(*) FROM $this->t_plr plr WHERE 1 ";
	} else {
		$cmd = "SELECT count(*) FROM $this->t_plr plr, $this->t_plr_profile pp WHERE pp.uniqueid=plr.uniqueid ";
	}
	if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
	// basic filter
	if ($filter != '') {
		$f = '%' . $this->db->escape($filter) . '%';
		$cmd .= " AND (pp.name LIKE '$f')";	// I don't like using OR logic, queries run much slower.
//		$cmd .= " AND (pp.name LIKE '$f' OR pp.uniqueid LIKE '$f')";
	}
	$this->db->query($cmd);
	list($total) = $this->db->fetch_row(0);
	return $total;
}

function get_total_clans($args = array()) {
	$args += array(
		'allowall'	=> 0,
		'where'		=> '',
	);
	$cmd  = "SELECT count(*) total FROM $this->t_clan clan ";
	if (!$args['allowall'] and $args['where']) {
		$cmd .= "WHERE clan.allowrank=1 AND " . $args['where'] . " ";
	} elseif (!$args['allowall']) {
		$cmd .= "WHERE clan.allowrank=1 ";
	} elseif ($args['where']) {
		$cmd .= $args['where'] . " ";
	}
	$this->db->query($cmd);
	list($total) = $this->db->fetch_row(0);

	return $total;
}

function get_total_weapons($args = array()) {
//	$args += array(	);
	$cmd  = "SELECT count(distinct weaponid) FROM $this->c_weapon_data LIMIT 1";
	$this->db->query($cmd);
	list($total) = $this->db->fetch_row(0);

	return $total;
}

function get_total_roles($args = array()) {
//	$args += array(	);
	$cmd  = "SELECT count(distinct roleid) FROM $this->c_role_data LIMIT 1";
	$this->db->query($cmd);
	list($total) = $this->db->fetch_row(0);

	return $total;
}

function get_total_awards($args = array()) {
	$args += array(
		'type'		=> '',
	);
	return 0; #########################################################
	$where = $args['type'] ? "WHERE type='" . $this->db->escape($args['type']) . "' " : "";
	$cmd  = "SELECT count(distinct awardid) FROM $this->t_awards $where LIMIT 1";
	$this->db->query($cmd);
	list($total) = $this->db->fetch_row(0);

	return $total;
}

function get_total_maps($args = array()) {
//	$args += array(	);
	$cmd  = "SELECT count(distinct mapid) FROM $this->c_map_data LIMIT 1";
	$this->db->query($cmd);
	list($total) = $this->db->fetch_row(0);

	return $total;
}

// deletes a player profile only, not player stats
function delete_player_profile($uniqueid) {
	global $cms;
	$_id = $this->db->escape($uniqueid, true);
	list($userid) = $this->db->fetch_row(0,"SELECT userid FROM $this->t_plr_profile WHERE uniqueid=$_id");
	$this->db->delete($this->t_plr_profile, 'uniqueid', $uniqueid);
	$cms->user->delete_user($userid);
}

// deletes a player and all of his stats. If $keep_profile is true than their profile is saved.
function delete_player($plrid, $keep_profile = TRUE) { 
	$_plrid = $this->db->escape($plrid, true);
	// get player uniqueid and userid 
	list($uniqueid,$userid) = $this->db->fetch_row(0,"SELECT p.uniqueid,userid FROM $this->t_plr p
		LEFT JOIN $this->t_plr_profile pp ON pp.uniqueid=p.uniqueid
		WHERE p.plrid=$_plrid"
	);

	// remove historical data related to this player ID
	$tables = array( 't_plr_data', 't_plr_maps' );
	foreach ($tables as $table) {
		$t = $this->$table;
		$ids = $this->db->fetch_list("SELECT dataid FROM $t WHERE plrid=$_plrid");
		while (count($ids)) {
			// limit how many we delete at a time, so we're sure the query is never too large
			$list = array_splice($ids, 0, 100);
			$this->db->query("DELETE FROM " . $t . $this->tblsuffix . " WHERE dataid IN (" . join(', ', $list) . ")");
		}
		$this->db->delete($t, 'plrid', $plrid);
	}

	// remove simple data related to this player ID
	$tables = array( 't_plr_ids', 't_plr_sessions', 't_plr_victims', 't_plr_weapons', 't_plr' );
	foreach ($tables as $table) {
		// don't use $_plrid, since delete() will escape it
		$this->db->delete($this->$table, 'plrid', $plrid);
	}

	// delete the player profile if specified
	if (!$keep_profile) {
		$this->delete_player_profile($uniqueid);
	}

	// remove player from any awards they are ranked in
	// this will probably be the slowest part of a player deletion
	if ($this->db->count($this->t_awards_plrs, '*', "plrid=$_plrid")) {
		$this->db->delete($this->t_awards_plrs, 'plrid', $plrid);
		// fix awards that had this player as #1
		$awardids = $this->db->fetch_list("SELECT id FROM $this->t_awards WHERE topplrid=$_plrid");
		foreach ($awardids as $id) {
			list($topplrid, $topplrvalue) = $this->db->fetch_list("SELECT plrid, value FROM $this->t_awards_plrs WHERE awardid=$id ORDER BY idx LIMIT 1");
			$this->db->update($this->t_awards, array( 'topplrid' => $topplrid, 'topplrvalue' => $topplrvalue ), 'id', $id);
		}
	}

	// delete all compiled stats for this player
	$tables = array( 'c_plr_data', 'c_plr_maps', 'c_plr_victims', 'c_plr_weapons' );
	foreach ($tables as $table) {
		$this->db->delete($this->$table, 'plrid', $plrid);
	}

	// and finally; delete the main plr record
	$this->db->delete($this->t_plr, 'plrid', $plrid);

	return true;
}

function getsortorder($args, $prefix='') {
	return $this->db->sortorder($args, $prefix);
}

function getlimit($args, $prefix='') {
	return $this->db->limit($args, $prefix);
}

// return's a SQL filter based on the parameters given.
// the returned SQL should be used on any WHERE clause that is selecting players.
function create_player_filter() {
/* This is an example of how the final SQL might look for a player search (minus the @q var)
SET @q := '%a%';
(select p.skill sorted,p.plrid,pp.uniqueid,pp.name FROM ps_plr p, ps_plr_profile pp WHERE p.uniqueid=pp.uniqueid AND pp.name like @q)
UNION
(select p.skill,p.plrid,pp.uniqueid,pp.name FROM (ps_plr p, ps_plr_profile pp) LEFT JOIN ps_plr_ids_name i ON (i.plrid=p.plrid) 
	WHERE p.uniqueid=pp.uniqueid AND i.name like @q)
UNION
(select p.skill,p.plrid,pp.uniqueid,pp.name FROM (ps_plr p, ps_plr_profile pp) LEFT JOIN ps_plr_ids_worldid i ON (i.plrid=p.plrid) 
	WHERE p.uniqueid=pp.uniqueid AND i.worldid like @q)
UNION
(select p.skill,p.plrid,pp.uniqueid,pp.name FROM (ps_plr p, ps_plr_profile pp) LEFT JOIN ps_plr_ids_ipaddr i ON (i.plrid=p.plrid) 
	WHERE p.uniqueid=pp.uniqueid AND INET_ATON(@q) = i.ipaddr)
ORDER BY sorted DESC
*/
}

// loads a portion of config into memory.
// This is optimized to only load the variables of the config, not the extra layout information.
// see the load_config_layout() function if config layout info is needed.
function load_config($type) {
	$conflist = !is_array($type) ? $conflist = array($type) : $type;
	$c = array();
	$cmd = "SELECT conftype,section,var,value FROM $this->t_config WHERE var IS NOT NULL AND conftype IN (";
	foreach ($conflist as $conftype) {
		$this->conf[$conftype] = array();
		$c[] = $this->db->escape($conftype, true);
	}
	$cmd .= join(', ', $c) . ")";
	$list = $this->db->fetch_rows(1, $cmd);
	foreach ($list as $row) {
		if (empty($row['section'])) {
			$this->_assignvar($this->conf[$row['conftype']], $row['var'], $row['value']);
		} else {
			$this->_assignvar($this->conf[$row['conftype']][$row['section']], $row['var'], $row['value']);
		}
	}
}

// loads the full config and it's layout. This WILL NOT overwrite the currently loaded config.
function load_config_layout($type, $where = "") {
	$conflist = !is_array($type) ? $conflist = array($type) : $type;
	$c = array();
	$cmd = "SELECT * FROM $this->t_config WHERE var IS NOT NULL AND conftype IN (";
	foreach ($conflist as $conftype) {
		$this->conf_layout[$conftype] = array();
		$c[] = $this->db->escape($conftype, true);
	}
	$cmd .= join(', ', $c) . ")";
	if ($where != '') $cmd .= " AND $where";
	$cmd .= " ORDER BY label,section,var";
	$list = $this->db->fetch_rows(1, $cmd);
	foreach ($list as $row) {
		if (empty($row['section'])) {
			$this->_assignvar($this->conf_layout[$row['conftype']], $row['var'], $row);
		} else {
			$this->_assignvar($this->conf_layout[$row['conftype']][$row['section']], $row['var'], $row);
		}
	}
	return $this->conf_layout;
}

// returns the entire config keyed on ID
function load_config_by_id($fields = '*', $where = "") {
	$cmd = "SELECT $fields FROM $this->t_config";
	if ($where != '') $cmd .= " $where";
	$list = $this->db->fetch_rows(1, $cmd);
	$c = array();
	foreach ($list as $row) {
		$c[ $row['id'] ] = $row;
	}
	return $c;
}

// returns a single config variable (with full layout) based on it's ID
// returns false if the row was not found
function load_conf_var($id, $key = 'id') {
	$row = $this->db->fetch_row(1, "SELECT * FROM $this->t_config WHERE " . $this->db->qi($key) . "=" . $this->db->escape($id, true));
	return $row ? $row : false;
}


// writes the error message to the error log
// trims the log if it grows too large (unless $notrim is true)
function errlog($msg, $severity='warning', $userid=NULL, $notrim=false) {
	if (!in_array($severity, array('info','warning','fatal'))) {
		$severity = 'warning';
	}
	$msg = trim($msg);
	if ($msg == '') return;		// do nothing if there is no message
	$this->db->insert($this->t_errlog, array(
		'id'		=> $this->db->next_id($this->t_errlog), 
		'timestamp'	=> time(),
		'severity'	=> $severity,
		'userid'	=> $userid,
		'msg'		=> $msg
	));

	if (!$notrim) {
		$this->trim_errlog();
	}
}

// trims the errlog size to the configured settings. 
// if $all is true then the errlog table is truncated
function trim_errlog($all=false) {
	$maxrows = $this->conf['main']['errlog']['maxrows'];
	$maxdays = $this->conf['main']['errlog']['maxdays'];
	if ($maxrows == '') $maxrows = 5000;
	if ($maxdays == '') $maxdays = 30;
	if (intval($maxrows) + intval($maxdays) == 0) return;		// nothing to trim
	$deleted = 0;
	if ($maxdays) {
		$this->db->query("DELETE FROM $this->t_errlog WHERE " . $this->db->qi('timestamp') . " < " . (time()-60*60*24*$maxdays));
		$deleted++;
	}
	if ($maxrows) {
		$total = $this->db->count($this->t_errlog);
		if ($total <= $maxrows) return;
		$diff = $total - $maxrows;
		$list = $this->db->fetch_list("SELECT id FROM $this->t_errlog ORDER BY " . $this->db->qi('timestamp') . " LIMIT $diff");
		if (is_array($list) and count($list)) {
			$this->db->query("DELETE FROM $this->t_errlog WHERE id IN (" . implode(',', $list) . ")");
			$deleted++;
		}
	}
	if ($deleted) {
		if (mt_rand(1,20) == 1) {	// approximately 20% chance of optimizing the table
			$this->db->optimize($this->t_errlog);
		}
	}
}

function get_types($prefix, $mod=1) {
	$var = $prefix . "_TYPES";
	$modvar = $prefix . "_MODTYPES";
	if ($mod and is_array($this->$modvar)) {
		return $this->$var + $this->$modvar;
	} else {
		return $this->$var;
	}
}

// internal function for load_config. do not call outside of class
function _assignvar(&$c,$var,$val) {
	if (!is_array($c)) $c = array();
	if (array_key_exists($var, $c)) {
		if (!is_array($c[$var])) {
			$c[$var] = array( $c[$var] );
		}
		$c[$var][] = $val;
	} else {
		$c[$var] = $val	;
	}
}

// returns a value string used for certain non-clan statistics (like player sessions)
function _calcvalues($fields, $types) {
	$values = "";
	foreach ($fields as $key) {
		if (array_key_exists($key, $types)) {
			$type = $types[$key];
			if (is_array($type)) {
				$func = "_soloexpr_" . array_shift($type);
				if (method_exists($this->db, $func)) {
					$values .= $this->db->$func($type) . " $key, ";
				}
			} else {
				$values .= "$key, ";
			} 
		} else {
			$values .= "$key, ";
		}
	}
	$values = substr($values, 0, -2);		// trim trailing comma: ", "
	return $values;
}

// returns a value string used in the clan statistics
function _values($fields, $types) {
	$values = "";
	foreach ($fields as $key) {
		if (array_key_exists($key, $types)) {
			$type = $types[$key];
			if (is_array($type)) {
				$func = "_expr_" . array_shift($type);
				if (method_exists($this->db, $func)) {
					$values .= $this->db->$func($type) . " $key, ";
				} else {
					# ignore key
				}
			} else {
				if ($type == '>') {
					$values .= "MAX($key) $key, ";
				} elseif ($type == '<') {
					$values .= "MIN($key) $key, ";
				} elseif ($type == '~') {
					$values .= "AVG($key) $key, ";
				} else {	# $type == '+'
					$values .= "SUM($key) $key, ";
				}
			}
		} else {
			$values .= "$key, ";
		}
	}
	$values = substr($values, 0, -2);		// trim trailing comma: ", "
	return $values;
}

// read a config from a file or string.
// If the TYPE can not be determined the imported variables are ignored.
// set $forcetype to a conftype if you know the type of the config you're loading.
// returns 'FALSE' if no errors, otherwise returns an array of all invalid config options that were ignored.
// *** FIX ME ***
function import_config($source, $forcetype = false, $opts = array()) {
	$opts += array(
		'replacemulti'	=> 1,
		'ignorenew'	=> 1,
	);
	$SEP = "^";
	if (is_array($source)) {
		$lines = $source;
	} elseif (strlen($source)<=255 and @is_file($source) and @is_readable($source)) {
		$lines = file($source);
	} else {
		$lines = explode("\n", $source);
	}
	$lines = array_map('trim', $lines);	// normalize all lines

	$section = '';
	$errors = array();
	$type = $forcetype !== false ? $forcetype : '';
	if ($type and !array_key_exists($type, $this->conf)) $this->load_config($type);

	$this->_layout = array();
	$this->_import_errors = array();
	$this->_import_multi = array();
	$this->_import_opts = $opts;

	foreach ($lines as $line) {
		if ($forcetype === false and preg_match('/^#\\$TYPE\s*=\s*([a-zA-Z_]+)/', $line, $m)) {
			$type = $m[1];
			if (!array_key_exists($type, $this->conf)) $this->load_config($type);
			$this->_update_layout($type);
			$section = '';
		} 
		if ($line[0] == '#') continue; 		// ignore comments;

		if (preg_match('/^\[([^\]]+)\]/', $line, $m)) {
			$section = $m[1];
			if (strtolower($section) == 'global') $section = '';
		} elseif (preg_match('/^([\w\d_]+)\s*=\s*(.*)/', $line, $m)) {
			if ($type) {
				$this->_import_var($type, $section, $m[1], $m[2]);
			} else {
				$this->_import_errors['unknown_types'][] = $section ? $section . "." . $m[1] : $m[1];
			}
		}
	}

	return count($this->_import_errors) ? $this->_import_errors : false;
}

// *** FIX ME ***
function _import_var($type, $section, $var, $val) {
#	print "$type:: $section.$var = $val<br>\n";
	$key = $section ? $section . "." . $var : $var;

	// do not allow changes to locked variables
	if ($this->_layout[$key]['locked']) {
		$this->_import_errors['locked_vars'][] = $key;
		return false;
	}

	// verify the variable is 'sane' according to the layout rules
	$field = array( 'val' => $this->_layout[$key]['verifycodes'], 'error' => '' );
	form_checks($val, $field);
	if ($field['error']) {
		$this->_import_errors['invalid_vars'][$key] = $field['error'];
		return false;
	}

	// do not accept NEW vars if 'ignorenew' is enabled
	$exists = (($section and array_key_exists($var, $this->conf[$type][$section])) or 
		(!$section and array_key_exists($var, $this->conf[$type])));
	if ($this->_import_opts['ignorenew'] and !$exists) {
		$this->_import_errors['ignored_vars'][] = $key;
		return false;
	}

	// save the imported settings. Take special care of 'multi' options.
	// first: find the matching ID of the current variable (might be more than 1).
	$id = $this->db->fetch_list(sprintf("SELECT id FROM $this->t_config WHERE conftype='%s' AND section='%s' AND var='%s'",
		$this->db->escape($type),
		$this->db->escape($section),
		$this->db->escape($var)
	));
	// if there's no ID, then this is a new option
	$new = false;
	if (!is_array($id) or !count($id)) {
		$new = true;
		$id = array( $this->db->next_id($this->t_config) );
	}
//	print "ID=" . implode(',',$id) . " ($var) == $val<br>";

	// single options can be simply inserted or updated
	// if a non-multi option ends up having more than 1, only the first fetched from the DB is updated
	if (!$this->_layout[$key]['multiple']) {
		if ($new) {
			$this->db->insert($this->t_config, array( 
				'id' 		=> $id[0],
				'conftype' 	=> $type,
				'section' 	=> $section,
				'var' 		=> $var,
				'value' 	=> $val
			));
		} else {
			$this->db->update($this->t_config, array( 'value' => $val ), 'id', $id[0]);
		}
	} else {
		// remove all multi options related to the variable the first time we see it
		if ($this->_import_opts['replacemulti'] and !$this->_import_multi[$key]) {
			$this->_import_multi[$key] = 1;
			$this->db->query("DELETE FROM $this->t_config WHERE id IN (" . implode(',', $id) . ")");
		}
		// now insert the option
		$this->db->insert($this->t_config, array( 
			'id' 		=> $this->db->next_id($this->t_config),
			'idx'		=> $this->_import_multi[$key]++,
			'conftype' 	=> $type,
			'section' 	=> $section,
			'var' 		=> $var,
			'value' 	=> $val
		));
	}
}

// *** FIX ME ***
function _update_layout($type) {
	if (array_key_exists($type, $this->_layout)) return;

	$t = $this->db->escape($type);
	$this->db->query("SELECT c.*,l.* FROM $this->t_config c " . 
		"LEFT JOIN $this->t_config_layout l ON (l.conftype='$t' AND l.section=c.section AND l.var=c.var) " . 
		"WHERE c.conftype='$t' AND (isnull(l.locked) OR !l.locked) " 
	);
	while ($r = $this->db->fetch_row()) {
		$key = $r['var'];
		if ($r['section']) $key = $r['section'] . $SEP . $key;
		$this->_layout[$key] = $r;
	}
}

// returns the config as a string to be imported with import_config
// only exports a single config type at a time.
// *** FIX ME ***
function export_config($type) {
	if (!array_key_exists($type, $this->conf)) $this->load_config($type);

	$config  = "# Configuration exported on " . date("D M j G:i:s T Y") . "\n";
	$config .= "#\$TYPE = $type # do not remove this line\n\n";

	$globalkeys = array();
	$nestedkeys = array();
	$this->_layout = array();
	$this->_update_layout($type);

	foreach (array_keys($this->conf[$type]) as $key) {
		// watch out for items that can be repeated, so we dont treat them like a [section]
		if (is_array($this->conf[$type][$key]) and !$this->_layout[$key]['multiple']) {
			$nestedkeys[$key] = $this->conf[$type][$key];
			ksort($nestedkeys[$key]);
		} else {
			if (is_array($this->conf[$type][$key])) {
				// add each repeated key into the array. 1+ values
				foreach ($this->conf[$type][$key] as $i) {
					$globalkeys[$key][] = $i;
				} 
			} else {
				// there will always only be 1 value in the array
				$globalkeys[$key][] = $this->conf[$type][$key];
			}
		}
	}
	ksort($globalkeys);
	ksort($nestedkeys);

	$width = 1;
	foreach ($globalkeys as $k => $v) if (strlen($k) > $width) $width = strlen($k);
	foreach ($globalkeys as $k => $values) {
		foreach ($values as $v) {
			$config .= sprintf("%-{$width}s = %s\n", $k, $v);
		}
	}

	$config .= "\n";
	foreach ($nestedkeys as $conf => $group) {
		$config .= "[$conf]\n";
		$width = 1;
		foreach ($group as $k => $v) if (strlen($k) > $width) $width = strlen($k);
		foreach ($group as $k => $v) $config .= sprintf("  %-{$width}s = %s\n", $k, $v);
		$config .= "\n";
	}

	return $config;
}

// Takes a logsource record and returns a string that represents it. 
// Which will either be a plain path or an FTP url, etc. 
// If the logsource has a password and $passwd is true then the password will be included in the URL,
// which should cause it to be a valid URL for use in a browser or FTP client, etc.
function parse_logsource($log, $passwd = false) {
	$str = ''; 
	$port = null;
	switch ($log['type']) {
		case 'ftp':
			if ($log['port'] != '21') $port = $log['port'];
		case 'sftp':
			if ($log['type'] == 'sftp' and $log['port'] != '22') $port = $log['port'];
			if ($log['username'] and $log['password'] and $passwd) {
				$str .= $log['username'] . ':' . $log['password'] . '@';
			} elseif ($log['username']) {
				$str .= $log['username'] . '@';
			}
		case 'stream':
			$str = $log['type'] . '://' . $str;
			if ($log['port']) $port = $log['port'];
			$str .= $log['host'];
			if ($port) $str .= ":$port";
		case 'file':
		default:
			if (!empty($log['path']) and substr($log['path'],0,1) != '/')  $str .= '/';
			$str .= $log['path'];
	}
	return $str;
}

// geocode lookup of an ip; returns an XML result of data that can be directly used on google maps.
// $ip can be a single IP address or an array of addresses.
function ip_lookup($ip) {
	if (is_array($ip)) {
		$ip = array_unique(array_filter($ip, 'not_empty'));
	}
	if (!$ip) {
		return '';
	}
	$url = $this->conf['theme']['map']['iplookup_url'];
	if (!$url) return false;
	if (substr($url,0,4) == 'http') {	// URL LOOKUP
		$ipstr = is_array($ip) ? implode(',',$ip) : $ip;
		$url = (strpos($url, '$ip') === FALSE) ? $url.$ipstr : str_replace('$ip', $ipstr, $url);
		include_once(dirname(__DIR__) . '/class_HTTP.php');
		$lookup = new HTTP_Request($url);
		$text = $lookup->download();
		return $text;
	} else {				// LOCAL FILE LOOKUP
		// geoip* must be in the path somewhere...
		if (!@include_once("includes/geoipcity.inc")) return '<markers></markers>';
		$gi = geoip_open($url, GEOIP_STANDARD);

		$list = (array)$ip;
		$info = array();
		foreach ($list as $ipstr) {
			$info[] = geoip_record_by_addr($gi,$ipstr);
		}
		geoip_close($gi);

		$xml = "<markers>\n";
		if ($info) {
			for ($i=0; $i < count($info); $i++) {
				$xml .= sprintf("  <marker lat='%s' lng='%s' ip='%s' />\n", 
					$info[$i]->latitude, $info[$i]->longitude, $list[$i]
				);
			}
		}
		$xml .= "</markers>\n";
		return $xml;
	}
}

// allows the PS object to initialize some theme related variables, etc...
function theme_setup(&$theme) {
	global $cms;
	$is_admin = $cms->user->is_admin();
	$theme->assign(array(
		'use_roles'		=> $this->use_roles,
		'show_ips'		=> $this->conf['theme']['permissions']['show_ips'] || $is_admin,
		'show_worldids'		=> $this->conf['theme']['permissions']['show_worldids']|| $is_admin,
		'show_login'		=> $this->conf['theme']['permissions']['show_login'] || $is_admin,
		'show_register'		=> $this->conf['theme']['permissions']['show_register'] || $is_admin,
		'show_version'		=> $this->conf['theme']['permissions']['show_version'] || $is_admin,
		'show_admin'		=> $this->conf['theme']['permissions']['show_admin'],
		'show_benchmark'	=> $this->conf['theme']['permissions']['show_benchmark'],
		'show_plr_icons'	=> $this->conf['theme']['permissions']['show_plr_icons'],
		'show_plr_flags'	=> $this->conf['theme']['permissions']['show_plr_flags'],
		'show_clan_icons'	=> $this->conf['theme']['permissions']['show_clan_icons'],
		'show_clan_flags'	=> $this->conf['theme']['permissions']['show_clan_flags'],
		'loggedin'		=> ($cms->input['loggedin'] and $cms->user->logged_in()),
		'shades'		=> $cms->session->opt('shades'),
		'worldid_noun'		=> $this->worldid_noun(),
		'worldid_noun_plural'	=> $this->worldid_noun(true),
	));
	$theme->assign_by_ref('conf', $this->conf);

	// allow templates to access some PS methods
	$theme->register_object('ps', $this, 
		array( 'version', 'worldid_noun' ),
		false
	);

	$theme->load_styles();
	if ($cms->input['loggedin'] and $cms->user->logged_in()) {
		$theme->add_js('js/loggedin.js');
	}

	// setup the elapsedtime_str static vars once, so all other calls to
	// it will automatically use the translated strings.
	// we ignore the return value.
	elapsedtime_str(array(),0,
			// note the leading space on each word
			array(
				$cms->trans(' years'),
				$cms->trans(' months'),
				$cms->trans(' weeks'),
				$cms->trans(' days'),
				$cms->trans(' hours'),
				$cms->trans(' minutes'),
				$cms->trans(' seconds')
			),
			array(
				$cms->trans(' year'),
				$cms->trans(' month'),
				$cms->trans(' week'),
				$cms->trans(' day'),
				$cms->trans(' hour'),
				$cms->trans(' minute'),
				$cms->trans(' second')
			),
			' ' . $cms->trans('and')
	);
	
	$this->ob_start();
}

// Start the output buffer only if headers have not been sent. If the headers
// have been sent that indicates some sort of error occurred and I don't want
// anything to be obfuscated due to buffering.
function ob_start() {
	if (!headers_sent()) {
		if ($this->conf['theme']['enable_gzip']) {
			ob_start('ps_ob_gzhandler');
		} else {
			ob_start('ps_ob_handler');
		}
	}
}

// Erase all output buffers and discard them
function ob_clean() {
	while (@ob_end_clean());
}

// Erase and restart the output buffer
function ob_restart() {
	$this->ob_clean();
	$this->ob_start();
}

// returns the noun used to describe the 'worldid' for players.
// For example, halflife uses a "STEAMID" to identify/describe a player.
// If $plural is true the plural form of the noun will be returned.
function worldid_noun($plural = false) {
	global $cms;
	return $plural ? $cms->trans('Worldids') : $cms->trans('Worldid');
}

// returns the version of PsychoStats
// If theme.show.ps_version is false this returns an empty string unless $force is true
function version($force = false) {
	$v = '';
	if ($this->conf['theme']['permissions']['show_version'] or $force) {
		$v = $this->conf['info']['version'];
		// if the DB version and class_PS version differ show both versions
		if ($v != PSYCHOSTATS_VERSION) {
			$v = "$v-db (" . PSYCHOSTATS_VERSION . "-php)";
		}
//		$v = 'v' . $v;
	}
	return $v;
}

// returns a full <img/> tag for a weapon. 
// $w is a single array from a result set or a name string of a weapon
function weaponimg($w, $args = array()) {
	$args += array(
		'alt'		=> NULL,
		'height'	=> NULL,
		'width'		=> NULL,
		'path'		=> '',		// add $path to the end of basedir? (eg: 'large/')
		'noimg'		=> NULL,	// if no img is found then return this instead of the name
		'urlonly'	=> 0,		// if true, only the url of the image is returned

		'style'		=> '',		// extra styles
		'class'		=> '',		// class for the image
		'id'		=> '',		// ID for the image
		'noid'		=> true,	// if true the ID will not be auto assigned
		'extra'		=> '',		// extra paramaters
	);
	$path = !empty($args['path']) ? $args['path'] : '';
	$basedir = catfile($this->conf['theme']['weapons_dir'], $this->conf['main']['gametype'], $this->conf['main']['modtype'], $path);
	$baseurl = catfile($this->conf['theme']['weapons_url'], $this->conf['main']['gametype'], $this->conf['main']['modtype'], $path);

	if (!is_array($w)) $w = array( 'uniqueid' => !empty($w) ? $w : 'unknown' );
	$name = !empty($w['uniqueid']) ? $w['uniqueid'] : 'unknown';
	$alt = ps_escape_html(($args['alt'] !== NULL) ? $args['alt'] : $w['name']);
	$label = ps_escape_html(!empty($alt) ? $alt : $name);
	$ext = array_map('trim', explode(',', str_replace('.','', $this->conf['theme']['images']['search_ext'])));

	$img = "";
	$depth = $path ? 1 : 3;		// if a path is given then we only check it and not sub dirs
	while ($depth and empty($img)) {
		$file = "";
		$url = "";
		foreach ($ext as $e) {
			$file = catfile($basedir,$name) . '.' . $e;
			$url  = catfile($baseurl,$name) . '.' . $e;
//			print "$file<br>";
			if (@file_exists($file)) break;
			$file = "";
		}
		if (empty($file)) {
			if (--$depth) {	// try the next parent directory
				$basedir = dirname($basedir);
				$baseurl = dirname($baseurl);
				continue;
			}
			// we're done... 
			$img = $args['noimg'] !== NULL ? $args['noimg'] : $label;
			break;
		}

		if ($args['urlonly']) return $url;

		// auto assign an ID to the image
		if (!$args['noid'] and empty($args['id'])) $args['id'] = 'weapon-' . $name;

		$attrs = "";
		if (is_numeric($args['width'])) $attrs .= " width='" . $args['width'] . "'";
		if (is_numeric($args['height'])) $attrs .= " height='" . $args['height'] . "'";
		if (!empty($args['style'])) $attrs .= " style='" . $args['style'] . "'";
		if (!empty($args['class'])) $attrs .= " class='" . $args['class'] . "'";
		if (!empty($args['id'])) $attrs .= " id='" . $args['id'] . "'";
		if (!empty($args['extra'])) $attrs .= " " . $args['extra'];
		$img = "<img src='$url' title='$label' alt='$alt'$attrs />";
	}
	return $img;
}

// returns a full <img/> tag for a role. 
// $w is a single array from a result set or a name string of a role
function roleimg($w, $args = array()) {
	$args += array(
		'alt'		=> NULL,
		'height'	=> NULL,
		'width'		=> NULL,
		'path'		=> '',		// add $path to the end of basedir? (eg: 'large/')
		'noimg'		=> NULL,	// if no img is found then return this instead of the name
		'urlonly'	=> 0,		// if true, only the url of the image is returned

		'style'		=> '',		// extra styles
		'class'		=> '',		// class for the image
		'id'		=> '',		// ID for the image
		'noid'		=> true,	// if true the ID will not be auto assigned
		'extra'		=> '',		// extra paramaters
	);
	$path = !empty($args['path']) ? $args['path'] : '';
	$basedir = catfile($this->conf['theme']['roles_dir'], $this->conf['main']['gametype'], $this->conf['main']['modtype'], $path);
	$baseurl = catfile($this->conf['theme']['roles_url'], $this->conf['main']['gametype'], $this->conf['main']['modtype'], $path);
	if (!is_array($w)) $w = array( 'uniqueid' => !empty($w) ? $w : 'unknown' );
	$name = !empty($w['uniqueid']) ? $w['uniqueid'] : 'unknown';
	$alt = ps_escape_html(($args['alt'] !== NULL) ? $args['alt'] : $w['name']);
	$label = ps_escape_html(!empty($alt) ? $alt : $name);
	$ext = array_map('trim', explode(',', str_replace('.','', $this->conf['theme']['images']['search_ext'])));

	$img = "";
	$depth = $path ? 1 : 3;		// if a path is given then we only check it and not sub dirs
	while ($depth and empty($img)) {
		$file = "";
		$url = "";
		foreach ($ext as $e) {
			$file = catfile($basedir,$name) . '.' . $e;
			$url  = catfile($baseurl,$name) . '.' . $e;
//			print "$file<br>";
			if (@file_exists($file)) break;
			$file = "";
		}
		if (empty($file)) {
			if (--$depth) {	// try the next parent directory
				$basedir = dirname($basedir);
				$baseurl = dirname($baseurl);
				continue;
			}
			// we're done... 
			$img = $args['noimg'] !== NULL ? $args['noimg'] : $label;
			break;
		}

		if ($args['urlonly']) return $url;

		// auto assign an ID to the image
		if (!$args['noid'] and empty($args['id'])) $args['id'] = 'role-' . $name;

		$attrs = "";
		if (is_numeric($args['width'])) $attrs .= " width='" . $args['width'] . "'";
		if (is_numeric($args['height'])) $attrs .= " height='" . $args['height'] . "'";
		if (!empty($args['style'])) $attrs .= " style='" . $args['style'] . "'";
		if (!empty($args['class'])) $attrs .= " class='" . $args['class'] . "'";
		if (!empty($args['id'])) $attrs .= " id='" . $args['id'] . "'";
		if (!empty($args['extra'])) $attrs .= " " . $args['extra'];
		$img = "<img src='$url' title='$label' alt='$alt'$attrs />";
	}
	return $img;
}

function overlayimg($m, $args = array()) {
	$args += array(
		'urlonly'	=> true,
		'_dir'		=> $this->conf['theme']['overlays_dir'],
		'_url'		=> $this->conf['theme']['overlays_url']
	);
	return $this->mapimg($m, $args);
}

function mapimg($m, $args = array()) {
	$args += array(
		'pq'		=> NULL,
		'alt'		=> NULL,
		'height'	=> NULL,
		'width'		=> NULL,
		'path'		=> '',		// add $path to the end of basedir? (eg: 'large/')
		'noimg'		=> NULL,	// if no img is found then return this instead of the name

		'style'		=> '',		// extra styles
		'class'		=> '',		// class for the image
		'id'		=> '',		// ID for the image
		'noid'		=> true,	// if true the ID will not be auto assigned
		'extra'		=> '',		// extra paramaters

		'urlonly'	=> false,	// if true, only the URL is returned
		'_dir'		=> $this->conf['theme']['maps_dir'],
		'_url'		=> $this->conf['theme']['maps_url'],
		
		'gametype'	=> NULL,
		'modtype'	=> NULL
	);
	$path = !empty($args['path']) ? $args['path'] : '';
	$gametype = is_object($args['pq']) ? $args['pq']->gametype() : coalesce($args['gametype'], $this->conf['main']['gametype']);
	$modtype = is_object($args['pq']) ? $args['pq']->modtype() : coalesce($args['modtype'], $this->conf['main']['modtype']);
	$basedir = catfile($args['_dir'], $gametype, $modtype, $path);
	$baseurl = catfile($args['_url'], $gametype, $modtype, $path);

	if (!is_array($m)) $m = array( 'uniqueid' => !empty($m) ? $m : 'unknown' );
	$name = !empty($m['uniqueid']) ? $m['uniqueid'] : 'unknown';
	$alt = ($args['alt'] !== NULL) ? $args['alt'] : $m['name'];
	$label = !empty($alt) ? $alt : $name;
	$ext = array_map('trim', explode(',', str_replace('.','', $this->conf['theme']['images']['search_ext'])));

	$img = "";
	$depth = $path ? 1 : 3;		// if a path is given then we only check it and not sub dirs
	while ($depth and empty($img)) {
		$file = "";
		$url = "";
		foreach ($ext as $e) {
			$file = catfile($basedir,$name) . '.' . $e;
			$url  = catfile($baseurl,$name) . '.' . $e;
			if (@file_exists($file)) break;
			$file = "";
		}
		if (empty($file)) {
			if (--$depth) {	// try the next parent directory
				$basedir = dirname($basedir);
				$baseurl = dirname($baseurl);
				continue;
			}
			// we're done... 
			$img = $args['noimg'] !== NULL ? $args['noimg'] : $label;
			break;
		}

		if ($args['urlonly']) {
			return $url;
		}

		// auto assign an ID to the image
		if (!$args['noid'] and empty($args['id'])) $args['id'] = 'map-' . $name;

		$attrs = "";
		if (is_numeric($args['width'])) $attrs .= " width='" . $args['width'] . "'";
		if (is_numeric($args['height'])) $attrs .= " height='" . $args['height'] . "'";
		if (!empty($args['style'])) $attrs .= " style='" . $args['style'] . "'";
		if (!empty($args['class'])) $attrs .= " class='" . $args['class'] . "'";
		if (!empty($args['id'])) $attrs .= " id='" . $args['id'] . "'";
		if (!empty($args['extra'])) $attrs .= " " . $args['extra'];
		$img = "<img src='$url' title='$label' alt='$alt'$attrs />";
	}
	return $img;
}

// returns a full <img/> tag for an icon
// $icon is the filename of the icon to display (no path)
function iconimg($icon, $args = array()) {
	$args += array(
		'alt'		=> NULL,
		'height'	=> NULL,
		'width'		=> NULL,
		'path'		=> '',		// add $path to the end of basedir? (eg: 'large/')
		'noimg'		=> '',		// if no img is found then return this instead of the name
		'urlonly'	=> false,	// if true, only the url of the image is returned

		'style'		=> '',		// extra styles
		'class'		=> '',		// class for the image
		'id'		=> '',		// ID for the image
		'extra'		=> '',		// extra paramaters
	);
	if (empty($icon)) return '';
	$icon = basename($icon);		// remove any potential path
	$path = !empty($args['path']) ? $args['path'] : '';
	$basedir = catfile($this->conf['theme']['icons_dir'], $path);
	$baseurl = catfile($this->conf['theme']['icons_url'], $path);

	$alt = ps_escape_html(($args['alt'] !== NULL) ? $args['alt'] : $icon);
	$label = $alt;
	$ext = array_map('trim', explode(',', str_replace('.','', $this->conf['theme']['images']['search_ext'])));

	$name = rawurlencode($icon);
	$img = "";
	$file = "";
	$url = "";
	$file = catfile($basedir,$icon);
	$url  = catfile($baseurl,$name);
	if (!@file_exists($file)) {
		// we're done... 
		return $args['noimg'] !== NULL ? $args['noimg'] : $label;
	}

	if ($args['urlonly']) return $url;

	$attrs = "";
	if (is_numeric($args['width'])) $attrs .= " width='" . $args['width'] . "'";
	if (is_numeric($args['height'])) $attrs .= " height='" . $args['height'] . "'";
	if (!empty($args['style'])) $attrs .= " style='" . $args['style'] . "'";
	if (!empty($args['class'])) $attrs .= " class='" . $args['class'] . "'";
	if (!empty($args['id'])) $attrs .= " id='" . $args['id'] . "'";
	if (!empty($args['extra'])) $attrs .= " " . $args['extra'];
	$img = "<img src='$url' title='$label' alt='$alt'$attrs />";

	return $img;
}

// returns a full <img/> tag for a flag
// $flag is the filename of the flag to display (no path)
function flagimg($cc, $args = array()) {
	$args += array(
		'alt'		=> NULL,
		'height'	=> NULL,
		'width'		=> NULL,
		'path'		=> '',		// add $path to the end of basedir? (eg: 'large/')
		'noimg'		=> '',		// if no img is found then return this instead of the name
		'urlonly'	=> false,	// if true, only the url of the image is returned

		'style'		=> '',		// extra styles
		'class'		=> '',		// class for the image
		'id'		=> '',		// ID for the image
		'extra'		=> '',		// extra paramaters
	);
	if (empty($cc)) return '';
	$cc = strtolower($cc);
	$path = !empty($args['path']) ? $args['path'] : '';
	$basedir = catfile($this->conf['theme']['flags_dir'], $path);
	$baseurl = catfile($this->conf['theme']['flags_url'], $path);

	$alt = ps_escape_html(($args['alt'] !== NULL) ? $args['alt'] : $cc);
	$label = $alt;
	$ext = array_map('trim', explode(',', str_replace('.','', $this->conf['theme']['images']['search_ext'])));

	$name = rawurlencode($cc);
	$img = "";
	$file = "";
	$url = "";
	foreach ($ext as $e) {
		$file = catfile($basedir,$cc) . '.' . $e;
		$url  = catfile($baseurl,$name) . '.' . $e;
		if (@file_exists($file)) break;
		$file = "";
	}
	if (!@file_exists($file)) {
		// we're done... 
		return $args['noimg'] !== NULL ? $args['noimg'] : $label;
	}

	if ($args['urlonly']) return $url;

	$attrs = "";
	if (is_numeric($args['width'])) $attrs .= " width='" . $args['width'] . "'";
	if (is_numeric($args['height'])) $attrs .= " height='" . $args['height'] . "'";
	if (!empty($args['style'])) $attrs .= " style='" . $args['style'] . "'";
	if (!empty($args['class'])) $attrs .= " class='" . $args['class'] . "'";
	if (!empty($args['id'])) $attrs .= " id='" . $args['id'] . "'";
	if (!empty($args['extra'])) $attrs .= " " . $args['extra'];
	$img = "<img src='$url' title='$label' alt='$alt'$attrs />";

	return $img;
}

// returns information for a single overlay (used in heatmaps and psycholive)
function get_overlay($map, $gametype = false, $modtype = false) {
	if ($gametype === false) {
		$gametype = $this->gametype();
	}
	if ($modtype === false) {
		$modtype = $this->modtype();
	}
	if ($modtype == 'cstrike' and $gametype == 'source') {
		$modtype = 'cstrikes';
	}
	if ($modtype == 'dod' and $gametype == 'source') {
		$modtype = 'dods';
	}
	if ($modtype == 'ns') {
		$modtype = 'natural';
	}
	if ($modtype == 'tf') {
		$modtype = 'tf2';
	}
	$cmd = "SELECT * FROM $this->t_config_overlays WHERE gametype=" . $this->db->escape($gametype, true);
	if ($modtype) {
		$cmd .= " AND modtype=" . $this->db->escape($modtype, true);
	} else {
		$cmd .= " AND modtype IS NULL";
	}
	$cmd .= " AND map=" . $this->db->escape($map, true);
	$overlay = $this->db->fetch_row(1, $cmd);
	if ($overlay) {
		$overlay['image_url'] = $this->overlayimg($overlay['map'], array( 'gametype' => $gametype, 'modtype' => $modtype));
	}
	return $overlay;
}

// resets the map stats
function reset_map_stats() {
	$this->mapstats = array();
}

// adds a new map stat to the list of stats to display on a map page
function add_map_player_list($var, $opts) {
	$this->mapstats[$var] = $opts + array(
		'sort'	 => $var,
		'fields' => "$var, $var value",
		'where'  => sprintf("%s > 0", $this->db->qi($var)),
	);
}

// collects all map stats previously added using add_map_player_list()
function build_map_stats() {
	global $cms;

	// setup a basic table structure for all topten result sets
	$stat_table = $cms->new_table();
	$stat_table->attr('class', 'ps-table ps-player-table');
	$stat_table->if_no_data($cms->trans("No Players Found"));
	$stat_table->sortable(false);
	$stat_table->start(0);
	$stat_table->columns(array(
		'+'	=> '#',
		'rank'	=> $cms->trans("Rank"),
		'name'	=> array( 'label' => $cms->trans("Player"), 'callback' => 'ps_table_plr_link' ),
		'value'	=> array( 'label' => $cms->trans("Value") ),
		'skill' => $cms->trans("Skill"),
	));
	$stat_table->header_attr('value', 'class', 'active');
	$stat_table->column_attr('name', 'class', 'left');
	$stat_table->column_attr('skill', 'class', 'right');
	$this->map_players_table_mod($stat_table);

	// collect the topten stats
	$stats = array();
	foreach ($this->mapstats as $var => $o) {
		$stats[$var]['label'] = $o['label'];
		$stats[$var]['modifier'] = $o['modifier'];
		$stats[$var]['callback'] = $o['callback'];
		$plrs = $this->get_map_player_list($o);
		if (!count($plrs)) {
			unset($stats[$var]);
			continue;
		}
		$stats[$var]['players'] = $plrs;
		if (!empty($o['modifier']) and function_exists($o['modifier'])) {
			$modifier = $o['modifier'];
			for ($i=0; $i < count($stats[$var]['players']); $i++) {
				$stats[$var]['players'][$i]['value'] = $modifier($stats[$var]['players'][$i]['value']);
			}
		} elseif (!empty($o['modifier']) and strpos('%', strval($o['modifier'])) !== false) {
			for ($i=0; $i < count($stats[$var]['players']); $i++) {
				$stats[$var]['players'][$i]['value'] = sprintf($o['modifier'], $stats[$var]['players'][$i]['value']);
			}
		}

		// allow plugins to modify the stats
		$cms->filter('map_topten_stat', 	$var, $stats[$var]);
		$cms->filter('map_topten_' . $var, 	$var, $stats[$var]);

		// build topten table; first copy the table template
		$table = (version_compare(phpversion(), '5.0') < 0) ? $stat_table : clone($stat_table);
		$table->data($stats[$var]['players']);
		$stats[$var]['table'] = $table->render();
		$stats[$var]['id'] = 's-map-' . $var;
		$stats[$var]['key'] = 's_map_' . $var;
	}

	return $stats;
}

// reset the stats database, deleting everything. player and clan profiles can be saved if 
// specified in the $keep array. By default all optional data is kept.
// config and geoip tables are never touched.
// returns TRUE if no errors were encountered, or an array of error strings that occured.
function reset_stats($keep = array()) {
	$keep += array(
		'player_profiles'	=> true,
		'player_aliases'	=> true,
		'player_bans'		=> true,
		'clan_profiles'		=> true,
		'users'			=> true,
	);
	$errors = array();

	$empty_compiled = array( 
		'c_map_data', 
		'c_plr_data', 'c_plr_maps', 'c_plr_victims', 'c_plr_roles', 'c_plr_weapons', 
		'c_role_data',
		'c_weapon_data', 
	);
	$empty_mod = array( 't_map_data', 't_plr_data', 't_plr_maps', 't_plr_roles', 't_role_data' );
	$empty = array( 
		't_awards', 't_awards_plrs', 
		't_clan',
//		't_clan_profle', 
		't_errlog',
		't_heatmaps',
		't_map', 't_map_data', 't_map_hourly', 't_map_spatial',
		't_plr', 
//		't_plr_aliases', 't_plr_bans',
		't_plr_data', 
		't_plr_ids_ipaddr', 't_plr_ids_name', 't_plr_ids_worldid', 
		't_plr_maps', 
//		't_plr_profile', 
		't_plr_roles', 't_plr_sessions', 't_plr_victims', 't_plr_weapons', 
//		't_plugins',
//		't_role', 
		't_role_data',
		't_search_results',
		't_state', 
//		't_user',
//		't_weapon',
		't_weapon_data'
	);

	// DROP complied tables
	// stats.pl will automatically recreate the tables as needed.
	foreach ($empty_compiled as $t) {
		$tbl = $this->$t;
		if (!$this->db->droptable($tbl) and !preg_match("/unknown table/i", $this->db->errstr)) {
			$errors[] = "$tbl: " . $this->db->errstr;
		}
	}

	// delete most of everything
	foreach ($empty as $t) {
		$tbl = $this->$t;
		if (!$this->db->truncate($tbl) and !preg_match("/exist/", $this->db->errstr)) {
			$errors[] = "$tbl: " . $this->db->errstr;
		}
	}

	// delete mod specific tables
	foreach ($empty_mod as $t) {
		$tbl = $this->$t . $this->tblsuffix;
		if (!$this->db->truncate($tbl) and !preg_match("/exist/", $this->db->errstr)) {
			$errors[] = "$tbl: " . $this->db->errstr;
		}
	}

	// delete optional data ...
	$empty_extra = array();
	if (!$keep['player_profiles']) $empty_extra[] = 't_plr_profile';
	if (!$keep['player_aliases']) $empty_extra[] = 't_plr_aliases';
	if (!$keep['player_bans']) $empty_extra[] = 't_plr_bans';
	if (!$keep['clan_profiles']) $empty_extra[] = 't_clan_profile';
	foreach ($empty_extra as $t) {
		$tbl = $this->$t;
		if (!$this->db->truncate($tbl) and !preg_match("/exist/", $this->db->errstr)) {
			$errors[] = "$tbl: " . $this->db->errstr;
		}
	} 

	// delete users (except those that are admins)
	if (!$keep['users']) {
		$ok = true;
		$users = $this->db->fetch_list("SELECT userid FROM $this->t_user WHERE accesslevel < 99");
		$this->db->begin();
		if ($users) {
			$ok = $this->db->query("UPDATE $this->t_plr_profile SET userid=NULL WHERE userid IN (" . implode(',', $users) . ")");
			if ($ok) $ok = $this->db->query("DELETE FROM $this->t_user WHERE accesslevel < 99");
		}
		if (!$ok) {
			$errors[] = "$this->t_user: " . $this->db->errstr;
			$this->db->rollback();
		} else {
			$this->db->commit();
		}
	}

	return count($errors) ? $errors : true;
}

function award_format($value, $format = '%s') {
	if (substr($format,0,1) == '%') return sprintf($format, $value);
	switch ($format) {
		case "commify": 	return commify($value);
		case "compacttime": 	return compacttime($value);
		case "date":		return ps_date_stamp($value);
		case "datetime":	return ps_datetime_stamp($value);
	}
	// the [brackets] will help troubleshoot issues when a invalid format is specified
	return "[ $value ]";
}

function gametype() {
	return $this->conf['main']['gametype'];
}
function modtype() {
	return $this->conf['main']['modtype'];
}

// mod sub-classes override these to modify various tables within the stats.
// this allows mods to add custom variables to tables specific to each mod.
function index_table_mod(&$table) {}
function player_weapons_table_mod(&$table) {}
function player_maps_table_mod(&$table) {}
function player_roles_table_mod(&$table) {}
function player_sessions_table_mod(&$table) {}
function player_victims_table_mod(&$table) {}

function weapons_table_mod(&$table) {}
function weapon_players_table_mod(&$table) {}

function roles_table_mod(&$table) {}
function role_players_table_mod(&$table) {}

function maps_table_mod(&$table) {}
function map_players_table_mod(&$table) {}

function clans_table_mod(&$table) {}
function clan_players_table_mod(&$table) {}
function clan_weapons_table_mod(&$table) {}
function clan_maps_table_mod(&$table) {}
function clan_roles_table_mod(&$table) {}

// add a new 'top10' player list to the map stats of the specified $map.
// $setup is an array of some pre-defined settings for the table.
function add_map_player_list_mod($map, $setup = array()) {}

// add a block of stats to the left side of the stats page.
// this is useful for mods to add their team specific stats.
function player_left_column_mod(&$plr, &$theme) {}
function clan_left_column_mod(&$clan, &$theme) {}
function map_left_column_mod(&$map, &$theme) {}

}  // end of PS class

?>
