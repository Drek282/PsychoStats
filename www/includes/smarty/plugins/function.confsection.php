<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {confsection} function plugin
 *
 * Type:     function<br>
 * Name:     confsection<br>
 * Purpose:  PS3 method to display a label or value text for the section given
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_confsection($args, &$smarty)
{
	global $cms, $section_labels;
	if (!is_array($section_labels)) return 'unknown';
	$args += array(
		'var'	=> 'label',
		'ct'	=> 'main',
		'sec'	=> 'unknown'
	);

	$text = $section_labels[ $args['ct'] ][ $args['sec'] ][ $args['var'] ];
	if ($args['var'] == 'label') {
		if ($text == '') $text = $args['sec'];
		$text = ps_escape_html($text);
	} else {
		if ($text == '') $text = $cms->trans("No description available");
	}
	return $text;
}

?>
