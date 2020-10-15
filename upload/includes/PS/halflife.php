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
 *	Version: $Id: halflife.php 506 2008-07-02 14:29:49Z lifo $
 *
 *	PS::halflife
 *	Halflife support for PsychoStats front-end.
 *	This is just a stub for mod sub-classes to override.
 */
if (!defined("PSYCHOSTATS_PAGE")) die("Unauthorized access to " . basename(__FILE__));
if (defined("CLASS_PS_HALFLIFE_PHP")) return 1;
define("CLASS_PS_HALFLIFE_PHP", 1);

include_once(__DIR__ . '/PS.php');

class PS_halflife extends PS {

var $class = 'PS::halflife';

function PS_halflife(&$db) {
	parent::PS($db);
}

function worldid_noun($plural = false) {
	global $cms;
	return $plural ? $cms->trans('Steamids') : $cms->trans('Steamid');
}

}

?>
