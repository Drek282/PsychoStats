This is, yet another, unofficial version of PsychoStats by Stormtrooper. Updated to work with PHP 5.3.0+ and MySQL 4.1.13+.

This version has been tested on PHP 7.4.11 and MariaDB 10.3.24 but it should work on any version of PHP and MySQL from 5.3.0 and 4.1.13, respectively, up.

This version was created without any input from Stormtrooper. The only objective was to get a pretty terrific piece of old software working again. I am Drek, the author of the updates. I am not a coder. I know enough abut scripting to have managed most of the fixes without too much trouble. However, I cannot guarantee that any of it is secure, or best practices. I am running it on my own clan website without any issues that I am aware of, but I can make no guarantees. Use this software at your own risk.

All of the versions on this repository should be considered beta software as at this time I simply do not have the capacity or the access to game servers to thoroughly test releases.  I know that prior to 2010 PsychoStats was tested on thousands of websites with logs from thousands of game servers.  The base PsychoStats code should be robust and stable, and as stated, the changes I have made have been relatively minor, so it stands to reason that the code is still robust and stable, but again, I can make no guarantees, so use this software at your own risk.

There was one serious security vulnerability that I am aware of, and that has been fixed.  Most of the "fixes" were already present in the code, as Stormtrooper was aware of some of the changes to PHP that had, at that time, recently been made, or had been announced. Very little had to actually be rewritten, and where rewriting was required the changes were pretty minor. There were a few minor changes with regard to mysqli syntax. As far as I am aware all the obsolete and deprecated code has been fixed.

This version of PsychoStats currently supports the following Half-Life and Source mods and games as well as Call of Duty 4, Gun Game and Soldat:

* Counter-Strike
* Counter-Strike: Source
* Day of Defeat
* Day of Defeat: Source
* Firearms 3.0
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

* The events for Firearms and Team Fortress Classic have not been thoroughly analyzed and tested. Some of them are definitely not working as hoped and expected, many of them are, but some are unknowns, especially in TFC. The reasons for this range from my not understanding the events themselves, and what they mean, because I'm not an experienced TFC player, to my weak coding skills, especially with Perl.

* The team wins and losses for Firearms, as well as rounds, don't work extremely well or consistently.  But I'm not sure there is much more I can do with them.  The log support for wins and losses in Firearms just isn't very good.  At this time there is no bonus support for Firearms wins and losses, and no way of changing that that I am aware of.

* The filter for bots isn't working properly in Natural Selection.

* The weapon images for TFC are hack jobs mostly stolen from TF2. I'm not an artist and can only copy, paste and modify. Maybe someone else will improve on them.

* The Flash hit box graphic no longer works on most browsers because Flash is about to become entirely extinct.  I haven't simply deleted the code because I have the insane hope that myself, or someone else, might be able to translate it to HTML5.

* The Firearms and TFC heatmaps are mostly untested, some are working as intended, some are not matching up with the data and I have been unable to ascertain why.

* Editing users in the Admin CP is broken.

* One person has reported that the Call of Duty 4 support is broken, however he wasn't using Wakachamo's latest fixes, as far as I know.


## **Stuff that Remains Untested**

* Most of the mods have not been tested, although my guess is that most of them will work.  The only games and mods that have been tested so far are Firearms 3.0, Natural Selection and Team Fortress Classic.


## **Future Plans**

* Heat map overviews and support for Team Fortress Classic.
* Update overlays data.
* Add map pictures for Firearms and Team Fortress Classic.
* Add conditionals to weapon page so that it doesn't show missing data.


## **Credits**

I want to express my admiration and gratitude to Jason Morriss, a.k.a. Stormtrooper, for all his work. This software deserves to be used. The period between 2000 and 2005 and all the old Half-Life and Source mods represent a golden age in pc game modding. Those games deserve to be played. With a little massaging most of them still run very well on new hardware and new operating systems.

Kudos to Valve as well for maintaining their back catalogue.

Credit to wakachamo and Rosenstein for their contributions.  Thanks also to RoboCop from APG and QuakerOates for their support and encouragement.
