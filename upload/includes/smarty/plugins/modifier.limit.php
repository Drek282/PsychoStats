<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty limit modifier plugin
 *
 * Type:     modifier<br>
 * Name:     limit<br>
 * Purpose:  limits the elements of an array 
 * @param array
 * @param string
 * @return integer
 */
function smarty_modifier_limit($ary, $max = null) {
	if (!is_array($ary)) return '';
	return $max !== NULL ? array_slice($ary, 0, $max) : $ary;
}

/* vim: set expandtab: */

?>
