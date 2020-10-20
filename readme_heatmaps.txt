Installation Instructions:

These instructions assume you already have the spatial statistics plugin installed, and Psychostats set up to handle your logs with those statistics.


Generating the Heatmaps:

To generate the heatmaps all you need to do is configure them as you wish in the web Admin CP, then run the heat.pl script which should be located wherever you installed your Psychostats scripts, not where yoou uploaded the web interface.

If you store the heatmaps in a folder it must be readable by the webserver software, and in a folder that it can serve, and you must enter the same absolute path into your Admin, Heatmaps, Directory field, in the web administration interface for your stats.  Note that this folder is not the same folder as the folder you store your overlays in (the map overview images).

If you add "-who='killer' -cold" command line switches when you run heat.pl you can generate a very cool, no pun intended, heatmap that shows where the killers are in blue.  This heatmap is saved separately from the default 'victim' heatmap and the web gui will allow the user to choose which map they want to view from the drop down web gui heatmap menu.


Creating the Heatmap Overlays:

I am providing some information on how I created the overlays so that if you need to create some for some custom maps, you will be able to create images with a consistent look.  If you aren't going to be doing this, you can skip the remainder of this file.

These overlays were edited using the GIMP and my instructions assume a basic working knowlege of the GIMP.  You should be able to do the same with any standard graphics editor, such as Corel Photopaint, or Adobe Photoshop, although the specifics will be different.  You can find the GIMP here:

http://www.gimp.org/


Creating the Overviews:

To create your own map overviews, follow the instructions you will find at this address:

http://www.slackiller.com/tommy14/overview.htm

Note that you may have to use the "+jump" command to remove the ready room from the picture for ready rooms that are located vertically above the body of the map.


Creating the Heatmap Overlays:

It is almost impossible to get the necessary coordinates for every map without using noclip.  The only reliable way I know to have access to noclip is by installing Admin Mod.  It might also be available on AMX Mod X using the AdminModX plugin.  I have not tested that.  For Admin Mod you will need to enable the cheats plugin.  Once you've installed Admin Mod and given your user the appropriate permissions, you can just start up the game normally and choose the option "Create Game" from the start screen for the game.  Your user on a listenserver will be "STEAM_ID_LAN".  I would recommend using Metamod-P if you are not running one of the more popular mods.

Valve suggests setting your video resolution to 1024x768 32 to generate the overview.

To see the overview use the following commands in the console:

(The "snapshot" command is what will take the screenshot.  Typically that is bound to F5.)

sv_cheats 1
dev_overview 2
hud_draw 0
crosshair 0.0

Editing Steps:

1.  Once I had created the overview screen shot, I opened it with the GIMP, and selected the green background using the "Select by Colour" tool.  Make sure the option to select transparent areas is checked, and the tolerance is set to 0.

2.  Invert your selection.

3.  Use the "Rectangle Select Tool" set on "subtract" to remove any unwanted areas, such as the Natural Selection ready room.

4.  Use ctrl-c to copy the selection.

5.  Use "Aquire" "Paste as New" to create the final heatmap image file.

6.  Scale the image so that the largest dimension is 600.

7.  Sharpen the image using the sharpen filter set at 10.

8.  Converted the image to grayscale.

9.  Adjust the brightness and contrast by setting the brightness to 120, and the contrast to 40.

10. Save the file as a png file to preserve the transparency.


Editing the heat.xml File:

What you need to do is go to the farthest map point in each map coordinate and use the "status" command, or the Admin Mod command "admin_userorigin [any unique part of your player name]", in the console to find your position.  Note that you need to find the farthest visible point in each cardinal direction on your map even if those points are not in a playing area.  You then use that position as the limits for your map in the heat.xml file.  There are lots of examples there of how that should look.  Your chances of getting those coordinates without using noclip are very small.
