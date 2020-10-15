<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */

require_once $smarty->_get_plugin_filepath('modifier', 'date');

/**
 * Smarty date modifier plugin
 *
 * Type:     modifier<br>
 * Name:     datetime<br>
 * Purpose:  returns a formatted date and time using date()
 * @param integer
 * @return string
 */
function smarty_modifier_datetime($time, $format='', $ignore_ofs = false) {
	global $ps;
	if (empty($format)) $format = $ps->conf['theme']['format']['datetime'];
	if (empty($format)) $format = "Y-m-d H:i:s";
	return smarty_modifier_date($time, $format, $ignore_ofs);
}

/* vim: set expandtab: */

?>
