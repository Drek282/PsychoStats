<?php
/**
 * Smarty plugin	-- Stormtrooper
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty number commifier modifier plugin
 *
 * Type:     modifier<br>
 * Name:     commify<br>
 * Purpose:  convert integer into a string representing the number with commas
 * @param string
 * @return string
 */
function smarty_modifier_commify($string)
{
    return number_format($string);
}

?>
