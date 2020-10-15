@echo off
rem -- This file is for window's systems so a user can simply double-click 
rem -- on the icon to perform daily calculations. 
rem -- Running this batch file *IS NOT REQUIRED*. The main stats.pl 
rem -- will also perform these updates automatically!! this file is just
rem -- an alternate way for users to force the daily calculations.
rem -----------------------------------------------------------------------

echo ** FORCING DAILY UPDATES! NO LOGS WILL BE PROCESSED
echo ** Double click on 'stats.pl' if you want to update your stats.
echo.


..\stats.pl -nologs -daily -verbose

echo.
echo ** DONE WITH DAILY CALCULATIONS!
echo.

rem -- remove the line below if you plan on scheduling this through Window's explorer.
echo (remove the @pause command from this file if you do not want it to pause here)
pause
