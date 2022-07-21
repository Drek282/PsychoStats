<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {teamcompare} function plugin
 *
 * Type:     function<br>
 * Name:     teamcompare<br>
 * Purpose:  returns a small horiz bar graph showing the difference between 2 team values
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_teamcompare($args, &$smarty)
{
  global $conf;
  $args += array(
	'var'		=> '',
	'width'		=> '100%',
	'leftcolor'     => 'blue',
	'rightcolor'    => 'red',
 	'leftwidth'     => 1,
 	'rightwidth'    => 1,
	'height'        => 4,
  );
  $output = "
<table width='{$args['width']}' align='center' border='0' cellspacing='0' cellpadding='0' style='border: 1px solid #000000'>
  <tr>
    <td bgcolor='{$args['leftcolor']}' style='width: {$args['leftwidth']}%;'><img src='{$conf['imagesurl']}spacer.gif' border='0' height='{$args['height']}' width='1' /></td>
    <td bgcolor='{$args['rightcolor']}' style='width: {$args['rightwidth']}%;'><img src='{$conf['imagesurl']}spacer.gif' border='0' height='{$args['height']}' width='1' /></td>
  </tr>
</table>
";
  if (!$args['var']) return $output;
  $smarty->assign($args['var'], $output);
}

?>
