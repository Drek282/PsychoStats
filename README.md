This is, yet another, unofficial version of PsychoStats by Stormtrooper. Updated to work with PHP 5.3.0+ and MySQL 4.1.13+.

This version has been tested on PHP 7.4.11 and MariaDB 10.3.24 but it should work on any version of PHP and MySQL from 5.3.0 and 4.1.13, respectively, up to PHP 7.x.x.  ***This version of PsychoStats is not yet compatible with PHP 8.x.x.***

Note that as of the release of version 3.2.7b on June 4, 2021, and specifically the change before that when the character encoding for the database was updated, if you do not drop and recreate your database, certain functionality will break.  You are strongly advised not to run the new web front end on an old database created before those changes were implemented.

This version was created without any input from Stormtrooper. The only objective was to get a pretty terrific piece of old software working again. I am Drek, the author of the updates. I am not a coder. I know enough about scripting to have managed most of the fixes without too much trouble.  However, I can make no guaratees about security or best practices.  Use this software at your own risk.

All of the versions on this repository should be considered beta software as at this time I simply do not have the capacity or the access to game servers to thoroughly test releases.  I know that prior to 2010 PsychoStats was tested on thousands of websites with logs from thousands of game servers.  The base PsychoStats code should be robust and stable, and as stated, the changes I have made have been relatively minor, so it stands to reason that the code is still robust and stable, but again, I can make no guarantees.

There was one serious security vulnerability that I am aware of, and that has been fixed.  Most of the "fixes" were already present in the code, as Stormtrooper was aware of some of the changes to PHP that had, at that time, recently been made, or had been announced. Very little had to actually be rewritten, and where rewriting was required the changes were pretty minor. There were a number of minor syntax changes, especially with regards to mysqli.

Flag icon images and many map and overlay images have been converted from jpg and png to webp.  There is a slight decrease in image quality that I don't think is noticeable unless you are specifically looking for it, and know what to look for.  The trade off is that the webp images are much smaller, require less bandwidth and will load faster.

This version of PsychoStats currently supports the following Half-Life and Source mods and games as well as Soldat:

* The Battle Grounds III
* Counter-Strike
* Counter-Strike: Source
* Day of Defeat
* Day of Defeat: Source
* Firearms 3.0
* Gun Game
* Half-Life 2 Death Match
* Half-Life Death Match
* Natural Selection
* Team Fortress 2
* Team Fortress Classic

I have, for the most part, tried not to make changes that change the way Psychostats works, by default. However, I have made one or two changes that reflect my own personal biases, and for my own convenience when faced with reinstalling Psychostats hundreds of times as I worked on it. One of those is that the bonus for an ffkill is now -10.  I also believe that winning the game or round is really the entire point, far more important than k:d ratios, this is reflected in the bonuses I have added for team wins.

If you don't like those changes they are easy to edit in the Admin CP.  One other significant change is that I have changed the resolution for bonuses to one decimal place, so you can now create bonuses that are 0.1 etc.  There were bonuses for events that can happen very often, such as medic heals in TFC, that were too large if they were a full point or more.

Most of the links and references to psychostats.com have been removed as that domain is no longer actively maintained.  The only exception is the xml database that provides GeoIP data for the flags functionality. That appears to still be hosted. All of the references to Stormtrooper's email address have been removed.


## **Known Issues**

* Occassionally GeoIP assigns the wrong nationality to a player. There is nothing that can be done about this.

*The plan for the following issues is to either fix them, or improve them, in future versions:*

* One of the biggest problems with PsychoStats is that psychostats.com no longer hosts the documentation that it once did.

* The events for Firearms and Team Fortress Classic have not been thoroughly analyzed and tested. Some of them are definitely not working as hoped and expected, many of them are, but some are unknowns, especially in TFC. The reasons for this range from my not understanding the events themselves, and what they mean, because I'm not an experienced TFC player, to my weak coding skills, especially with Perl.

* The team wins and losses for Firearms, as well as rounds, don't work extremely well or consistently.  But I'm not sure there is much more I can do with them.  The log support for wins and losses in Firearms just isn't very good.  At this time there is no bonus support for Firearms wins and losses, and no way of changing that that I am aware of.

* PsychoStats is decidedly **not** mobile friendly.

* The live server views can be flakey and unreliable.

* Mcrypt is deprecated/obsolete.

* Some obsolete and deprecated html and php still to be cleaned up.

* Roles support for The Battle Grounds III is not yet complete.


## **Stuff that Remains Untested**

* Most of the mods have not been tested, although my guess is that most of them will work.  The only games and mods that have been tested so far are The Battle Grounds III, Firearms 3.0, Natural Selection and Team Fortress Classic.

* The contents of the "scripts" folder are largely unexplored.  Most of them should be self explanatory but they should be considered untested.


## **Future Plans**

* Considering dropping COD support altogether.
* Improve display on mobile devices.
* I have found the old PsychoStats documentation on The Wayback Machine, thanks to QuakerOates.  I will be spending some time with that and seeing how it directs further efforts to restore and update functionality.  I will also be looking into transferring the old PsychoStats Wiki to GitHub.
* Full PHP 8.x compatibility.
* Clean up deprecated PHP.
* Clean up obsolete and deprecated html.

## **Credits**

I want to express my admiration and gratitude to Jason Morriss, a.k.a. Stormtrooper, for all his work. This software deserves to be used. The period between 2000 and 2005 and all the old Half-Life and Source mods represent a golden age in pc game modding. Those games deserve to be played. With a little massaging most of them still run very well on new hardware and new operating systems.

Kudos to Valve as well for maintaining their back catalogue.

Credit to wakachamo, Rosenstein and Solomenka for their contributions.  Thanks also to RoboCop from APG and QuakerOates for their support and encouragement.
