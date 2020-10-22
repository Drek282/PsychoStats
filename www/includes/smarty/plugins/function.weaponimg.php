<?php
/**
	PsychoStats 'weapon' <img> function.

	Returns a <img> tag for the weapon specified. 
	If no image exists then the name is returned instead.
*/
function smarty_function_weaponimg($args, &$smarty) {
	global $ps;
	$w = $args['weapon'];
	unset($args['weapon']);
	return $ps->weaponimg($w, $args);
}

?>
