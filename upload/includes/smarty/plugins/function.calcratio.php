<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {calcratio} function plugin
 *
 * Type:     function<br>
 * Name:     calcpct<br>
 * Purpose:  returns the ratio of $sum / $total
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_calcratio($args, &$smarty)
{
  $args += array(
	'var'		=> '',
	'left'		=> 0,
	'right'		=> 0,
	'digits'	=> 2,
  );
  $ratio = 0;

  if (empty($args['digits']) or $args['digits'] < 0) $args['digits'] = 2;

  if ($args['right']) {
    $ratio = sprintf("%." . $args['digits'] . "f", $args['left'] / $args['right']);
  } else {
    $ratio = $args['digits']
	? '0.' . str_repeat('0', $args['digits']) 
	: '0';
  }

  if (!$args['var']) return $ratio;
  $smarty->assign($args['var'], $ratio);
}

?>
