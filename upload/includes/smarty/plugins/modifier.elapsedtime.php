<?php
/**
 * Smarty plugin	-- Stormtrooper
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty number elapsedtime modifier plugin
 *
 * Type:     modifier<br>
 * Name:     elapsedtime<br>
 * Purpose:  convert seconds into a string representing "years, months, weeks, days, hours, minutes, seconds"
 * @param string
 * @return string
 */
function smarty_modifier_elapsedtime($seconds, $start = 0, $wantarray = false)
{
  return elapsedtime($seconds, $start, $wantarray);
}

?>
