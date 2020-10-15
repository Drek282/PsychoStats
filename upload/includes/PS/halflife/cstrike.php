<?php
/**
	PS::halflife::cstrike
	$Id: cstrike.php 506 2008-07-02 14:29:49Z lifo $

	Halflife::cstrike mod support for PsychoStats front-end
*/
if (!defined("PSYCHOSTATS_PAGE")) die("Unauthorized access to " . basename(__FILE__));
if (defined("CLASS_PS_HALFLIFE_CSTRIKE_PHP")) return 1;
define("CLASS_PS_HALFLIFE_CSTRIKE_PHP", 1);

include_once(dirname(__DIR__) . '/halflife.php');

class PS_halflife_cstrike extends PS_halflife {

var $class = 'PS::halflife::cstrike';

var $CLAN_MODTYPES = array(
	'ctkills'		=> '+',
	'terroristkills'	=> '+',
	'ctdeaths'		=> '+',
	'terroristdeaths'	=> '+',
	'joinedct'		=> '+',
	'joinedterrorist'	=> '+',
	'joinedspectator'	=> '+',
	'bombdefuseattempts'	=> '+',
	'bombdefused'		=> '+',
	'bombdefusedpct'	=> array( 'percent', 'bombdefused', 'bombdefuseattempts' ),
	'bombplanted'		=> '+',
	'bombplantedpct'	=> array( 'percent', 'bombplanted', 'rounds' ),
	'bombexploded'		=> '+',
	'bombexplodedpct'	=> array( 'percent', 'bombexploded', 'bombplanted' ),
	'bombspawned'		=> '+',
	'bombrunner'		=> '+',
	'killedhostages'	=> '+',
	'touchedhostages'	=> '+',
	'rescuedhostages'	=> '+',
	'rescuedhostagespct'	=> array( 'percent', 'rescuedhostages', 'touchedhostages' ),
	'vip'			=> '+',
	'vipescaped'		=> '+',
	'vipkilled'		=> '+',
	'ctwon'			=> '+',
	'ctwonpct'		=> array( 'percent2', 'ctwon', 'terroristwon' ),
	'ctlost'		=> '+',
	'terroristwon'		=> '+',
	'terroristwonpct'	=> array( 'percent2', 'terroristwon', 'ctwon' ),
	'terroristlost'		=> '+',
);

function PS_halflife_cstrike(&$db) {
	parent::PS_halflife($db);
	$this->CLAN_MAP_MODTYPES = $this->CLAN_MODTYPES;
}

function get_clan_modtypes() {
	return $this->CLAN_MODTYPES;	
}

function get_clan_map_modtypes() {
	return $this->CLAN_MAP_MODTYPES;	
}

// extra map stats specific for the mod
function add_map_player_list_mod($map, $setup = array()) {
	global $cms;
	$prefix = substr($map['uniqueid'], 0, 3);
	if ($prefix == 'cs_') {
		$this->add_map_player_list('touchedhostages', $setup + array('label' => $cms->trans("Most Hostages Touched")) );
		$this->add_map_player_list('rescuedhostages', $setup + array('label' => $cms->trans("Most Hostages Rescued")) );
		$this->add_map_player_list('killedhostages',  $setup + array('label' => $cms->trans("Most Hostages Killed")) );
	} elseif ($prefix == 'de_') {
		$this->add_map_player_list('bombdefused',  $setup + array('label' => $cms->trans("Most Bombs Defused")) );
		$this->add_map_player_list('bombexploded', $setup + array('label' => $cms->trans("Most Bombs Exploded")) );
		$this->add_map_player_list('bombplanted',  $setup + array('label' => $cms->trans("Most Bombs Planted")) );
		$this->add_map_player_list('bombrunner',   $setup + array('label' => $cms->trans("Most Active Bomb Runner")) );
	} elseif ($prefix == 'as_') {
		$this->add_map_player_list('vip',        $setup + array('label' => $cms->trans("Most Often VIP")) );
		$this->add_map_player_list('vipescaped', $setup + array('label' => $cms->trans("Most VIP Escapes")) );
		$this->add_map_player_list('vipkilled',  $setup + array('label' => $cms->trans("Most VIP Kills")) );
	} 

}

// Add or remove columns from maps.php listing
function maps_table_mod(&$table) {
	global $cms;
	$table->insert_columns(
		array( 
			'ctwonpct' => array( 'label' => $cms->trans('Wins'), 'tooltip' => $cms->trans("Terr / CT Wins"), 'callback' => array(&$this, 'team_wins') ), 
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
		
		$joined = $plr['joinedterrorist'] + $plr['joinedct'];
		if ($joined) {
			$pct1 = sprintf('%0.02f', $plr['joinedterrorist'] / $joined * 100);
			$pct2 = sprintf('%0.02f', $plr['joinedct'] / $joined * 100);
		} else {
			$pct1 = $pct2 = 0;
		}

		$actions['joined'] = array(
			'label'	=> $cms->trans("T / CT Joins"),
			'type'	=> 'dual_bar',
			'value'	=> array(
				'pct1'	 	=> $pct1,
				'pct2'	 	=> $pct2,
				'title1'	=> $plr['joinedterrorist'] . ' ' . $cms->trans('Terrorists') . ' (' . $pct1 . '%)',
				'title2'	=> $plr['joinedct'] . ' ' . $cms->trans('CT') . ' (' . $pct2 . '%)',
				'color1'	=> 'cc0000',
				'color2'	=> '00cc00',
				'width'		=> 130
			)
		);

		$actions['won'] = array(
			'label'	=> $cms->trans("T / CT Wins"),
			'type'	=> 'dual_bar',
			'value'	=> array(
				'pct1'	 	=> $plr['terroristwonpct'],
				'pct2'	 	=> $plr['ctwonpct'],
				'title1'	=> $plr['terroristwon'] . ' ' . $cms->trans('Terrorist') . ' (' . $plr['terroristwonpct'] . '%)',
				'title2'	=> $plr['ctwon'] . ' ' . $cms->trans('CT') . ' (' . $plr['ctwonpct'] . '%)',
				'color1'	=> 'cc0000',
				'color2'	=> '00cc00',
				'width'		=> 130
			)
		);

		$actions['bombexploded'] = array(
			'label'	=> $cms->trans("Bombs Exploded"),
			'type'	=> 'dual_bar',
			'value'	=> array(
				'pct1'	 	=> $plr['bombexplodedpct'],
				'pct2'	 	=> $plr['bombplantedpct'],
				'title1'	=> $plr['bombexploded'] . ' ' . $cms->trans('exploded') . ' (' . $plr['bombexplodedpct'] . '%)',
				'title2'	=> $plr['bombplanted'] . ' ' . $cms->trans('planted'),
				'color1'	=> 'cc0000',
				'color2'	=> '00cc00',
				'width'		=> 130
			)
		);


		$actions['bombdefused'] = array(
			'label'	=> $cms->trans("Bombs Defused %"),
			'type'	=> 'pct_bar',
			'value'	=> array(
				'pct'	 	=> $plr['bombdefusedpct'],
				'title'		=> $plr['bombdefused'] . ' ' . $cms->trans('bombs defused') . ' (' . $plr['bombdefusedpct'] . '%)',
				'color1'	=> 'cc0000',
				'color2'	=> '00cc00',
				'width'		=> 130
			)
		);

		$actions['rescuedhostages'] = array(
			'label'	=> $cms->trans("Rescued Hostages"),
			'type'	=> 'pct_bar',
			'value'	=> array(
				'pct'	 	=> $plr['rescuedhostagespct'],
				'title'		=> $plr['rescuedhostages'] . ' ' . $cms->trans('hostages saved') . ' (' . $plr['rescuedhostagespct'] . '%)',
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
		$output = $theme->parse($tpl, false);
		$theme->assign('player_left_column_mod', $output);
	}
}

// used in maps.php as a callback for the wins of each team
function team_wins($value, $data) {
	global $cms;
	$bar = dual_bar(array(
		'pct1'	=> $data['terroristwonpct'], 
		'pct2'	=> $data['ctwonpct'],
		'title1'=> $data['terroristwon'] . " " . $cms->trans("Terrorist Wins") . " (" . $data['terroristwonpct'] . "%)",
		'title2'=> $data['ctwon'] . " " . $cms->trans("Counter-Terrorist Wins") . " (" . $data['ctwonpct'] . "%)",
	));
	return $bar;
}

} // end of ps::halflife::cstrike

?>
