/*
Version: $Id: 20230702_maps-API-key-commits.sql 001 2023-07-02 15:45:00Z lifo $

This code allows you to update PS MySQL DB with changes done on July 02, 2023 (Google Maps API key changes) without dropping and recreating your database.
Insert this code into PHPMyAdmin ^ONLY^ if you installed Psychostats before July 02, 2023.

*/

UPDATE `ps_config` SET `help` = 'Some features of the player stats require the use of a google API key. You can obtain a key by going to <a href=\"https://console.cloud.google.com/project/_/google/maps-apis/credentials\" target=\"_blank\">https://console.cloud.google.com/project/_/google/maps-apis/credentials</a>\r\n\r\nThe keys are no longer free, it is a paid service. Google offers $200 usage per month for free which is ~14,000 requests to Maps JavaScript API (subject to change).\r\n\r\nMore info about Maps API setup <a href=\"#\" target=\"_blank\">here</a>. For more info about pricing, visit <a href=\"https://mapsplatform.google.com/pricing/\" target=\"_blank\">https://mapsplatform.google.com/pricing/</a>.' WHERE `ps_config`.`id` = 2568;
UPDATE `ps_config` SET `value` = 'Google map settings. Configure how the google map within the player stats will appear.<br><span class=\"small\"><strong>Most Important:</strong> Configure your google map API key. Go to: <a href=\"https://console.cloud.google.com/google/maps-apis/start\" target=\"_blank\">https://console.cloud.google.com/google/maps-apis/start</a> for more information.</span>' WHERE `ps_config`.`id` = 5013;
