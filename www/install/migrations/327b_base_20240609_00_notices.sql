INSERT INTO `ps_config` (`id`, `conftype`, `section`, `var`, `value`, `label`, `type`, `locked`, `verifycodes`, `options`, `help`)
    VALUES
        (5004,'main','notice',NULL,'Allows for a short notice message to be displayed which appears below header.','Notice','none',1,NULL,NULL,NULL),
        (2523,'main','notice','enable','0','Enable notice?','boolean',0,NULL,'',''),
        (2703,'main','notice','notice','','Notice','textarea',0,'','','This is the content of the Notice for PsychoStats for VRat.  You can edit this to create your own custom notice.  It uses html formatting.');