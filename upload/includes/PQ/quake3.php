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

/**********

	This QUAKE3 class will allow you to query the master servers for quake3 based games
	and query Q3 based games for their server status. There is no 'ping' and 'rules' query for Q3.
	To get the ping just do a normal 'info' query and grab the ping value from the returned data.

	So far only basic Q3 games have been tested.

	master servers: 						Protocol#?
		monster.idsoftware.com:27950	(quake3)		68
		wolf.idsoftware.com:27950	(RTCW v1.41)		
		etmaster.idsoftware.com:27950	(Enemy Territory)	

**********/

if (!defined("CLASS_PQ_PHP")) die("Access Denied!");
if (defined("CLASS_PQ_QUAKE3_PHP")) return 1;
define("CLASS_PQ_QUAKE3_PHP", 1);

define("PQ_Q3_GAMETYPE_FREEFORALL", 	0);
define("PQ_Q3_GAMETYPE_TOURNAMENT", 	1);
define("PQ_Q3_GAMETYPE_SINGLEPLAYER", 	2);
define("PQ_Q3_GAMETYPE_TEAMDM", 	3);
define("PQ_Q3_GAMETYPE_CTF",	 	4);
define("PQ_Q3_GAMETYPE_ONEFLAGCTF", 	5);
define("PQ_Q3_GAMETYPE_OBELISK", 	6);
define("PQ_Q3_GAMETYPE_HARVESTER", 	7);
define("PQ_Q3_GAMETYPE_TEAMTOURNAMENT",	8);

class PQ_quake3 extends PQ_PARENT {

function __construct($conf) {
	$this->PQ_quake3($conf);
}

function PQ_quake3($conf) {
	$this->conf = $conf;		// always save the config to the class variable first
	$this->init();			// always run the class initialization method
}

function init() {
	parent::init();			// always run the parent init method
	// add your own initialization steps here.
	// ...

	$this->conf += array(
		'protocol'	=> 68,	// 68 == Q3 servers
	);

}

// The sub-class must always contain this method to query basic server information
function query_info($ip=NULL) {
	if (!$ip) $ip = $this->ipaddr();
	if (!$ip) return FALSE;
	$start = $this->_getmicrotime();
	$res = $this->_sendquery($ip, 'getstatus');
	$end = $this->_getmicrotime();
	if (!$res) return FALSE;
	$this->data = array();					// query_info always resets the data array (so call this before other queries)
	$code = '';
	if ($this->raw != '') {
		$this->data['ping'] = ceil(($end - $start) * 1000);	// return the time (ms) it took for the packet to return (ping)
		$this->data['ipport'] = $ip;
		list($this->data['ip'], $this->data['port']) = explode(':', $this->data['ipport']);

		$this->raw = substr($this->raw, 18);		// strip off response header bytes
		$code = $this->_getchar();			// get EOT character: 0x0A
		$this->_getchar();				// clear trailing slash \
		$block = "";

		$pos = strpos($this->raw, $code);		// extract the status block from the raw input (0x0A . . . 0x0A)
		$block = substr($this->raw, 0, $pos);
		$block = str_replace("\\", "\0", $block);	// replace \ with nulls to make parsing in a loop easier
		$this->raw = substr($this->raw, $pos+1);	// cut the status block out of our raw buffer

		$old = $this->raw;
		$this->raw = $block . "\0";
		while ($this->raw != '') {
			$key = $this->_getnullstr();
			$value = $this->_getnullstr();
			$key = str_replace(' ', '_', $key);	// do not allow spaces in key names
			$this->data[strtolower($key)] = $value;
#			if ($key{0} == '.') {			// some keys have a leading dot. record a 2nd key w/o the dot.
#				$key = substr($key, 1);
#				$this->data[strtolower($key)] = $value;
#			}

			if ($key == 'sv_maxclients') $this->data['maxplayers'] = $value;
			if ($key == 'sv_hostname') $this->data['name'] = $value;
		}
		$this->raw = $old;
		// gather player information ...
		if ($this->raw != '') {
			$this->raw = substr($this->raw, 0, -1);		// ignore trailing newline (0x0A)
			$plrs = explode(chr(0x0A), $this->raw);
			$this->raw = '';
			$this->data['players'] = array();
			foreach ($plrs as $p) {
				list($kills, $ping, $name) = explode(" ", $p, 3);
				$this->data['players'][] = array(
					'name'	=> substr($name, 1, -1),	// ignore surrounding quotes
					'kills' => $kills,
					'ping'	=> $ping
				);
			}
		}

		return $this->data;
	}
	return FALSE;
}

function query_rules() {
	// no support	
}

function query_ping($ip) {
	$q = $this->query_info($ip);
	return $q['ping'];
}

// internal function to send a non-authoritative query to a quake3 server (NOT RCON COMMANDS)
function _sendquery($ipport, $cmd) {
	list($ip,$port) = explode(':', $ipport);
	if (!$port) $port = 27960;
	$retry = 0;

	if (!$this->_connectsocket($ip, $port)) {
		trigger_error("Failed to connect to socket on $ip:$port", E_USER_WARNING);
		return FALSE;
	}

	$packets = array();					// stores each packet seperately, so we can combine them afterwards
	$command = pack("N", 0xFFFFFFFF) . $cmd . pack('x');
	$this->raw = "";

	if ($this->DEBUG) print "DEBUG: Sending query to $ip:$port:\n" . $this->hexdump($command) . "\n";
	fwrite($this->sock, $command, strlen($command));

	$expected = 0;						// # of packets we're expecting
	do {
		$packet = fread($this->sock, 1500);
		if (strlen($packet) == 0) {
			$retry++;
			if ($this->DEBUG) print "DEBUG: Resending query $ip:$port:\n" . $this->hexdump($command) . "\n";
			fwrite($this->sock, $command, strlen($command));
			$expected = 1;
			next;
		}

		if ($this->DEBUG) print "DEBUG: Received " . strlen($packet) . " bytes from $ip:$port:\n" . $this->hexdump($packet) . "\n";

		$header = substr($packet, 0, 4);				// get the 4 byte header
		$ack = @unpack("N1split", $header);
		$split = sprintf("%u", $ack['split']);
		if ($this->DEBUG) print "DEBUG: ACK = " . sprintf("0x%X", $ack['split']) . "\n";
		if ($split == 0xFeFFFFFF) {				// we need to deal with multiple packets
			$packet = substr($packet, 4);				// strip off the leading 4 bytes
			$header = substr($packet, 0, 5);			// get the 'sub-header ack'
			$packet = substr($packet, 5);				// strip off 32bit int ID, seq# and total packet#
			$info = @unpack("N1id/C1byte", $header);		// we don't really care about the ID
			if ($this->DEBUG) printf("DEBUG: Sub ACK: %X (%08b)\n", $info['byte'], $info['byte']);
			if (!$expected) $expected = $info['byte'] & 0x0F;	// now we know how many packets to receive
			$seq = (int)($info['byte'] >> 4);			// get the sequence number of this packet
			$packets[$seq] = $packet;				// store the packet
			$expected--;
		} elseif ($split == 0xFFFFFFFF) {				// we're dealing with a single packet
			$packets[0] = $packet;
			$expected = 0;
		}
	} while ($expected and $retry < $this->maxretries());

	fclose($this->sock);
	ksort($packets, SORT_NUMERIC);
	$this->raw = implode('', $packets);				// glue the packets together to make our final data string
	return TRUE;
}

function query_master($ip=NULL, $filter=array(), $callback=NULL) {
	if (!$ip) $ip = $this->ipaddr();
	if (!$ip) return FALSE;
	if (!$filter['protocol']) $filter['protocol'] = $this->conf['protocol'];
	$iplist = array();
	$res = $this->_sendmasterquery($ip, $filter['protocol']);
	if (!$res) {
		return FALSE;
	}
	$this->raw = substr($this->raw, 23);		// remove leading '....serverResponse\' header
	$iplist = array();
	while ($this->raw != '') {
		$newip = "";
		$newip .= $this->_getbyte() . '.';
		$newip .= $this->_getbyte() . '.';
		$newip .= $this->_getbyte() . '.';
		$newip .= $this->_getbyte() . ':';
		$newip .= ($this->_getbyte() << 8) | $this->_getbyte();
		$this->_getbyte();			// ignore '/'
		$iplist[] = $newip;
	}

	return $iplist;
}

function _sendmasterquery($ipport, $protocol) {
	list($ip,$port) = explode(':', $ipport);
	if (!$port) $port = 27950;
	$retry = 0;

	if (!$this->_connectsocket($ip, $port)) {
		trigger_error("Failed to connect to socket on $ip:$port", E_USER_WARNING);
		return FALSE;
	}

	$command = pack("V", 0xFFFFFFFF) . 'getservers ' . $protocol;
	$this->raw = "";

	if ($this->DEBUG) print "DEBUG: Sending query to $ip:$port:\n" . $this->hexdump($command) . "\n";
	fwrite($this->sock, $command, strlen($command));

	// note: this network code doesn't actually try any RETRIES
	do {
		$packet = fread($this->sock, 64000);
		if ($packet != '') {
			if ($this->DEBUG) print "DEBUG: Received " . strlen($packet) . " bytes from $ip:$port:\n" . $this->hexdump($packet) . "\n";
			if ($this->raw != '') {
				$packet = substr($packet, 23);		// remove leading "....getserverResponse\"
			}
			$packet = substr($packet, 0, -3);		// remove trailing "EOT"
			$this->raw .= $packet;
		}
	} while ($packet != '');

	fclose($this->sock);
	return TRUE;
}

}

?>
