This is, yet another, unofficial version of PsychoStats by Stormtrooper. Updated to work with PHP 5.3.0+ and MySQL 4.1.13+.

Note that as of the release of version 3.2.7b on June 4, 2021, and specifically the change before that when the character encoding for the database was updated, if you do not drop and recreate your database, certain functionality will break.  You are strongly advised not to run the new web front end on an old database created before those changes were implemented.

This version was created without any input from Stormtrooper. The only objective was to get a pretty terrific piece of old software working again. I am Drek, the author of the updates. I am not a coder. I know enough about scripting to have managed most of the fixes without too much trouble.  However, I can make no guaratees about security or best practices.  Use this software at your own risk.

All of the versions on this repository and the game support repositories should be considered beta software as at this time I simply do not have the capacity or the access to game servers to thoroughly test releases.  I know that prior to 2010 PsychoStats was tested on thousands of websites with logs from thousands of game servers.  The base PsychoStats code should be robust and stable, and as stated, the changes I have made have been relatively minor, so it stands to reason that the code is still robust and stable, but again, I can make no guarantees.

There was one serious security vulnerability that I am aware of, and that has been fixed.  Most of the "fixes" were already present in the code, as Stormtrooper was aware of some of the changes to PHP that had, at that time, recently been made, or had been announced. Very little had to actually be rewritten, and where rewriting was required the changes were pretty minor. There were a number of minor syntax changes, especially with regards to mysqli.

Flag icon images and many map and overlay images have been converted from jpg and png to webp.  There is a slight decrease in image quality that I don't think is noticeable unless you are specifically looking for it, and know what to look for.  The trade off is that the webp images are much smaller, require less bandwidth and will load faster.

This version of PsychoStats currently supports the following games:  
***We are always looking for server logs to allow for testing and improved game support.***

* [The Battle Grounds III](https://github.com/Drek282/ps_bg3 "The Battle Grounds III")
* [Call of Duty 4X](https://github.com/Drek282/ps_cod4x "Call of Duty 4X")
* [Counter-Strike](https://github.com/Drek282/ps_cstrike "Counter-Strike")
* [Firearms 3.0](https://github.com/Drek282/ps_firearms "Firearms 3.0")
* [Natural Selection](https://github.com/Drek282/ps_natural "Natural Selection")
* [Team Fortress 2](https://github.com/Drek282/ps_tf2 "Team Fortress 2")
* [Team Fortress Classic](https://github.com/Drek282/ps_tfc "Team Fortress Classic")


There are also modules available for the following games, but they are untested and may not be functional:  
***If you wish to improve support for these games we will require server logs.***

* [Call of Duty 4](https://github.com/Drek282/ps_cod4 "Call of Duty 4")
* [Counter-Strike: Source](https://github.com/Drek282/ps_cstrikes "Counter-Strike: Source")
* [Day of Defeat](https://github.com/Drek282/ps_dod "Day of Defeat")
* [Day of Defeat: Source](https://github.com/Drek282/ps_dods "Day of Defeat: Source")
* [Gun Game](https://github.com/Drek282/ps_gungame "Gun Game")
* [Half-Life 2 Death Match](https://github.com/Drek282/ps_hl2dm "Half-Life 2 Death Match")
* [Half-Life Death Match](https://github.com/Drek282/ps_hldm "Half-Life Death Match")
* [Soldat](https://github.com/Drek282/ps_soldat "Soldat")

I have, for the most part, tried not to make changes that change the way Psychostats works, by default. However, I have made one or two changes that reflect my own personal biases, and for my own convenience when faced with reinstalling Psychostats hundreds of times as I worked on it. One of those is that the bonus for an ffkill is now -10.  I also believe that winning the game or round is really the entire point, far more important than k:d ratios, this is reflected in the bonuses I have added for team wins.

If you don't like those changes they are easy to edit in the Admin CP.  One other significant change is that I have changed the resolution for bonuses to one decimal place, so you can now create bonuses that are 0.1 etc.  There were bonuses for events that can happen very often, such as medic heals in TFC, that were too large if they were a full point or more.

Most of the links and references to psychostats.com have been removed as that domain is no longer actively maintained.  The only exception is the xml database that provides GeoIP data for the flags functionality. That appears to still be hosted. All of the references to Stormtrooper's email address have been removed.


## **Known Issues**

*The plan for the following issues is to either fix them, or improve them, in future versions:*

* Occassionally GeoIP assigns the wrong nationality to a player.

* One of the biggest problems with PsychoStats is that psychostats.com no longer hosts the documentation that it once did.

* The events for Firearms and Team Fortress Classic have not been thoroughly analyzed and tested.

* The team wins and losses for Firearms don't work extremely well or consistently.

* PsychoStats is decidedly **not** mobile friendly.

* The live server views can be flakey and unreliable.

* Mcrypt is deprecated/obsolete.

* Obsolete and deprecated php still to be cleaned up.

* Roles support for The Battle Grounds III is not yet complete.


## **Stuff that Remains Untested**

* The contents of the "addons" folder are largely unexplored with the exception of the AMX Mod X ps_heatmaps script, which does work.

* The contents of the "scripts" folder are also largely unexplored.  Most of them should be self explanatory but they should be considered untested.


## **Future Plans**

* Improve display on mobile devices.
* Copy the old PsychoStats wiki content from The Wayback Machine to the GitHub wiki.
* Full PHP 8.x compatibility.
* Clean up deprecated PHP.
* GDPR compliance.

## **Credits**

I want to express my admiration and gratitude to Jason Morriss, a.k.a. Stormtrooper, for all his work. This software deserves to be used. The period between 2000 and 2005 and all the old Half-Life and Source mods represent a golden age in pc game modding. Those games deserve to be played. With a little massaging most of them still run very well on new hardware and new operating systems.

Kudos to Valve as well for maintaining their back catalogue.

Credit to wakachamo, Rosenstein and Solomenka for their contributions.  Thanks also to RoboCop from APG and QuakerOates for their support and encouragement.
