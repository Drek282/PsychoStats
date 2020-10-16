Installation Instructions:

These instructions assume you already have the spatial statistics plugin installed, and Psychostats set up to handle your logs with those statistics.


Generating the Heatmaps:

To generate the heatmaps all you need to do is configure them as you wish in the web Admin CP, then run the heat.pl script which should be located wherever you installed your Psychostats scripts, not where yoou uploaded the web interface.

If you store the heatmaps in a folder it must be readable by the webserver software, and in a folder that it can serve, and you must enter the same absolute path into your Admin, Heatmaps, Directory field, in the web administration interface for your stats.  Note that this folder is not the same folder as the folder you store your overlays in (the map overview images).


Creating the Heatmap Overlays:

I am providing some information on how I created the overlays so that if you need to create some for some custom maps, you will be able to create images with a consistent look.  If you aren't going to be doing this, you can skip the remainder of this file.

These overlays were edited using the GIMP and my instructions assume a basic working knowlege of the GIMP.  You should be able to do the same with any standard graphics editor, such as Corel Photopaint, or Adobe Photoshop, although the specifics will be different.  You can find the GIMP here:

http://www.gimp.org/


Creating the Overviews:

To create your own map overviews, follow the instructions you will find at this address:

http://www.slackiller.com/tommy14/overview.htm

Note that you may have to use the "+jump" command to remove the ready room from the picture for ready rooms that are located vertically above the body of the map.


Creating the Heatmap Overlays:

You create the overlays by starting a listenserver in developer mode.  To start your game you should add the following commands to your game launch options in Steam.  To find those options right click on the game in your games list, choose properties, then click on Set Launch Options:

-dev -console +map [map name]

Valve suggests setting your video resolution to 1024x768 32 to generate the overview.

Noclip is a very valuable tool when you are trying to get the coords of the map.  noclip is not available on most mods, however, you can get it by installing Admin Mod, or AMXModX with the AdminModX plugin.  I'm not going to go into details here on how to do that, other than to say I would recommend Admin Mod and you will need to enable the cheats plugin.  Your user on a listenserver will be "STEAM_ID_LAN".  If you are working with a mod that isn't one of the popular mods, you will want to use Metamod-P.  You can google that.  If you do this, leave out the "-dev" in your launch options.

To see the overview use the following commands in the console:

(The "snapshot" command is what will take the screenshot.  Typically that is bound to F5.)

dev_overview 2
hud_draw 0
crosshair 0.0

Note that you will need to set sv_cheats 1 in the console for these commands to work.

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

What you need to do is go to the farthest map point in each map coordinate and use the "status" command in the console to find your position.  You then use that position as the limits for your map in the heat.xml file.  There are lots of examples there of how that should look.  If noclip doesn't work in your mod, getting those coordinates can be a challenge.
