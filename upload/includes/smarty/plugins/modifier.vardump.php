<?php
/**
 * Smarty plugin	-- Stormtrooper
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty vardump modifier plugin
 *
 * Type:     modifier<br>
 * Name:     vardump<br>
 * Purpose:  prints the vardump() data for the variable
 * @param varies
 * @return string
 */
function smarty_modifier_vardump($var)
{
  ob_start();
  print_r($var);
  $text = ob_get_contents();
  ob_end_clean();
  return $text;
}

?>
