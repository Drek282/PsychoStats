@echo off
rem -- This file is for window's systems so a user can simply double-click 
rem -- on the icon to completely rescan their clantags. This is useful
rem -- after adding a new clantag to the configuration.
rem -----------------------------------------------------------------------

echo ** NOTE: Running this will cause all clans to be deleted and then re-scanned
echo ** for new clantags. All current clan profiles will NOT be deleted.
echo ** If you do not want to do this press CTRL-C now.
echo.

pause 

..\stats.pl -nologs -scanclantags all -debug

echo.
echo ** DONE SCANNING CLANTAGS!
echo.

pause