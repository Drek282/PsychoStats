<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty digits2 modifier plugin
 *
 * Type:     modifier<br>
 * Name:     digits<br>
 * Purpose:  format a FLOAT to have 2 trailing digits
 * @param string
 * @return string
 */
function smarty_modifier_digits2($string)
{
    return sprintf("%.02f", $string);
}

?>
