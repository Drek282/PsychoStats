<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty join modifier plugin
 *
 * Type:     modifier<br>
 * Name:     join<br>
 * Purpose:  joins the elements of an array with a string
 * @param array
 * @param string
 * @return integer
 */
function smarty_modifier_join($ary, $glue=', ') {
	if (!is_array($ary)) return '';
	return implode($glue, $ary);
}

/* vim: set expandtab: */

?>
