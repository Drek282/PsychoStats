<?php
/**
	PS::halflife::tfc
	$Id: tfc.php 475 2008-06-01 14:20:09Z lifo $

	Halflife::tfc mod support for PsychoStats front-end
*/
if (!defined("PSYCHOSTATS_PAGE")) die("Unauthorized access to " . basename(__FILE__));
if (defined("CLASS_PS_HALFLIFE_TFC_PHP")) return 1;
define("CLASS_PS_HALFLIFE_TFC_PHP", 1);

include_once(dirname(__DIR__) . '/halflife.php');

class PS_halflife_tfc extends PS_halflife {

var $class = 'PS::halflife::tfc';
var $use_roles = true;

var $CLAN_MODTYPES = array(
	'bluekills'		=> '+',
	'redkills'      => '+',
	'greenkills'	=> '+',
	'yellowkills'	=> '+',
	'bluedeaths'	=> '+',
	'reddeaths'		=> '+',
	'greendeaths'	=> '+',
	'yellowdeaths'	=> '+',
	'joinedblue'	=> '+',
	'joinedred'		=> '+',
	'joinedgreen'	=> '+',
	'joinedyellow'	=> '+',
	'redwon'		=> '+',
	'redwonpct'		=> array( 'percent2', 'redwon', 'bluewon' ),
	'bluewon'		=> '+',
	'bluewonpct'	=> array( 'percent2', 'bluewon', 'redwon' ),
	'redlost'		=> '+',
	'bluelost'		=> '+',
	'greenwon'		=> '+',
	'greenlost'		=> '+',
	'yellowwon'		=> '+',
	'yellowlost'	=> '+',
	'dustbowl_team1kills'		=> '+',
	'dustbowl_team2kills'		=> '+',
	'hunted_team1kills'		=> '+',
	'hunted_team2kills'		=> '+',
	'joineddustbowl_team1'		=> '+',
	'joineddustbowl_team2'		=> '+',
	'joinedhunted_team1'		=> '+',
	'joinedhunted_team2'		=> '+',
	'dustbowl_team1won'		=> '+',
	'dustbowl_team2won'		=> '+',
	'hunted_team1won'		=> '+',
	'hunted_team2won'		=> '+',
	'dustbowl_team1lost'		=> '+',
	'dustbowl_team2lost'		=> '+',
	'hunted_team1lost'		=> '+',
	'hunted_team2lost'		=> '+',
    'structuresbuilt'   => '+',
    'structuresdestroyed'   => '+',
    'capturepoint'  => '+',
    'mapspecial'    => '+',
    'bandage'       => '+',
);

function PS_halflife_tfc(&$db) {
	parent::PS_halflife($db);
	$this->CLAN_MAP_MODTYPES = $this->CLAN_MODTYPES;
}

// Add or remove columns from maps.php listing
function maps_table_mod(&$table) {
	global $cms;
	$table->insert_columns(
		array( 
			'bluewonpct' => array( 'label' => $cms->trans('Wins'), 'tooltip' => $cms->trans("Red / Blue Wins"), 'callback' => array(&$this, 'team_wins') ), 
		),
		'rounds',
		true
	);
}

function map_left_column_mod(&$map, &$theme) {
	// maps and players have the same stats ...
	$this->player_left_column_mod($map, $theme);
	$theme->assign('map_left_column_mod', $theme->get_template_vars('player_left_column_mod'));
}

function clan_left_column_mod(&$clan, &$theme) {
	// clans and players have the same stats ...
	$this->player_left_column_mod($clan, $theme);
	$theme->assign('clan_left_column_mod', $theme->get_template_vars('player_left_column_mod'));
}

function player_left_column_mod(&$plr, &$theme) {
	global $cms;
	$tpl = 'player_left_column_mod';
	if ($theme->template_found($tpl, false)) {
		$actions = array();
		$joined = $plr['joinedred'] + $plr['joinedblue'];
		if ($joined) {
			$pct1 = sprintf('%0.02f', $plr['joinedred'] / $joined * 100);
			$pct2 = sprintf('%0.02f', $plr['joinedblue'] / $joined * 100);
		} else {
			$pct1 = $pct2 = 0;
		}
		
		$actions['joined'] = array(
			'label'	=> $cms->trans("Red / Blue Joins"),
			'type'	=> 'dual_bar',
			'value'	=> array(
				'pct1'	 	=> $pct1,
				'pct2'	 	=> $pct2,
				'title1'	=> $plr['joinedred'] . ' ' . $cms->trans('Red') . ' (' . $pct1 . '%)',
				'title2'	=> $plr['joinedblue'] . ' ' . $cms->trans('Blue') . ' (' . $pct2 . '%)',
				'color1'	=> 'ff0000',
				'color2'	=> '0000ff',
				'width'		=> 130
			)
		);

		$actions['won'] = array(
			'label'	=> $cms->trans("Red / Blue Wins"),
			'type'	=> 'dual_bar',
			'value'	=> array(
				'pct1'	 	=> $plr['redwonpct'],
				'pct2'	 	=> $plr['bluewonpct'],
				'title1'	=> $plr['redwon'] . ' ' . $cms->trans('Red') . ' (' . $plr['redwonpct'] . '%)',
				'title2'	=> $plr['bluewon'] . ' ' . $cms->trans('Blue') . ' (' . $plr['bluewonpct'] . '%)',
				'color1'	=> 'ff0000',
				'color2'	=> '0000ff',
				'width'		=> 130
			)
		);

		$cms->filter('left_column_actions', $actions);
		foreach (array_keys($actions) as $i) {
			if ($actions[$i]['type'] == 'dual_bar') {
				$actions[$i]['value'] = dual_bar( $actions[$i]['value'] );
			} else {
				$actions[$i]['value'] = pct_bar( $actions[$i]['value'] );
			}
		}
		
		$theme->assign(array(
			'mod_actions'	=> $actions,
			'mod_actions_title' => $cms->trans("Team / Action Profile"),
		));
		$output = $theme->parse($tpl);
		$theme->assign('player_left_column_mod', $output);
	}
}

// used in maps.php as a callback for the wins of each team
function team_wins($value, $data) {
	global $cms;
	$bar = dual_bar(array(
		'pct1'	=> $data['redwonpct'], 
		'pct2'	=> $data['bluewonpct'],
		'title1'=> $data['redwon'] . " " . $cms->trans("Red Wins") . " (" . $data['redwonpct'] . "%)",
		'title2'=> $data['bluewon'] . " " . $cms->trans("Blue Wins") . " (" . $data['bluewonpct'] . "%)",
	));
	return $bar;
}

}

?>
