CREATE TABLE `ps_map_data_cod` (
  `dataid` int unsigned NOT NULL default '0',
  `allieskills` smallint unsigned NOT NULL default '0',
  `axiskills` smallint unsigned NOT NULL default '0',
  `joinedallies` smallint unsigned NOT NULL default '0',
  `joinedaxis` smallint unsigned NOT NULL default '0',
  `joinedspectator` smallint unsigned NOT NULL default '0',
  `allieswon` smallint unsigned NOT NULL default '0',
  `allieslost` smallint unsigned NOT NULL default '0',
  `axiswon` smallint unsigned NOT NULL default '0',
  `axislost` smallint unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;
CREATE TABLE `ps_plr_data_cod` (
  `dataid` int unsigned NOT NULL default '0',
  `allieskills` smallint unsigned NOT NULL default '0',
  `axiskills` smallint unsigned NOT NULL default '0',
  `alliesdeaths` smallint unsigned NOT NULL default '0',
  `axisdeaths` smallint unsigned NOT NULL default '0',
  `joinedallies` smallint unsigned NOT NULL default '0',
  `joinedaxis` smallint unsigned NOT NULL default '0',
  `joinedspectator` smallint unsigned NOT NULL default '0',
  `allieswon` smallint unsigned NOT NULL default '0',
  `allieslost` smallint unsigned NOT NULL default '0',
  `axiswon` smallint unsigned NOT NULL default '0',
  `axislost` smallint unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;
CREATE TABLE `ps_plr_maps_cod` (
  `dataid` int unsigned NOT NULL default '0',
  `allieskills` smallint unsigned NOT NULL default '0',
  `alliesdeaths` smallint unsigned NOT NULL default '0',
  `axiskills` smallint unsigned NOT NULL default '0',
  `axisdeaths` smallint unsigned NOT NULL default '0',
  `joinedallies` smallint unsigned NOT NULL default '0',
  `joinedaxis` smallint unsigned NOT NULL default '0',
  `joinedspectator` smallint unsigned NOT NULL default '0',
  `allieswon` smallint unsigned NOT NULL default '0',
  `allieslost` smallint unsigned NOT NULL default '0',
  `axiswon` smallint unsigned NOT NULL default '0',
  `axislost` smallint unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;
