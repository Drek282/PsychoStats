<?php
// returns the HTML to display an open-flash-chart movie

function smarty_function_ofc($args, &$smarty) {
	include_once(PS_ROOTDIR. "/includes/ofc/open_flash_chart_object.php");

	$args += array(
		'width'		=> 320,
		'height'	=> 240,
		'url'		=> null,
		'data'		=> null,
		'swfobject'	=> true,
		'baseurl'	=> 'includes/ofc/',
	);
	if ($args['url'] == null) {
		if ($args['data'] == null) {
			$args['url'] = $_SERVER['SCRIPT_NAME'] . '?ofc=1';
		} else {
			$args['url'] = $_SERVER['SCRIPT_NAME'] . '?ofc=' . $args['data'];
		}
	}

	// this prints, so no need to return 
	open_flash_chart_object($args['width'], $args['height'], $args['url'], $args['swfobject'], $args['baseurl']);
}

?>
