<?php
/**
	PS::halflife::firearms
	$Id: firearms.php 475 2008-06-01 14:20:09Z lifo $

	Halflife::firearms mod support for PsychoStats front-end
*/
if (!defined("PSYCHOSTATS_PAGE")) die("Unauthorized access to " . basename(__FILE__));
if (defined("CLASS_PS_HALFLIFE_FIREARMS_PHP")) return 1;
define("CLASS_PS_HALFLIFE_FIREARMS_PHP", 1);

include_once(dirname(__DIR__) . '/halflife.php');

class PS_halflife_firearms extends PS_halflife {

var $class = 'PS::halflife::firearms';
var $use_roles = false;

var $CLAN_MODTYPES = array(
	'blue_forcekills'		=> '+',
	'red_forcekills'		=> '+',
	'blue_forcedeaths'		=> '+',
	'red_forcedeaths'		=> '+',
	'joinedblue_force'		=> '+',
	'joinedred_force'		=> '+',
	'blue_forcewon'		=> '+',
	'blue_forcewonpct'		=> array( 'percent2', 'blue_forcewon', 'red_forcewon' ),
	'red_forcewon'		=> '+',
	'red_forcewonpct'		=> array( 'percent2', 'red_forcewon', 'blue_forcewon' ),
	'blue_forcelost'		=> '+',
	'red_forcelost'		=> '+',
    'bandage'       => '+',
    'medevac'       => '+',
    'capturepoint'       => '+',
);

function PS_halflife_firearms(&$db) {
	parent::PS_halflife($db);
	$this->CLAN_MAP_MODTYPES = $this->CLAN_MODTYPES;
}

function add_map_player_list_mod($map, $setup = array()) {
	global $cms;
	$this->add_map_player_list('ffkills',		$setup + array('label' => $cms->trans("Friendly Fire Kills")) );
	$this->add_map_player_list('bandage',	$setup + array('label' => $cms->trans("Soldiers Bandaged")) );
	$this->add_map_player_list('suture',	$setup + array('label' => $cms->trans("Soldiers Sutured")) );
	$this->add_map_player_list('treatconcussion',		$setup + array('label' => $cms->trans("Concussions Treated")) );
	$this->add_map_player_list('medevac',	$setup + array('label' => $cms->trans("Casualties Medevaced")) );
	$this->add_map_player_list('capturepoint',	$setup + array('label' => $cms->trans("Areas Captured")) );
	$this->add_map_player_list('howitzerammo',		$setup + array('label' => $cms->trans("Howizer Ammo Delivered")) );
	$this->add_map_player_list('targettingpack',	$setup + array('label' => $cms->trans("Targetting Pack Delivered")) );
	$this->add_map_player_list('redintelligence',	$setup + array('label' => $cms->trans("Red Intel Delivered")) );
	$this->add_map_player_list('blueintelligence',	$setup + array('label' => $cms->trans("Blue Intel Delivered")) );
	$this->add_map_player_list('secretdocuments',	$setup + array('label' => $cms->trans("Secret Docs Delivered")) );
	$this->add_map_player_list('destroyobject',	$setup + array('label' => $cms->trans("Objective Destroyed")) );
}

// Add or remove columns from maps.php listing
function maps_table_mod(&$table) {
	global $cms;
	$table->insert_columns(
		array( 
			'blue_forcewonpct' => array( 'label' => $cms->trans('Wins'), 'tooltip' => $cms->trans("Red Force / Blue Force Wins"), 'callback' => array(&$this, 'team_wins') ), 
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
		$joined = $plr['joinedred_force'] + $plr['joinedblue_force'];
		if ($joined) {
			$pct1 = sprintf('%0.02f', $plr['joinedred_force'] / $joined * 100);
			$pct2 = sprintf('%0.02f', $plr['joinedblue_force'] / $joined * 100);
		} else {
			$pct1 = $pct2 = 0;
		}
		
		$actions['joined'] = array(
			'label'	=> $cms->trans("Red / Blue Joins"),
			'type'	=> 'dual_bar',
			'value'	=> array(
				'pct1'	 	=> $pct1,
				'pct2'	 	=> $pct2,
				'title1'	=> $plr['joinedred_force'] . ' ' . $cms->trans('Red Force') . ' (' . $pct1 . '%)',
				'title2'	=> $plr['joinedblue_force'] . ' ' . $cms->trans('Blue Force') . ' (' . $pct2 . '%)',
				'color1'	=> 'ff0000',
				'color2'	=> '0000ff',
				'width'		=> 130
			)
		);

		$actions['won'] = array(
			'label'	=> $cms->trans("Red / Blue Wins"),
			'type'	=> 'dual_bar',
			'value'	=> array(
				'pct1'	 	=> $plr['red_forcewonpct'],
				'pct2'	 	=> $plr['blue_forcewonpct'],
				'title1'	=> $plr['red_forcewon'] . ' ' . $cms->trans('Red Force') . ' (' . $plr['red_forcewonpct'] . '%)',
				'title2'	=> $plr['blue_forcewon'] . ' ' . $cms->trans('Blue Force') . ' (' . $plr['blue_forcewonpct'] . '%)',
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
		'pct1'	=> $data['red_forcewonpct'], 
		'pct2'	=> $data['blue_forcewonpct'],
		'title1'=> $data['red_forcewon'] . " " . $cms->trans("Red Force Wins") . " (" . $data['red_forcewonpct'] . "%)",
		'title2'=> $data['blue_forcewon'] . " " . $cms->trans("Blue Force Wins") . " (" . $data['blue_forcewonpct'] . "%)",
	));
	return $bar;
}

}

?>
