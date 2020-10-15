<?php
/**
	PsychoStats 'icon' <img> function.

	Returns a <img> tag for the icon specified. 
	If no image exists then the name is returned instead.
*/
function smarty_function_iconimg($args, &$smarty)
{
	global $ps;
	$i = $args['icon'];
	unset($args['icon']);
	return $ps->iconimg($i, $args);
}
?>
