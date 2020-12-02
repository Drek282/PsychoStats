<?php
/**
	PS::halflife::bg3
	$Id: bg3.php 475 2008-06-01 14:20:09Z lifo $

	Halflife::bg3 mod support for PsychoStats front-end
*/
if (!defined("PSYCHOSTATS_PAGE")) die("Unauthorized access to " . basename(__FILE__));
if (defined("CLASS_PS_HALFLIFE_TF2_PHP")) return 1;
define("CLASS_PS_HALFLIFE_TF2_PHP", 1);

include_once(dirname(__DIR__) . '/halflife.php');

class PS_halflife_bg3 extends PS_halflife {

var $class = 'PS::halflife::bg3';
var $use_roles = true;

var $CLAN_MODTYPES = array(
	'britishkills'		=> '+',
	'americanskills'		=> '+',
	'britishdeaths'		=> '+',
	'americansdeaths'		=> '+',

	'britishwon'		=> '+',
	'britishwonpct'		=> array( 'percent2', 'britishwon', 'americanswon' ),
	'americanswon'		=> '+',
	'americanswonpct'		=> array( 'percent2', 'americanswon', 'britishwon' ),
	'britishlost'		=> '+',
	'americanslost'		=> '+',

	'flagscaptured'		=> '+',

	'britishflagscaptured'	=> '+',
	'britishflagscapturedpct'	=> array( 'percent', 'britishflagscaptured', 'flagscaptured' ),

	'americansflagscaptured'	=> '+',
	'americansflagscapturedpct'	=> array( 'percent', 'americansflagscaptured', 'flagscaptured' ),

	'joinedbritish'		=> '+',
	'joinedamericans'		=> '+'
);

function PS_halflife_bg3(&$db) {
	parent::PS_halflife($db);
	$this->CLAN_MAP_MODTYPES = $this->CLAN_MODTYPES;
}

function add_map_player_list_mod($map, $setup = array()) {
	global $cms;

	$prefix = substr($map['uniqueid'], 0, 3);
	if ($prefix == 'ctf') {
		$this->add_map_player_list('flagscaptured', $setup + array('label' => $cms->trans("Most Flags Captured")) );
	}
}

// Add or remove columns from maps.php listing
function maps_table_mod(&$table) {
	global $cms;
	$table->insert_columns(
		array( 
			'americanswonpct' => array( 'label' => $cms->trans('Wins'), 'tooltip' => $cms->trans("British / Amerians Wins"), 'callback' => array(&$this, 'team_wins') ), 
		),
		'rounds',
		true
	);
}

// Add or remove columns from index.php listing
function index_table_mod(&$table) {
	global $cms;
}

// Add or remove columns from roles.php listing
function roles_table_mod(&$table) { 
	global $cms;
	$table->insert_columns(
		array( 
			'backstabkills' => array( 'label' => $cms->trans('BS'), 'modifier' => 'commify', 'tooltip' => $cms->trans("Backstab Kills") ),
			'backstabkillspct' => array( 'label' => $cms->trans('BS%'), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Backstab Kills Percentage") ),
		),
		'headshotkillspct',
		true
	);
}

function player_roles_table_mod(&$table) {
	$this->roles_table_mod($table);
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
	static $strings = array();
	if (!$strings) {
		$strings = array(
			'won'			=> $cms->trans("British / American Wins"),
			'flagscaptured'		=> $cms->trans("Flags Captured")
		);
	}
	$tpl = 'player_left_column_mod';
	if ($theme->template_found($tpl, false)) {
		$actions = array();
		$joined = $plr['joinedbritish'] + $plr['joinedamericans'];
		if ($joined) {
			$pct1 = sprintf('%0.02f', $plr['joinedbritish'] / $joined * 100);
			$pct2 = sprintf('%0.02f', $plr['joinedamericans'] / $joined * 100);
		} else {
			$pct1 = $pct2 = 0;
		}
		
		$actions['joined'] = array(
			'label'	=> $cms->trans("British / American Joins"),
			'type'	=> 'dual_bar',
			'value'	=> array(
				'pct1'	 	=> $pct1,
				'pct2'	 	=> $pct2,
				'title1'	=> $plr['joinedbritish'] . ' ' . $cms->trans('british') . ' (' . $pct1 . '%)',
				'title2'	=> $plr['joinedamericans'] . ' ' . $cms->trans('ally') . ' (' . $pct2 . '%)',
				'color1'	=> 'cc0000',
				'color2'	=> '00cc00',
				'width'		=> 130
			)
		);

		foreach (array('won','flagscaptured') as $var) {
			$actions[$var] = array(
				'what'	=> $var,
				'label'	=> $strings[$var],
				'type'	=> 'dual_bar',
				'value'	=> array(
					'pct1'	 	=> $plr['british' . $var . 'pct'],
					'pct2'	 	=> $plr['americans' . $var . 'pct'],
					'title1'	=> $plr['british' . $var] . ' ' . $cms->trans('British') . ' (' . $plr['british' . $var . 'pct'] . '%)',
					'title2'	=> $plr['americans' . $var] . ' ' . $cms->trans('American') . ' (' . $plr['americans' . $var . 'pct'] . '%)',
					'color1'	=> 'cc0000',
					'color2'	=> '0000cc',
					'width'		=> 130
				)
			);
		}

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
		'pct1'	=> $data['britishwonpct'], 
		'pct2'	=> $data['americanswonpct'],
		'title1'=> $data['britishwon'] . " " . $cms->trans("British Wins") . " (" . $data['britishwonpct'] . "%)",
		'title2'=> $data['americanswon'] . " " . $cms->trans("American Wins") . " (" . $data['americanswonpct'] . "%)",
		'color1'=> 'cc0000',
		'color2'=> '0000cc',
	));
	return $bar;
}

} // end of ps::halflife::bg3

?>
