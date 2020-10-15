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

/********

	This PQ class will query old Halflife servers (CS 1.5 and older). You have to explicitly use this as your
	querytype when creating the PQ object as it's not possible for the normal halflife class to autodetect 
	the old halflife servers (before steam).

********/


if (!defined("CLASS_PQ_PHP")) die("Access Denied!");
if (defined("CLASS_PQ_OLDHALFLIFE_PHP")) return 1;
define("CLASS_PQ_OLDHALFLIFE_PHP", 1);

include_once("halflife.php");

// Normally PQ classes extend the 'PQ_PARENT', but in this case we just want to change a tiny bit of code in the newer
// halflife queries in order to work with the old halflife (CS1.5).
class PQ_oldhalflife extends PQ_halflife {

function __construct($conf) {
	$this->PQ_oldhalflife($conf);
}

function PQ_oldhalflife($conf) {
	$this->conf = $conf;		// always save the config to the class variable first
	$this->init();			// always run the class initialization method
}

function init() {
	parent::init();			// always run the parent init method

	$this->halflife_version = 1;	// force HL v1
	$this->infostr = 'details' . pack('x');
	$this->plrstr = 'players' . pack('x');
	$this->rulestr = 'rules' . pack('x');
	$this->pingstr = 'ping' . pack('x');
}

// override so we dont try challenge requests
function _getchallenge($ip=NULL) { return ''; }

// everything else is handled normally by the original halflife parent class ...


}
?>
