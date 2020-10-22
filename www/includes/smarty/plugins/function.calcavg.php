<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {calcavg} function plugin
 *
 * Type:     function<br>
 * Name:     calcavg<br>
 * Purpose:  returns the average of values given
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_calcavg($args, &$smarty)
{
  $args += array(
	'var'		=> '',
	'total'		=> '',
	'values'	=> array(),
	'digits'	=> 2,
  );
  if (empty($args['digits']) or $args['digits'] < 0) $args['digits'] = 2;

  $values = is_array($args['values']) ? $args['values'] : explode(',', $args['values']);
  $total = empty($args['total']) ? count($values) : $args['total'];
  $sum = 0;
  foreach ($values as $v) {
    if (is_numeric($v)) $sum += $v;
  }

  if ($total) {
    $avg = sprintf("%." . $args['digits'] . "f", $sum / $total);
  } else {
    $avg = ($args['digits']) 
	? '0.' . str_repeat('0', $args['digits']) 
	: '0';
  }

  if (!$args['var']) return $avg;
  $smarty->assign($args['var'], $avg);
}

?>
