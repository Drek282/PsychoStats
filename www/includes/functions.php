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
 *	Version: $Id: functions.php 559 2008-09-05 13:15:47Z lifo $
 */

/***

	functions.php

	General utility functions for PsychoStats.

***/

if (!defined("PSYCHOSTATS_PAGE")) die("Unauthorized access to " . basename(__FILE__));

if (defined("FILE_PS_FUNCTIONS_PHP")) return 1; 
define("FILE_PS_FUNCTIONS_PHP", 1); 


define("ACL_NONE", -1);
define("ACL_DENIED", -1);
define("ACL_USER", 1);
define("ACL_CLANADMIN", 5);
define("ACL_ADMIN", 99);

// wrapper for xml_response. returns a single 'code' and 'message' xml response
function xml_result($code, $message, $header = true, $extra = array()) {
	$data = array_merge(array( 'code' => $code, 'message' => $message ), $extra);
	if ($header) header("Content-Type: text/xml");
	return xml_response($data);
}

// converts the array key=>value pairs into an XML (SML) response string.
// this function is recursive and will work with sub arrays to create nested <nodes>.
// key names are not checked for validity. 
function xml_response($data = array(), $root = 'response', $indent = 0) {
	$tab = str_repeat("\t", $indent);
	$do_root = !empty($root);
	$xml = $do_root ? "$tab<$root>\n" : "";
	foreach ($data as $key => $value) {
		if (is_array($value)) {
			$xml .= xml_response($value, $key, $indent + 1);
		} else {
			if (is_numeric($key)) $key = 'index';
			$xml .= "$tab\t<$key>"; // one extra tab is added to nest under its root node
			if (strpos($value,'<') || strpos($value,'>') || strpos($value,'&')) {
				$xml .= "<![CDATA[$value]]>"; 
			} else {
				$xml .= $value;
			}
			$xml .= "</$key>\n";
		}
	}
	if ($do_root) $xml .= "$tab</$root>\n";
	return $xml;
}

// Interpolates special $tokens in the string.
// $tokens is a hash array containing variables and can be nested 1 level deep (ie $tok1 or $tok2.value)
// if $fill is true than any tokens in the string that do not have a matching variable in $tokens is not removed.
function simple_interpolate($str, $tokens, $fill = false) {
	$ofs = 0;
	$idx = 0;
	$i = 0;
	while (preg_match('/\{\$([a-z][a-z\d_]+)(?:\.([a-z][a-z\d_]+))?\}/', $str, $m, PREG_OFFSET_CAPTURE, $ofs)) {
		if ($i++ > 1000)  {
			die("ENDLESS LOOP in simple_interpolate (line " . __LINE__ . ") with string '$str'");
		}
		$var1	= strtolower($m[1][0]);
		$var2 	= isset($m[2][0]) ? strtolower($m[2][0]) : '';
		$idx	= $m[0][1];	// get position of where match begins
		if (array_key_exists($var1, $tokens)) {
			if (!empty($var2)) {
				if (is_array($tokens[$var1]) and array_key_exists($var2, $tokens[$var1])) {
					$rep = $tokens[$var1][$var2];
				} else {
					$rep = $fill ? "{\$$var1.$var2}" : '';
				}
			} else {
				$rep = $tokens[$var1];
			}
		} else {
			$rep = $fill ? $var1 : '';
		}

		// We replace each token 1 by 1 even if $token1 matches more than once.
		// this will prevent possible $tokens inside replacement strings from being interpolated.
		$varstr = $var2 ? "$var1.$var2" : $var1;
		$str = substr_replace($str, $rep, $idx, strlen($varstr)+3);	// +3 for chars ${}
		$ofs = $idx + strlen($rep);
	}
	return $str;
}

function pct_bar($args = array()) {
	global $cms;
	require_once(__DIR__ . "/class_Color.php");
	$args += array(
		'pct'		=> 0,
		'color1'	=> 'cc0000',
		'color2'	=> '00cc00',
		'degrees'	=> 1,
		'width'		=> null,
		'class'		=> 'pct-bar',
		'styles'	=> '',
		'title'		=> null,
	);
	static $colors = array();
	if (!empty($args['width']) and (!is_numeric($args['width']) or $args['width'] < 1)) $args['width'] = 100;
	$w = $args['width'] ? $args['width'] : 100;
//	$width = $args['pct'] / 100 * $w; 				// scaled width
	$key = $args['color1'] . ':' . $args['color2'];
	$colors[$key] ??= null;
	if (!$colors[$key]) {
		$c = new Image_Color();
		$c->setColors($args['color1'], $args['color2']);
		$colors[$key] = $c->getRange(100, $args['degrees']);	// 100 colors, no matter the width
/**
		foreach ($colors[$key] as $col) {
			printf("<div style='color: white; background-color: %s'>%s</div>", $col, $col);
		}
/**/
	}

	$styles = !empty($args['styles']) ? $args['styles'] : '';
	if (!empty($args['width'])) {
		$styles = " width: " . $args['width'] . "px;";
	}
	if (!empty($styles)) $styles = " style='$styles'";

	$out = sprintf("<span %s title='%s'%s><span style='width: %s%s'></span></span>",
		!empty($args['class']) ? "class='" . $args['class'] . "'" : "",
		!empty($args['title']) ? $args['title'] : (int)($args['pct']) . '%',
		$styles,
		(int)($args['pct']) . '%',
		!empty($colors[$key][intval($args['pct']) - 1]) ? "; background-color: #" . $colors[$key][intval($args['pct']) - 1] : ''
	);
	return $out;
}

// Returns HTML for a dual percentage bar between 2 percentages. Pure html+css.
function dual_bar($args = array()) {
	global $cms;
	$args += array(
		'pct1'		=> 0,
		'pct2'		=> 0,
		'color1'	=> 'ff0000',
		'color2'	=> '0000ff',
		'title1'	=> null,
		'title2'	=> null,
		'width'		=> null,
		'class'		=> 'dual-bar',
		'styles'	=> '',
	);
	if (!empty($args['width']) and (!is_numeric($args['width']) or $args['width'] < 1)) $args['width'] = 100;
	$w = $args['width'] ? $args['width'] : 100;
//	$width = $args['pct'] / 100 * $w; 				// scaled width

	if (!$args['pct2']) {
		$args['pct2'] = $args['pct1'] ? 100 - $args['pct1'] : 100;
	}

	$styles  = (int)$args['pct2'] ? "background-color: #" . $args['color2'] . "; " : '';
	$styles .= !empty($args['styles']) ? $args['styles'] : '';
	if (!empty($args['width'])) {
		$styles .= " width: " . $args['width'] . "px;";
	}
	if (!empty($styles)) $styles = " style='$styles'";
	// add the 'title' to the end of the styles string for the title of the 2nd (right) bar
	$styles .= " title='" . ($args['title2'] ? $args['title2'] : $args['pct2'].'%') . "'";

	$out = sprintf("<span %s%s>" . 
			"<span class='left'  title='%s' style='width: %s; background-color: #%s'></span>" . 
			"<span class='center'%s></span>" . 
			//"<span class='right' title='%s' style='width: %s; background-color: #%s'></span>" . 
			"</span>",
		!empty($args['class']) ? "class='" . $args['class'] . "'" : "",
		$styles, 

		!empty($args['title1']) ? $args['title1'] : (int)($args['pct1']) . '%',
		(int)($args['pct1']) . '%',
		$args['color1'],

		(int)($args['pct2']) ? '' : " style='display: none'"

// instead of trying to float a 2nd span for the other percentage, just set the background of the overall div
//		!empty($args['title2']) ? $args['title2'] : (int)($args['pct2']) . '%',
//		(int)($args['pct2']) . '%',
//		$args['color2']
	);
	return $out;
}

function rank_change($args = array()) {
	global $cms, $ps;
	if (!is_array($args)) $args['plr'] = array( 'plr' => $args );
	$args += array(
		'plr'		=> NULL,
		'rank'		=> 0,
		'prevrank'	=> 0,
		'imgfmt'	=> "rank_%s.gif",
		'difffmt'	=> "%d",
		'attr'		=> "",
		'acronym'	=> true,
		'textonly'	=> false,
	);

	$output = "";
	$rank = $prevrank = 0;
	if (is_array($args['plr'])) {
		$rank = $args['plr']['rank'];
		$prevrank = $args['plr']['prevrank'];
	} else {
		$rank = $args['rank'];
		$prevrank = $args['prevrank'];
	}

	$alt = $cms->trans("no change");
	$dir = "same";
	$diff = sprintf($args['difffmt'], $prevrank - $rank);	// note: LESS is better. Opposite of 'skill'.

	if ($prevrank == 0) {
		// no change
	} elseif ($diff > 0) {
		$dir = "up";
		$alt = $cms->trans("Diff") . ": +$diff";
	} elseif ($diff < 0) {
		$dir = "down";
		$alt = $cms->trans("Diff") . ": $diff";
	}

	if ($args['textonly']) {
		$output = sprintf("<span class='rankchange-$dir'>%s%s</span>",
			$diff > 0 ? '+' : '',
			$prevrank == 0 ? '' : $diff
		);
	} else {
		$img = '/img/icons/' . sprintf($args['imgfmt'], $dir);
		$path = catfile($ps->conf['theme']['template_dir'], $cms->theme->theme(), $img);
		if (!@file_exists($path) and $cms->theme->is_child()) {
			$img = $cms->theme->url($cms->theme->is_child()) . $img;
		} else {
			$img = $cms->theme->url() . $img;
		}

		$output = sprintf("<img src='%s' alt='%s' title='%s' %s>", $img, $alt, $alt, $args['attr']);
//		if ($args['acronym']) {
//			$output = "<abbr title='$alt'>$output</abbr>";
//		}
		$output = "<span class='rankchange-$dir'>$output</span>";
	}
	return $output;
}

function skill_change($args = array()) {
	global $cms, $ps;
	if (!is_array($args)) $args['plr'] = array( 'plr' => $args );
	$args += array(
		'plr'		=> NULL,
		'skill'		=> 0,
		'prevskill'	=> 0,
		'imgfmt'	=> "skill_%s.gif",
		'difffmt'	=> "%.02f",
		'attr'		=> "",
		'acronym'	=> true,
		'textonly'	=> false,
	);

	$output = "";
	$skill = $prevskill = 0;
	if (is_array($args['plr'])) {
		$skill = $args['plr']['skill'];
		$prevskill = $args['plr']['prevskill'];
	} else {
		$skill = $args['skill'];
		$prevskill = $args['prevskill'];
	}

	$alt = $cms->trans("no change");
	$dir = "same";
	$diff = sprintf($args['difffmt'], $skill - $prevskill);

	if ($prevskill == 0) {
		// no change
	} elseif ($diff > 0) {
		$dir = "up";
		$alt = $cms->trans("Diff") . ": +$diff";
	} elseif ($diff < 0) {
		$dir = "down";
		$alt = $cms->trans("Diff") . ": $diff";
	}

	if ($args['textonly']) {
		$output = sprintf("<span class='skillchange-$dir'>%s%s</span>",
			$diff > 0 ? '+' : '',
			$prevskill == 0 ? '' : $diff
		);
	} else {
		$img = '/img/icons/' . sprintf($args['imgfmt'], $dir);
		$path = catfile($ps->conf['theme']['template_dir'], $cms->theme->theme(), $img);
		if (!@file_exists($path) and $cms->theme->is_child()) {
			$img = $cms->theme->url($cms->theme->is_child()) . $img;
		} else {
			$img = $cms->theme->url() . $img;
		}

		$output = sprintf("<img src='%s' alt='%s' title='%s' %s>", $img, $alt, $alt, $args['attr']);
//		if ($args['acronym']) {
//			$output = "<abbr title='$alt'>$output</abbr>";
//		}
		$output = "<span class='skillchange-$dir'>$output</span>";
	}
	return $output;
}

// safer rename function (win/linux compatable)
function rename_file($oldfile,$newfile) {
	// first, try to rename since it's atomic (and faster)
	if (!rename($oldfile,$newfile)) {
		if (copy($oldfile,$newfile)) {		// try to copy file instead
			return unlink($oldfile);	// .. but be sure to remove old file
		}
		return false;
	}
	return true;
}

// builds an URL 
function url($arg = array()) {
	if (!is_array($arg)) $arg = array( '_base' => $arg );
	$arg += array(					// argument defaults
		'_base'		=> NULL,		// base URL; if NULL $PHP_SELF is used
		'_anchor'	=> '',			// optional anchor
		'_encode'	=> 1,			// should parameters be url encoded?
		'_encodefunc'	=> 'rawurlencode',	// how to encode params
		'_amp'		=> '&amp;',		// param separator
		'_raw'		=> '',			// raw URL appended to final result (is not encoded)
		'_ref'		=> NULL,		// if true/numeric referrer is autoset, if a string it is used instead
		// any other key => value pair is treated as a parameter in the URL
	);
	$base = ($arg['_base'] === NULL) ? ps_escape_html($_SERVER['SCRIPT_NAME']) : $arg['_base'];
	$enc = $arg['_encode'] ? 1 : 0;
	$encodefunc = ($arg['_encodefunc'] && function_exists($arg['_encodefunc'])) ? $arg['_encodefunc'] : 'rawurlencode';
	$i = (strpos($base, '?') === FALSE) ? 0 : 1;

	foreach ($arg as $key => $value) {
		if ($key[0] == '_') continue;		// ignore any param starting with '_'
		if (empty($value)) continue;		// ignore empty values
		$base .= ($i++) ? $arg['_amp'] : '?';
		$base .= "$key=";			// do not encode keys
		$base .= $enc ? $encodefunc($value ?? '') : $value;
	}

	if ($arg['_ref']) {
		$base .= ($i++) ? $arg['_amp'] : '?';
		if ($arg['_ref'] and $arg['_ref'] == 1) {
			$base .= 'ref=' . $encodefunc($_SERVER['SCRIPT_NAME'] .
				($_SERVER['QUERY_STRING'] != null ? '?' . $_SERVER['QUERY_STRING'] : '')
			);
		} elseif (!empty($arg['_ref'])) {
			$base .= 'ref=' . $encodefunc($arg['_ref']);
		}
	}

	if ($arg['_raw']) $base .= ($i ? $arg['_amp'] : '?') . $arg['_raw'];
	if (!empty($arg['_anchor'])) $base .= '#' . $arg['_anchor'];

	return $base;
}


function remote_addr($alt='') {
	$ip = $alt;
	if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))  {
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	} elseif (isset($_SERVER['REMOTE_ADDR'])) {
		$ip = $_SERVER['REMOTE_ADDR'];
	}
	return $ip;
}

// redirects user to a page using $_REQUEST['ref'] if available, or the $alt URL
// provided (must be ABSOLUTE URL)
function previouspage($alt=NULL) {
	if ($alt==NULL) $alt = 'index.php';
	if ($_REQUEST['ref']) {
//		$ref = (get_magic_quotes_gpc()) ? stripslashes($_REQUEST['ref']) : $_REQUEST['ref'];
		// Sanitize $_REQUEST['ref'].
		$ref = htmlspecialchars($_REQUEST['ref']); //XSS Fix. Thanks to JS2007
		// Don't allow links to external pages or long uris.
		if (strlen($ref) > 64 or preg_match('/http(?:s|):\/\//', $ref)) $ref = 'index.php';
		gotopage($ref);				// jump to previous page, if specified
	} else {
		gotopage($alt);
	}
}

// Always specify an ABSOLUTE URL. Never send a relative URL, as the redirection
// will not work correctly.
function gotopage($url) {
	global $cms;
//	while (@ob_end_clean()) /* nop */; 		// erase all pending output buffers
	$cms->session->close();
	// if the SID was set from a command line we need to make sure the redirect contains the SID
	if ($cms->session->sid_method() == 'get' and $cms->session->sid()) {
		$query = parse_url($url);
		if (is_array($query)) {
			parse_str($query['query'], $args);
			if (!array_key_exists($cms->session->sid_name(), $args)) {
				$url .= (strpos($url, '?') !== FALSE ? '&' : '?') . $cms->session->sid_name() . "=" . $cms->session->sid();
			}
		}
	}
	if (!headers_sent()) { 				// in case output buffering (OB) isn't supported
		header("Location: " . ps_url_wrapper($url)); 
	} else { 					// Last ditch effort. Try a meta refresh to redirect to new page
		$url = ps_escape_html($url);
		print "<meta http-equiv=\"refresh\" content=\"0;url=$url\">\n"; 
		print "<a href='$url'>Redirect Failed. Please click here to proceed</a>";
	} 
	exit();
}

// converts all HTML entities from all elements in the array.
function htmlentities_all(&$ary, $trimtags=0) {
	if (!is_array($ary)) {
		$ary = ps_escape_html($ary);
		return;
	}
	reset($ary);
	
	while ($key = key($ary) && $val = current($ary)) {
//	while (list($key,$val) = each($ary)) {
		$ary[$key] = ps_escape_html($ary[$key]);
	}
}

// removes any keys in the $set that are the same in the $mainset. useful for
// removing keys that do not need to be changed in a database update
function trimset(&$set, &$mainset) {
	foreach ($set as $key => $value) {
		if (array_key_exists($key, $mainset) and $set[$key] == $mainset[$key]) {
			unset($set[$key]);
		}
	}
}

// Trims white space from the RIGHT of all strings in the array 
function rtrim_all(&$ary, $trimtags=0) {
	if (!is_array($ary)) {
		$ary = rtrim($ary);
		return;
	}
	reset($ary);
	while ($key = key($ary) && $val = current($ary)) {
//	while (list($key,$val) = each($ary)) {
		if ($trimtags) $ary[$key] = strip_tags($val);
		$ary[$key] = rtrim($ary[$key]);
	}
}

// Trims white space and HTML/PHP tags from all elements in the array.
function trim_all(&$ary, $trimtags=0) {
	if (!is_array($ary)) {
		if ($trimtags) $ary = strip_tags($ary);
		$ary = trim($ary);
		return;
	}
	reset($ary);
	while ($key = key($ary) && $val = current($ary)) {
//	while (list($key,$val) = each($ary)) {
		if (is_array($ary[$key])) {
			trim_all($ary[$key]);
		} else {
			if ($trimtags) $ary[$key] = strip_tags($val);
			$ary[$key] = trim($ary[$key]);
		}
	}
}

// removes slashes from all elements in the array.
function stripslashes_all(&$ary) {
	if (!is_array($ary)) {
		$ary = stripslashes($ary);
		return;
	}
	reset($ary);
	while ($key = key($ary) && $val = current($ary)) {
//	while (list($key,$val) = each($ary)) {
		if (is_array($ary[$key])) {
			stripslashes_all($ary[$key]);
		} else {
			$ary[$key] = stripslashes($ary[$key]);
		}
	}
}

// strips slashes and all tags from the variables in the array
function tidy_all(&$ary, $trimtags=0) {
	trim_all($ary, $trimtags);
	stripslashes_all($ary);
}

// Returns an HTML string to be used as a pager for displaying long lists of
// information. Use .pager styles from the css code to change the look.
function pagination($args = array()) {
	$args += array(
		'baseurl'		=> '',
		'total'			=> 0,
		'perpage'		=> 100,
		'start'			=> 0,
		'startvar'		=> 'start',
		'pergroup'		=> 3,
		'force_prev_next'	=> false,
		'urltail'		=> '',
		'prefix'		=> '',
		'next'			=> 'Next',
		'prev'			=> 'Previous',
		'separator'		=> ', ',
		'middle_separator'	=> ' ... ',
	);
	$total = ceil($args['total'] / $args['perpage']);		// calculate total pages needed for dataset
	$current = floor($args['start'] / $args['perpage']) + 1;	// what page we're currently on
	if ($total <= 1) return "";					// There's no pages to output, so we output nothing
	if ($args['pergroup'] < 3) $args['pergroup'] = 3;		// pergroup can not be lower than 3
	if ($args['pergroup'] % 2 == 0) $args['pergroup']++;		// pergroup is EVEN, so we add 1 to make it ODD
	$maxlinks = $args['pergroup'] * 3 + 1;
	$halfrange = floor($args['pergroup'] / 2);
	$minrange = $current - $halfrange;				// gives us our current min/max ranges based on $current page
	$maxrange = $current + $halfrange;
	$output = "";

	if ($total > $maxlinks) {
		// create first group of links ...
		$list = array();
		for ($i=1; $i <= $args['pergroup']; $i++) {
			if ($i == $current) {
				$list[] = "<span class='pager-current'>$i</span>";
			} else {
				$list[] = sprintf("<a href='%s' class='pager-goto'>%d</a>", 
					ps_url_wrapper(array('_base' => $args['baseurl'], $args['startvar'] => ($i-1)*$args['perpage'], '_anchor' => $args['urltail'])), 
					$i
				);
			}
		}
		$output .= implode($args['separator'], $list);

		// create middle group of links ...
		if ($maxrange > $args['pergroup']) {
			$output .= ($minrange > $args['pergroup']+1) ? $args['middle_separator'] : $args['separator'];
			$min = ($minrange > $args['pergroup']+1) ? $minrange : $args['pergroup'] + 1;
			$max = ($maxrange < $total - $args['pergroup']) ? $maxrange : $total - $args['pergroup'];

			$list = array();
			for ($i=$min; $i <= $max; $i++) {
				if ($i == $current) {
					$list[] = "<span class='pager-current'>$i</span>";
				} else {
					$list[] = sprintf("<a href='%s' class='pager-goto'>%d</a>", 
						ps_url_wrapper(array('_base' => $args['baseurl'], $args['startvar'] => ($i-1)*$args['perpage'], '_anchor' => $args['urltail'])), 
						$i
					);
				}
			}
			$output .= implode($args['separator'], $list);
			$output .= ($maxrange < $total - $args['pergroup']) ? $args['middle_separator'] : $args['separator'];
		} else {
			$output .= $args['middle_separator'];
		}

		// create last group of links ...
		$list = array();
		for ($i=$total-$args['pergroup']+1; $i <= $total; $i++) {
			if ($i == $current) {
				$list[] = "<span class='pager-current'>$i</span>";
			} else {
				$list[] = sprintf("<a href='%s' class='pager-goto'>%d</a>", 
					ps_url_wrapper(array('_base' => $args['baseurl'], $args['startvar'] => ($i-1)*$args['perpage'], '_anchor' => $args['urltail'])), 
					$i
				);
			}
		}
		$output .= implode($args['separator'], $list);

	} else {
		$list = array();
		for ($i=1; $i <= $total; $i++) {
			if ($i == $current) {
				$list[] = "<span class='pager-current'>$i</span>";
			} else {
				$list[] = sprintf("<a href='%s' class='pager-goto'>%d</a>", 
					ps_url_wrapper(array('_base' => $args['baseurl'], $args['startvar'] => ($i-1)*$args['perpage'], '_anchor' => $args['urltail'])), 
					$i
				);
			}
		}
		$output .= implode($args['separator'], $list);
	}

	// create 'Prev/Next' links
	if (($args['force_prev_next'] and $total) or $current > 1) {
		if ($current > 1) {
			$output = sprintf("<a href='%s' class='pager-prev'>%s</a> ", 
				ps_url_wrapper(array('_base' => $args['baseurl'], $args['startvar'] => ($current-2)*$args['perpage'], '_anchor' => $args['urltail'])), 
				$args['prev']
			) . $output;
		} else {
			$output = "<span class='pager-prev'>" . $args['prev'] . "</span> " . $output;
		}
	}
	if (($args['force_prev_next'] and $total) or $current < $total) {
		if ($current < $total) {
			$output .= sprintf(" <a href='%s' class='pager-next'>%s</a> ", 
				ps_url_wrapper(array('_base' => $args['baseurl'], $args['startvar'] => $current*$args['perpage'], '_anchor' => $args['urltail'])), 
				$args['next']
			);
		} else {
			$output .= " <span class='pager-next'>" . $args['next'] . "</span>";
		}
	}

	if ($args['prefix'] != '' and !empty($output)) {
		$output = $args['prefix'] . $output;
	}

	return "<span class='pager'>$output</span>";
}

// returns the number given with commas separating 'thousands'.
function commify($num) {
	return number_format($num);
}

// abbreviates the number given into the closet KB, MB, GB, TB range.
function abbrnum($num, $tail=2, $size = null, $base = 1024) {
	if ($size === null) {
		$size = array(' bytes',' KB',' MB',' GB',' TB');
	}
	if (!is_numeric($tail)) $tail = 2;
	if (!$num) return '0' . $size[0];

	$i = 0;
	while (($num >= $base) and ($i < count($size))) {
		$num /= $base;
		$i++;
	}

	return sprintf("%." . $tail . "f",$num) . $size[$i];
}

// shortcut for callback functions, number is abbreviated with no tail and the
// base is 1000 (isntead of 1024)
function abbrnum0($string, $tail = 0) {
	if (intval($string) < 1000) {
		return $string;
	} else {
		return abbrnum($string, $tail, array('', 'K', 'M', 'B'), 1000);
	}
}

// returns a compact time string representing the total time from seconds in the
// form "hh:mm:ss". NOTE: the timing routines in this function should be updated
// to the use the same in the elapsedtime() function
function compacttime($seconds, $format="hh:mm:ss") {
  $d = $h = $m = $s = "00";
  if (!isset($seconds)) $seconds = 0;
  $old = $seconds;
  $str = $format;
  if ( (strpos($str, 'dd') !== FALSE) && ($seconds / (60*60*24)) >= 1) 	{ $d = sprintf("%d", $seconds / (60*60*24)); $seconds -= $d * (60*60*24); }
  if ( (strpos($str, 'hh') !== FALSE) && ($seconds / (60*60)) >= 1) 	{ $h = sprintf("%d", $seconds / (60*60)); $seconds -= $h * (60*60); }
  if ( (strpos($str, 'mm') !== FALSE) && ($seconds / 60) >= 1) 		{ $m = sprintf("%d", $seconds / 60); $seconds -= $m * (60); }
  if ( (strpos($str, 'ss') !== FALSE) && ($seconds % 60) >= 1) 		{ $s = sprintf("%d", $seconds % 60); }
  $str = str_replace('dd', sprintf('%02d',$d), $str);
  $str = str_replace('hh', sprintf('%02d',$h), $str);
  $str = str_replace('mm', sprintf('%02d',$m), $str);
  $str = str_replace('ss', sprintf('%02d',$s), $str);
  return $str;
}

/*

 Returns the total time elapsed from the seconds given. 

 Returns a string or an array of variables representing: "1 year, 2 weeks, 5
 days, 4 hours, 34 minutes, 20 seconds".

 This uses 'leap seconds' to calculate the time passed, which will partially
 compensate for leap years and DST (i think). This is not 100% accurate, but
 is actually pretty close. This is good enough for our purposes.

 $seconds is the total seconds elapsed.

 $start is a number between 0..5. 0=years, 5=minutes and represents which timing
 value will start the calculations. IE: $start=2 means the values for weeks on
 down will be returned. The examples below all use the same elapsed time.
 EG: 	$start=0 == 1 year, 2 months, 2 weeks, 3 days, 8 hours, 47 minutes, 27 seconds
 	$start=1 == 14 months, 2 weeks, 3 days, 8 hours, 47 minutes, 27 seconds
	$start=2 == 63 weeks, 1 day, 37 minutes, 54 seconds
*/
function elapsedtime($seconds, $start = 0, $wantarray = false) {
	// total 'leap seconds' in a single year. This is not truly static and
	// changes slightly every few years.
	static $oneyear = 31556925.9936;
	$years = $months = $weeks = $days = $hours = $minutes = 0;
	if ($start <= 0) {
		$years 	= floor($seconds / $oneyear);
		if ($years) $seconds -= $oneyear * $years;
	}
	if ($start <= 1) {
		$months	= floor($seconds / ($oneyear / 12));
		if ($months) $seconds -= $oneyear / 12 * $months;
	}
	if ($start <= 2) {
		$weeks 	= floor($seconds / ($oneyear / 52));
		if ($weeks) $seconds -= $oneyear / 52 * $weeks;
	}
	if ($start <= 3) {
		$days 	= floor($seconds / ($oneyear / 365));
		if ($days) $seconds -= $oneyear / 365 * $days;
	}
	if ($start <= 4) {
		$hours	= floor($seconds / 3600);
		if ($hours) $seconds -= 3600 * $hours;
	}
	if ($start <= 5) {
		$minutes = floor($seconds / 60);
		$seconds = intval($seconds) % 60;
	}

	if ($wantarray) {
		return array($years,$months,$weeks,$days,$hours,$minutes,$seconds);
	} else {
		$str = elapsedtime_str(array($years,$months,$weeks,$days,$hours,$minutes,$seconds));
		return $str;
	}
}

// helper function for array results from elapsedtime(). This mainly allows
// for translations to be done on the strings.
function elapsedtime_str($elapsed, $max = 7, $plural = array(), $singular = array(), $and = ' and') {
	// note the leading spaces on each word
	static $static_singular = array(' year',' month',' week',' day',' hour',' minute',' second');
	static $static_plural   = array(' years',' months',' weeks',' days',' hours',' minutes',' seconds');

	// static vars are assigned once if the arrays are specfied, so any
	// successive call to this function can use the new statics w/o having
	// to pass the arrays each time.
	if (empty($plural)) {
		$plural = $static_plural;
	} else {
		$static_plural = $plural;
	}
	if (empty($singular)) {
		$singular = $static_singular;
	} else {
		$static_singular = $singular;
	}
	
	$str = '';
	for ($i = 0, $j = count($plural)-1; $i <= $j; $i++) {
        $elapsed[$i] = $elapsed[$i] ?? null;
		$var = $elapsed[$i];
		if ($var == 0) continue;			// ignore values of 0
		$word = $var == 1 ? $singular[$i] : $plural[$i];
		$str .= $var . $word;
		if (--$max <= 0) break;
		if ($i != $j) $str .= ", ";
	}
	if ($str) {
		if (substr($str,-2) == ", ") {	// remove trailing comma
			$str = substr($str, 0, -2);
		}
		$p = strrpos($str, ',');
		if ($p !== false) {
			// replace the last comma with the word 'and'
			$str = substr($str, 0, $p) . $and . substr($str, $p+1);
		}
	}
	
	if ($str == '') {
		$str = "0" . $plural[ count($plural)-1 ];
	}
	
	return $str;
	
}

// Concatenate file path parts together always using / as the directory separator.
// since '/' is always used this can be used on URL's as well.
function catfile() {
  $args = func_get_args();
  $args = str_replace(array('\\\\','\\'), '/', $args);
  $path = array_shift($args);
  foreach ($args as $part) {
	if (substr($path, -1, 1) == '/') $path = substr($path, 0, -1);
	if ($part != '' and $part[0] != '/') $part = '/' . $part;
	$path .= $part;
  }
  // remove the trailing slash if it's present
  if (substr($path, -1, 1) == '/') $path = substr($path, 0, -1);
  return $path;
}

// returns a CSV line of text with the elements of the $data array
function csv($data,$del=',',$enc='"') {
	$csv = '';
	foreach ($data as $element) {
		$element = str_replace($enc, "$enc$enc", $element);
  		if ($csv != '') $csv .= $del;
		$csv .= $enc . $element . $enc;
	}
	return "$csv\n";
}

function ymd2time($date, $char='-') {
	list($y,$m,$d) = explode($char, $date);
	return mktime(0,0,0,$m,$d,$y);
}

function time2ymd($time, $char='-') {
	return date(implode($char, array('Y','m','d')), $time);
}

function array_map_recursive($function, $data) {
	if (is_array($data)) {
		foreach ($data as $i => $item) {
			$data[$i] = is_array($item)
				? array_map_recursive($function, $item)
				: $function($item);
		}
	}
	return $data;
}

// Inserts $arr2 after the $key (string). if $before is true then it's inserted before the $key specified.
// I do not know why PHP doesn't have this built in already. It can be very useful. (array_splice works on numeric indexes only)
function array_insert($arr1, $key, $arr2, $before = false) {
	$index = array_search($key, array_keys($arr1));
	if ($index === false){
		$index = count($arr1); // insert at end of array if $key not found
	} else {
		if (!$before) $index++;
	}
	$end = array_splice($arr1, $index);
	return array_merge($arr1, $arr2, $end);
}

// joins a single key value from an array into a string using the glue
function key_join($glue, $pieces, $key) {
	if (!is_array($pieces)) return '';
	$str = '';
	foreach ($pieces as $p) {
		$str .= $p[$key] . $glue;
	}
	return substr($str, 0, -strlen($glue));
}

// very simple recursive array2xml routine
function array2xml($data, $key_prefix = 'key_', $depth = 0) {
	if (!is_array($data)) return '';
	$xml = (!$depth) ? "<?xml version=\"1.0\" ?>\n<data>\n" : "";
	foreach ($data as $key => $val) {
		$pad = str_repeat("\t", $depth+1);
		if (is_numeric(substr($key,0,1))) $key = "$key_prefix$key";	// is first char numeric?
		$key = str_replace(':', '_', $key);
		if (is_array($val)) {
			$xml .= "$pad<$key>\n";
			$xml .= array2xml($val, $key_prefix, $depth+1);
			$xml .= "$pad</$key>\n";
		} else {
			$xml .= "$pad<$key>";
			$xml .= htmlspecialchars($val, ENT_QUOTES);
			$xml .= "</$key>\n";
		}
	}
	if (!$depth) $xml .= "</data>\n";
	return $xml;
}

// dumps all output buffers, sends a content-type, prints the xml
function print_xml($data, $clear_ob = true, $send_ct = true, $do_exit = true) {
	global $ps;
	if ($clear_ob) $ps->ob_restart();
	if ($send_ct) @header("Content-Type: text/xml; charset=utf-8", true);
	print array2xml($data);
	if ($do_exit) exit();
}

/*
	* function gradient
	* Returns a linear gradient array between 2 numbers
	* 
	* @param integer  $low  Low gradient value
	* @param integer  $high  High gradient value
	* @param integer  $totalsteps  Number of steps between low..high.
	* @return array  An array of gradient values
*/
function gradient($low, $high, $totalsteps) {
	$steps = $totalsteps - 1;
	$dist = $high - $low;
	$inc = $dist / $steps;
	$value = $low;
	$ary = array();
	$ary[] = $low;
	for ($i=1; $i < $steps; $i++) {
		$value += $inc;
		$ary[] = $value;
	}
	$ary[] = $high;
	return $ary;
}

// returns the RGB gradient between 2 RGB pairs
function rgbGradient($low, $high, $totalsteps) {
	$r1 = $low >> 16;
	$g1 = ($low & 0x00FF00) >> 8;
	$b1 = $low & 0x0000FF;
	$r2 = $high >> 16;
	$g2 = ($high & 0x00FF00) >> 8;
	$b2 = $high & 0x0000FF;
	$r = gradient($r1, $r2, $totalsteps);
	$g = gradient($g1, $g2, $totalsteps);
	$b = gradient($b1, $b2, $totalsteps);
	$ary = array();
	for ($i=0; $i < count($r); $i++) {
		$ary[] = ($r[$i] << 16) | ($g[$i] << 8) | $b[$i];
	}
	return $ary;
}

// returns a list of $key values from the array of arrays
function array_values_by_key(&$ary, $key) {
	$list = array();
	if (is_array($ary) and count($ary)) {
		foreach ($ary as $a) {
			$list[] = $a[$key];
		}
	}
	return $list;
}

/*
	* function mkdir_recursive
	* works like mkdir() but will work recursively in PHP4 or PHP5.
	* 
	* @param string $path  Directory to create
	* @param integer $mode  Permissions for the file
	* @return mixed
*/
function mkdir_recursive($path, $mode = 0777) {
	if (version_compare(PHP_VERSION, '5.0.0', '>=')) {
		return mkdir($path, $mode, true);
	} else {
		is_dir(rtrim(dirname($path), '/\\')) || mkdir_recursive(rtrim(dirname($path), '/\\'), $mode);
		return is_dir($path) || @mkdir($path, $mode);
	}
}

/*
	* function coalesce
	* Returns the first non-empty value.
	* 
	* @param mixed,mixed[,mixed,...]	2 or more parmaters to check
	* @return mixed
*/
function coalesce() {
	$args = func_get_args();
	foreach ($args as $arg) {
		if (!empty($arg)) {
		return $arg;
		}
	}
	return $args[0];
}

/*
	* function query_to_tokens
	* Tokenizes a string phrase for search queries and accounts for double
	* quoted strings properly (Multibyte safe).
	* 
	* @param string $string  Query string to tokenize
	* @return array  An array of query tokens (phrases)
*/
function query_to_tokens($string) {
	if (!is_string($string)) {
		return false;
	}

	$x = trim($string);
	// short circuit if the string is empty
	if (empty($x)) {
		return array();
	}
	   
	// tokenize string into individual characters
	$chars = multib_str_split($x);
	$mode = 'normal';
	$token = '';
	$tokens = array();
	for ($i=0, $j = count($chars); $i < $j; $i++) {
		switch ($mode) {
			case 'normal':
				if ($chars[$i] == '"') {
					if ($token != '') {
						$tokens[] = $token;
					}
					$token = '';
					$mode = 'quoting';
				} else if (in_array($chars[$i], array(' ', "\t", "\n"))) {
					if ($token != '') {
						$tokens[] = $token;
					}
					$token = '';
				} else {
					$token .= $chars[$i];
				}
				break;
	   
			case 'quoting':
				if ($chars[$i] == '"') {
					if ($token != '') {
						$tokens[] = $token;
					}
					$token = '';
					$mode = 'normal';
				} else {
					$token .= $chars[$i];
				}
				break;
		}
	}
	if ($token != '') {
		$tokens[] = $token;
	}

	return $tokens;
}   

/*
	* function mb_str_split
	* Multibyte safe str_split function. Splits a string into an array with
	* 1 character per element (note: 1 char does not always mean 1 byte).
	* 
	* @param string  $str  string to split.
	* @param integer  $length  character length of each array index. 
	* @return array  Array of characters
*/
function multib_str_split($str, $length = 1) {
	if ($length < 1) return FALSE;

	$result = array();

	for ($i = 0; $i < mb_strlen($str); $i += $length) {
		$result[] = mb_substr($str, $i, $length);
	}

	return $result;
}

// returns true if the variable given is not empty. Used as a callback function.
function not_empty($i) {
	return !empty($i);
}

/**
 * @get distance from latitude and longitute
 * @param float $lat_from
 * @param float $long_from
 * @param float $lat_to
 * @param float *long_to
 * @param $unit options k, m, n, Default k
 * @return float
 * @credit http://www.phpro.org/examples/Get-Riemann-Distance.html
 */
function getEarthDistance($lat_from, $long_from, $lat_to, $long_to, $unit='k') {
	/*** distance unit ***/
	switch ($unit) {
		/*** miles ***/
		case 'm':
		   $unit = 3963;
		   break;
		/*** nautical miles ***/
		case 'n':
		   $unit = 3444;
		   break;
		default:
		   /*** kilometers ***/
		   $unit = 6371;
	}
	
	/*** 1 degree = 0.017453292519943 radius ***/
	$degreeRadius = deg2rad(1);
	
	/*** convert longitude and latitude to radians ***/
	$lat_from  *= $degreeRadius;
	$long_from *= $degreeRadius;
	$lat_to	   *= $degreeRadius;
	$long_to   *= $degreeRadius;
	
	/*** apply the Great Circle Distance Formula ***/
	$dist = sin($lat_from) * sin($lat_to) +
		cos($lat_from) * cos($lat_to) *
		cos($long_from - $long_to);
	
	/*** radius of earth * arc cosine ***/
	return ($unit * acos($dist));
}

// substitute for json_encode if it's not defined already (PHP5.2.1)
// http://us2.php.net/manual/en/function.json-encode.php
if (!function_exists('json_encode')) {
	function json_encode($a=false) {
		if (is_null($a)) return 'null';
		if ($a === false) return 'false';
		if ($a === true) return 'true';
		if (is_scalar($a)) {
			if (is_float($a)) {
				// Always use "." for floats.
				return floatval(str_replace(",", ".", strval($a)));
			}
		
			if (is_string($a)) {
				static $jsonReplaces = array(
					array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'),
					array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"')
				);
				return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
			}
			
			return $a;
		}

		$isList = true;
		for ($i = 0, reset($a); $i < count($a); $i++, next($a)) {
			if (key($a) !== $i) {
				$isList = false;
				break;
			}
		}
		
		$result = array();
		if ($isList) {
			foreach ($a as $v) {
				$result[] = json_encode($v);
			}
			return '[' . join(',', $result) . ']';
		} else {
			foreach ($a as $k => $v) {
				$result[] = json_encode($k) . ':' . json_encode($v);
			}
			return '{' . join(',', $result) . '}';
		}
	}
}

/**
 * Recursively deletes a directory tree.
 *
 * @param string $folder		 The directory path.
 * @param bool   $keepRootFolder Whether to keep the top-level folder.
 *
 * @return bool TRUE on success, otherwise FALSE.
 * Source - https://gist.github.com/mindplay-dk/a4aad91f5a4f1283a5e2#gistcomment-2036828
 **/
function deleteTree($folder, $keepRootFolder) {
	// Handle bad arguments.
	if (empty($folder) || !file_exists($folder)) {
		return true; // No such file/folder exists.
	} elseif (is_file($folder) || is_link($folder)) {
		return @unlink($folder); // Delete file/link.
	}

	// Set permissions and delete all children.
	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($files as $fileinfo) {
		chmod($fileinfo->getRealPath(), 0775);
		$action = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
		if (!@$action($fileinfo->getRealPath())) {
			return false; // Abort due to the failure.
		}
	}

	// Delete the root folder itself?
	if (!$keepRootFolder) {
		chmod($folder, 0775);
		rmdir($folder);
		// If this fails try unlink.
		if (file_exists($folder)) {
			$mask = "*";
   			array_map( "unlink", glob( $mask ) );
			rmdir($folder);
			return (file_exists($folder)) ? false : true;
		}
	}
	return true;
}

// Returns true if url exists.
if (function_exists('curl_init')) {
	function url_exists($web_address) {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $web_address);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$page_exists = curl_exec($curl);
		curl_close($curl);
		return !empty($page_exists);
	}
}

if (!function_exists('str_contains')) {
	/**
	 * Polyfill for 'str_contains()' function added in PHP 8.0. Thanks to PHP.Watch
	 *
	 * Performs a case-sensitive check indicating if needle is contained in haystack.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for in the '$haystack'.
	 * @return bool True if '$needle' is in '$haystack', otherwise false.
	 *
	 * If you search for an empty $needle (""), PHP will always return true.
	 */
	function str_contains(string $haystack, string $needle): bool {
		return '' === $needle || false !== mb_strpos($haystack, $needle);
	}
}

// Returns true if PsychoStats is hosted on HTTPS.
function host_secure() {
  return
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || $_SERVER['SERVER_PORT'] == 443;
}

function db_escape($s) {
	global $ps;
	return $ps->db->escape($s,true);
}

?>
