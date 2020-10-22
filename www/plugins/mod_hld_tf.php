<?php
/***
Plugin to remove hit location and damage from stats.
File: plugins/mod_hld_tf.php

$Id$
***/

class mod_hld_tf extends PsychoPlugin {
var $version = '1.0';
var $errstr = '';

function load(&$cms) {
$cms->register_filter($this, 'clan_weapons_table_object');
$cms->register_filter($this, 'players_table_object');
$cms->register_filter($this, 'player_weapon_table_object');
$cms->register_filter($this, 'player_session_table_object');
$cms->register_filter($this, 'player_role_table_object');
$cms->register_filter($this, 'player_history_table_object');
$cms->register_filter($this, 'roles_table_object');
$cms->register_filter($this, 'weapons_table_object');
return true;
}

function install(&$cms) {
$info = array();
$info['version'] = $this->version;
$info['description'] = "Plugin to remove hit location and damage from stats.";
return $info;
}

// clan.php
function filter_clan_weapons_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('accuracy','shotsperkill','damage'));
}

// index.php
function filter_players_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('accuracy','shotsperkill','damage'));
}

// player.php
function filter_player_weapon_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('accuracy','shotsperkill','damage'));
}

// player.php
function filter_player_session_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('accuracy'));
}

// player.php
function filter_player_role_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('accuracy','shotsperkill','damage'));
}

// plrhist.php
function filter_player_history_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('accuracy'));
}

// roles.php
function filter_roles_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('accuracy','shotsperkill','damage'));
}

// weapons.php
function filter_weapons_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('accuracy','shotsperkill','damage'));

/*$table->insert_columns(
array(
'class' => array( 'label' => $cms->trans("Class"), 'modifier' => '%s' ),
'skillweight' => array( 'label' => $cms->trans("Weight"), 'modifier' => '%s' ),
),
'uniqueid', // current column to insert the new column(s) next to
true // true = after the column referenced; false = before
);*/
}

} // end of mod_hld_tf


?>