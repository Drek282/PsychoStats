<?php
/***
Plugin to add class and skill weight to weapons stats.
File: plugins/mod_csw.php

$Id$
***/

class mod_csw extends PsychoPlugin {
var $version = '1.0';
var $errstr = '';

function load(&$cms) {
$cms->register_filter($this, 'weapons_table_object');
return true;
}

function install(&$cms) {
$info = array();
$info['version'] = $this->version;
$info['description'] = "Plugin to add weapon class and weight to stats.";
return $info;
}

// weapons.php
function filter_weapons_table_object(&$table, &$cms, $args = array()) {
$table->insert_columns(
array(
'class' => array( 'label' => $cms->trans("Class"), 'modifier' => '%s' ),
'skillweight' => array( 'label' => $cms->trans("Weight"), 'modifier' => '%s' ),
),
'uniqueid', // current column to insert the new column(s) next to
true // true = after the column referenced; false = before
);
}

} // end of mod_csw


?>