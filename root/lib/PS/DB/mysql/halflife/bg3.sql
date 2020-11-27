CREATE TABLE `ps_map_data_halflife_bg3` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `britishkills` smallint(5) unsigned NOT NULL default '0',
  `americanskills` smallint(5) unsigned NOT NULL default '0',
  `joinedbritish` smallint(5) unsigned NOT NULL default '0',
  `joinedamericans` smallint(5) unsigned NOT NULL default '0',
  `joinedspectator` smallint(5) unsigned NOT NULL default '0',
  `britishwon` smallint(5) unsigned NOT NULL default '0',
  `britishlost` smallint(5) unsigned NOT NULL default '0',
  `americanswon` smallint(5) unsigned NOT NULL default '0',
  `americanslost` smallint(5) unsigned NOT NULL default '0',
  `britishflagscaptured` smallint(5) unsigned NOT NULL default '0',
  `americansflagscaptured` smallint(5) unsigned NOT NULL default '0',
  `flagscaptured` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_data_halflife_bg3` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `britishkills` smallint(5) unsigned NOT NULL default '0',
  `americanskills` smallint(5) unsigned NOT NULL default '0',
  `britishdeaths` smallint(5) unsigned NOT NULL default '0',
  `americansdeaths` smallint(5) unsigned NOT NULL default '0',
  `joinedbritish` smallint(5) unsigned NOT NULL default '0',
  `joinedamericans` smallint(5) unsigned NOT NULL default '0',
  `joinedspectator` smallint(5) unsigned NOT NULL default '0',
  `britishwon` smallint(5) unsigned NOT NULL default '0',
  `britishlost` smallint(5) unsigned NOT NULL default '0',
  `americanswon` smallint(5) unsigned NOT NULL default '0',
  `americanslost` smallint(5) unsigned NOT NULL default '0',
  `britishflagscaptured` smallint(5) unsigned NOT NULL default '0',
  `americansflagscaptured` smallint(5) unsigned NOT NULL default '0',
  `flagscaptured` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_maps_halflife_bg3` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `britishkills` smallint(5) unsigned NOT NULL default '0',
  `americanskills` smallint(5) unsigned NOT NULL default '0',
  `britishdeaths` smallint(5) unsigned NOT NULL default '0',
  `americansdeaths` smallint(5) unsigned NOT NULL default '0',
  `joinedbritish` smallint(5) unsigned NOT NULL default '0',
  `joinedamericans` smallint(5) unsigned NOT NULL default '0',
  `joinedspectator` smallint(5) unsigned NOT NULL default '0',
  `britishwon` smallint(5) unsigned NOT NULL default '0',
  `britishlost` smallint(5) unsigned NOT NULL default '0',
  `americanswon` smallint(5) unsigned NOT NULL default '0',
  `americanslost` smallint(5) unsigned NOT NULL default '0',
  `britishflagscaptured` smallint(5) unsigned NOT NULL default '0',
  `americansflagscaptured` smallint(5) unsigned NOT NULL default '0',
  `flagscaptured` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_roles_halflife_bg3` (
  `dataid` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_role_data_halflife_bg3` (
  `dataid` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
