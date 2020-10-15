<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty avg modifier plugin
 *
 * Type:     modifier<br>
 * Name:     avg<br>
 * Purpose:  Averages the values in a plain or associative array
 * @param array
 * @param string
 * @param integer
 * @return integer/float
 */
function smarty_modifier_avg($ary, $key='', $digits=2)
{
  if (!is_array($ary)) return 0;
  if (empty($args['digits']) or $args['digits'] < 0) $args['digits'] = 2;

  $total = count($ary);
  $sum = 0;
  $avg = 0;
  foreach ($ary as $k => $v) {
    $sum += (!empty($key)) ? (int)$ary[$k][$key] : (int)$v;
  }

  if ($total) {
    $avg = sprintf("%." . $args['digits'] . "f", $sum / $total);
  } else {
    $avg = ($args['digits']) 
	? '0.' . str_repeat('0', $args['digits']) 
	: '0';
  }

  return $avg;
}

/* vim: set expandtab: */

?>
