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

/* 

		PsychoQuery PHP class :: Game server network queries.

		The PQ API was created using a FACTORY pattern. This makes it very easy to plug in new sub-classes by
		simply dropping in the new sub-class file into the PQ directory. All sub-classes must extend 'PQ_PARENT'
		PsychoQuery works with PHP4 and PHP5.

		Current game support:		(supported)
			Halflife 1		(info, rcon)
			Halflife 2 (source)	(info, rcon, master)
			COD4            (info)
			Quake 3			(info, master)
*/

if (!defined("CLASS_PQ_PHP")) die("Access Denied!");
if (defined("PQ_PARENT_PHP")) return 1;
define("PQ_PARENT_PHP", 1);

#[AllowDynamicProperties]
class PQ_PARENT {

// to remain PHP4 compatible I declare our object members here like this
var $DEBUG 	= 0;
var $sock	= NULL;
var $errno	= 0;
var $errstr	= '';
var $conf 	= array();
var $rconpass	= '';
var $raw	= '';				// raw data string
var $data	= array();			// array of parsed data from a query
var $ping	= 0;				// response time from last command sent/recv

function __construct($conf=array()) {
	$this->PQ_PARENT($conf);
}

function PQ_PARENT($conf=array()) {
    self::__construct($conf);
}

// This is called by the sub-classes to initialize extra stuff for the class
function init() {
	// nothing to do here for now ...
}

function query($query, $resetdata=0) {
	$result = array();
	if (!is_array($query)) {		// force $query into an array
		$q = $query;
		$query = array( $q );
		unset($q);
	}

	if ($resetdata) {			// reset/clear the current data array
		$this->data = array();
	}

	// loop through requested queries
	foreach ($query as $q) {
		$method = "query_" . $q;
		if (method_exists($this, $method)) {
			$res = $this->$method();
			if (is_array($res)) {
				$result += $res;
			} else {
				trigger_error("Server did not respond to '$q' query", E_USER_WARNING);
			}
		} else {
			trigger_error("Invalid query specified ($q)", E_USER_WARNING);
		}
	}
	return $result;
}

// shortcut for calling the query_ping() method. returns the number of milliseconds it took for the server to respond
function ping($ip=NULL) {
	return $this->query_ping($ip);
}

// alias for query_rcon
function rcon($command, $password="", $ip=NULL) {
	return $this->query_rcon($command, $password, $ip);
}

// If the sub-class does not implement these functions then the unsupported error is reported as a 'warning'
function query_info() {
	trigger_error("PQ subclass '{$this->conf['querytype']}' does not support the 'info' server query", E_USER_WARNING);
}
function query_players() {
	trigger_error("PQ subclass '{$this->conf['querytype']}' does not support the 'players' server query", E_USER_WARNING);
}
function query_rules() {
	trigger_error("PQ subclass '{$this->conf['querytype']}' does not support the 'rules' server query", E_USER_WARNING);
}
function query_ping($ip) {
	trigger_error("PQ subclass '{$this->conf['querytype']}' does not support the 'ping' server query", E_USER_WARNING);
}
function query_rcon($command, $password=NULL, $ip=NULL) {
	trigger_error("PQ subclass '{$this->conf['querytype']}' does not support the 'rcon' server query", E_USER_WARNING);
}
function query_master() {
	trigger_error("PQ subclass '{$this->conf['querytype']}' does not support the 'master' server query", E_USER_WARNING);
}
function connect_url() {
//	trigger_error("PQ subclass '{$this->conf['querytype']}' does not support 'connect_url'", E_USER_WARNING);
	return "";
}

function gametype() {
	return $this->conf['querytype'];
}

function modtype() {
	return "";
}

// returns the current IP address (and port optionally). Port is included by default.
function ipaddr($incport=1) {
	$ipaddr = $this->conf['ip'];
	if ($incport) $ipaddr .= ':' . $this->conf['port'];
	return $ipaddr;
}

function maxretries() {
	return $this->conf['retries'] >= 0 ? $this->conf['retries'] : 0;
}

// connects the socket for reading/writting
function _connectsocket($ip, $port, $proto='udp') {
	if ($this->DEBUG) print nl2br("DEBUG: Opening socket to $ip:$port >>>\n");
	$this->sock = @fsockopen("$proto://$ip", $port, $this->errno, $this->errstr);
	$this->_set_timeout($this->conf['timeout']);
	return $this->sock;
}

// sets the timeout value on the current socket, takes into account the newer PHP timeout function if it's present
function _set_timeout($seconds=5, $sock=NULL) {
	if (!$sock) $sock =& $this->sock;
	if (!$sock) return;
	if (function_exists('stream_set_timeout')) {			// PHP >= 4.3.0
		stream_set_timeout($sock, $seconds);
	} elseif (function_exists('socket_set_timeout')) {		// this function is deprecated
		socket_set_timeout($sock, $seconds);
	}
}

// reads a null terminated string from the raw input
function _getnullstr() {
	if (empty($this->raw)) return '';
	$end = strpos($this->raw, "\0");			// find position of first null byte
	$str = substr($this->raw, 0, $end);			// extract the string (excluding null byte)
	$this->raw = substr($this->raw, $end+1);		// remove the extracted string (including null byte)
	return $str;						// return our str (no null byte)
}

// reads a character from the raw input
function _getchar() {
	return sprintf("%c", $this->_getbyte());
}

// reads a byte from the raw input
function _getbyte() {
	if (empty($this->raw)) return '';
	$byte = substr($this->raw, 0, 1);
	$this->raw = substr($this->raw, 1);
	return ord($byte);
}

// reads a short integer/word (2 bytes) from the raw input
function _getshort() {
	if (empty($this->raw)) return '';
	$lo = $this->_getbyte();
	$hi = $this->_getbyte();
	$short = ($hi << 8) | $lo;
	return $short;
}

// reads a long integer (4 bytes) from the raw input
function _getlong() {
	if (empty($this->raw)) return '';
	$lo = $this->_getshort();
	$hi = $this->_getshort();
	$long = ($hi << 16) | $lo;
	return $long;
}

function _getfloat() {
	if (empty($this->raw)) return '';
	$f = @unpack("f1float", $this->raw);
	$this->raw = substr($this->raw, 4);
	return $f['float'];
}

// gets the current time in microseconds (returned as a float)
function _getmicrotime() { 
	list($usec, $sec) = explode(" ", microtime()); 
	return ((float)$usec + (float)$sec); 
} 

function _unpack($type, $data) {
	$ary = @unpack($type . '1value', $data);
	return $ary['value'];
}

// debugging support function. prints out a hexdump of the string buffer given.
function hexdump($string, $maxwidth=16) {
//	$maxwidth = 16;			// how many left side hex values to show before starting a new line

	$output = "";
	$curwidth = 1;
	$bytes = array();

	for ($i=0; $i<strlen($string); $i++) {
		$byte = ord($string[$i]);
		$bytes[] = $byte;
		$output .= sprintf("%02X ", $byte);

		// If we're working on the last character we need to make sure we pad the output properly 
		// so that the code block after this outputs the right hand side of the hexdump correctly
		if ($i+1 == strlen($string) and $curwidth != $maxwidth) {
			$padlen = ($maxwidth * 3) - (count($bytes) * 3);
			$output .= sprintf("%-{$padlen}s", " ");
			$curwidth = $maxwidth;
		}
		if ($curwidth >= $maxwidth) {
			$output .= "| ";
			foreach ($bytes as $b) {
				if ($b <= 32 or $b >= 127) {
//				if ($b <= 32) {
					$output .= ".";
				} else {
					$output .= chr($b);
				}
			}
			$bytes = array();
			$output .= "\n";
			$curwidth = 1;
		} else {
			$curwidth++;
		}
	}
	return "<pre>" . $output . "</pre>\n";
}

} // end of PQ class

?>
