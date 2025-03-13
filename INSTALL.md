# PsychoStats Installation


## BASICS

PsychoStats has two parts. The local Perl scripts in the 'root' folder that do all the database updates and stats processing. And the PHP 'www' files that
make up the web front end that allow you to view the stats web pages on your website.

The local 'root' files SHOULD NEVER BE located inside your website directory tree.  If you put it somewhere where the webserver can access it then any user on the Internet would be able to read your stats.cfg file and see your database
settings, user name and password. You have been warned.

You will need to download and install game support separately for each game.  If you wish to install support for multiple games each game will need it's own PsychoStats installation.  The game support is divided into two parts, just as the base PsychoStats software is.

To install game support you will need to paste the contents of the game "root" folder into the folder where you installed the contents of the base "root" folder and the contents of the game "www" folder into the folder where you installed the contents of the base "www" folder.

You should install the game files after you install the base files, and overwrite any files you are asked to.

You can find the game modules at the following links:

Tested and functional:  
***We are always looking for server logs to allow for testing and improved game support.***

* [The Battle Grounds III](https://github.com/Drek282/ps_bg3 "The Battle Grounds III")
* [Call of Duty 4X](https://github.com/Drek282/ps_cod4x "Call of Duty 4X")
* [Counter-Strike](https://github.com/Drek282/ps_cstrike "Counter-Strike")
* [Counter-Strike: Source](https://github.com/Drek282/ps_cstrikes "Counter-Strike: Source")
* [Firearms 3.0](https://github.com/Drek282/ps_firearms "Firearms 3.0")
* [Natural Selection](https://github.com/Drek282/ps_natural "Natural Selection")
* [Team Fortress Classic](https://github.com/Drek282/ps_tfc "Team Fortress Classic")

Untested and may not be functional:  
***If you wish to improve support for these games we will require server logs.***

* [Call of Duty 4](https://github.com/Drek282/ps_cod4 "Call of Duty 4")
* [Day of Defeat](https://github.com/Drek282/ps_dod "Day of Defeat")
* [Day of Defeat: Source](https://github.com/Drek282/ps_dods "Day of Defeat: Source")
* [Gun Game](https://github.com/Drek282/ps_gungame "Gun Game")
* [Half-Life Death Match](https://github.com/Drek282/ps_hldm "Half-Life Death Match")
* [Half-Life 2 Death Match](https://github.com/Drek282/ps_hl2dm "Half-Life 2 Death Match")
* [Soldat](https://github.com/Drek282/ps_soldat "Soldat")
* [Team Fortress 2](https://github.com/Drek282/ps_tf2 "Team Fortress 2")


## IF YOU ARE UPGRADING FROM A PREVIOUS VERSION OF PSYCHOSTATS

There is no option currenty for a graceful or automated upgrade.  Any database generated with any version of PsychoStats earlier than the released version of PsychoStats 3.2.7b will not work well with any more recent version.  This is because the old deprecated and soon to be obsolete MySQL character encoding has been changed from utf8_general_ci to utf8mb4_general_ci.

You can try going through your old database and changing the character encoding on all the tables, but you are probably better off to go with a fresh install where you drop and recreate the database.


## INSTALLATION

1. If you've already unzipped the archive you will have a directory structure that looks like this:

\addons  
\root  
\scripts  
\www  
changelog.txt  
INSTALL.md  
license.txt  
readme_*.txt  
README.md  

2. You will need a MySQL or MariaDB database, user and password.  If you don't know how to set that up, Google is your friend.  It's fairly simple and there is a lot of information on how to do that on the web.  Make sure your user has full permissions on the database.

You should not use a database super user for PsychoStats, especially in a production context.  You should create a user then create a database, then make sure your user has full permissions on that database, before you run the installer.  However, for test purposes, in a local network environment you can use a database user with general permissions.  However you will still have to create the database and enter that database name into the config.php file before you run the installer, as well as add your database user name and password to the config.php file.

3. You will need to have Perl installed to run the stats generation scripts, and your web hosting will need to have PHP installed on it.  Note that there are a number of Perl and PHP modules that you will need to have installed.  Again, Google is your friend, if you encounter errors when you try to run the web install, or when you run the stats generation scripts, those errors should let you know what you need to install.

The minimum required version of Perl is `5.08`, of PHP is `7.1.0` and MySQL*/MariaDB is `5.5.0`.  Required Perl modules include the `Digest-MD5`, `Digest-SHA1` and `GD` modules.

\* **Oracle's MySQL `8.0+` is *NOT* supported.  Please use [MariaDB](https://mariadb.org/ "MariaDB") instead.**

4. These instructions assume you already know the basics of hosting a website The specifics of that will depend on your hosting arrangements.  Copy the contents of the 'www' directory to your web hosting folder.  You can install the front end files from the "www" folder in a subfolder, but only one level deep.  It is recommended that you don't expose your PsychoStats web folder to the public web until you have completed the install process.

5. Browse to the stats installation wizard with your browser and follow the instructions:  
	http[s]://[domain|localhost]/[stats folder name if any]/install/

### IMPORTANT

The install process should automatically delete your install folder when it is completed.  If that doesn't happen you will get the following error message, "PsychoStats hasn't been properly installed, please see INSTALL.md for details."  If that happens you will need to delete the install folder manually.  If you need to run the install process again you will need to upload the install folder to your PsychoStats web folder again.

6. The 'root' folder contains the heart of your PsychoStats.  You can put that anywhere as long as your user has executable permissions and you can access your PsychoStats database from that machine.  The contents of the 'root' folder should not be exposed to the web.  Edit the 'stats.cfg' in that folder with your PsychoStats database name, user and password.

### Security Notes

Common sense is your best protection.  You want a distinct PsychoStats database user that only has permissions on the PsychoStats database.  Never run the PsychoStats scripts as root in Linux.  Never use your MySQL/MariaDB super user as your PsychoStats user.

The config.php and stats.cfg files contain your PsychoStats database user name and password, once the config.php file has been written in the install process, and once you've entered your information into the stats.cfg files, the respective users only need read access to them.

No other user should have permissions to read them, let alone write or execute them.

It is also our recommendation that, at this time, user registration should not be be enabled on installations that are exposed to the public web.  PsychoStats isn't vulnerable from a security standpointl but it has no defense against spam registrations.

7. The stats are generated by running the 'stats.pl' script.  Once you have done that, if you have heatmap support for your game/mod, you can run the heat.pl script to generate the heatmaps.  For specific instructions regarding the heatmaps see readme_heatmaps.txt or run the heat.pl script with the --help switch.

### A Note Regarding the Cookie Consent, a.k.a. GDPR and CCPA Compliance

If you choose to enable Cookie Consent, in the Admin CP Security section, by default no cookies will be saved in users browsers. Cookies must be accepted for them to be saved by users in their browsers. If cookeies are not accepted users will be able to browse the stats but they will not be able to register or log into an account if those options are enabled or if they already have an account. They will also not be able to log into an admin account if they have one, or choose anything other than the default theme or language support.
