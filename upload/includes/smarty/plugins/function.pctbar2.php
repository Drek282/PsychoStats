<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {pctbar2} function plugin
 *
 * Type:     function<br>
 * Name:     pctbar2<br>
 * Purpose:  returns a pair of percentage bars that mesh together
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_pctbar2($args, &$smarty)
{
	$args += array(
		'var'		=> '',
		'pct1'		=> 0,
		'pct2'		=> 0,
		'color1'	=> 'blue',
		'color2'	=> 'red',
		'width'		=> 100,
		'bordersize'	=> 0,
		'bordercolor'	=> 'black',
	);
	if (empty($args['width']) or !is_numeric($args['width']) or $args['width'] < 1) $args['width'] = 100;
	$w = $args['width'];
	$left = $args['pct1'] / 100 * $w;
	$right = $args['pct2'] / 100 * $w;

	$out  = '<div style="width: %dpx; height: 8px; overflow: hidden;%s">';
	$out .= '<span style="width: %d; float: left; background: %s">&nbsp;</span>';
	$out .= '<span style="width: %d; float: right; background: %s">&nbsp;</span>';
	$out .= '</div>';
	$out = sprintf($out, 
		$w,
		$args['bordersize'] ? " border: " . $args['bordersize'] . "px solid " . $args['bordercolor'] : '',
		$left,
		$args['color1'],
		$right,
		$args['color2']
	);

	if (!$args['var']) return $out;
	$smarty->assign($args['var'], $out);
}

?>
