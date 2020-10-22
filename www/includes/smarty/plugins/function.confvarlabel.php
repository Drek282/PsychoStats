<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {confvarlabel} function plugin
 *
 * Type:     function<br>
 * Name:     confvarlabel<br>
 * Purpose:  PS3 method to display a label for the special config variable given
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_confvarlabel($args, &$smarty)
{
	global $cms, $conf_layout, $form;
	$args += array(
		'var'	=> '',
		'edit'	=> 1,
	);

	$var = $args['var'];
	$name = $var['id'];
	$value = $form->value($name);
	$label = !empty($var['label']) ? $cms->trans($var['label']) : $var['var'];

/*
	if ($cms->session->opt('advconfig') and $args['edit']) {
		$url = ps_url_wrapper(array( 
			'_base' => 'var.php',
			'id'	=> $var['id'],
//			'ct' 	=> $form->value('ct'), 
//			's' 	=> $form->value('s')
		));
		$label = "<a href='$url'>$label</a>";
	}
*/

	$help = $cms->theme->url() . '/img/icons/information';
	$help .= !empty($var['help']) ? '.png' : '-off.png';
	$id = $var['id'];
	$labelid = $var['help'] ? " id='label-$id' class='help'" : "";
	$label = "<label$labelid><img class='helpimg' id='helpimg-$id' src='$help' /> $label</label>";
	return $label;
}

?>
