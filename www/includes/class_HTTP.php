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
 *	Version: $Id: class_HTTP.php 442 2008-05-13 10:30:11Z lifo $
 */

/***
	HTTP Request class

	This class allows HTTP requests to be made to other servers. 
	Original code from info at b1g dot de on http://us2.php.net/manual/en/function.fopen.php
	modified by Stormtrooper and slightly enhanced.

***/

class HTTP_Request {
var $_fp;
var $_url;
var $_method;
var $_postdata;
var $_host;
var $_protocol;
var $_uri;
var $_port;
var $_error;
var $_headers;
var $_text;
var $errstr;
var $errno;

// constructor
function __construct($url, $method="GET", $data="") {
	$this->_url = $url;
	$this->_method = $method;
	$this->_postdata = $data;
	$this->_scan_url();
}

function HTTP_Request($url, $method="GET", $data="") {
    self::__construct($url, $method, $date);
}

// scan url
function _scan_url() {
	$req = $this->_url;
	$pos = strpos($req, '://');
	$this->_protocol = strtolower(substr($req, 0, $pos));
	$req = substr($req, $pos+3);
	$pos = strpos($req, '/');
	if($pos === false) $pos = strlen($req);
	$host = substr($req, 0, $pos);
      
	if(strpos($host, ':') !== false) {
		list($this->_host, $this->_port) = explode(':', $host);
	} else {
		$this->_host = $host;
		$this->_port = ($this->_protocol == 'https') ? 443 : 80;
	}

	$this->_uri = substr($req, $pos);
	if ($this->_uri == '') $this->_uri = '/';
}
  
// returns all headers. only call after download()
function getAllHeaders() {
	return $this->_headers;
}

// return the value of a single header
function header($key) {
	return array_key_exists($key, $this->_headers) ? $this->_headers[$key] : null;
}

function status() {
	return $this->_error;
}

function text() {
	return $this->_text;
}

// download contents of an URL to a string
function download($follow_redirect = true) {
	$crlf = "\r\n";
      
	// generate request
	$req = $this->_method . ' ' . $this->_uri . ' HTTP/1.0' . $crlf .
		'Host: ' . $this->_host . $crlf . 
		$crlf;
	if ($this->_postdata) $req .= $this->_postdata;

	// fetch
	ob_start();
	$this->_fp = fsockopen(($this->_protocol == 'https' ? 'ssl://' : '') . $this->_host, $this->_port, $this->errno, $this->errstr, 10);
	$err = ob_get_clean();
	if ($err) {
		$this->_fp = false;
		$this->_error = strip_tags($err);
		$response = '';
	}

	if ($this->_fp) {
		fwrite($this->_fp, $req);
		while (is_resource($this->_fp) && $this->_fp && !feof($this->_fp)) {
			$response .= fread($this->_fp, 1024);
		}
		fclose($this->_fp);
	} else {
		$response = '';
	}
      
	// split header and body
	$pos = strpos($response, $crlf . $crlf);
	if ($pos === false) return $response;

	$header = substr($response, 0, $pos);
	$body = substr($response, $pos + 2 * strlen($crlf));
      
	// parse headers
	$this->_headers = array();
	$lines = explode($crlf, $header);
	list($zzz, $this->_error, $zzz) = explode(" ", $lines[0], 3); unset($zzz);
	foreach ($lines as $line) {
		if (($pos = strpos($line, ':')) !== false) {
			$this->_headers[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos+1));
		}
	}

	// redirection?
	if (isset($headers['location']) and $follow_redirect) {
		$http = new HTTP_Request($headers['location']);
		return $http->download($http);
	} else {
		$this->_text = $body;
		return $body;
	}
}

} // end HTTP_Request

?>
