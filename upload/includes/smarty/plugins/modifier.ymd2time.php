<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


function smarty_modifier_ymd2time($date, $char='-') {
	list($y,$m,$d) = explode($char, $date);
	return mktime(0,0,0,$m,$d,$y);
}

/* vim: set expandtab: */

?>
