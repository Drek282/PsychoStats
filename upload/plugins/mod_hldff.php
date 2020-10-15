<?php
/***
Plugin to remove hit location, damage, and friendly fire kills from stats.
File: plugins/mod_hldff.php

$Id$
***/

class mod_hldff extends PsychoPlugin {
var $version = '1.0';
var $errstr = '';

function load(&$cms) {
$cms->register_filter($this, 'clan_members_table_object');
$cms->register_filter($this, 'clan_weapons_table_object');
$cms->register_filter($this, 'clan_maps_table_object');
$cms->register_filter($this, 'clans_table_object');
$cms->register_filter($this, 'players_table_object');
$cms->register_filter($this, 'maps_table_object');
$cms->register_filter($this, 'player_weapon_table_object');
$cms->register_filter($this, 'player_session_table_object');
$cms->register_filter($this, 'player_map_table_object');
$cms->register_filter($this, 'player_role_table_object');
$cms->register_filter($this, 'player_history_table_object');
$cms->register_filter($this, 'roles_table_object');
$cms->register_filter($this, 'weapons_table_object');
return true;
}

function install(&$cms) {
$info = array();
$info['version'] = $this->version;
$info['description'] = "Plugin to remove hit location, damage, and friendly fire kills from stats.";
return $info;
}

// clan.php
function filter_clan_members_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('headshotkills','headshotkillspct'));
}

// clan.php
function filter_clan_weapons_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('headshotkills','headshotkillspct','accuracy','shotsperkill','damage'));
}

// clan.php
function filter_clan_maps_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('ffkills'));
}

// clans.php
function filter_clans_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('headshotkills','headshotkillspct'));
}

// index.php
function filter_players_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('headshotkills','headshotkillspct','accuracy','shotsperkill','damage'));
}

// maps.php
function filter_maps_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('ffkills'));
}

// player.php
function filter_player_weapon_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('headshotkills','headshotkillspct','accuracy','shotsperkill','damage'));
}

// player.php
function filter_player_session_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('headshotkills','headshotkillspct','accuracy'));
}

// player.php
function filter_player_map_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('ffkills'));
}

// player.php
function filter_player_role_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('headshotkills','headshotkillspct','accuracy','shotsperkill','damage'));
}

// plrhist.php
function filter_player_history_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('headshotkills','headshotkillspct','accuracy'));
}

// roles.php
function filter_roles_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('headshotkills','headshotkillspct','ffkills','ffkillspct','accuracy','shotsperkill','damage'));
}

// weapons.php
function filter_weapons_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('headshotkills','headshotkillspct','ffkills','ffkillspct','accuracy','shotsperkill','damage'));

/*$table->insert_columns(
array(
'class' => array( 'label' => $cms->trans("Class"), 'modifier' => '%s' ),
'skillweight' => array( 'label' => $cms->trans("Weight"), 'modifier' => '%s' ),
),
'uniqueid', // current column to insert the new column(s) next to
true // true = after the column referenced; false = before
);*/
}

} // end of mod_hldff


?>