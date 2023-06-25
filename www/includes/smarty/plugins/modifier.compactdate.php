<?php
/**
 * Smarty plugin	-- Stormtrooper
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty number compactdate modifier plugin
 *
 * Type:     modifier<br>
 * Name:     compactdate<br>
 * Purpose:  convert 2 timestamps into a compact date range.
 * @param string
 * @return string
 */
function smarty_modifier_compactdate($start, $end, $datefmt="M jS", $timefmt='H:i', $sep=' @ ')
{
	if (date("YMD",$start) == date('YMD',$end)) {
		return date($datefmt, $start) . $sep . 
			date($timefmt, $start) . ' - ' . 
			date($timefmt, $end);
	} else {
		return date($datefmt.$timefmt, $start) . ' - ' . 
			date($datefmt.$timefmt, $end);
	}
}

?>
