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
 *	Version: $Id: ajax_common.php 450 2008-05-20 11:34:52Z lifo $
 *
 *	Common routines for various ajax requests
 *
 */

// verify the page was viewed from a valid entry point.
if (!defined("PSYCHOSTATS_PAGE")) die("Unauthorized access to " . basename(__FILE__));

function output_list($type, $list, $fields, $idstr) {
	switch ($type) {
		case 'xml':
			xml_header($fields);
			xml_data($list, $fields);
			xml_footer();
			break;
		case 'dom':
			dom_header($fields);
			dom_data($list, $fields);
			dom_footer();
			break;
		case 'img': 
			img_header($fields);
			img_data($list, $fields, $idstr);
			img_footer();
			break;
		case 'csv': 
		default:
			csv_header($fields);
			csv_data($list, $fields);
			csv_footer();
			break;
	}
}

function img_header($fields) {}
function img_footer() {}
function img_data($data, $fields, $idstr = 'id') {
	$id = 0;
	foreach ($data as $file) {
		printf("<img id='%s%d' src='%s' alt='%s' title='%s' %s />\n", 
			$idstr, ++$id, $file['url'], $file['filename'], $file['desc'], $file['attr']
		);
	}
}


function csv_header($fields) {
	header("Content-Type: text/plain");
	print csv($fields);
}

function csv_data($data, $fields) {
	foreach ($data as $line) {
		$set = array();
		// create a set with the fields ordered properly.
		// i know php keeps keys in insert order, but I'm a paranoid perl programmer too.
		foreach ($fields as $f) {
			$set[] = $line[$f];
		}
		print csv($set);
	}
}

function csv_footer() {
	// nothing to do here
}

?>
