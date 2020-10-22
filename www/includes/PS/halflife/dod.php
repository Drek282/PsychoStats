<?php
/**
	PS::halflife::dod
	$Id: dod.php 475 2008-06-01 14:20:09Z lifo $

	Halflife::dod mod support for PsychoStats front-end
*/
if (!defined("PSYCHOSTATS_PAGE")) die("Unauthorized access to " . basename(__FILE__));
if (defined("CLASS_PS_HALFLIFE_DOD_PHP")) return 1;
define("CLASS_PS_HALFLIFE_DOD_PHP", 1);

include_once(dirname(__DIR__) . '/halflife.php');

class PS_halflife_dod extends PS_halflife {

var $class = 'PS::halflife::dod';
var $use_roles = true;

var $CLAN_MODTYPES = array(
	'allieskills'		=> '+',
	'axiskills'		=> '+',
	'alliesdeaths'		=> '+',
	'axisdeaths'		=> '+',
	'joinedallies'		=> '+',
	'joinedaxis'		=> '+',
	'allieswon'		=> '+',
	'allieswonpct'		=> array( 'percent2', 'allieswon', 'axiswon' ),
	'axiswon'		=> '+',
	'axiswonpct'		=> array( 'percent2', 'axiswon', 'allieswon' ),
	'allieslost'		=> '+',
	'axislost'		=> '+',
#	'tnt'			=> '+',
#	'tntused'		=> '+',
	'alliesflagscaptured'	=> '+',
	'alliesflagscapturedpct'=> array( 'percent', 'alliesflagscaptured', 'flagscaptured' ),
	'axisflagscaptured'	=> '+',
	'axisflagscapturedpct'	=> array( 'percent', 'axisflagscaptured', 'flagscaptured' ),
	'flagscaptured'		=> '+',

	'alliesflagsblocked'	=> '+',
	'alliesflagsblockedpct'	=> array( 'percent', 'alliesflagsblocked', 'flagsblocked' ),
	'axisflagsblocked'	=> '+',
	'axisflagsblockedpct'	=> array( 'percent', 'axisflagsblocked', 'flagsblocked' ),
	'flagsblocked'		=> '+',

	'bombdefused'		=> '+',
	'bombplanted'		=> '+',
	'killedbombplanter'	=> '+',
	'alliesscore'		=> '+',	
	'alliesscorepct'	=> array( 'percent2', 'alliesscore', 'axisscore' ),
	'axisscore'		=> '+',	
	'axisscorepct'		=> array( 'percent2', 'axisscore', 'alliesscore' ),
);

function PS_halflife_dod(&$db) {
	parent::PS_halflife($db);
	$this->CLAN_MAP_MODTYPES = $this->CLAN_MODTYPES;
}

// Add 'top10' player lists to the map.php page
function add_map_player_list_mod($map, $setup = array()) {
	global $cms;
	$this->add_map_player_list('alliesflagscaptured', 	$setup + array('label' => $cms->trans("Most Ally flags captured")) );
	$this->add_map_player_list('axisflagscaptured',		$setup + array('label' => $cms->trans("Most Axis flags captured")) );
	$this->add_map_player_list('flagscaptured',  		$setup + array('label' => $cms->trans("Most flags captured")) );
//	$this->add_map_player_list('areascaptured',  		$setup + array('label' => $cms->trans("Most areas captured")) );
}

// Add or remove columns from maps.php listing
function maps_table_mod(&$table) {
	global $cms;
	$table->insert_columns(
		array( 
			'axiswonpct' => array( 'label' => $cms->trans('Wins'), 'tooltip' => $cms->trans("Axis / Allied Wins"), 'callback' => array(&$this, 'team_wins') ), 
		),
		'rounds',
		true
	);
}

// Add or remove columns from index.php listing
function index_table_mod(&$table) {
	global $cms;
	$table->insert_columns(
		array( 
			'flagscaptured' => array( 'label' => $cms->trans('Flags'), 'tooltip' => $cms->trans("Flags captured") ), 
		),
		'onlinetime',
		false
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
		$joined = $plr['joinedaxis'] + $plr['joinedallies'];
		if ($joined) {
			$pct1 = sprintf('%0.02f', $plr['joinedaxis'] / $joined * 100);
			$pct2 = sprintf('%0.02f', $plr['joinedallies'] / $joined * 100);
		} else {
			$pct1 = $pct2 = 0;
		}
		
		$actions['joined'] = array(
			'label'	=> $cms->trans("Axis / Ally Joins"),
			'type'	=> 'dual_bar',
			'value'	=> array(
				'pct1'	 	=> $pct1,
				'pct2'	 	=> $pct2,
				'title1'	=> $plr['joinedaxis'] . ' ' . $cms->trans('axis') . ' (' . $pct1 . '%)',
				'title2'	=> $plr['joinedallies'] . ' ' . $cms->trans('ally') . ' (' . $pct2 . '%)',
				'color1'	=> 'cc0000',
				'color2'	=> '00cc00',
				'width'		=> 130
			)
		);

		$actions['won'] = array(
			'label'	=> $cms->trans("Axis / Ally Wins"),
			'type'	=> 'dual_bar',
			'value'	=> array(
				'pct1'	 	=> $plr['axiswonpct'],
				'pct2'	 	=> $plr['allieswonpct'],
				'title1'	=> $plr['axiswon'] . ' ' . $cms->trans('axis') . ' (' . $plr['axiswonpct'] . '%)',
				'title2'	=> $plr['allieswon'] . ' ' . $cms->trans('ally') . ' (' . $plr['allieswonpct'] . '%)',
				'color1'	=> 'cc0000',
				'color2'	=> '00cc00',
				'width'		=> 130
			)
		);

		$actions['flagscaptured'] = array(
			'label'	=> $cms->trans("Flags Captured"),
			'type'	=> 'dual_bar',
			'value'	=> array(
				'pct1'	 	=> $plr['axisflagscapturedpct'],
				'pct2'	 	=> $plr['alliesflagscapturedpct'],
				'title1'	=> $plr['axisflagscaptured'] . ' ' . $cms->trans('axis') . ' (' . $plr['axisflagscapturedpct'] . '%)',
				'title2'	=> $plr['alliesflagscaptured'] . ' ' . $cms->trans('ally') . ' (' . $plr['alliesflagscapturedpct'] . '%)',
				'color1'	=> 'cc0000',
				'color2'	=> '00cc00',
				'width'		=> 130
			)
		);

		$actions['flagsblocked'] = array(
			'label'	=> $cms->trans("Blocked Captures"),
			'type'	=> 'dual_bar',
			'value'	=> array(
				'pct1'	 	=> $plr['axisflagsblockedpct'],
				'pct2'	 	=> $plr['alliesflagsblockedpct'],
				'title1'	=> $plr['axisflagsblocked'] . ' ' . $cms->trans('axis') . ' (' . $plr['axisflagsblockedpct'] . '%)',
				'title2'	=> $plr['alliesflagsblocked'] . ' ' . $cms->trans('ally') . ' (' . $plr['alliesflagsblockedpct'] . '%)',
				'color1'	=> 'cc0000',
				'color2'	=> '00cc00',
				'width'		=> 130
			)
		);

		$actions['score'] = array(
			'label'	=> $cms->trans("Team Scores"),
			'type'	=> 'dual_bar',
			'value'	=> array(
				'pct1'	 	=> $plr['axisscorepct'],
				'pct2'	 	=> $plr['alliesscorepct'],
				'title1'	=> $plr['axisscore'] . ' ' . $cms->trans('axis') . ' (' . $plr['axisscorepct'] . '%)',
				'title2'	=> $plr['alliesscore'] . ' ' . $cms->trans('ally') . ' (' . $plr['alliesscorepct'] . '%)',
				'color1'	=> 'cc0000',
				'color2'	=> '00cc00',
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
			'mod_actions' => $actions,
			'mod_actions_title' => $cms->trans("Team / Action Profile"),
		));
		$output = $theme->parse($tpl);
		$theme->assign('player_left_column_mod', $output);
	}
}

// used in maps.php as a table callback for the wins of each team
function team_wins($value, $data) {
	global $cms;
	$bar = dual_bar(array(
		'pct1'	=> $data['axiswonpct'], 
		'pct2'	=> $data['allieswonpct'],
		'title1'=> $data['axiswon'] . " " . $cms->trans("Axis Wins") . " (" . $data['axiswonpct'] . "%)",
		'title2'=> $data['allieswon'] . " " . $cms->trans("Ally Wins") . " (" . $data['allieswonpct'] . "%)",
	));
	return $bar;
}

} // end of ps::halflife::dod
?>
