<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {dberrors} function plugin
 *
 * Type:     function<br>
 * Name:     db<br>
 * Purpose:  PS3 method to return various DB information, w/o exposing the entire $ps_db object to the theme
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_db($args, &$smarty)
{
	global $ps_db;
	$args += array(
		'var'	=> '',
		'filter' => '',
		'info' 	=> 'totalqueries',
	);

	$info = strtolower($args['info']);
	switch ($info) {
		// more to add later ...
		case 'queries':
			$output = implode(";\n\n",$ps_db->queries) . ';';
			break;
		case 'totalqueries': 
		default:
			$output = $ps_db->totalqueries;
			break;
	}

	$output = str_replace("\t\t", "\t", $output);
//	$output = str_replace("\n\n", "\n", $output);

	if ($args['filter'] and function_exists($args['filter'])) {
		$func = $args['filter'];
		$output = $func($output);
	}

	if (!$args['var']) return $output;
	$smarty->assign($args['var'], $output);
}

?>
