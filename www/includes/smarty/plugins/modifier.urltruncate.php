<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty truncate URL modifier plugin
 *
 * Type:     modifier<br>
 * Name:     urltruncate<br>
 * Purpose:  Truncate an URL string to a certain length if necessary, 
 *		inserting '...' in the middle of the URL as needed.
 * @author   Stormtrooper
 * @param string
 * @param integer
 * @param string
 * @param boolean
 * @return string
 */
function smarty_modifier_urltruncate($string, $length = 80, $etc = '...', $no_http_ok = true)
{
	if ($length == 0) return '';
	if (strlen($string) > $length) {
		$tmp = $no_http_ok ? preg_replace('|^https?://|', '', $string) : $string;
		// if removing the protocol shortens url enough, then we're done.
		if (strlen($tmp) > $length) {
			$length -= min($length, strlen($etc));
			$mid = $length / 2;
			return substr($string, 0, $mid) . $etc . substr($string, -$mid);
		} else {
			return $tmp;
		}
	} else {
		return $string;
	}
}

?>
