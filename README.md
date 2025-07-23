
![header](https://i.imgur.com/4gf4l2i.png)

[![GitHub repo size](https://img.shields.io/github/repo-size/drek282/psychostats)](https://github.com/Drek282/PsychoStats/archive/refs/heads/main.zip)
 [![GitHub Issues or Pull Requests](https://img.shields.io/github/issues-raw/drek282/psychostats)](https://github.com/Drek282/PsychoStats/issues) [![GitHub Issues or Pull Requests](https://img.shields.io/github/issues-closed-raw/drek282/psychostats)](https://github.com/Drek282/PsychoStats/issues?q=is%3Aissue+is%3Aclosed) [![Average time to resolve an issue](http://isitmaintained.com/badge/resolution/drek282/psychostats.svg)](http://isitmaintained.com/project/drek282/psychostats)
 
---

This is, yet another, unofficial version of PsychoStats by Stormtrooper. Updated to work with PHP `7.1.0+` and MySQL*/MariaDB `5.5.0+`.  The minimum required version of Perl is `5.08`.

\* **Oracle's MySQL `8.0+` is *NOT* supported.  Please use [MariaDB](https://mariadb.org/ "MariaDB") instead.**

***Note that as of the release of version 3.2.7b on June 4, 2021, and specifically the change before that when the character encoding for the database was updated, if you do not drop and recreate your database, certain functionality will break.  You are strongly advised not to run the new web front end on an old database created before those changes were implemented.***

All of the versions on this repository and the game support repositories should be considered beta software as at this time we do not have the access to game servers necessary to thoroughly test releases.  Prior to 2010 PsychoStats was tested on thousands of websites with logs from thousands of game servers.  The base PsychoStats code should be robust and stable, the changes that have been made have been relatively minor, so it stands to reason that the code is still robust and stable, but there are no guarantees.

There was one serious known security vulnerability and that has been fixed.  Most of the "fixes" were already present in the code, as Stormtrooper was aware of some of the changes to PHP that had, at that time, recently been made, or had been announced. Very little had to actually be rewritten, and where rewriting was required the changes were pretty minor. There were a number of minor syntax changes, especially with regards to mysqli.

Flag icon images and many map and overlay images have been converted from jpg and png to webp.  There is a slight decrease in image quality that is not  noticeable unless you are specifically looking for it, and know what to look for.  The trade off is that the webp images are much smaller, require less bandwidth and will load faster.

This version of PsychoStats currently supports the following games:  
***We are always looking for server logs to allow for testing and improved game support.***

| :label: Game | Protocol | Supported | Tested |
| :--- | :---: | :---: | :---: |
| [Counter-Strike](https://github.com/Drek282/ps_cstrike "Counter-Strike") | half-life | :heavy_check_mark: | :heavy_check_mark: |
| [Day of Defeat](https://github.com/Drek282/ps_dod "Day of Defeat") | half-life | :heavy_check_mark: | :heavy_check_mark: |
| [Firearms 3.0](https://github.com/Drek282/ps_firearms "Firearms 3.0") | half-life | :heavy_check_mark: | :heavy_check_mark: |
| [Gun Game](https://github.com/Drek282/ps_gungame "Gun Game") | half-life | :heavy_check_mark: | :x: |
| [Half-Life Death Match](https://github.com/Drek282/ps_hldm "Half-Life Death Match") | half-life | :heavy_check_mark: | :heavy_check_mark: |
| [Natural Selection](https://github.com/Drek282/ps_natural "Natural Selection") | half-life | :heavy_check_mark: | :heavy_check_mark: |
| [Soldat](https://github.com/Drek282/ps_soldat "Soldat") | half-life | :heavy_check_mark: | :x: |
| [Team Fortress Classic](https://github.com/Drek282/ps_tfc "Team Fortress Classic") | half-life | :heavy_check_mark: | :heavy_check_mark: |
| [Counter-Strike: Source](https://github.com/Drek282/ps_cstrikes "Counter-Strike: Source") | source | :heavy_check_mark: | :heavy_check_mark: |
| [Day of Defeat: Source](https://github.com/Drek282/ps_dods "Day of Defeat: Source") | source | :heavy_check_mark: | :x: |
| [Gun Game: Source](https://github.com/Drek282/ps_gungames "Gun Game: Source") | source | :heavy_check_mark: | :x: |
| [Half-Life 2 Death Match](https://github.com/Drek282/ps_hl2dm "Half-Life 2 Death Match") | source | :heavy_check_mark: | :x: |
| [Team Fortress 2](https://github.com/Drek282/ps_tf2 "Team Fortress 2") | source | :heavy_check_mark: | :x: |
| [The Battle Grounds III](https://github.com/Drek282/ps_bg3 "The Battle Grounds III") | source | :heavy_check_mark: | :heavy_check_mark: |
| [Call of Duty 4](https://github.com/Drek282/ps_cod4 "Call of Duty 4") | cod4 | :heavy_check_mark: | :x: |
| [Call of Duty 4X](https://github.com/Drek282/ps_cod4x "Call of Duty 4X") | cod4x | :heavy_check_mark: | :x: |

There is also a GitHub repo for [PsychoStats Mods](https://github.com/Rosenstein/Psychostats-Mods/ "PsychoStats Mods").

We have, for the most part, tried not to make changes to the way Psychostats works, by default. However, one or two changes have been made that reflect personal biases, to make the process of reinstalling Psychostats hundreds of times more convenient as it has been worked on. One of those is that the bonus for an ffkill is now -10.  Winning games or rounds is really the entire point, far more important than k:d ratios, this is reflected in the bonuses that have been added for team wins.

If you don't like those changes they are easy to edit in the Admin Control Panel.  One other significant change is that the resolution for bonuses has been changed to one decimal place, so you can now create bonuses that are 0.1 etc.  There were bonuses for events that can happen very often, such as medic heals in Team Fortress Classic, that were too large if they were a full point or more.

Most of the links and references to psychostats.com have been removed as that domain is no longer actively maintained.  The only exception is the xml database that provides GeoIP data for the flags functionality. That appears to still be hosted. All of the references to Stormtrooper's email address have been removed.


You can view working demos of PsychoStats at the following links: 
*Note that this is a testing environment and as such the availability of the demos will not be 100%, 24/7.*

* [The Battle Grounds III](https://displaced.zone/ps_bg3/ "The Battle Grounds III")
* [Firearms 3.0](https://displaced.zone/ps_firearms-30/ "Firearms 3.0")
* [Natural Selection](https://displaced.zone/ps_ns/ "Natural Selection")
* [Team Fortress Classic](https://displaced.zone/ps_tfc/ "Team Fortress Classic")


## **Known Issues**

*The plan for the following issues is to either fix them, or improve them, in future versions:*

* One of the biggest problems with PsychoStats is that psychostats.com no longer hosts the documentation that it once did.

* The events for Firearms and Team Fortress Classic have not been thoroughly analyzed and tested.

* The team wins and losses for Firearms don't work extremely well or consistently.


## **Stuff that Remains Untested**

* The contents of the "addons" folder are largely unexplored with the exception of the AMX Mod X ps_heatmaps script, which does work.

* The contents of the "scripts" folder are also largely unexplored.  Most of them should be self explanatory but they should be considered untested.


## **Future Plans**

* Copy the old PsychoStats wiki content from The Wayback Machine to the GitHub wiki.
* Create a PschoStats HELP page and supporting software infrastructure.

## A Note Regarding the Cookie Consent, a.k.a. GDPR and CCPA Compliance

If you choose to enable Cookie Consent, in the Admin CP Security section, by default no cookies will be saved in users browsers. Cookies must be accepted for them to be saved by users in their browsers. If cookeies are not accepted users will be able to browse the stats but they will not be able to register or log into an account if those options are enabled or if they already have an account. They will also not be able to log into an admin account if they have one, or choose anything other than the default theme or language support.


## **Credits**

Thank you to Jason Morriss, a.k.a. Stormtrooper, for all his oringinal work. This software deserves to be used. The period between 2000 and 2005 and all the old Half-Life and Source mods represent a golden age in PC game modding. Those games deserve to be played. With a little massaging most of them still run very well on new hardware and new operating systems.

Kudos to Valve as well for maintaining their back catalogue.

Credit to wakachamo, Rosenstein, Solomenka and janzagata for their contributions.  Thanks also to RoboCop from APG for his support and encouragement.

The basic text for the default privacy policy has been copied from the default WordPress privacy policy.
