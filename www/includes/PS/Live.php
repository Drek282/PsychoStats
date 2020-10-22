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
 *	Version: $Id: Live.php 566 2008-10-14 15:09:38Z lifo $
 *	
 *	PsychoStats PsychoLive class
 *
 *	@package PsychoStats
 */
if (defined("CLASS_PS_LIVE_PHP")) return 1;
define("CLASS_PS_LIVE_PHP", 1);

class PS_Live {
var $ps = null;
var $delay = 0;

// $ps is an PS object
function PS_Live(&$ps, $delay = 0) {
	$this->ps = &$ps;
	$this->delay = is_numeric($delay) ? $delay : 0;
}

// returns the newest game_id available for playback (false if no games)
function newest_game_id($server = null) {
	$ip = $port = 0;
	if ($server) {
		// extract IP : port from server string
		list($ip,$port) = explode(':', $server);
		if (!is_numeric($ip)) {	// convert the IP to an integer
			$ip = sprintf('%u', ip2long($ip));
		}
	}
	if ($ip) {
		$game_id = $this->ps->db->fetch_item(
			"SELECT game_id FROM {$this->ps->t_live_games} " .
			"WHERE server_ip=$ip " . ($port ? "AND server_port=" . $this->ps->db->escape($port,true) . " " : "") . 
			"ORDER BY start_time DESC,end_time DESC LIMIT 1"
		);
	} else {
		$game_id = $this->ps->db->fetch_item(
			"SELECT game_id FROM {$this->ps->t_live_games} " .
			"ORDER BY start_time DESC,end_time DESC LIMIT 1"
		);
	}
	return $game_id ? $game_id : false;
}

// returns true or false if a game matching the game_id given exists
function game_exists($id) {
	$exists = $this->ps->db->exists($this->ps->t_live_games, 'game_id', $id);
	return $exists;
}

// returns basic info on a single game.
// @param integer $id Game ID to load
// @param integer $advance Advance/forward game to a particular spot.
//	-1 = last known event. Any other positive number will advance to
// 	the specified number.
// @param boolean $nomap If true, no map or overlay information is loaded.
// @param integer $delay Delay value
function get_game_info($id, $advance = false, $nomap = false, $delay = null) {
	if (is_null($delay)) $delay = $this->delay;
	$delay = abs(intval($delay));
	$_id = $this->ps->db->escape($id, true);
	$game = $this->ps->db->fetch_row(1,
		"SELECT g.*, " .
			"(SELECT MAX(event_idx) FROM {$this->ps->t_live_events} e " .
			"WHERE e.game_id=g.game_id) max_idx, " .
			"(SELECT COUNT(*) FROM {$this->ps->t_live_events} e " .
			"WHERE e.game_id=g.game_id) total_events " .
		"FROM {$this->ps->t_live_games} g WHERE game_id=$_id"
	);
	if ($game) {
		// The next_offset allows us to determine where we need to
		// advance/fast-foward to when starting in the middle of a live
		// game. And the newest_timestamp allows us to determine the
		// onlinetime for the map.
		if ($advance) {
			list($game['next_offset'], $game['newest_timestamp']) =
				$this->ps->db->fetch_list(
					"SELECT MAX(event_idx),MAX(event_time) " .
					"FROM {$this->ps->t_live_events} WHERE game_id=$_id" . 
					(($delay>0) ? " AND UNIX_TIMESTAMP() - event_time >= $delay " : "")
				);
		} else {
			$game['next_offset'] = '0';
			$game['newest_timestamp'] =
				$this->ps->db->fetch_item(
					"SELECT MIN(event_time) " .
					"FROM {$this->ps->t_live_events} WHERE game_id=$_id" . 
					(($delay>0) ? " AND UNIX_TIMESTAMP() - event_time >= $delay " : "")
				);
		}
	
		if (!$nomap) {
			// get the map thumbnail URL (or false if not found)
			$game['map_url'] = $this->ps->mapimg($game['map'], array(
				'gametype' => $game['gametype'],
				'modtype' => $game['modtype'],
				'urlonly' => true,
				'noimg' => false
			));
	
			// determine where our overlay image is and its dimensions
			$game['overlay'] = $this->ps->get_overlay($game['map'], $game['gametype'], $game['modtype']);
		}
		
		// If we need to advance to the current offset then we loop
		// through all the events for this game up to the current time.
		// this is the only way to accurately determine the stats for
		// each player and who is alive, etc...
		// TODO: consider caching this information in another table so
		// it only has to be performed once per offset.
		if ($advance and $game['next_offset']) {
			$ofs = $advance > 0 ? $advance : $game['next_offset'];
			$list = $this->get_players_and_items($id, $game['next_offset'], $game['newest_timestamp']);
			$game['items'] = $list['items'];
			$game['players'] = $list['players'];
		}
	}
	return $game ? $game : false;
}

// Returns a list of players including their known state up to the offset given.
// @param integer $id  Game ID.
// @param integer $offset  Ending offset to calculate player state from.
function get_players_and_items($id, $offset = 0, $timestamp = null) {
	$plrs = array();
	$items = array();
	$offset = abs(intval($offset));
	if (!$offset) return $plrs;	// shortcut; nothing to do
	
	$_id = $this->ps->db->escape($id, true);
	$cmd  = "SELECT e.event_time,e.event_type,e.ent_id,e.ent_id2,e.xyz,e.value,p.ent_type,p.ent_name " .
		"FROM {$this->ps->t_live_events} e " .
		"LEFT JOIN {$this->ps->t_live_entities} p ON (p.game_id=$_id AND p.ent_id=e.ent_id) " . 
		"WHERE e.game_id=$_id AND event_idx < $offset " .
		"AND NOT event_type IN ('PLR_MOVE','PLR_HURT') " . 
		"ORDER BY event_idx ";
	if (!$this->ps->db->query($cmd)) {
		// an error occured, just ignore it and return nothing
		return array();
	}
	
	// Loop through all game events to build up the player states. This
	// almost mimics what would normally happen in the front-end, just w/o
	// all the animation, etc. There's no better way to do this yet...
	while ($e = $this->ps->db->fetch_row(1)) {
		$e['ent_id'] = intval($e['ent_id']);
		switch ($e['event_type']) {
			case 'ROUND_START':
				break;
			case 'ROUND_END':
				// reset flags on all connected players
				$keys = array_keys($plrs);
				for ($i=0,$j=count($keys); $i<$j; $i++) {
					$plrs[ $keys[$i] ]['spawned'] = false;
					unset($plrs[ $keys[$i] ]['items']);
					unset($plrs[ $keys[$i] ]['actions']);
				}
				$items = array();
				break;
			case 'PLR_CONNECT':
				$plrs[ $e['ent_id'] ] = array(
					'ent_id'	=> $e['ent_id'],
					'ent_name'	=> $e['ent_name'],
					'ent_type'	=> $e['ent_type'],
					'start_time'	=> intval($e['event_time']),
					'kills'		=> 0,
					'deaths'	=> 0,
					'suicides'	=> 0,
					'onlinetime'	=> 0,
					'team'		=> 0,
					'health'	=> 100,
					'xyz'		=> '',
					'spawned'	=> false,
					'alive'		=> false
				);
				break;
			case 'PLR_DISCONNECT':
				unset($plrs[ $e['ent_id'] ]);
				break;
			case 'PLR_SPAWN':
				$plrs[ $e['ent_id'] ]['xyz'] = $e['xyz'];
				$plrs[ $e['ent_id'] ]['spawned'] =
				$plrs[ $e['ent_id'] ]['alive'] = true;
				break;
			//case 'PLR_MOVE': break;
			case 'PLR_KILL':
				if ($e['ent_id'] == $e['ent_id2']) {
					$plrs[ $e['ent_id'] ]['suicides']++;
					$plrs[ $e['ent_id'] ]['alive'] = false;
				} else {
					$plrs[ $e['ent_id'] ]['kills']++;
					$plrs[ $e['ent_id2'] ]['deaths']++;
					$plrs[ $e['ent_id2'] ]['alive'] = false;
				}
				break;
			case 'PLR_TEAM':
				$plrs[ $e['ent_id'] ]['team'] = intval($e['value']);
				break;
			case 'PLR_NAME':
				$plrs[ $e['ent_id'] ]['ent_name'] = $e['value'];
				break;
			//case 'PLR_HURT': break;
			case 'PLR_BOMB_PICKUP':
				$this->_plr_pickup($plrs, $e['ent_id'], 'bomb');
				unset($items['bomb']);
				break;
			case 'PLR_BOMB_PLANTED':
				$this->_plr_action($plrs, $e['ent_id'], 'planted_bomb', $e['xyz']);
				$items['bomb'] = array(
					'xyz' => $e['xyz'] ? $e['xyz'] : $plrs[ $e['ent_id'] ]['xyz'],
					'timestamp' => intval($e['event_time']),
					'value' => intval($e['value'])
				);
			case 'PLR_BOMB_DROPPED':
				$this->_plr_drop($plrs, $e['ent_id'], 'bomb');
				if (!$items['bomb']) $items['bomb'] = array();
				$items['bomb']['xyz'] = $e['xyz'] ? $e['xyz'] : $plrs[ $e['ent_id'] ]['xyz'];
				break;
			case 'PLR_BOMB_DEFUSED':
			case 'PLR_BOMB_EXPLODED':
				unset($items['bomb']);
				break;
		}
	}

	// get the last movement for each player that is connected. This is done
	// separately from the event loop above because its extremely wasteful
	// to process all movement events instead of just looking at the last
	// one for each player.
	$ids = array_keys($plrs);
	$list = $this->ps->db->fetch_rows(1,
		"SELECT ent_id,xyz FROM {$this->ps->t_live_events} e " .
		"WHERE e.game_id=$_id AND event_idx < $offset " .
		"AND event_type='PLR_MOVE' " .
		"AND ent_id IN (" . implode(',',$ids) . ") " .
		"ORDER BY event_idx DESC " .
		"LIMIT " . count($ids)
	);
	for ($i=0, $j=count($list); $i < $j; $i++) {
		$plrs[ $list[$i]['ent_id'] ]['xyz'] = $list[$i]['xyz'];
	}
	unset($list);

	if (!$timestamp) {
		// determine the newest timestamp of the current player state
		$timestamp = $this->ps->db->fetch_item(
			"SELECT MAX(event_time) FROM {$this->ps->t_live_events} " .
			"WHERE game_id=$_id AND event_idx < $offset "
		);
	}
	
	// calculate onlinetime for each player
	$keys = array_keys($plrs);
	for ($i=0,$j=count($keys); $i<$j; $i++) {
		$plrs[ $keys[$i] ]['onlinetime'] = $timestamp - $plrs[ $keys[$i] ]['start_time'];
	}

	$list = array();
	$list['players'] = array_values($plrs);	// remove the keys from the array
	$list['items'] = $items;
	return $list;
}

// private: a player did something to affect the environment
function _plr_action(&$plrs, $ent_id, $item, $value = true) {
	// make sure no other players are doing the same thing
	$keys = array_keys($plrs);
	for ($i=0,$j=count($keys); $i<$j; $i++) {
		unset($plrs[ $keys[$i] ]['actions'][$item]);
		if (!$plrs[$ent_id]['actions']) {
			unset($plrs[$ent_id]['actions']);
		}
	}
	$plrs[$ent_id]['actions'][$item] = $value;
}

// private: a player picked up something
function _plr_pickup(&$plrs, $ent_id, $item, $mulitple = false) {
	if (!$multiple) {
		// make sure no other players have the item
		$keys = array_keys($plrs);
		for ($i=0,$j=count($keys); $i<$j; $i++) {
			unset($plrs[ $keys[$i] ]['items'][$item]);
		}
	}
	// give it the player
	$plrs[$ent_id]['items'][$item] = true;
}
// private: a player dropped something
function _plr_drop(&$plrs, $ent_id, $item) {
	unset($plrs[$ent_id]['items'][$item]);
	if (!$plrs[$ent_id]['items']) {
		unset($plrs[$ent_id]['items']);
	}
}

// Returns a list of events for the game.
// @param integer $id  Game ID to match.
// @param integer $offset  Starting offset for events to fetch (inclusive)
// @param integer $seconds  Total seconds of events to return. 0 will return all starting at $offset
// @param integer $delay  Total seconds to delay realtime playback
function get_game_events($id, $offset = 0, $seconds = 5, $delay = null) {
	if (is_null($delay)) $delay = $this->delay;
	$delay = abs(intval($delay));
	$offset = abs(intval($offset));
	if (!is_numeric($seconds) or $seconds < 0) {
		$seconds = 5;
	}
	$limit = 400;
	$_id = $this->ps->db->escape($id, true);

	$cmd =  "SELECT event_idx,event_time,event_type,ent_id,ent_id2,xyz,weapon,value,json " .
		"FROM {$this->ps->t_live_events} WHERE game_id=$_id " . 
		"AND event_idx >= %u ";
	if ($delay > 0) {
		$cmd .= "AND UNIX_TIMESTAMP() - event_time >= $delay ";
	}
	$cmd .= "ORDER BY event_idx LIMIT " . $limit;

	// Collect events until we have $seconds total events.
	// This is done in a loop like this since there can be several second
	// gaps between events which will cause a problem when trying to fetch
	// the most recent events within those gaps if done purely by
	// event_time.
	$events = array();
	$newest_time = 0;
	$stop_time = 0;
	do {
		// fetch a list of $limit events
		$list = $this->ps->db->fetch_rows(1, sprintf($cmd, $offset));
		//syslog(LOG_INFO, "pslive: " . $this->ps->db->lastcmd);
		if (!$list) {
			// no more events! ohhh noes!
			break;
		}
	
		// initialize the stopping time now that we have some events
		if (!$stop_time) {
			$stop_time = $list[0]['event_time'] + $seconds;
		}
		
		$newest_time = $list[count($list)-1]['event_time'];
		//syslog(LOG_INFO, "pslive: NEWEST: $newest_time");
		//syslog(LOG_INFO, "pslive: STOP:   $stop_time");
		if ($newest_time <= $stop_time) {
			// if true, the current chunk of events is not enough,
			// so we eat the entire array all at once...
			$events = array_merge($events, $list);
			$offset = $events[count($events)-1]['event_idx'] + 1;
		} else {
			// $list contains too many events. So we need to
			// selectively add them to our $events queue and stop.
			for ($i=0, $j=count($list); $i < $j; $i++) {
				$newest_time = $list[$i]['event_time'];
				if ($newest_time < $stop_time) {
					$events[] = $list[$i];
				} else {
					break;
				}
			}
			$offset = $events[count($events)-1]['event_idx'] + 1;
		}
	} while ($newest_time < $stop_time);
	
	return $events;
}

// returns a list of games that are available
function get_game_list($args = array()) {
	$args['sort'] = trim(strtolower($args['sort']));
	$args['order'] = trim(strtolower($args['order']));
	if (!in_array($args['sort'], array('start_time','start_time,end_time','server_name','gametype','modtype','map'))) $args['sort'] = 'start_time,end_time';
	if (!in_array($args['order'], array('asc','desc'))) $args['order'] = 'desc';
	if (!is_numeric($args['start']) || $args['start'] < 0) $args['start'] = 0;
	if (!is_numeric($args['limit']) || $args['limit'] < 0) $args['limit'] = 100;
	$args['filter'] = trim($args['filter']);
	
	$cmd  = "SELECT g.*, " .
		"IF(end_time, end_time - start_time, " . 
			"(SELECT MAX(event_time) FROM {$this->ps->t_live_events} e " .
			"WHERE e.game_id=g.game_id) - start_time" .
		") total_time, " .
		"(SELECT COUNT(*) FROM {$this->ps->t_live_entities} " .
			"WHERE game_id=g.game_id) total_players " .
		//"end_time - start_time total_time " .
		"FROM {$this->ps->t_live_games} g ";
	if ($args['filter']) {
		$q = $this->ps->db->escape($args['filter']);
		$cmd .= "WHERE map LIKE '$q' OR server_name LIKE '$q'";
		unset($q);
	}
	$cmd .= $this->ps->getsortorder($args);
	$list = $this->ps->db->fetch_rows(1, $cmd);

	for ($i=0, $j=count($list); $i<$j; $i++) {
		// calculate the elapsed time
		if (!is_null($list[$i]['total_time'])) {
			$elapsed = elapsedtime($list[$i]['total_time'],0,true);
			$list[$i]['elapsed_time'] = elapsedtime_str($elapsed);
		}
		// get the map thumbnail url (or false if not found)
		$list[$i]['map_url'] = $this->ps->mapimg($list[$i]['map'], array(
			'gametype' => $list[$i]['gametype'],
			'modtype' => $list[$i]['modtype'],
			'urlonly' => true,
			'noimg' => false
		));
	}

	return $list;
}

} // END OF class PS_Live
?>
