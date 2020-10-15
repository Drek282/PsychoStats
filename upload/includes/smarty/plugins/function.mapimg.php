<?php
/**
	PsychoStats 'map' <img> function.

	Returns a <img> tag for the map specified. 
	If no image exists then the name is returned instead.

*/
function smarty_function_mapimg($args, &$smarty) {
	global $ps;
	$m = $args['map'];
	unset($args['map']);
	return $ps->mapimg($m, $args);
}

?>
