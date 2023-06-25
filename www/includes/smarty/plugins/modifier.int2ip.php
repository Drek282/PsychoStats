<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty int2ip modifier plugin
 *
 * Type:     modifier<br>
 * Name:     ip2int<br>
 * Purpose:  convert 32bit integer to an IP Address
 * @param string
 * @return string
 */
function smarty_modifier_int2ip($string)
{
    return long2ip($string);
}

?>
