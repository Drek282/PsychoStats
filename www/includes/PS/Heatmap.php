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
 *	Version: $Id: Heatmap.php 510 2008-07-04 16:41:47Z lifo $
 *	
 *	PsychoStats Heatmap class
 *
 *      PsychoStats heatmaps show spatial information related to where players
 *	are on a map when they do something. A "Death Map" is the most common
 *	usage to show areas where people are killed the most on a map.
 *	
 *	@package PsychoStats
 */
if (defined("CLASS_PS_HEATMAP_PHP")) return 1;
define("CLASS_PS_HEATMAP_PHP", 1);

class PS_Heatmap {
var $ps = null;

// $ps is an PS object
function __construct(&$ps) {
	$this->ps = &$ps;
}
 
function PS_Heatmap(&$ps) {
        self::__construct($ps);
}

// returns a list of available heatmaps for the map specified
function get_map_heatmaps($mapid, $criteria = array()) {
//	$criteria += array( /* not used */ );
	$db =& $this->ps->db;
	$maps = array();

/*
	// get overall heatmaps (no hourly, weapons or players)
	$list = $db->fetch_rows(1, 
		"SELECT heatid,heatkey,statdate,enddate,who,DATEDIFF(enddate,statdate)+1 totaldays" . 
		" FROM " . $this->ps->t_heatmaps .
		" WHERE mapid=" . $db->escape($mapid, true) . 
		" AND hour IS NULL" . 
		" AND (heatkey=". $db->escape($this->heatkey(array( 'mapid' => $mapid, 'who' => 'victim' )),true) . 
		" OR heatkey=" . $db->escape($this->heatkey(array( 'mapid' => $mapid, 'who' => 'killer' )),true) . 
		" OR heatkey=" . $db->escape($this->heatkey(array( 'mapid' => $mapid, 'who' => 'both' )),true) . 
		")" . 
		" ORDER BY who DESC" .
		" LIMIT 3"
	);
	foreach ($list as $m) {
		$m['label'] = "Overall";
		if ($m['who'] != 'both') $m['label'] .= " (" . $m['who'] . "s)";
		$maps[] = $m;
	}
#	print $db->lastcmd . "<br/>";
#	print $db->errstr . "<br/>";
*/

	$list = $db->fetch_rows(1, 
		"SELECT heatid,heatkey,statdate,enddate,hour,who,h.weaponid,lastupdate,DATEDIFF(enddate,statdate)+1 totaldays" . 
		",COALESCE(w.name,w.uniqueid) weaponlabel" . 
		" FROM {$this->ps->t_heatmaps} h" .
		" LEFT JOIN {$this->ps->t_weapon} w ON w.weaponid=h.weaponid" .
		" WHERE mapid=" . $db->escape($mapid, true) .
		" GROUP BY heatkey,who"
	);
//	print $db->errstr;
	// loop through each heatmap row and determine the type and what criteria it has
	$no_criteria 	= $this->heatkey(array( 'mapid' => $mapid ));
	$hourly 	= $this->heatkey(array( 'mapid' => $mapid, 'hourly' => true ));
	foreach ($list as $m) {
		// overall heatmap: no specific criteria except for 'who'
		if ($m['hour'] == null and $m['heatkey'] == $no_criteria) {
			$m['label'] = $this->_who_label("Overall ", $m['who']);

		} elseif ($m['hour'] != null and $m['heatkey'] == $hourly) {
			$m['hour'] = true;
			$m['label'] = $this->_who_label("Hourly ", $m['who']);

		} elseif ($m['team'] != null) {
			$m['label'] = $this->_who_label("Combined " . $m['team'] . " " . $m['weaponlabel'] . " ", $m['who']);

		} elseif ($m['kteam'] != null and $m['vteam'] != null) {
			$m['label'] = $this->_who_label("Killer team " . $m['vteam'] . ", victim team " . $m['vteam'] . " " . $m['weaponlabel'] . " ", $m['who']);

		} elseif ($m['kteam'] != null) {
			$m['label'] = $this->_who_label("Killer team " . $m['kteam'] . ": " . $m['weaponlabel'] . " ", $m['who']);

		} elseif ($m['vteam'] != null) {
			$m['label'] = $this->_who_label("Victim team " . $m['vteam'] . ": " . $m['weaponlabel'] . " ", $m['who']);

		} elseif ($m['hour'] == null and $m['weaponid'] != null
			and $m['heatkey'] == $this->heatkey(array( 'mapid' => $mapid, 'weaponid' => $m['weaponid'] )))
		{
			$m['label'] = $this->_who_label("Weapon: " . $m['weaponlabel'] . " ", $m['who']);

		} elseif ($m['hour'] != null and $m['weaponid'] != null
			and $m['heatkey'] == $this->heatkey(array( 'mapid' => $mapid, 'weaponid' => $m['weaponid'], 'hourly' => true )))
		{
			$m['hour'] = true;
			$m['label'] = $this->_who_label("Hourly Weapon: " . $m['weaponlabel'] . " ", $m['who']);

		} else {
			continue;
		}

		//          print_r($m);
		$maps[ $m['heatid'] ] = $m;
	}
	return $maps;
}

// returns a list of available heatmaps for the player specified
function get_player_heatmaps($plrid, $criteria = array()) {
	// ...
}

// returns a list of heatmap ids that are related to the criteria given
// if $ids_only is true then a flat list of heatmap id's are returned (recommended). 
// If false, all columns are returned including the datablob! which could amount to a lot of data!!
function get_heatmap_images($mapid, $criteria = array(), $ids_only = true) {
	static $valid = array('heatkey','statdate','enddate','who','weaponid','pid','kid','vid','team','kteam','vteam','headshot');
	$db =& $this->ps->db;
	$where = "mapid=" . $db->escape($mapid, true);
	foreach ($criteria as $key => $val) {
		if (!in_array($key, $valid)) continue;
		$where .= " AND $key" . (is_null($val) ? ' IS NULL' : "=" . $db->escape($val,true));
	}
	// hour is checked separately due to its special requirement
	$where .= " AND hour IS " . (isset($criteria['hour']) ? 'NOT NULL' : 'NULL');
	if ($ids_only) {
		$list = $db->fetch_list("SELECT heatid FROM {$this->ps->t_heatmaps} WHERE $where ORDER BY hour");
	} else {
		$list = $db->fetch_rows(1,"SELECT * FROM {$this->ps->t_heatmaps} WHERE $where ORDER BY hour");
	}
	return $list;
}

function total_heatmap_images($mapid, $criteria = array()) {
	static $valid = array('heatkey','statdate','enddate','who','weaponid','pid','kid','vid','team','kteam','vteam','headshot');
	$db =& $this->ps->db;
	$where = "mapid=" . $db->escape($mapid, true);
	foreach ($criteria as $key => $val) {
		if (!in_array($key, $valid)) continue;
		$where .= " AND $key" . (is_null($val) ? ' IS NULL' : "=" . $db->escape($val,true));
	}
	// hour is checked separately due to its special requirement
	$where .= " AND hour IS " . (isset($criteria['hour']) ? 'NOT NULL' : 'NULL');
	list($total) = $db->fetch_list("SELECT COUNT(*) FROM {$this->ps->t_heatmaps} WHERE $where");
	return $total;
}

// private function. modifies the label given depending on the 'who' setting
function _who_label($label, $who = false) {
	switch ($who) {
		case 'both': 	$label .= '(killers and deaths locations)'; break;
		case 'victim': 	$label .= 'deaths locations'; break;
		case 'killer': 	$label .= 'killers locations'; break;
	}
	return $label;
}

// outputs the heatmap that matches the $id given.
// if $content_type is true 'image/png' will be sent, 
// or if $content_type is a non-empty string its value is sent instead.
// returns FALSE if no matching image is found.
function image_passthru($id, $content_type = true, $skip_cache = false) {
	// first check and see if the client has the requested image cached.
	// this only works on apache servers.
	if (!$skip_cache and function_exists('apache_request_headers')) {
		list($lastupdate) = $this->ps->db->fetch_list("SELECT UNIX_TIMESTAMP(lastupdate) FROM {$this->ps->t_heatmaps} WHERE heatid=" . 
			$this->ps->db->escape($id, true)
		);
		$headers = apache_request_headers();
		$if_modified_since = preg_replace('/;.*$/', '', $headers['If-Modified-Since']);
		if ($if_modified_since) {
			$gmtime = gmdate("D, d M Y H:i:s", $lastupdate) . " GMT";
			if ($if_modified_since == $gmtime) {
				header("HTTP/1.1 304 Not Modified");
				return;
			}
		}
	}

	list($type,$img,$lastupdate) = $this->ps->db->fetch_list(
		"SELECT datatype,COALESCE(datafile,datablob),UNIX_TIMESTAMP(lastupdate) FROM {$this->ps->t_heatmaps} WHERE heatid=" . 
			$this->ps->db->escape($id, true)
	);
//	print $this->ps->db->errstr;
	if (!empty($img)) {
		if ($content_type) {
			// if content_type is boolean use default type, otherwise use what was passed in.
			$ct = is_bool($content_type) ? 'image/png' : $content_type;
			header("Content-Type: $ct");
		}

/*
		$expires = $this->ps->conf['main']['heatmap']['expires'];
		if ($expires) {
			header("Expires: " . gmdate("D, d M Y H:i:s", $lastupdate + $expires) . " GMT");
		}
*/
//		header("Cache-Control: max-age=$expires, public, must-revalidate");
		header("Cache-Control: public, must-revalidate");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s", $lastupdate) . " GMT");
		header("Connection: close");
		if ($type == 'file') {
			if (file_exists($img)) {
				$length = @filesize($img);	// @ to suppress warning if file doesn't exist
				if (!$length) $length = 0;
				header('Content-Length: ' . $length); 
				readfile($img);
			} else {
				header('X-PsychoStats-Error: File not found!');
			}
		} else {
			header('Content-Length: ' . strlen($img)); 
			print $img;
		}
		return true;
	}
	return false;
}

// generates a heatkey with the criteria specified
function heatkey($criteria = array()) {
	// this order is very important and should never change
	static $order = array( 'mapid', 'weaponid', 'pid', 'kid', 'team', 'kteam', 'vid', 'vteam', 'headshot' );
	$criteria += array( 'who' => 'victim' );	 // 'who' is the only criteria that doesn't default to NULL
	$key = "";
	foreach ($order as $k) {
		if (array_key_exists($k, $criteria) and $criteria[$k] !== NULL) {
			$key .= $criteria[$k];
		} else {
			$key .= 'NULL';
		}
		$key .= '-';
	}
	// add 'hourly' string to key if an hour is present in criteria
	if (array_key_exists('hourly', $criteria) and !is_null($criteria)) {
		$key .= 'hourly-';
	}
	return sha1(substr($key,0,-1));
}

} // END OF class PS_Heatmap
?>
