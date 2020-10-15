<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty digits modifier plugin
 *
 * Type:     modifier<br>
 * Name:     digits<br>
 * Purpose:  format a FLOAT to have <N> trailing digits
 * @param string
 * @return string
 */
function smarty_modifier_digits($string,$d=0)
{
    return sprintf("%.0{$d}f", $string);
}

?>
