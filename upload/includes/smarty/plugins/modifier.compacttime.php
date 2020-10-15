<?php
/**
 * Smarty plugin	-- Stormtrooper
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty number compacttime modifier plugin
 *
 * Type:     modifier<br>
 * Name:     compacttime<br>
 * Purpose:  convert seconds into a string representing the "h:m:s"
 * @param string
 * @return string
 */
function smarty_modifier_compacttime($seconds, $format="hh:mm:ss")
{
  return compacttime($seconds, $format);
}

?>
