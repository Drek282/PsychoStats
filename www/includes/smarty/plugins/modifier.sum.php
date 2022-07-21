<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty sum modifier plugin
 *
 * Type:     modifier<br>
 * Name:     sum<br>
 * Purpose:  Sumarizies the values in a plain or associative array
 * @param array
 * @param string
 * @return integer
 */
function smarty_modifier_sum($ary, $key='')
{
  if (!is_array($ary)) return 0;
  $total = 0;
  foreach ($ary as $k => $v) {
    $total += (!empty($key)) ? (int)$ary[$k][$key] : (int)$v;
  }
  return $total;
}

/* vim: set expandtab: */

?>
