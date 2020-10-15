<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty date modifier plugin
 *
 * Type:     modifier<br>
 * Name:     date<br>
 * Purpose:  returns a formatted date using date()
 * @param integer
 * @return string
 */
function smarty_modifier_date($time, $format='', $ignore_ofs = false) {
	return ps_date($format, $time, $ignore_ofs);
}

/* vim: set expandtab: */

?>
