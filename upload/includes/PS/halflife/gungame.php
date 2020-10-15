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
 *	Version: $Id: halflife.php 457 2008-05-21 16:23:14Z lifo $
 *
 *	$Id$
 *
 *	PS::halflife::gungame
 *	Halflife::gungame mod support for PsychoStats front-end
 */
if (!defined("PSYCHOSTATS_PAGE")) die("Unauthorized access to " . basename(__FILE__));
if (defined("CLASS_PS_HALFLIFE_GUNGAME_PHP")) return 1;
define("CLASS_PS_HALFLIFE_GUNGAME_PHP", 1);

include_once(dirname(__DIR__) . '/halflife/cstrike.php');

class PS_halflife_gungame extends PS_halflife_cstrike {

var $class = 'PS::halflife::gungame';

// do not declare the variables array since we're using the existing array
// from the cstrike subclass.
//var $CLAN_MODTYPES = array();

function PS_halflife_gungame(&$db) {
	parent::PS_halflife_cstrike($db);
	// add gungame specific variables to the list
	$this->CLAN_MODTYPES += array(
		'lvlsgained'		=> '+',
		'lvlslost'		=> '+',
		'lvlsstolen'		=> '+',
		'lvlsgiven'		=> '+',
		'winsgained'		=> '+',
		'winsgiven'		=> '+',
		'killsperlvl'		=> array( 'ratio', 'kills', 'lvlsgained' ),
		'killsperwin'		=> array( 'ratio', 'kills', 'winsgained' ),
		'lvlsperwin'		=> array( 'ratio', 'lvlsgained', 'winsgained' ),
		'winsgainedpct'		=> array( 'percent', 'winsgained', 'games' ),
		'lvlsperminute'		=> array( 'ratio_minutes', 'lvlsgained', 'onlinetime' ),
		'lvlspergame'		=> array( 'ratio', 'lvlsgained', 'games' ),
	);
	$this->CLAN_MAP_MODTYPES = $this->CLAN_MODTYPES;
}

// extra map stats specific for the mod
function add_map_player_list_mod($map, $setup = array()) {
	global $cms;
	parent::add_map_player_list_mod($map, $setup);
	
	$prefix = substr($map['uniqueid'], 0, 3);
	if ($prefix == 'gg_') {
#		$this->add_map_player_list('touchedhostages', $setup + array('label' => $cms->trans("Most Hostages Touched")) );
	} 
}

// Add or remove columns from maps.php listing
function maps_table_mod(&$table) {
	global $cms;
	//$table->insert_columns(
	//	array( 
	//		'ctwonpct' => array( 'label' => $cms->trans('Wins'), 'tooltip' => $cms->trans("Terr / CT Wins"), 'callback' => array(&$this, 'team_wins') ), 
	//	),
	//	'rounds',
	//	true
	//);
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
	parent::player_left_column_mod($plr, $theme);
	$tpl = 'player_left_column_mod';
	if ($theme->template_found($tpl, false)) {
		$actions = $theme->get_template_vars('mod_actions');
		if (!is_array($actions)) {
			$actions = array();
		}
		
		// remove actions that are not relevant to GG
		//unset($actions['bombexploded'], $actions['bombdefused'],$actions['rescuedhostages'],...);
		
		$actions['winsgained'] = array(
			'label'	=> $cms->trans("Wins Gained"),
			'type'	=> 'pct_bar',
			'value'	=> array(
				'pct'	 	=> $plr['winsgainedpct'],
				'title'		=> $plr['winsgained'] . ' ' . $cms->trans('wins gained') . ' (' . $plr['winsgainedpct'] . '%)',
				'color1'	=> 'cc0000',
				'color2'	=> '00cc00',
				'width'		=> 130
			)
		);

		$cms->filter('left_column_actions', $actions);
		foreach (array_keys($actions) as $i) {
			// if the value is not an array then it's been processed
			// already from the sub-class.
			if (!is_array($actions[$i]['value'])) {
				continue;
			}
			if ($actions[$i]['type'] == 'dual_bar') {
				$actions[$i]['value'] = dual_bar( $actions[$i]['value'] );
			} else {
				$actions[$i]['value'] = pct_bar( $actions[$i]['value'] );
			}
		}
		
		$theme->assign(array(
			'mod_actions' => $actions,
//			'mod_actions_title' => $cms->trans("Team / Action Profile"),
		));
		$output = $theme->parse($tpl, false);
		$theme->assign('player_left_column_mod', $output);
	}
}

} // end of ps::halflife::gungame

?>
