<?php
/**
 * Smarty plugin	-- Stormtrooper
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty stripslashes modifier plugin
 *
 * Type:     modifier<br>
 * Name:     stripslashes<br>
 * Purpose:  removes slashed characters from the string if get_magic_quotes_gpc() is enabled, or $force is specified
 * @param string
 * @return string
 */
function smarty_modifier_stripslashes($string, $force=0)
{
  if ($force ) $string = stripslashes($string);
  return $string;
}

?>
