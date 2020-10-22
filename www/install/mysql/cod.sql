CREATE TABLE `ps_map_data_cod` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `allieskills` smallint(5) unsigned NOT NULL default '0',
  `axiskills` smallint(5) unsigned NOT NULL default '0',
  `joinedallies` smallint(5) unsigned NOT NULL default '0',
  `joinedaxis` smallint(5) unsigned NOT NULL default '0',
  `joinedspectator` smallint(5) unsigned NOT NULL default '0',
  `allieswon` smallint(5) unsigned NOT NULL default '0',
  `allieslost` smallint(5) unsigned NOT NULL default '0',
  `axiswon` smallint(5) unsigned NOT NULL default '0',
  `axislost` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_data_cod` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `allieskills` smallint(5) unsigned NOT NULL default '0',
  `axiskills` smallint(5) unsigned NOT NULL default '0',
  `alliesdeaths` smallint(5) unsigned NOT NULL default '0',
  `axisdeaths` smallint(5) unsigned NOT NULL default '0',
  `joinedallies` smallint(5) unsigned NOT NULL default '0',
  `joinedaxis` smallint(5) unsigned NOT NULL default '0',
  `joinedspectator` smallint(5) unsigned NOT NULL default '0',
  `allieswon` smallint(5) unsigned NOT NULL default '0',
  `allieslost` smallint(5) unsigned NOT NULL default '0',
  `axiswon` smallint(5) unsigned NOT NULL default '0',
  `axislost` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_maps_cod` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `allieskills` smallint(5) unsigned NOT NULL default '0',
  `alliesdeaths` smallint(5) unsigned NOT NULL default '0',
  `axiskills` smallint(5) unsigned NOT NULL default '0',
  `axisdeaths` smallint(5) unsigned NOT NULL default '0',
  `joinedallies` smallint(5) unsigned NOT NULL default '0',
  `joinedaxis` smallint(5) unsigned NOT NULL default '0',
  `joinedspectator` smallint(5) unsigned NOT NULL default '0',
  `allieswon` smallint(5) unsigned NOT NULL default '0',
  `allieslost` smallint(5) unsigned NOT NULL default '0',
  `axiswon` smallint(5) unsigned NOT NULL default '0',
  `axislost` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
