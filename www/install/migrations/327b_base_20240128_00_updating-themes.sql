UPDATE `ps_config_themes`
    SET `source` = 'themes/default/theme.xml', `image` = 'default.webp'
    WHERE `name` = 'default';
UPDATE `ps_config_themes`
    SET `source` = 'themes/default-blue/theme.xml', `image` = 'blue.webp'
    WHERE `name` = 'default-blue';
