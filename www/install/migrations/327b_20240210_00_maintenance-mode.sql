INSERT INTO `ps_config` (`id`, `conftype`, `section`, `var`, `value`, `label`, `type`, `locked`, `verifycodes`, `options`, `help`)
    VALUES
        (5018,'main','maintenance_mode',NULL,'In maintenance mode only the overall header and the admin login will be displayed together with a status message.','Maintenance Mode','none',1,NULL,NULL,NULL),
        (2522,'main','maintenance_mode','enable','0','Enable maintenance mode?','boolean',0,NULL,'','');