@echo off
rem -- This file is for window's systems so a user can simply double-click 
rem -- on the icon to update their stats. This causes the dos window to pause
rem -- and not close instantly incase there are errors.
rem -----------------------------------------------------------------------

..\stats.pl --version
echo.
..\stats.pl -verbose
echo.

pause
