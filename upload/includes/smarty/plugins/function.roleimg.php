<?php
/**
	PsychoStats 'role' <img> function.

	Returns a <img> tag for the role specified. 
	If no image exists then the name is returned instead.
*/
function smarty_function_roleimg($args, &$smarty) {
	global $ps;
	$r = $args['role'];
	unset($args['role']);
	return $ps->roleimg($r, $args);
}

?>
