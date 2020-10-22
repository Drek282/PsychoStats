<?php

function smarty_function_award_phrase($params, &$smarty) {
	global $ps, $cms;
	$award = $params['award'];		// combined array of the award and player data
	$phrase = $cms->trans($award['phrase']);

	// if 'desc' is true then we print the award description
	if ($params['desc']) {
		$phrase = $cms->trans($award['description']);
		if (empty($phrase)) {
			$phrase = $cms->trans("No description available");
		}
	}

	// create some dynamic values for this award
	$award['value'] = $ps->award_format($award['topplrvalue'], $award['format']);
	$award['link'] = ps_table_plr_link($award['name'], $award);

	$weapon = array();
	// is this a weapon award?
	if ($award['weapon']) {
		$award['weapon']['link'] = ps_table_weapon_link($award['weapon']['name'], $award['weapon']);
		$weapon =& $award['weapon'];
	} else {
		$weapon =& $award;
	}

	$tokens = array(
		'award' 	=> &$award,
		'player' 	=> &$award,
		'weapon'	=> &$weapon,
	);
	if ($award['weapon']) {
		$tokens['weapon'] = array_merge($tokens['weapon'], $award['weapon']);
	}
	return simple_interpolate($phrase, $tokens);
}
?>
