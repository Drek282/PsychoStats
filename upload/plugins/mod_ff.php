<?php
/***
Plugin to remove friendly fire kills from stats.
File: plugins/mod_ff.php

$Id$
***/

class mod_ff extends PsychoPlugin {
var $version = '1.0';
var $errstr = '';

function load(&$cms) {
$cms->register_filter($this, 'clan_maps_table_object');
$cms->register_filter($this, 'maps_table_object');
$cms->register_filter($this, 'player_map_table_object');
$cms->register_filter($this, 'roles_table_object');
$cms->register_filter($this, 'weapons_table_object');
return true;
}

function install(&$cms) {
$info = array();
$info['version'] = $this->version;
$info['description'] = "Plugin to remove friendly fire kills from stats.";
return $info;
}

// clan.php
function filter_clan_maps_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('ffkills'));
}

// maps.php
function filter_maps_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('ffkills'));
}

// player.php
function filter_player_map_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('ffkills'));
}

// roles.php
function filter_roles_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('ffkills','ffkillspct'));
}

// weapons.php
function filter_weapons_table_object(&$table, &$cms, $args = array()) {
$table->remove_columns(array('ffkills','ffkillspct'));

/*$table->insert_columns(
array(
'class' => array( 'label' => $cms->trans("Class"), 'modifier' => '%s' ),
'skillweight' => array( 'label' => $cms->trans("Weight"), 'modifier' => '%s' ),
),
'uniqueid', // current column to insert the new column(s) next to
true // true = after the column referenced; false = before
);*/
}

} // end of mod_ff


?>