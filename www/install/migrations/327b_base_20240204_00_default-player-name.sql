UPDATE `ps_config`
    SET `label` = 'Player name selection'
    WHERE `var` = 'plr_primary_name';
UPDATE `ps_config`
    SET `options` = 'size=16'
    WHERE `var` = 'calcskill_kill';
INSERT INTO `ps_config` (`id`, `conftype`, `section`, `var`, `value`, `label`, `type`, `locked`, `verifycodes`, `options`, `help`) 
    VALUES 
        (122,'main',NULL,'plr_default_name','Player','Player name default','text',0,NULL,'size=16','This is the default name for players when they join a server, usually \"Player\".  This name will be replaced with the next name choice, if there is one available, in the player lists and stats pages.  See \"Player name selection\".');