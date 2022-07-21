<?php
/**
 * Smarty plugin	-- Stormtrooper
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty number compactdate function plugin
 *
 * Type:     function<br>
 * Name:     compactdate<br>
 * Purpose:  convert 2 timestamps into a compact date range.
 * @param string
 * @return string
 */
function smarty_function_compactdate($args, &$smarty)
{
	$args += array(
		'var'		=> "",
		'start'		=> time(),
		'end'		=> time(),
		'datefmt'	=> "M jS",
		'timefmt'	=> "H:i",
		'datesep'	=> " @ ",
		'timesep'	=> " - ",
	);

	$output = "";
	if (date("YMD",$args['start']) == date('YMD',$args['end'])) {
		$output = 
			date($args['datefmt'], $args['start']) . 
			$args['datesep'] . 
			date($args['timefmt'], $args['start']) . 
			$args['timesep'] . 
			date($args['timefmt'], $args['end']);
	} else {
		$output = 
			date($args['datefmt'] . ' ' . $args['timefmt'], $args['start']) . 
			$args['timesep'] . 
			date($args['datefmt'] . ' ' . $args['timefmt'], $args['end']);
	}

	if (!$args['var']) return $output;
	$smarty->assign($args['var'], $output);
}

?>
