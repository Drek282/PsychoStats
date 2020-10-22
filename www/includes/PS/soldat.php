<?php
/**
 *	This file is part of PsychoStats.
 *
 *	Written by Jason Morriss
 *	Copyright 2008 Jason Morriss
 *
 *	PsychoStats is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	PsychoStats is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with PsychoStats.  If not, see <http://www.gnu.org/licenses/>.
 *
 *	Version: $Id: soldat.php 527 2008-08-03 16:06:41Z lifo $
 *
 */
if (!defined("PSYCHOSTATS_PAGE")) die("Unauthorized access to " . basename(__FILE__));
if (defined("CLASS_PS_SOLDAT_PHP")) return 1;
define("CLASS_PS_SOLDAT_PHP", 1);

include_once(__DIR__ . '/PS.php');

class PS_soldat extends PS {

var $class = 'PS::soldat';

var $CLAN_MODTYPES = array(
	'alphakills'		=> '+',
	'bravokills'		=> '+',
	'alphadeaths'		=> '+',
	'bravodeaths'		=> '+',
	'joinedalpha'		=> '+',
	'joinedbravo'		=> '+',
	'alphawon'		=> '+',
	'alphawonpct'		=> array( 'percent2', 'alphawon', 'bravowon' ),
	'alphalost'		=> '+',
	'bravowon'		=> '+',
	'bravowonpct'		=> array( 'percent2', 'bravowon', 'alphawon' ),
	'bravolost'		=> '+',
	
	'flagscaptured'		=> '+',
	'flagsdefended'		=> '+',

	'bravoflagscaptured'	=> '+',
	'bravoflagscapturedpct'	=> array( 'percent', 'bravoflagscaptured', 'flagscaptured' ),
	'bravoflagsdefended'	=> '+',
	'bravoflagsdefendedpct'	=> array( 'percent', 'bravoflagsdefended', 'flagsdefended' ),
	'bravoflagspickedup'	=> '+',

	'alphaflagscaptured'	=> '+',
	'alphaflagscapturedpct'	=> array( 'percent', 'alphaflagscaptured', 'flagscaptured' ),
	'alphaflagsdefended'	=> '+',
	'alphaflagsdefendedpct'	=> array( 'percent', 'alphaflagsdefended', 'flagsdefended' ),
	'alphaflagspickedup'	=> '+',
);

function PS_soldat(&$db) {
	parent::PS($db);
	$this->CLAN_MAP_MODTYPES = $this->CLAN_MODTYPES;
//	$this->soldat_remove_columns = array('headshotkills','headshotkillspct','accuracy','shotsperkill','damage');
	$this->soldat_remove_columns = array();
}

function add_map_player_list_mod($map, $setup = array()) {
	global $cms;
	$this->add_map_player_list('flagscaptured', $setup + array('label' => $cms->trans("Most Flags Captured")) );
	$this->add_map_player_list('flagsdefended', $setup + array('label' => $cms->trans("Most Flags Recovered")) );
}

// Add or remove columns from index.php listing
function index_table_mod(&$table) {
	global $cms;
	$table->remove_columns($this->soldat_remove_columns);
	$table->insert_columns(
		array( 
			'flagscaptured' => array( 'label' => $cms->trans('Flags'), 'tooltip' => $cms->trans("Flags captured") ), 
		),
		'onlinetime',
		false
	);
}

// Add or remove columns from maps.php listing
function maps_table_mod(&$table) {
	global $cms;
	$table->insert_columns(
		array( 
			'bravowonpct' => array( 'label' => $cms->trans('Wins'), 'tooltip' => $cms->trans("Bravo / Alpha Wins"), 'callback' => array(&$this, 'team_wins') ), 
		),
		'rounds',
		true
	);
}

function weapons_table_mod(&$table) {
	$table->remove_columns($this->soldat_remove_columns);
}
function weapon_players_table_mod(&$table) {
	$table->remove_columns($this->soldat_remove_columns);
}

function clans_table_mod(&$table) {
	$table->remove_columns($this->soldat_remove_columns);
}

function clan_weapons_table_mod(&$table) {
	$table->remove_columns($this->soldat_remove_columns);
}
function clan_players_table_mod(&$table) {
	$table->remove_columns($this->soldat_remove_columns);
}

function player_sessions_table_mod(&$table) {
	$table->remove_columns($this->soldat_remove_columns);
}
function player_weapons_table_mod(&$table) {
	$table->remove_columns($this->soldat_remove_columns);
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
			'won'			=> $cms->trans("Bravo / Alpha Wins"),
			'flagscaptured'		=> $cms->trans("Flags Captured"),
			'flagsdefended'		=> $cms->trans("Flags Recovered"),
		);
	}
	$tpl = 'player_left_column_mod';
	if ($theme->template_found($tpl, false)) {
		$actions = array();

		foreach (array_keys($strings) as $var) {
			$actions[$var] = array(
				'what'	=> $var,
				'label'	=> $strings[$var],
				'type'	=> 'dual_bar',
				'value'	=> array(
					'pct1'	 	=> $plr['bravo' . $var . 'pct'],
					'pct2'	 	=> $plr['alpha' . $var . 'pct'],
					'title1'	=> $plr['bravo' . $var] . ' ' . $cms->trans('Bravo') . ' (' . $plr['bravo' . $var . 'pct'] . '%)',
					'title2'	=> $plr['alpha' . $var] . ' ' . $cms->trans('Alpha') . ' (' . $plr['alpha' . $var . 'pct'] . '%)',
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
		'pct1'	=> $data['bravowonpct'], 
		'pct2'	=> $data['alphawonpct'],
		'title1'=> $data['bravowon'] . " " . $cms->trans("Bravo Wins") . " (" . $data['bravowonpct'] . "%)",
		'title2'=> $data['alphawon'] . " " . $cms->trans("Alpha Wins") . " (" . $data['alphawonpct'] . "%)",
		'color1'=> 'cc0000',
		'color2'=> '0000cc',
	));
	return $bar;
}

}

?>
