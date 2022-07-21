<?php
/**
	PsychoStats 'flag' <img> function.

	Returns a <img> tag for the flag specified. 
	If no image exists then the name is returned instead.
*/
function smarty_function_flagimg($args, &$smarty)
{
	global $ps;
	$f = $args['cc'];
	unset($args['cc']);
	return $ps->flagimg($f, $args);
}

?>
