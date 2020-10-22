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
	This GAMESPY class allows you to query any modern game that supports the NEW Gamespy network protocol.
	This class uses the new GameSpy query protocol (for games within the last year, 2004)

	This has been tested with the following games:

		Battlefield Vietnam
		
***/

if (!defined("CLASS_PQ_PHP")) die("Access Denied!");
if (defined("CLASS_PQ_GAMESPY_PHP")) return 1;
define("CLASS_PQ_GAMESPY_PHP", 1);

class PQ_gamespy extends PQ_PARENT {

function __construct($conf) {
	$this->PQ_gamespy($conf);
}

function PQ_gamespy($conf) {
	$this->conf = $conf;		// always save the config to the class variable first
	$this->init();			// always run the class initialization method
}

function init() {
	parent::init();			// always run the parent init method
	// add your own initialization steps here.
	// ...
}

function query_info($ip=NULL) {
	if (!$ip) $ip = $this->ipaddr();
	if (!$ip) return FALSE;
	$start = $this->_getmicrotime();
	$res = $this->_sendquery($ip, pack("CCC", 0xff,0xff,0xff));
	$end = $this->_getmicrotime();
	if (!$res) return FALSE;
	$this->data = array();			// query_info always resets the data array (so call this before other queries)
	if ($this->raw != '') {
		$this->raw = substr($this->raw, 5);			// remove header bytes
		$this->data['ping'] = ceil(($end - $start) * 1000);	// return the time (ms) it took for the packet to return (ping)
		$this->data['ipport'] = $ip;
		list($this->data['ip'], $this->data['port']) = explode(':', $this->data['ipport']);
		while ($this->raw != '') {
			$key = $this->_getnullstr();
			if ($key != '') {
				$this->data[$key] = $this->_getnullstr();
			} else {	// we've reached the end of the info block, next up: players
				break;
			}
		}
		$this->data['name'] = $this->data['hostname'];
		if ($this->raw == '') {
			return $this->data;
		} 

		// get player information
		$this->_getbyte();				// clear the first null byte
		$this->data['totalplayers'] = $this->_getbyte();
		$fields = array();
		do {						// get order of player info fields
			$key = $this->_getnullstr();
			if (strpos($key, "_") !== FALSE) $key = substr($key, 0, -1);	// remove trailing _ (useless!)
			if ($key == 'player') $key = 'name';
			if ($key != '') $fields[] = $key;
		} while ($key != '');

		$this->data['players'] = array();
		for ($i=0; $i < $this->data['totalplayers']; $i++) {
			$plr = array();
			foreach ($fields as $f) {
				$plr[$f] = $this->_getnullstr();
			}
			$this->data['players'][] = $plr;
		}

		// check and see if we have 'team' information still in the packet, if not, return our data
		if ($this->_getshort() != 0x0200) {
			return $this->data;
		}

		// get team information, this works in the same way as the player information
		$fields = array();
		do {						// get order of player info fields
			$key = $this->_getnullstr();
			if (strpos($key, "_") !== FALSE) $key = substr($key, 0, -2);	// remove trailing _t (useless!)
			if ($key != '') $fields[] = $key;
		} while ($key != '');

		$this->data['teams'] = array();
		while ($this->raw != '') {			// read the rest of the packet
			$team = array();
			foreach ($fields as $f) {
				$team[$f] = $this->_getnullstr();
			}
			$this->data['teams'][] = $team;
		}

		return $this->data;
	}
	return FALSE;
}

function query_players($ip=NULL) {
	return $this->query_info($ip);
}

function query_rules($ip=NULL) {
	return $this->query_info($ip);
}

function query_ping($ip=NULL) {
	$old = $this->data;
	$this->query_info($ip);
	$ping = $this->data['ping'];
	$this->data = $old;
	return $ping;
}

function _sendquery($ipport, $cmd) {
	list($ip,$port) = explode(':', $ipport);
	if (!$port) $port = '23000';
	$retry = 0;

	if (!$this->_connectsocket($ip, $port)) {
		trigger_error("Failed to connect to socket on $ip:$port", E_USER_WARNING);
		return FALSE;
	}

	$packets = array();
	$myid = 0x04030201;
	$command = pack("CCCV", 0xFE, 0xFD, 0x00, $myid) . $cmd;
	$this->raw = "";

	if ($this->DEBUG) print "DEBUG: Sending query to $ip:$port:\n" . $this->hexdump($command) . "\n";
	fwrite($this->sock, $command, strlen($command));

	$expected = 0;
	do {
		$packet = fread($this->sock, 64000);
		if ($packet == '') {
			$retry++;
			if ($this->DEBUG) print "DEBUG: Resending query $ip:$port:\n" . $this->hexdump($command) . "\n";
			fwrite($this->sock, $command, strlen($command));
			next;
		}

		if ($this->DEBUG) print "DEBUG: Received " . strlen($packet) . " bytes from $ip:$port:\n" . $this->hexdump($packet) . "\n";
		$ack = @unpack("x1null/V1remoteid", $packet);
//		print "ack (remoteid == " . 0x04030201 . ") = "; print_r($ack);
		if ($ack['remoteid'] != $myid) {
			$this->errstr = "Invalid response packet from server. Aborting!";
			trigger_error($this->errstr, E_USER_WARNING);
			return FALSE;
		} 
//		$this->raw .= substr($packet, 5);	// skip 5 header bytes
		$this->raw .= $packet;

	} while ($expected and $retry < $this->maxretries());

	fclose($this->sock);
	return TRUE;
}

}  // end of PQ_gamespy class

?>
