<?php
/**
 * Smarty plugin	-- Stormtrooper
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty PS3 skillchange function plugin
 *
 * Type:     function<br>
 * Name:     skillchange<br>
 * Purpose:  outputs the proper img tag for the change in skill
 * @param string
 * @return string
 */
function smarty_function_skillchange($args, &$smarty)
{
	return skill_change($args);
}

?>
