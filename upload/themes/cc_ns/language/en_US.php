<?php
/*
	en_US.php
	$Id: en_US.php 466 2008-05-28 14:30:14Z lifo $

	Language mapping for 'en_US' auto-generated from pslang.pl.

	To start a new language set, copy this file to a new name using the locale or a simple name 
	representing the language (ie: chinese) as its name. A locale string is normally a 2 character
	code for the language, ie: en for english, fr for french, etc... Followed by an underscore (_) 
	then a 2 character country code for the language, ie: US, DE, PT, etc...
	For example, for french you might use "fr_FR", or for spanish use "es_US".

	Use the pslang.pl script included with PsychoStats to auto-generate a new language file like this.
*/
if (!defined("PSYCHOSTATS_PAGE")) die("Unauthorized access to " . basename(__FILE__));

// If the language translation extends another translation set then you should include
// that class file once here. This is useful for updating a translation set w/o having to define 
// every single language map if some translations are no different from the extended language.
//include_once($this->language_dir('en_US') . '/en_US.php');

class PsychoLanguage_default_en_US extends PsychoLanguage {

function PsychoLanguage_default_en_US() {
	$this->PsychoLanguage();
	// You can set a locale if you want (which will affect certain system calls)
	// however, setting a locale is not 100% portable between systems and setlocale is not
	// thread-safe. Setting the locale on a multi-threaded server (ie: apache2 using mm_worker model) 
	// will affect other threads that are running at the same time.
//	setlocale(LC_ALL, 'en_US.UTF-8');

	// Every english phrase that can be translated is located here.
	// Becareful to properly escape strings so quotes are displayed properly.
	// Most strings are simple phrases or words. For more complex or larger translations, see the methods below.
	$this->map = array(
	'%s (%s) was added to the clan.' =>
		'',
	'%s (%s) was removed from the clan.' =>
		'',
	'100 Players are not always shown due to certain IP ranges not being geocoded' =>
		'',
	'A file location must be defined to download the theme from' =>
		'',
	'A name must be defined' =>
		'',
	'A password must be entered for new users' =>
		'',
	'A source location must be defined to download the theme from' =>
		'',
	'A user can only be associated with a single player.' =>
		'',
	'Acc' =>
		'',
	'Access Level' =>
		'',
	'Accuracy' =>
		'',
	'Activity' =>
		'',
	'Activity Level' =>
		'',
	'Add Friend' =>
		'',
	'Add New Member' =>
		'',
	'Add Selected Members' =>
		'',
	'Admin' =>
		'',
	'AIM' =>
		'',
	'AIM Name' =>
		'',
	'Alien' =>
		'',
	'Alien / Marine Joins' =>
		'',
	'Alien / Marine Wins' =>
		'',
	'Alien Wins' =>
		'',
	'All informational fields are optional and used solely for display purposes.' =>
		'',
	'Allowed Tags' =>
		'',
	'ally' =>
		'',
	'Ally Kills' =>
		'',
	'Ally Wins' =>
		'',
	'and have done' =>
		'',
	'Are you sure you want to delete the player?' =>
		'',
	'Assists' =>
		'',
	'Auto Login' =>
		'',
	'Available Maps' =>
		'',
	'Available Roles' =>
		'',
	'Available Weapons' =>
		'',
	'Average Connections' =>
		'',
	'Average Kills' =>
		'',
	'Average: %d' =>
		'',
	'Avg' =>
		'',
	'Awards' =>
		'',
	'axis' =>
		'',
	'Axis / Allied Wins' =>
		'',
	'Axis / Ally Joins' =>
		'',
	'Axis / Ally Kills' =>
		'',
	'Axis / Ally Wins' =>
		'',
	'Axis Kills' =>
		'',
	'Axis Wins' =>
		'',
	'Backstab Kills' =>
		'',
	'Backstab Kills Percentage' =>
		'',
	'Blocked Captures' =>
		'',
	'Blue' =>
		'',
	'Blue Force' =>
		'',
	'Blue Wins' =>
		'',
	'Blue Force Wins' =>
		'',
	'bombs defused' =>
		'',
	'Bombs Defused %' =>
		'',
	'Bombs Exploded' =>
		'',
	'Bonus Points' =>
		'',
	'bots' =>
		'',
	'BS' =>
		'',
	'BS%' =>
		'',
	'Cancel' =>
		'',
	'Captured points' =>
		'',
	'Captures Blocked' =>
		'',
	'Change Password?' =>
		'',
	'Change Theme' =>
		'',
	'Chest' =>
		'',
	'Clan' =>
		'',
	'Clan Maps' =>
		'',
	'Clan member' =>
		'',
	'Clan Members' =>
		'',
	'Clan Name' =>
		'',
	'Clan Rundown' =>
		'',
	'Clan Statistics' =>
		'',
	'Clan Statistics for Tag' =>
		'',
	'Clan Tag' =>
		'',
	'Clan Weapons' =>
		'',
	'Clans' =>
		'',
	'clans rank out of' =>
		'',
	'Click here to clear your avatar.' =>
		'',
	'Click here to login again.' =>
		'',
	'Click here to logout!' =>
		'',
	'Click here to refresh' =>
		'',
	'Click on an avatar to select it.' =>
		'',
	'Click to connect' =>
		'',
	'Click to select theme' =>
		'',
	'Connections' =>
		'',
	'Connections: %d' =>
		'',
	'Counter-Terrorist Wins' =>
		'',
	'Country Breakdown' =>
		'',
	'Country Code' =>
		'',
	'CP' =>
		'',
	'Create user for this player' =>
		'',
	'CT' =>
		'',
	'Current Map' =>
		'',
	'D' =>
		'',
	'Daily awards for' =>
		'',
	'Daily Connections' =>
		'',
	'Damage' =>
		'',
	'Damage Done' =>
		'',
	'Date' =>
		'',
	'Day' =>
		'',
	'Death Streak' =>
		'',
	'Deaths' =>
		'',
	'Dedicated' =>
		'',
	'Delete' =>
		'',
	'Deleting a player does not prevent them from re-appearing in the stats.' =>
		'',
	'Details' =>
		'',
	'Diff' =>
		'',
	'Dmg' =>
		'',
	'Dom' =>
		'',
	'Dominations' =>
		'',
	'Edit' =>
		'',
	'Edit Clan' =>
		'',
	'Edit Player' =>
		'',
	'Edit user for this player' =>
		'',
	'Email' =>
		'',
	'Email Address' =>
		'',
	'Entering the Lat,Lng will override the automatic geocoding of the players IP address' =>
		'',
	'Error creating user: ' =>
		'',
	'Error deleting player: ' =>
		'',
	'Error loading icons' =>
		'',
	'Error retreiving user from database' =>
		'',
	'Error saving user: ' =>
		'',
	'Error updating player profile: ' =>
		'',
	'exploded' =>
		'',
	'Fatal Error' =>
		'',
	'FF' =>
		'',
	'FF Kills' =>
		'',
	'FF%' =>
		'',
	'First Seen' =>
		'',
	'Flags' =>
		'',
	'Flags captured' =>
		'',
	'Flags Captured' =>
		'',
	'Forgot password?' =>
		'',
	'Forgot your password?' =>
		'',
	'Friendly Fire' =>
		'',
	'Friendly Fire Kills' =>
		'',
	'Friendly Fire Kills Percentage' =>
		'',
	'G' =>
		'',
	'Game' =>
		'',
	'Games' =>
		'',
	'Go to' =>
		'',
	'Google Map' =>
		'',
	'Green' =>
		'',
	'Green Wins' =>
		'',
	'Guest' =>
		'',
	'GUID' =>
		'',
	'GUIDs' =>
		'',
	'has' =>
		'',
	'has been played for' =>
		'',
	'Head' =>
		'',
	'Headshot Kills' =>
		'',
	'Headshot Kills Percentage' =>
		'',
	'Heatmaps' =>
		'',
	'Historical Days' =>
		'',
	'Hitbox Zones' =>
		'',
	'hits' =>
		'',
	'Home' =>
		'',
	'hostages saved' =>
		'',
	'HS' =>
		'',
	'HS%' =>
		'',
	'HTML Logo' =>
		'',
	'ICQ' =>
		'',
	'ICQ Number' =>
		'',
	'Image must start with http:// or https://' =>
		'',
	'in damage' =>
		'',
	'Informational Fields' =>
		'',
	'Insufficient privileges to edit clan!' =>
		'',
	'Insufficient privileges to edit player!' =>
		'',
	'Intel' =>
		'',
	'Intel Captured' =>
		'',
	'Intel Recovered' =>
		'',
	'Intelligence captured' =>
		'',
	'Invalid access level specified' =>
		'',
	'Invalid characters found in name' =>
		'',
	'Invalid characters found in parent' =>
		'',
	'Invalid clan ID Specified' =>
		'',
	'Invalid clan ID specified.' =>
		'',
	'Invalid image defined' =>
		'',
	'Invalid map ID specified.' =>
		'',
	'Invalid name defined' =>
		'',
	'Invalid parent defined' =>
		'',
	'Invalid player ID Specified' =>
		'',
	'Invalid player ID specified.' =>
		'',
	'Invalid role ID specified.' =>
		'',
	'Invalid tags were removed.' =>
		'',
	'Invalid username or password' =>
		'',
	'Invalid weapon ID specified.' =>
		'',
	'Invalid website defined' =>
		'',
	'IP' =>
		'',
	'IP Address' =>
		'',
	'is not ranked' =>
		'',
	'is ranked' =>
		'',
	'K' =>
		'',
	'K:D' =>
		'',
	'K:M' =>
		'',
	'Kill Assists' =>
		'',
	'Kill Profile' =>
		'',
	'Kill Streak' =>
		'',
	'kills' =>
		'',
	'Kills' =>
		'',
	'Kills per Death' =>
		'',
	'Kills Per Death' =>
		'',
	'Kills Per Minute' =>
		'',
	'Kills per Minute' =>
		'',
	'Last' =>
		'',
	'Last 24 Hours' =>
		'',
	'Last Played' =>
		'',
	'Last Seen' =>
		'',
	'Latitude' =>
		'',
	'Left Arm' =>
		'',
	'Left Leg' =>
		'',
	'Linux' =>
		'',
	'Listen' =>
		'',
	'Live Server Info' =>
		'',
	'Live Server Views' =>
		'',
	'Loading ...' =>
		'',
	'Loading avatars, please wait' =>
		'',
	'Loading flags, please wait' =>
		'',
	'Lock Member List?' =>
		'',
	'Locked?' =>
		'',
	'Logged in as' =>
		'',
	'Login' =>
		'',
	'Login Help' =>
		'',
	'Login to admin control panel' =>
		'',
	'Login to edit this player\'s profile!' =>
		'',
	'Longitude' =>
		'',
	'Manage Clan Members' =>
		'',
	'Managing Members' =>
		'',
	'Map' =>
		'',
	'Map Statistics' =>
		'',
	'Maps' =>
		'',
	'maps have been played' =>
		'',
	'Maps Played' =>
		'',
	'Marine' =>
		'',
	'Marine Wins' =>
		'',
	'matched' =>
		'',
	'Member Added!' =>
		'',
	'Member Removed!' =>
		'',
	'Members' =>
		'',
	'members with an average skill of' =>
		'',
	'Mini Avatar' =>
		'',
	'Month' =>
		'',
	'Monthly awards for' =>
		'',
	'Most Active Bomb Runner' =>
		'',
	'Most Ally flags captured' =>
		'',
	'Most areas captured' =>
		'',
	'Most Axis flags captured' =>
		'',
	'Most Backstabs' =>
		'',
	'Most Blocked Captures' =>
		'',
	'Most Bombs Defused' =>
		'',
	'Most Bombs Exploded' =>
		'',
	'Most Bombs Planted' =>
		'',
	'Most Dominations' =>
		'',
	'Most FF Deaths' =>
		'',
	'Most FF Kills' =>
		'',
	'Most flags captured' =>
		'',
	'Most Hostages Killed' =>
		'',
	'Most Hostages Rescued' =>
		'',
	'Most Hostages Touched' =>
		'',
	'Most Intel Captured' =>
		'',
	'Most Intel Recovered' =>
		'',
	'Most Kills' =>
		'',
	'Most Often VIP' =>
		'',
	'Most Online Time' =>
		'',
	'Most Points Captured' =>
		'',
	'Most VIP Escapes' =>
		'',
	'Most VIP Kills' =>
		'',
	'ms' =>
		'',
	'MSN' =>
		'',
	'MSN Messenger' =>
		'',
	'Name' =>
		'',
	'New' =>
		'',
	'New Password' =>
		'',
	'Newbie?' =>
		'',
	'Next' =>
		'',
	'next' =>
		'',
	'No Awards Found' =>
		'',
	'no change' =>
		'',
	'No Clan Found!' =>
		'',
	'No Clans Found' =>
		'',
	'No clans to display' =>
		'',
	'No file defined' =>
		'',
	'No Historical Stats Found' =>
		'',
	'No Map Found!' =>
		'',
	'No Maps Found' =>
		'',
	'No Members Found!' =>
		'',
	'No name defined' =>
		'',
	'No player awards on this date' =>
		'',
	'No Player Found!' =>
		'',
	'No Players Found' =>
		'',
	'No Role Found!' =>
		'',
	'No Roles Found' =>
		'',
	'No Sessions Found' =>
		'',
	'No source defined' =>
		'',
	'No Stats Available' =>
		'',
	'No Victims Found' =>
		'',
	'No weapon class awards on this date' =>
		'',
	'No Weapon Found!' =>
		'',
	'No Weapons Found' =>
		'',
	'none' =>
		'',
	'Not registered?' =>
		'',
	'Not Set' =>
		'',
	'Not VAC secured' =>
		'',
	'on' =>
		'',
	'Online' =>
		'',
	'Online Time' =>
		'',
	'Only enter a password if you want to change it from the current password.' =>
		'',
	'Only the first player on a lat,lng location is displayed' =>
		'',
	'Only the top' =>
		'',
	'Optional' =>
		'',
	'Or click here to continue' =>
		'',
	'Other' =>
		'',
	'out of' =>
		'',
	'Overall Accuracy' =>
		'',
	'Overview' =>
		'',
	'Page loaded in' =>
		'',
	'Password' =>
		'',
	'Passwords do not match' =>
		'',
	'Passwords do not match; please try again' =>
		'',
	'ping' =>
		'',
	'planted' =>
		'',
	'Player' =>
		'',
	'Player Awards' =>
		'',
	'Player Game Sessions' =>
		'',
	'Player History' =>
		'',
	'Player Hitbox' =>
		'',
	'player IDs are shown' =>
		'',
	'Player IP Addresses' =>
		'',
	'Player locations are geocoded based on their connection IP and are approximate' =>
		'',
	'Player Maps' =>
		'',
	'Player Names' =>
		'',
	'Player Registration Completed!' =>
		'',
	'Player registration is currently disabled!' =>
		'',
	'Player Roles' =>
		'',
	'Player Roles / Classes' =>
		'',
	'Player Rundown' =>
		'',
	'Player Statistics' =>
		'',
	'Player Victims' =>
		'',
	'Player Weapons' =>
		'',
	'players' =>
		'',
	'Players' =>
		'',
	'players rank out of' =>
		'',
	'Please feel free to browse the <a href="{url _base=\'index.php\'}">player statistics</a> now.' =>
		'',
	'Please go back and try again.' =>
		'',
	'Please try another map' =>
		'',
	'Please wait' =>
		'',
	'Points Captured' =>
		'',
	'potential clans' =>
		'',
	'Powered by' =>
		'',
	'Previous' =>
		'',
	'Previous Rank' =>
		'',
	'Previous Skill' =>
		'',
	'PsychoStats Overview' =>
		'',
	'Querying Server' =>
		'',
	'Quick Login Popup' =>
		'',
	'Quick Search Popup' =>
		'',
	'R' =>
		'',
	'Rank' =>
		'',
	'ranked players out of' =>
		'',
	'RCON' =>
		'',
	'Red' =>
		'',
	'Red Force' =>
		'',
	'Red / Blue Wins' =>
		'',
	'Red Force / Blue Force Wins' =>
		'',
	'Red Wins' =>
		'',
	'Red Force Wins' =>
		'',
	'Register' =>
		'',
	'Register new user' =>
		'',
	'Register now!' =>
		'',
	'Register!' =>
		'',
	'Registering a user will allow you to edit your player\'s profile information.' =>
		'',
	'Registration is closed! No players may be registered at this time.' =>
		'',
	'Registration is OPEN. New users will have instant access to their player profiles.' =>
		'',
	'Registration requires confirmation from the admin. You won\'t be able to login until confirmed.' =>
		'',
	'Remember me!' =>
		'',
	'Rescued Hostages' =>
		'',
	'Reset your password' =>
		'',
	'Resubmit to try again.' =>
		'',
	'Retype Password' =>
		'',
	'Right Arm' =>
		'',
	'Right Leg' =>
		'',
	'Role' =>
		'',
	'Role Statistics' =>
		'',
	'Roles' =>
		'',
	'Rounds' =>
		'',
	'Rule' =>
		'',
	'Rules' =>
		'',
	'S:K' =>
		'',
	'Save' =>
		'',
	'Search' =>
		'',
	'Search criteria' =>
		'',
	'Search criteria "<em>%s</em>" matched %d ranked players out of %d total' =>
		'',
	'seconds with' =>
		'',
	'Secure' =>
		'',
	'Select a Language' =>
		'',
	'Select a theme from this gallery.' =>
		'',
	'Select Members' =>
		'',
	'Server' =>
		'',
	'Server timed out' =>
		'',
	'Servers' =>
		'',
	'Session Time' =>
		'',
	'shots' =>
		'',
	'Shots Fired' =>
		'',
	'Shots Hit' =>
		'',
	'Shots Per Kill' =>
		'',
	'Shots per Kill' =>
		'',
	'Skill' =>
		'',
	'SQL queries' =>
		'',
	'Start Time' =>
		'',
	'Steam ID' =>
		'',
	'Steam Profile' =>
		'',
	'Steamid' =>
		'',
	'Steamids' =>
		'',
	'Stomach' =>
		'',
	'Structures Built' =>
		'',
	'Structures Destroyed' =>
		'',
	'Structures Recycled' =>
		'',
	'Suicides' =>
		'',
	'T / CT Joins' =>
		'',
	'T / CT Wins' =>
		'',
	'Team' =>
		'',
	'Team / Action Profile' =>
		'',
	'Team Kills' =>
		'',
	'Team Scores' =>
		'',
	'Terr / CT Wins' =>
		'',
	'Terrorist' =>
		'',
	'Terrorist Wins' =>
		'',
	'Terrorists' =>
		'',
	'Thank you for registering your player' =>
		'',
	'The %s does not exist!' =>
		'',
	'The unique ID ({$conf.main.uniqueid}) given for your player must already exist or registration will fail.' =>
		'',
	'The unique ID entered above should match the player you play as.' =>
		'',
	'Theme download file not found or invalid type (' =>
		'',
	'Theme Gallery' =>
		'',
	'Themes will be applied to your session even if you\'re not logged in.' =>
		'',
	'There are currently no awards in the database to display.' =>
		'',
	'This field can not be blank' =>
		'',
	'This player is already registered!' =>
		'',
	'This user has not been confirmed yet and can not login at this time.' =>
		'',
	'This window will close in a few seconds.' =>
		'',
	'Time' =>
		'',
	'Time Left' =>
		'',
	'Today' =>
		'',
	'Toggle flags' =>
		'',
	'Toggle gallery' =>
		'',
	'top 1 percentile' =>
		'',
	'Top 100 Highest Ranked Players' =>
		'',
	'Top Players' =>
		'',
	'total' =>
		'',
	'Total Awards' =>
		'',
	'Total Bans' =>
		'',
	'Total Connections' =>
		'',
	'Total Deaths' =>
		'',
	'Total Games' =>
		'',
	'Total Kicks' =>
		'',
	'Total Kills' =>
		'',
	'Total Online' =>
		'',
	'Total Rounds' =>
		'',
	'Total Servers' =>
		'',
	'Type' =>
		'',
	'Unable to download theme file from ' =>
		'',
	'Unclassified' =>
		'',
	'Unique ID' =>
		'',
	'Unknown' =>
		'',
	'unknown' =>
		'',
	'Used' =>
		'',
	'User does not have permission to login' =>
		'',
	'User Login' =>
		'',
	'User Logout' =>
		'',
	'User Registration' =>
		'',
	'Username' =>
		'',
	'Username already exists!' =>
		'',
	'Username already exists; please try another name' =>
		'',
	'Users with "admin" privileges will be able to access the Admin Control Panel (ACP).' =>
		'',
	'Users with "clan admin" privileges can edit their own clan profiles (if you\'re in a clan).' =>
		'',
	'VAC secured' =>
		'',
	'Value' =>
		'',
	'Victim' =>
		'',
	'View' =>
		'',
	'View Heatmap' =>
		'',
	'View History' =>
		'',
	'View Statistics' =>
		'',
	'Weapon' =>
		'',
	'Weapon Statistics' =>
		'',
	'Weapons' =>
		'',
	'weapons have killed' =>
		'',
	'Website' =>
		'',
	'Website must start with http:// or https://' =>
		'',
	'Week' =>
		'',
	'Weekly awards for week' =>
		'',
	'Welcome' =>
		'',
	'Why and How to Register?' =>
		'',
	'Windows' =>
		'',
	'Wins' =>
		'',
	'Worldid' =>
		'',
	'Worldids' =>
		'',
	'Yellow' =>
		'',
	'Yellow Wins' =>
		'',
	'Yesterday' =>
		'',
	'You are using this theme' =>
		'',
	'You can now access and modify your own <b><a href="{url _base=\'editplr.php\' id=$plr.plrid}">player profile</a></b>.' =>
		'',
	'You have been logged in.' =>
		'',
	'You have been logged out.' =>
		'',
	'You must have logged into the game server at least once before attempting to register.' =>
		'',
	'You will be redirected in a few seconds' =>
		'',
	'Your information is never sold or given away to third parties.' =>
		'',

	) + $this->map;
}

// if a translation keyword maps to a method below then the matching method should return the translated string.
// This is most useful for those large blocks of text in the theme. 
function LOGIN_AUTOLOGIN() {
	$text  = '';
	$text .= 'If auto login is enabled a cookie will be saved in your browser and the next time you' . "\n";
	$text .= 'visit this site you will automatically be logged in again, even if you close your browser.' . "\n";
	return $text;
}

function MANAGE_CLAN_MEMBERS() {
	$text  = '';
	$text .= 'If you manually edit the member list you should check the "lock" button.' . "\n";
	$text .= 'Otherwise there is no guarantee the listing will remain the way you set it.' . "\n";
	$text .= 'Changes to the member list below are instant!' . "\n";
	return $text;
}

function REGISTER_NEW_USER() {
	$text  = '';
	$text .= 'Register a user for this player by entering a username and password below.' . "\n";
	$text .= 'This will allow the user to login and modify their profile.' . "\n";
	return $text;
}

function REGISTRATION_COMPLETE() {
	$text  = '';
	$text .= 'In the future, to <a href="{url _base=\'login.php\'}">login</a> please use your username ' . "\n";
	$text .= '\'<b><a href="{url _base=\'edituser.php\' id=$reg.userid}">{$reg.username|escape}</a></b>\' with the password you supplied ' . "\n";
	$text .= 'in your registration.' . "\n";
	return $text;
}

function REGISTRATION_CONFIRM() {
	$text  = '';
	$text .= 'You will not be able to login and access your profile until an administrator confirms your account. ' . "\n";
	$text .= 'If you entered an email address in your registration you will be notified when the account is confirmed.' . "\n";
	return $text;
}



}

?>
