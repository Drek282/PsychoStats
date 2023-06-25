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
 *	Version: $Id: class_timer.php 367 2008-03-17 17:47:45Z lifo $
 */

/***
	Basic Timer Class

	Provides a benchmark timer to time the span between 2 points within your code.

***/

if (defined("CLASS_TIMER_PHP")) return 1; 
define("CLASS_TIMER_PHP", 1); 

class Timer {
  var $_marks = array();
  var $_order = array();
  var $_precision = 5;
  var $_default = true;
  var $_stop = 0;

  // constructor: Starts a main timer called 'MAIN' by default
  function __construct($precision=5, $default=true) {
    $this->_precision = $precision;
    if ($default) {
      $this->_default = true;
      $this->addmarker("MAIN");
    }
  }
 
function Timer($precision=5, $default=true) {
        self::__construct($precision, $default);
}

  // stop tracking time passing. After called, all functions will no longer see time pass
  function stop() {
    if (!$this->_stop) $this->_stop = $this->getmicrotime();
    $this->addmarker("MAINEND");
    return $this->_stop;
  }

  // Adds a marker to the time frame
  function addmarker($name) {
    $total = count($this->_marks);
    $name = strtolower($name);
    $this->_marks[$name] = $this->getmicrotime();
    $this->_order[] = $name;					// preserves the order of the marks (for debug output)
  }

  function getmicrotime($override=false) { 
    if ($this->_stop and !$override) return $this->_stop;	// return last stop value if we stopped already (and no override)
    list($usec, $sec) = explode(" ", microtime()); 
    return ((float)$usec + (float)$sec); 
  } 
  
  // returns the difference between two markers, if only 1 marker is given then its assumed the MAINEND is desired and If the 
  // script hasn't actually ended yet, then stop() will be called first
  function timediff($m1='MAIN', $m2='MAINEND') {
    $m1 = strtolower($m1);
    $m2 = strtolower($m2);
//    if (!$this->_stop and $m2=='mainend') return sprintf("%.0" . $this->_precision . "f", 0);
    if (!$this->_stop and $m2=='mainend') $this->stop();
    return sprintf("%.0" . $this->_precision . "f", $this->_marks[$m2] - $this->_marks[$m1]);
  }
    
  // this function displays all of the information that was collected during the course of the script
  function debug() {
    $output = "";
    if (!$this->_stop) $this->stop();
    $output .= "<table border='0' cellspacing='5' cellpadding='5'>\n";
    $output .= "<tr><td><b>Marker</b></td><td><b>Time</b></td><td><b>Diff</b></td><td><b>Total</b></td></tr>\n";
    $output .= "<tr>\n";
    $output .= "<td>MAIN</td>";
    $output .= "<td>" . sprintf("%.0" . $this->_precision . "f", $this->_marks['main']) . "</td>";
    $output .= "<td>-</td>\n";
    $output .= "</tr>\n";
    $lastmark = "MAIN";
    for ($i = 1; $i < count($this->_order); $i++) {
      $mark = strtolower($this->_order[$i]);
      $output .= "<tr>\n";
      $output .= "<td>$mark</td>";
      $output .= "<td>" . sprintf("%.0" . $this->_precision . "f", $this->_marks[$mark]) . "</td>";
      $output .= "<td>" . $this->timediff($lastmark, $mark) . "</td>";
      $output .= "<td>" . $this->timediff('main', $mark) . "</td>";
      $output .= "</tr>\n";
      $lastmark = $mark;
    }
    $output .= "</table>";
    return $output;
  }
}
?>
