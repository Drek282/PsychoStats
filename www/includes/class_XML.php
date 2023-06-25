<?php
/**
 *	This file is part of PsychoStats.
 *
 *	Originally written by Keith Devens, version 1.2b (original copyright notice is below)
 *	Re-written by Jason Morriss
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
 *	Version: $Id: class_XML.php 394 2008-04-19 21:41:47Z lifo $
 *
 *	Original copyright notice	
 *	###################################################################################
 *	#
 *	# XML Library, by Keith Devens, version 1.2b
 *	# http://keithdevens.com/software/phpxml
 *	#
 *	# This code is Open Source, released under terms similar to the Artistic License.
 *	# Read the license at http://keithdevens.com/software/license
 *	#
 *	###################################################################################
 *	Modifications by Jason Morriss include better handling of XML attributes and this now
 *	works with PHP5 w/o any deprecated errors.
 *
 */

if (defined("CLASS_XMLDATA_PHP")) return 1; 
define("CLASS_XMLDATA_PHP", 1); 

include("class_XML5.php");

// Takes raw XML as a parameter (a string) and returns an equivalent PHP data structure
function XML_unserialize(&$xml){
	$parser = new XMLstruct();
	$data = &$parser->parse($xml);
	$parser->destruct();
	return $data;
}

// Serializes any PHP data structure into XML. 
// $data is an array to serialize into an XML structure.
// $root_key is the name of the root key to use. Optional.
// $level and $prior_key are internal recursive parameters. Do not use directly.
function XML_serialize(&$data, $root_key = 'data', $level = 0, $prior_key = NULL) {
	if ($level == 0) { 
		ob_start(); 
		echo '<?xml version="1.0" ?>',"\n<$root_key>\n"; 
	}
    while ($key = key($data) && $value = current($data)) {
//	while (list($key, $value) = each($data)) {
		if (strpos($key, '@') === false) { # not an attribute
			# we don't treat attributes by themselves, so for an empty element
			# that has attributes you still need to set the element to NULL

			if (is_array($value) and array_key_exists(0, $value)) {
				XML_serialize($value, $root_key, $level+1, $key);
			} else {
				$tag = $prior_key ? $prior_key : $key;
				if (is_numeric($tag)) $tag = "key_$tag";
				echo str_repeat("\t", $level+1),'<',$tag;
				if (array_key_exists("@$key", $data)) { # if there's an attribute for this element
					while ($attr_name = key($data["@$key"]) && $attr_value = current($data["@$key"])) {
				//	while (list($attr_name, $attr_value) = each($data["@$key"])) {
						echo ' ',$attr_name,'="',htmlspecialchars($attr_value),'"';
					}
					reset($data["@$key"]);
				}

				if (is_null($value)) { 
					echo " />\n";
				} elseif (!is_array($value)) {
					echo '>',htmlspecialchars($value),"</$tag>\n";
				} else { 
					echo ">\n",XML_serialize($value, $root_key, $level+1),str_repeat("\t", $level+1),"</$tag>\n";
				}
			}
		}
	}
	reset($data);
	if ($level == 0) {
		$str = ob_get_contents(); 
		ob_end_clean(); 
		return "$str</$root_key>\n"; 
	}
}

?>
