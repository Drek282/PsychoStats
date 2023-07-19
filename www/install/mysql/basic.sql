CREATE TABLE `ps_awards` (
  `id` int unsigned NOT NULL default '0',
  `awardid` int unsigned NOT NULL default '0',
  `awardtype` enum('player','weapon','weaponclass') NOT NULL default 'player',
  `awardname` varchar(128) NOT NULL default '',
  `awarddate` date NOT NULL,
  `awardrange` enum('month','week','day') NOT NULL default 'month',
  `awardweapon` varchar(64) default NULL,
  `awardcomplete` tinyint unsigned NOT NULL default '1',
  `interpolate` text,
  `topplrid` int unsigned NOT NULL default '0',
  `topplrvalue` varchar(16) NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `awardid` (`awardid`),
  KEY `awardrange` (`awardrange`,`awarddate`),
  KEY `awarddate` (`awarddate`),
  KEY `topplrid` (`topplrid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_awards_plrs` (
  `id` int unsigned NOT NULL default '0',
  `idx` tinyint unsigned NOT NULL default '0',
  `awardid` int unsigned NOT NULL default '0',
  `plrid` int unsigned NOT NULL default '0',
  `value` float NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `awardid` (`awardid`),
  KEY `plrid` (`plrid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_clan` (
  `clanid` int unsigned NOT NULL default '0',
  `clantag` varchar(32) NOT NULL default '',
  `locked` tinyint unsigned NOT NULL default '0',
  `allowrank` tinyint unsigned NOT NULL default '0',
  PRIMARY KEY  (`clanid`),
  UNIQUE KEY `clantag` (`clantag`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_clan_profile` (
  `clantag` varchar(32) NOT NULL default '',
  `name` varchar(128) default NULL,
  `logo` text,
  `email` varchar(128) default NULL,
  `icon` varchar(64) default NULL,
  `website` varchar(191) default NULL,
  `discord` varchar(191) default NULL,
  `twitch` varchar(191) default NULL,
  `youtube` varchar(191) default NULL,
  `steamprofile` varchar(191) default NULL,
  `cc` varchar(2) default NULL,
  PRIMARY KEY  (`clantag`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_config` (
  `id` int unsigned NOT NULL default '0',
  `conftype` varchar(20) NOT NULL default 'main',
  `section` varchar(70) default NULL,
  `var` varchar(100) default NULL,
  `value` text NOT NULL,
  `label` varchar(128) default NULL,
  `type` enum('none','text','textarea','checkbox','select','boolean') NOT NULL default 'text',
  `locked` tinyint unsigned NOT NULL default '0',
  `verifycodes` varchar(64) default NULL,
  `options` text,
  `help` text,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `conftype` (`conftype`,`section`,`var`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_config_awards` (
  `id` int unsigned NOT NULL default '0',
  `enabled` tinyint unsigned NOT NULL default '1',
  `idx` int NOT NULL,
  `type` enum('player','weapon','weaponclass') NOT NULL default 'player',
  `negative` tinyint unsigned NOT NULL default '0',
  `class` varchar(64) NOT NULL,
  `name` varchar(128) NOT NULL default '',
  `groupname` varchar(128) NOT NULL default '',
  `phrase` varchar(191) NOT NULL,
  `expr` varchar(191) NOT NULL default '',
  `order` enum('desc','asc') NOT NULL default 'desc',
  `where` varchar(191) NOT NULL default '',
  `limit` smallint unsigned NOT NULL default '1',
  `format` varchar(64) NOT NULL default '',
  `gametype` varchar(32) default NULL,
  `modtype` varchar(32) default NULL,
  `rankedonly` tinyint unsigned NOT NULL default '1',
  `description` text NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_config_clantags` (
  `id` int unsigned NOT NULL default '0',
  `idx` int unsigned NOT NULL default '0',
  `clantag` varchar(128) NOT NULL default '',
  `overridetag` varchar(64) NOT NULL default '',
  `pos` enum('left','right') NOT NULL default 'left',
  `type` enum('plain','regex') NOT NULL default 'plain',
  `example` varchar(64) NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `idx` (`type`,`idx`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_config_events` (
  `id` int unsigned NOT NULL,
  `gametype` varchar(191) NOT NULL,
  `modtype` varchar(191) NOT NULL,
  `eventname` varchar(64) NOT NULL,
  `alias` varchar(64) default NULL,
  `regex` varchar(191) NOT NULL,
  `idx` smallint NOT NULL default '0',
  `ignore` tinyint unsigned NOT NULL default '0',
  `codefile` varchar(191) default NULL,
  PRIMARY KEY  (`id`),
  KEY `idx` (`idx`),
  KEY `gametype` (`gametype`),
  KEY `modtype` (`modtype`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_config_overlays` (
  `id` int unsigned NOT NULL,
  `gametype` varchar(32) NOT NULL,
  `modtype` varchar(32) NOT NULL,
  `map` varchar(64) NOT NULL,
  `minx` smallint NOT NULL,
  `miny` smallint NOT NULL,
  `maxx` smallint NOT NULL,
  `maxy` smallint NOT NULL,
  `width` smallint unsigned NOT NULL,
  `height` smallint unsigned NOT NULL,
  `flipv` tinyint unsigned NOT NULL,
  `fliph` tinyint unsigned NOT NULL,
  `rotate` smallint NOT NULL default '0',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `gametype` (`gametype`,`modtype`,`map`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_config_logsources` (
  `id` int unsigned NOT NULL,
  `type` varchar(64) NOT NULL default 'file',
  `path` varchar(191) NOT NULL,
  `host` varchar(191) default NULL,
  `port` smallint unsigned default NULL,
  `passive` tinyint unsigned default NULL,
  `username` varchar(128) default NULL,
  `password` varchar(128) default NULL,
  `recursive` tinyint unsigned default NULL,
  `depth` tinyint unsigned default NULL,
  `skiplast` tinyint unsigned default NULL,
  `skiplastline` tinyint unsigned default NULL,
  `delete` tinyint unsigned default NULL,
  `options` text,
  `defaultmap` varchar(128) NOT NULL default 'unknown',
  `enabled` tinyint unsigned NOT NULL,
  `idx` int NOT NULL default '0',
  `lastupdate` int unsigned default NULL,
  PRIMARY KEY  (`id`),
  KEY `idx` (`idx`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_config_plrbans` (
  `id` int unsigned NOT NULL default '0',
  `bandate` int unsigned NOT NULL default '0',
  `enabled` tinyint unsigned NOT NULL default '1',
  `matchtype` enum('worldid','ipaddr','name') NOT NULL default 'worldid',
  `matchstr` varchar(128) NOT NULL default '',
  `reason` varchar(191) NOT NULL default '',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `match` (`matchtype`,`matchstr`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_config_plrbonuses` (
  `id` int unsigned NOT NULL default '0',
  `eventname` varchar(64) NOT NULL,
  `enactor` float NOT NULL default '0',
  `enactor_team` float NOT NULL default '0',
  `victim` float NOT NULL default '0',
  `victim_team` float NOT NULL default '0',
  `description` varchar(191) default NULL,
  `gametype` varchar(191) default NULL,
  `modtype` varchar(191) default NULL,
  PRIMARY KEY  (`id`),
  KEY `gametype` (`gametype`),
  KEY `modtype` (`modtype`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_config_servers` (
  `id` smallint unsigned NOT NULL,
  `host` varchar(191) NOT NULL,
  `port` smallint unsigned NOT NULL default '27015',
  `alt` varchar(191) default NULL,
  `querytype` varchar(32) NOT NULL,
  `rcon` varchar(64) default NULL,
  `cc` char(2) default NULL,
  `idx` smallint NOT NULL,
  `enabled` tinyint unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `hostport` (`host`,`port`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_config_themes` (
  `name` varchar(128) NOT NULL,
  `parent` varchar(128) default NULL,
  `enabled` tinyint unsigned NOT NULL,
  `version` varchar(32) NOT NULL default '1.0',
  `title` varchar(128) NOT NULL,
  `author` varchar(128) default NULL,
  `website` varchar(128) default NULL,
  `source` varchar(191) default NULL,
  `image` varchar(191) default NULL,
  `description` text,
  PRIMARY KEY  (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_heatmaps` (
  `heatid` int unsigned NOT NULL,
  `heatkey` char(40) character set ascii NOT NULL,
  `statdate` date NOT NULL,
  `enddate` date default NULL,
  `hour` tinyint unsigned default NULL,
  `who` enum('killer','victim','both') NOT NULL default 'victim',
  `mapid` smallint unsigned NOT NULL,
  `weaponid` smallint unsigned default NULL,
  `pid` int unsigned default NULL,
  `kid` int unsigned default NULL,
  `team` enum('CT','TERRORIST','BLUE','RED','RED_FORCE','BLUE_FORCE','ALLIES','AXIS','MARINES','ALIENS','BRITISH','AMERICANS') default NULL,
  `kteam` enum('CT','TERRORIST','BLUE','RED','RED_FORCE','BLUE_FORCE','ALLIES','AXIS','MARINES','ALIENS','BRITISH','AMERICANS') default NULL,
  `vid` int unsigned default NULL,
  `vteam` enum('CT','TERRORIST','BLUE','RED','RED_FORCE','BLUE_FORCE','ALLIES','AXIS','MARINES','ALIENS','BRITISH','AMERICANS') default NULL,
  `headshot` tinyint unsigned default NULL,
  `datatype` enum('blob','file') NOT NULL default 'blob',
  `datafile` varchar(191) default NULL,
  `datablob` mediumblob,
  `lastupdate` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`heatid`),
  UNIQUE KEY `heatkey` (`heatkey`,`statdate`,`enddate`,`hour`,`who`),
  KEY `mapid` (`mapid`,`heatkey`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_errlog` (
  `id` int unsigned NOT NULL default '0',
  `timestamp` int unsigned NOT NULL default '0',
  `severity` enum('info','warning','fatal') NOT NULL default 'info',
  `userid` int unsigned default NULL,
  `msg` text NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `timestamp` (`timestamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_geoip_cc` (
  `cc` char(2) NOT NULL,
  `cn` varchar(52) NOT NULL,
  PRIMARY KEY  (`cc`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_geoip_ip` (
  `cc` char(2) NOT NULL,
  `start` int unsigned NOT NULL,
  `end` int unsigned NOT NULL,
  PRIMARY KEY  (`start`,`end`),
  KEY `cc` (`cc`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_map` (
  `mapid` smallint unsigned NOT NULL default '0',
  `uniqueid` varchar(32) NOT NULL default '',
  `name` varchar(128) default NULL,
  PRIMARY KEY  (`mapid`),
  UNIQUE KEY `uniqueid` (`uniqueid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_map_data` (
  `dataid` int unsigned NOT NULL default '0',
  `mapid` smallint unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '2001-01-01',
  `games` smallint unsigned NOT NULL default '0',
  `rounds` smallint unsigned NOT NULL default '0',
  `kills` smallint unsigned NOT NULL default '0',
  `suicides` smallint unsigned NOT NULL default '0',
  `ffkills` smallint unsigned NOT NULL default '0',
  `connections` smallint unsigned NOT NULL default '0',
  `onlinetime` int unsigned NOT NULL default '0',
  `lasttime` int unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `mapid` (`mapid`,`statdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_map_hourly` (
  `dataid` int unsigned NOT NULL default '0',
  `mapid` smallint unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '2001-01-01',
  `hour` smallint unsigned NOT NULL default '0',
  `games` smallint unsigned NOT NULL default '0',
  `rounds` smallint unsigned NOT NULL default '0',
  `kills` smallint unsigned NOT NULL default '0',
  `connections` smallint unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `mapid` (`mapid`,`statdate`,`hour`),
  KEY `global` (`statdate`,`hour`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_map_spatial` (
  `mapid` smallint unsigned NOT NULL,
  `weaponid` smallint unsigned NOT NULL,
  `statdate` date NOT NULL,
  `hour` tinyint unsigned NOT NULL,
  `roundtime` smallint unsigned NOT NULL default '0',
  `kid` int unsigned NOT NULL,
  `kx` smallint NOT NULL,
  `ky` smallint NOT NULL,
  `kz` smallint NOT NULL,
  `kteam` enum('CT','TERRORIST','BLUE','RED','RED_FORCE','BLUE_FORCE','ALLIES','AXIS','MARINES','ALIENS','BRITISH','AMERICANS') default NULL,
  `vid` int unsigned NOT NULL,
  `vx` smallint NOT NULL,
  `vy` smallint NOT NULL,
  `vz` smallint NOT NULL,
  `vteam` enum('CT','TERRORIST','BLUE','RED','RED_FORCE','BLUE_FORCE','ALLIES','AXIS','MARINES','ALIENS','BRITISH','AMERICANS') default NULL,
  `headshot` tinyint unsigned NOT NULL,
  KEY `mapid` (`mapid`,`statdate`,`hour`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_live_entities` (
  `game_id` int unsigned NOT NULL,
  `ent_id` int NOT NULL,
  `ent_type` enum('unknown','player','bot','medkit','ammo','weapon','structure','turret','teleport') NOT NULL default 'unknown',
  `ent_name` varchar(191) NOT NULL,
  `ent_team` tinyint unsigned NOT NULL default '0',
  `onlinetime` int unsigned NOT NULL default '0',
  `kills` int NOT NULL default '0',
  `deaths` int unsigned NOT NULL default '0',
  `suicides` int unsigned NOT NULL default '0',
  PRIMARY KEY  (`game_id`,`ent_id`,`ent_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_live_events` (
  `game_id` int unsigned NOT NULL,
  `event_idx` int unsigned NOT NULL,
  `event_time` int unsigned NOT NULL,
  `event_type` enum('ROUND_START','ROUND_END','PLR_CONNECT','PLR_DISCONNECT','PLR_SPAWN','PLR_MOVE','PLR_KILL','PLR_TEAM','PLR_NAME','PLR_HURT','PLR_BOMB_PICKUP','PLR_BOMB_DROPPED','PLR_BOMB_PLANTED','PLR_BOMB_DEFUSED','PLR_BOMB_EXPLODED') NOT NULL,
  `ent_id` int default NULL,
  `ent_id2` int default NULL,
  `xyz` varchar(20) default NULL,
  `weapon` varchar(32) default NULL,
  `value` varchar(191) default NULL,
  `json` text,
  UNIQUE KEY `sequential` (`game_id`,`event_idx`),
  KEY `eventtypes` (`game_id`,`event_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_live_games` (
  `game_id` int unsigned NOT NULL auto_increment,
  `start_time` int unsigned NOT NULL,
  `end_time` int unsigned default NULL,
  `server_ip` int unsigned default NULL,
  `server_port` smallint unsigned default NULL,
  `server_name` varchar(191) default NULL,
  `game_name` varchar(191) default NULL,
  `gametype` varchar(32) NOT NULL,
  `modtype` varchar(32) default NULL,
  `map` varchar(64) default NULL,
  PRIMARY KEY  (`game_id`),
  KEY `start_time` (`start_time`,`end_time`),
  KEY `lastgame` (`server_ip`,`server_port`,`start_time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_plr` (
  `plrid` int unsigned NOT NULL default '0',
  `uniqueid` varchar(128) NOT NULL default '',
  `firstseen` int unsigned NOT NULL default '0',
  `clanid` int unsigned NOT NULL default '0',
  `rank` mediumint unsigned NOT NULL default '0',
  `prevrank` mediumint unsigned NOT NULL default '0',
  `skill` float NOT NULL default '0.00',
  `prevskill` float NOT NULL default '0.00',
  `activity` smallint NOT NULL default '0',
  `lastdecay` int unsigned NOT NULL default '0',
  `lastactivity` int unsigned NOT NULL default '0',
  `allowrank` tinyint unsigned NOT NULL default '1',
  PRIMARY KEY  (`plrid`),
  UNIQUE KEY `uniqueid` (`uniqueid`),
  KEY `allowrank` (`allowrank`,`clanid`),
  KEY `skill` (`skill`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_plr_aliases` (
  `id` int unsigned NOT NULL default '0',
  `uniqueid` varchar(128) NOT NULL default '',
  `alias` varchar(128) NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `uniqueid` (`uniqueid`),
  KEY `alias` (`alias`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_plr_bans` (
  `plrid` int unsigned NOT NULL,
  `ban_date` int unsigned NOT NULL default '0',
  `unban_date` int unsigned default NULL,
  `ban_reason` varchar(191) default NULL,
  `unban_reason` varchar(191) default NULL,
  KEY `plrid` (`plrid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_plr_data` (
  `dataid` int unsigned NOT NULL default '0',
  `plrid` int unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '2001-01-01',
  `dayskill` float NOT NULL default '0.00',
  `dayrank` int unsigned NOT NULL default '0',
  `connections` smallint unsigned NOT NULL default '0',
  `kills` smallint unsigned NOT NULL default '0',
  `deaths` smallint unsigned NOT NULL default '0',
  `headshotkills` smallint unsigned NOT NULL default '0',
  `ffkills` smallint unsigned NOT NULL default '0',
  `ffdeaths` smallint unsigned NOT NULL default '0',
  `kills_streak` smallint unsigned NOT NULL default '0',
  `deaths_streak` smallint unsigned NOT NULL default '0',
  `damage` int unsigned NOT NULL default '0',
  `shots` int unsigned NOT NULL default '0',
  `hits` int unsigned NOT NULL default '0',
  `suicides` smallint unsigned NOT NULL default '0',
  `games` smallint unsigned NOT NULL default '0',
  `rounds` smallint unsigned NOT NULL default '0',
  `kicked` smallint unsigned NOT NULL default '0',
  `banned` smallint unsigned NOT NULL default '0',
  `cheated` smallint unsigned NOT NULL default '0',
  `totalbonus` smallint NOT NULL default '0',
  `onlinetime` int unsigned NOT NULL default '0',
  `lasttime` int unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `plrid` (`plrid`,`statdate`),
  KEY `statdate` (`statdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_plr_ids_ipaddr` (
  `plrid` int unsigned NOT NULL default '0',
  `ipaddr` int unsigned NOT NULL default '0',
  `totaluses` int unsigned NOT NULL default '1',
  `firstseen` datetime NOT NULL,
  `lastseen` datetime NOT NULL,
  PRIMARY KEY  (`plrid`,`ipaddr`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_plr_ids_name` (
  `plrid` int unsigned NOT NULL default '0',
  `name` varchar(128) NOT NULL default '',
  `totaluses` int unsigned NOT NULL default '1',
  `firstseen` datetime NOT NULL,
  `lastseen` datetime NOT NULL,
  PRIMARY KEY  (`plrid`,`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_plr_ids_worldid` (
  `plrid` int unsigned NOT NULL default '0',
  `worldid` varchar(128) NOT NULL,
  `totaluses` int unsigned NOT NULL default '1',
  `firstseen` datetime NOT NULL,
  `lastseen` datetime NOT NULL,
  PRIMARY KEY  (`plrid`,`worldid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_plr_maps` (
  `dataid` int unsigned NOT NULL default '0',
  `plrid` int unsigned NOT NULL default '0',
  `mapid` int unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '2001-01-01',
  `games` smallint unsigned NOT NULL default '0',
  `rounds` smallint unsigned NOT NULL default '0',
  `kills` smallint unsigned NOT NULL default '0',
  `deaths` smallint unsigned NOT NULL default '0',
  `ffkills` smallint unsigned NOT NULL default '0',
  `ffdeaths` smallint unsigned NOT NULL default '0',
  `connections` smallint unsigned NOT NULL default '0',
  `onlinetime` int unsigned NOT NULL default '0',
  `lasttime` int unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `plrid` (`plrid`,`mapid`,`statdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_plr_profile` (
  `uniqueid` varchar(128) NOT NULL default '',
  `userid` int unsigned default NULL,
  `name` varchar(128) NOT NULL default '',
  `email` varchar(128) default NULL,
  `discord` varchar(191) default NULL,
  `twitch` varchar(191) default NULL,
  `youtube` varchar(191) default NULL,
  `socialclub` varchar(191) default NULL,
  `website` varchar(191) default NULL,
  `icon` varchar(64) default NULL,
  `cc` varchar(2) default NULL,
  `latitude` double default NULL,
  `longitude` double default NULL,
  `logo` text,
  `namelocked` tinyint unsigned NOT NULL default '0',
  PRIMARY KEY  (`uniqueid`),
  UNIQUE KEY `userid` (`userid`),
  KEY `name` (`name`),
  KEY `cc` (`cc`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_plr_roles` (
  `dataid` int unsigned NOT NULL default '0',
  `plrid` int unsigned NOT NULL default '0',
  `roleid` smallint unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '2001-01-01',
  `kills` smallint unsigned NOT NULL default '0',
  `deaths` smallint unsigned NOT NULL default '0',
  `headshotkills` smallint unsigned NOT NULL default '0',
  `shots` int unsigned NOT NULL default '0',
  `hits` int unsigned NOT NULL default '0',
  `damage` int unsigned NOT NULL default '0',
  `ffkills` smallint unsigned NOT NULL default '0',
  `joined` smallint unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  KEY `plrroles` (`plrid`,`roleid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_plr_sessions` (
  `dataid` int unsigned NOT NULL default '0',
  `plrid` int unsigned NOT NULL default '0',
  `mapid` int unsigned NOT NULL default '0',
  `sessionstart` int unsigned NOT NULL default '0',
  `sessionend` int unsigned NOT NULL default '0',
  `skill` float NOT NULL default '0.00',
  `prevskill` float NOT NULL default '0.00',
  `kills` smallint unsigned NOT NULL default '0',
  `deaths` smallint unsigned NOT NULL default '0',
  `headshotkills` smallint unsigned NOT NULL default '0',
  `ffkills` smallint unsigned NOT NULL default '0',
  `ffdeaths` smallint unsigned NOT NULL default '0',
  `damage` int unsigned NOT NULL default '0',
  `shots` smallint unsigned NOT NULL default '0',
  `hits` smallint unsigned NOT NULL default '0',
  `suicides` smallint unsigned NOT NULL default '0',
  `totalbonus` smallint NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  KEY `plrid` (`plrid`,`sessionstart`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_plr_victims` (
  `dataid` int unsigned NOT NULL default '0',
  `plrid` int unsigned NOT NULL default '0',
  `victimid` int unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '2001-01-01',
  `kills` smallint unsigned NOT NULL default '0',
  `deaths` smallint unsigned NOT NULL default '0',
  `headshotkills` smallint unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `plrid` (`plrid`,`victimid`,`statdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_plr_weapons` (
  `dataid` int unsigned NOT NULL default '0',
  `plrid` int unsigned NOT NULL default '0',
  `weaponid` smallint unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '2001-01-01',
  `kills` smallint unsigned NOT NULL default '0',
  `deaths` smallint unsigned NOT NULL default '0',
  `headshotkills` smallint unsigned NOT NULL default '0',
  `shots` int unsigned NOT NULL default '0',
  `hits` int unsigned NOT NULL default '0',
  `damage` int unsigned NOT NULL default '0',
  `ffkills` smallint unsigned NOT NULL default '0',
  `ffdeaths` smallint unsigned NOT NULL default '0',
  `shot_head` smallint unsigned NOT NULL default '0',
  `shot_chest` smallint unsigned NOT NULL default '0',
  `shot_stomach` smallint unsigned NOT NULL default '0',
  `shot_leftarm` smallint unsigned NOT NULL default '0',
  `shot_rightarm` smallint unsigned NOT NULL default '0',
  `shot_leftleg` smallint unsigned NOT NULL default '0',
  `shot_rightleg` smallint unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  KEY `plrweaps` (`plrid`,`weaponid`),
  KEY `statdate` (`statdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_plugins` (
  `plugin` varchar(64) NOT NULL,
  `version` varchar(32) NOT NULL,
  `enabled` tinyint unsigned NOT NULL default '0',
  `idx` smallint NOT NULL default '0',
  `installdate` int unsigned NOT NULL default '0',
  `description` text NOT NULL,
  PRIMARY KEY  (`plugin`),
  KEY `enabled` (`enabled`,`idx`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_role` (
  `roleid` smallint unsigned NOT NULL default '0',
  `uniqueid` varchar(32) NOT NULL default '',
  `name` varchar(128) default NULL,
  `team` varchar(16) default NULL,
  PRIMARY KEY  (`roleid`),
  UNIQUE KEY `uniqueid` (`uniqueid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_role_data` (
  `dataid` int unsigned NOT NULL default '0',
  `roleid` smallint unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '2001-01-01',
  `deaths` smallint unsigned NOT NULL default '0',
  `kills` smallint unsigned NOT NULL default '0',
  `ffkills` smallint unsigned NOT NULL default '0',
  `headshotkills` smallint unsigned NOT NULL default '0',
  `shots` int unsigned NOT NULL default '0',
  `hits` int unsigned NOT NULL default '0',
  `damage` int unsigned NOT NULL default '0',
  `joined` smallint unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `roleid` (`roleid`,`statdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_search_results` (
  `search_id` char(32) NOT NULL,
  `session_id` char(32) NOT NULL,
  `phrase` varchar(191) NOT NULL,
  `result_total` int unsigned NOT NULL default '0',
  `abs_total` int unsigned NOT NULL default '0',
  `results` text,
  `query` text,
  `updated` datetime NOT NULL,
  PRIMARY KEY  (`search_id`),
  KEY `session_id` (`session_id`),
  KEY `updated` (`updated`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_sessions` (
  `session_id` char(32) NOT NULL default '',
  `session_userid` int unsigned NOT NULL default '0',
  `session_start` int unsigned NOT NULL default '0',
  `session_last` int unsigned NOT NULL default '0',
  `session_ip` int unsigned NOT NULL default '0',
  `session_logged_in` tinyint NOT NULL default '0',
  `session_is_admin` tinyint unsigned NOT NULL default '0',
  `session_is_bot` tinyint unsigned NOT NULL default '0',
  `session_key` char(32) default NULL,
  `session_key_time` int unsigned default NULL,
  PRIMARY KEY  (`session_id`),
  KEY `session_userid` (`session_userid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_state` (
  `id` smallint unsigned NOT NULL default '0',
  `logsource` int unsigned NOT NULL,
  `lastupdate` int unsigned NOT NULL default '0',
  `timestamp` int unsigned NOT NULL default '0',
  `file` varchar(191) NOT NULL default '',
  `line` int unsigned NOT NULL default '0',
  `pos` int unsigned default NULL,
  `map` varchar(32) NOT NULL default '',
  `players` text NOT NULL,
  `ipaddrs` text NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `logsource` (`logsource`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_user` (
  `userid` int unsigned NOT NULL default '0',
  `username` varchar(64) NOT NULL default '',
  `password` varchar(32) NOT NULL default '',
  `session_last` int unsigned NOT NULL default '0',
  `session_login_key` varchar(8) default NULL,
  `lastvisit` int unsigned NOT NULL default '0',
  `accesslevel` tinyint NOT NULL default '1',
  `confirmed` tinyint unsigned NOT NULL default '0',
  PRIMARY KEY  (`userid`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_weapon` (
  `weaponid` smallint unsigned NOT NULL default '0',
  `uniqueid` varchar(32) NOT NULL default '',
  `name` varchar(128) default NULL,
  `skillweight` float default NULL,
  `class` varchar(32) default NULL,
  PRIMARY KEY  (`weaponid`),
  UNIQUE KEY `uniqueid` (`uniqueid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
CREATE TABLE `ps_weapon_data` (
  `dataid` int unsigned NOT NULL default '0',
  `weaponid` smallint unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '2001-01-01',
  `kills` int unsigned NOT NULL default '0',
  `ffkills` int unsigned NOT NULL default '0',
  `headshotkills` int unsigned NOT NULL default '0',
  `shots` int unsigned NOT NULL default '0',
  `hits` int unsigned NOT NULL default '0',
  `damage` int unsigned NOT NULL default '0',
  `shot_head` smallint unsigned NOT NULL default '0',
  `shot_chest` smallint unsigned NOT NULL default '0',
  `shot_stomach` smallint unsigned NOT NULL default '0',
  `shot_leftarm` smallint unsigned NOT NULL default '0',
  `shot_rightarm` smallint unsigned NOT NULL default '0',
  `shot_leftleg` smallint unsigned NOT NULL default '0',
  `shot_rightleg` smallint unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `weaponid` (`weaponid`,`statdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
