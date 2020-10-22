<?php
/**
	PS::halflife::natural
	$Id: natural.php 475 2008-06-01 14:20:09Z lifo $

	Halflife::natural mod support for PsychoStats front-end
*/
if (!defined("PSYCHOSTATS_PAGE")) die("Unauthorized access to " . basename(__FILE__));
if (defined("CLASS_PS_HALFLIFE_NATURAL_PHP")) return 1;
define("CLASS_PS_HALFLIFE_NATURAL_PHP", 1);

include_once(dirname(__DIR__) . '/halflife.php');

class PS_halflife_natural extends PS_halflife {

var $class = 'PS::halflife::natural';
var $use_roles = true;

var $CLAN_MODTYPES = array(
	'marinekills'		=> '+',
	'alienkills'		=> '+',
	'marinedeaths'		=> '+',
	'aliendeaths'		=> '+',
	'joinedmarine'		=> '+',
	'joinedalien'		=> '+',
	'marinewon'		=> '+',
	'marinewonpct'		=> array( 'percent2', 'marinewon', 'alienwon' ),
	'alienwon'		=> '+',
	'alienwonpct'		=> array( 'percent2', 'alienwon', 'marinewon' ),
	'marinelost'		=> '+',
	'alienlost'		=> '+',
	'commander'		=> '+',
	'commanderwon'		=> '+',
	'commanderwonpct'	=> array( 'percent', 'commanderwon', 'commander' ),
	'votedown'		=> '+',
	'structuresbuilt'	=> '+',
	'structuresdestroyed'	=> '+',
	'structuresrecycled'	=> '+',
);

function PS_halflife_natural(&$db) {
	parent::PS_halflife($db);
	$this->CLAN_MAP_MODTYPES = $this->CLAN_MODTYPES;
}

function add_map_player_list_mod($map, $setup = array()) {
	global $cms;
	$this->add_map_player_list('structuresbuilt',		$setup + array('label' => $cms->trans("Structures Built")) );
	$this->add_map_player_list('structuresdestroyed',	$setup + array('label' => $cms->trans("Structures Destroyed")) );
	$this->add_map_player_list('structuresrecycled',	$setup + array('label' => $cms->trans("Structures Recycled")) );
}

// Add or remove columns from maps.php listing
function maps_table_mod(&$table) {
	global $cms;
	$table->insert_columns(
		array( 
			'marinewonpct' => array( 'label' => $cms->trans('Wins'), 'tooltip' => $cms->trans("Alien / Marine Wins"), 'callback' => array(&$this, 'team_wins') ), 
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
		$joined = $plr['joinedalien'] + $plr['joinedmarine'];
		if ($joined) {
			$pct1 = sprintf('%0.02f', $plr['joinedalien'] / $joined * 100);
			$pct2 = sprintf('%0.02f', $plr['joinedmarine'] / $joined * 100);
		} else {
			$pct1 = $pct2 = 0;
		}
		
		$actions['joined'] = array(
			'label'	=> $cms->trans("Alien / Marine Joins"),
			'type'	=> 'dual_bar',
			'value'	=> array(
				'pct1'	 	=> $pct1,
				'pct2'	 	=> $pct2,
				'title1'	=> $plr['joinedalien'] . ' ' . $cms->trans('Alien') . ' (' . $pct1 . '%)',
				'title2'	=> $plr['joinedmarine'] . ' ' . $cms->trans('Marine') . ' (' . $pct2 . '%)',
				'color1'	=> 'ff0000',
				'color2'	=> '0000ff',
				'width'		=> 130
			)
		);

		$actions['won'] = array(
			'label'	=> $cms->trans("Alien / Marine Wins"),
			'type'	=> 'dual_bar',
			'value'	=> array(
				'pct1'	 	=> $plr['alienwonpct'],
				'pct2'	 	=> $plr['marinewonpct'],
				'title1'	=> $plr['alienwon'] . ' ' . $cms->trans('Alien') . ' (' . $plr['alienwonpct'] . '%)',
				'title2'	=> $plr['marinewon'] . ' ' . $cms->trans('Marine') . ' (' . $plr['marinewonpct'] . '%)',
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
		'pct1'	=> $data['alienwonpct'], 
		'pct2'	=> $data['marinewonpct'],
		'title1'=> $data['alienwon'] . " " . $cms->trans("Alien Wins") . " (" . $data['alienwonpct'] . "%)",
		'title2'=> $data['marinewon'] . " " . $cms->trans("Marine Wins") . " (" . $data['marinewonpct'] . "%)",
	));
	return $bar;
}

}

?>
