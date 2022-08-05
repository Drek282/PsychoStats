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
 *	Version: $Id: class_XML.php 389 2008-04-18 15:04:10Z lifo $
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
 *	works with PHP5 w/o any deprecated errors (also see class_XML4.php for details).
 *
 */

if (defined("CLASS_XMLDATA5_PHP")) return 1; 
define("CLASS_XMLDATA5_PHP", 1); 

// XML class: utility class to be used with PHP's XML handling functions
class XMLstruct {
	var $parser;   		// a reference to the XML parser
	var $document; 		// the entire XML structure built up so far
	var $parent;   		// a pointer to the current parent - the parent will be an array
	var $stack;    		// a stack of the most recent parent at each nesting level
	var $last_opened_tag; 	// keeps track of the last tag opened.

	function __construct(){
 		$this->parser =& xml_parser_create();
		xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);
		xml_set_object($this->parser, $this);
		xml_set_element_handler($this->parser, 'open','close');
		xml_set_character_data_handler($this->parser, 'data');
	}
    function XMLstruct() {
    self::__construct();
    }
	function destruct(){ 
		xml_parser_free($this->parser); 
	}
	function & parse(&$data){
		$this->document = array();
		$this->stack    = array();
		$this->parent   = &$this->document;
		return xml_parse($this->parser, $data, true) ? $this->document : NULL;
	}
	function open($parser, $tag, $attributes){
		$this->data = ''; #stores temporary cdata
		$this->last_opened_tag = $tag;
		if(is_array($this->parent) and array_key_exists($tag,$this->parent)){ #if you've seen this tag before
			if(is_array($this->parent[$tag]) and array_key_exists(0,$this->parent[$tag])){ #if the keys are numeric
				#this is the third or later instance of $tag we've come across
				$key = $this->count_numeric_items($this->parent[$tag]);
			}else{
				#this is the second instance of $tag that we've seen. shift around
				if(array_key_exists("@$tag",$this->parent)){
					$arr = array('@0'=> &$this->parent["@$tag"], &$this->parent[$tag]);
					unset($this->parent["@$tag"]);
				}else{
					$arr = array(&$this->parent[$tag]);
				}
				$this->parent[$tag] = &$arr;
				$key = 1;
			}
			$this->parent = &$this->parent[$tag];
		}else{
			$key = $tag;
		}
		if($attributes) $this->parent["@$key"] = $attributes;
		$this->parent  = &$this->parent[$key];
		$this->stack[] = &$this->parent;
	}
	function data($parser, $data){
		if($this->last_opened_tag != NULL) # you don't need to store whitespace in between tags
			$this->data .= $data;
	}
	function close($parser, $tag){
		if($this->last_opened_tag == $tag){
			$this->parent = $this->data;
			$this->last_opened_tag = NULL;
		}
		array_pop($this->stack);
		if($this->stack) $this->parent = &$this->stack[count($this->stack)-1];
	}
	function count_numeric_items(&$array){
		return is_array($array) ? count(array_filter(array_keys($array), 'is_numeric')) : 0;
	}
}

?>
