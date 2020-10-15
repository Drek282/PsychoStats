<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {calcpct} function plugin
 *
 * Type:     function<br>
 * Name:     calcpct<br>
 * Purpose:  returns the percentage of $sum / $total * 100
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_calcpct($args, &$smarty)
{
  $args += array(
	'var'		=> '',
	'total'		=> 0,
	'sum'		=> 0,
	'digits'	=> 2,
  );
  $pct = 0;

  if (empty($args['digits']) or $args['digits'] < 0) $args['digits'] = 2;

  if ($args['total'] and $args['sum']) {
    $pct = sprintf("%." . $args['digits'] . "f", $args['sum'] / $args['total'] * 100);
  } else {
    $pct = ($args['digits']) 
	? '0.' . str_repeat('0', $args['digits']) 
	: '0';
  }

  if (!$args['var']) return $pct;
  $smarty->assign($args['var'], $pct);
}

?>
