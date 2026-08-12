/* BK's Psychostats Plugin for Admin Mod
 *
 * Opens PsychoStats in the MOTD window from chat or console.
 *
 * Configuration (needs access level 512):
 *
 *   admin_ps_set                  lists the options
 *   admin_ps_set <option>         shows the current value(s)
 *   admin_ps_set <option> <value> sets a value
 *
 *   PREFIX     Character the signal words are prefixed with in chat,
 *              usually "/" or ".". Saying it again removes it.
 *   STATSME    Signal word for personal stats, usually "stats". Add a
 *              (part of a) name after it to look somebody else up.
 *   STATSRANK  Signal word for the overall ranking, usually "rank".
 *   URL        Address PsychoStats is installed under, for instance
 *              http://www.example.com/psychostats/ - pointing it at
 *              index.php the way older versions of this plugin wanted
 *              works as well.
 *   VERSION    Major version of the PsychoStats install, 2 or 3.
 *              Defaults to 3, older installs keep working when this is
 *              set back to 2.
 *
 * 3.x is searched through index.php?q=<authid or name>, which redirects
 * straight to the player page whenever exactly one player matches.
 */

#include <core>
#include <console>
#include <string>
#include <admin>
#include <adminlib>

#define MAX_CMDS 10
#define MAX_OPTS 5

/* PsychoStats turns down anything longer than this */
#define MAX_SEARCH_LENGTH 50

new STRING_VERSION[MAX_DATA_LENGTH] = "2.0";

new gsKeywordsMe[MAX_CMDS][MAX_COMMAND_LENGTH];
new gsKeywordsRank[MAX_CMDS][MAX_COMMAND_LENGTH];
new gsURL[MAX_DATA_LENGTH];
new gsPrefix[MAX_CMDS][MAX_COMMAND_LENGTH];
new giKeywordsMe,giKeywordsRank,giPrefix;
new giPSVersion;

display_array(iNumber,aArray[MAX_CMDS][]){
	for(new i=0;i<iNumber;i++){
		selfmessage(aArray[i]);
	}
	return PLUGIN_CONTINUE;
}

change_array(sVault[],sValue[],iNumber,aArray[][]){
	new sData[MAX_DATA_LENGTH]="";
	new iMerk=0;

	for(new i=0;i<iNumber;i++){
		if(strcmp(aArray[i],sValue)!=0){
			strcat(sData,aArray[i],MAX_DATA_LENGTH);
			strcat(sData," ",MAX_DATA_LENGTH);
		}
		else{
			iMerk=1;
		}
	}
	if(!iMerk){
		strcat(sData,sValue,MAX_DATA_LENGTH);
	}
	else{
		strtrim(sData," ",1);
	}

	set_vaultdata(sVault,sData);

	load_data();

	return PLUGIN_CONTINUE;
}

change_value(sVault[],sValue[]){

	set_vaultdata(sVault,sValue);

	load_data();

	return PLUGIN_CONTINUE;
}


public admin_ps_set(HLCommand,HLData,HLUserName,UserIndex){
	new aCommands[MAX_OPTS][MAX_COMMAND_LENGTH]={"PREFIX","STATSME","STATSRANK","URL","VERSION"};
	new sText[MAX_TEXT_LENGTH];
	new iMerk=-1;
	new sValue[MAX_DATA_LENGTH];
	new sCvar[MAX_DATA_LENGTH];
	new sUser[MAX_NAME_LENGTH];

	convert_string(HLData,sValue,MAX_DATA_LENGTH);
	convert_string(HLUserName,sUser,MAX_NAME_LENGTH);
	strbreak(sValue,sCvar,sValue,MAX_DATA_LENGTH);
	strtrim(sValue," ",2);
	strtrim(sCvar," ",2);

	for(new i=0;i<MAX_OPTS;i++){
		if(strcasecmp(aCommands[i],sCvar)==0){
			iMerk=i;
		}
	}

	if(iMerk<0 && strlen(sCvar)>0){
		selfmessage("Option not found!");
		strinit(sCvar);
	}

	if(!strlen(sCvar)){
		selfmessage("Possible Options:");
		for(new i=0;i<MAX_OPTS;i++){
			selfmessage(aCommands[i]);
		}
		return PLUGIN_HANDLED;
	}

	if(!strlen(sValue)){
		snprintf(sText,MAX_TEXT_LENGTH,"Current value(s) of option %s:",aCommands[iMerk]);
		selfmessage(sText);
		switch (iMerk) {
			case 0:
				display_array(giPrefix,gsPrefix);
			case 1:
				display_array(giKeywordsMe,gsKeywordsMe);
			case 2:
				display_array(giKeywordsRank,gsKeywordsRank);
			case 3:
				selfmessage(gsURL);
			case 4: {
				snprintf(sText,MAX_TEXT_LENGTH,"%d",giPSVersion);
				selfmessage(sText);
			}
		}
		return PLUGIN_HANDLED;
	}

	/* The URL scheme changed between PsychoStats 2.x and 3.x, so we have to
	 * be told which one we are pointed at. */
	if(iMerk==4 && strtonum(sValue)!=2 && strtonum(sValue)!=3){
		selfmessage("VERSION has to be either 2 or 3.");
		return PLUGIN_HANDLED;
	}

	snprintf(sText,MAX_TEXT_LENGTH,"BK_PS_%s",aCommands[iMerk]);

	switch (iMerk) {
		case 0:
			change_array(sText,sValue,giPrefix,gsPrefix);
		case 1:
			change_array(sText,sValue,giKeywordsMe,gsKeywordsMe);
		case 2:
			change_array(sText,sValue,giKeywordsRank,gsKeywordsRank);
		case 3:
			change_value(sText,sValue);
		case 4:
			change_value(sText,sValue);
	}
	selfmessage("Option changed");

	return PLUGIN_HANDLED;
}

public admin_stats(HLCommand,HLData,HLUserName,UserIndex) {
	new sData[MAX_DATA_LENGTH]="";
	new sPrefix[MAX_DATA_LENGTH]="";
	new sCommand[MAX_COMMAND_LENGTH]="";
	new sUser[MAX_NAME_LENGTH]="";
	new sTarget[MAX_DATA_LENGTH]="";
	new iPrefix=-1;

	convert_string(HLCommand,sCommand,MAX_COMMAND_LENGTH);
	convert_string(HLData,sData,MAX_DATA_LENGTH);
	convert_string(HLUserName,sUser,MAX_NAME_LENGTH);
	strstripquotes(sData);

	if(streq(sCommand,"say")){
		if(!giPrefix || !giKeywordsMe || !giKeywordsRank){
			return PLUGIN_CONTINUE;
		}
		strncpy(sPrefix,sData,1,MAX_DATA_LENGTH);
		for(new i=0;i<giPrefix;i++){
			if(streq(sPrefix,gsPrefix[i])){
				iPrefix=i;
				break;
			}
		}
		if(iPrefix<0){
			return PLUGIN_CONTINUE;
		}
		strtrim(sData,gsPrefix[iPrefix],0);
		if(strcount(sData,' ')>0){
			strbreak(sData,sData,sTarget,MAX_DATA_LENGTH);
		}
		for(new i=0;i<giKeywordsMe;i++){
			if(streq(sData,gsKeywordsMe[i])){
				show_stats(sUser,sTarget);
				return PLUGIN_HANDLED;
			}
		}
		for(new i=0;i<giKeywordsRank;i++){
			if(streq(sData,gsKeywordsRank[i])){
				open_motd(sUser,"",1);
				return PLUGIN_HANDLED;
			}
		}
		return PLUGIN_CONTINUE;
	}

	/* Everything after "admin_stats" is the player to look up, so a name
	 * with a space in it still works when it is quoted. */
	strtrim(sData," ",2);
	show_stats(sUser,sData);

	return PLUGIN_HANDLED;
}

/* Opens the stats of sTarget for sUser. An empty sTarget means sUser
 * wants to see his own stats. */
show_stats(sUser[],sTarget[]){
	new sSearch[MAX_DATA_LENGTH]="";
	new sName[MAX_NAME_LENGTH]="";

	/* Somebody who left the server can still be looked up, we just have to
	 * hand PsychoStats the name the way it was typed. Anything longer than
	 * MAX_SEARCH_LENGTH gets cut off here, both because PsychoStats refuses
	 * to search for it and to keep the escaping below inside sSearch. */
	strncpy(sSearch,sTarget,MAX_SEARCH_LENGTH,MAX_DATA_LENGTH);

	if(!strlen(sTarget)){
		strcpy(sName,sUser,MAX_NAME_LENGTH);
	}
	else{
		get_username(sTarget,sName,MAX_NAME_LENGTH);
	}

	/* PsychoStats keys players on their authid, so use that whenever we
	 * have a usable one and fall back to the name otherwise. */
	if(strlen(sName)){
		if(!get_userAuthID(sName,sSearch,MAX_AUTHID_LENGTH) || !is_steamid(sSearch)){
			strcpy(sSearch,sName,MAX_DATA_LENGTH);
		}
	}

	url_encode(sSearch,MAX_DATA_LENGTH);
	open_motd(sUser,sSearch,0);

	return PLUGIN_CONTINUE;
}

/* Bots, LAN servers and clients that Steam has not answered for yet all
 * hand out authids that are of no use for a stats lookup. */
is_steamid(sAuthID[]){
	if(strncasecmp(sAuthID,"STEAM_",6)!=0){
		return 0;
	}
	if(strcount(sAuthID,':')!=2){
		return 0;
	}
	return 1;
}

/* Escapes the characters that can turn up in a player name and would
 * otherwise break either the query string or the html we wrap it in. */
url_encode(sString[],iMaxLen){
	strsubst(sString,"%","%25",iMaxLen);	/* has to be substituted first */
	strsubst(sString,"^"","%22",iMaxLen);
	strsubst(sString,"'","%27",iMaxLen);
	strsubst(sString,"<","%3C",iMaxLen);
	strsubst(sString,">","%3E",iMaxLen);
	strsubst(sString,"&","%26",iMaxLen);
	strsubst(sString,"#","%23",iMaxLen);
	strsubst(sString,"+","%2B",iMaxLen);
	strsubst(sString,"?","%3F",iMaxLen);
	strsubst(sString,"=","%3D",iMaxLen);
	strsubst(sString," ","+",iMaxLen);

	return strlen(sString);
}

/* Reduces the configured URL to the directory PsychoStats lives in, so
 * that both "http://host/stats/" and the "http://host/stats/index.php"
 * that older versions of this plugin wanted keep working. */
ps_baseurl(sDest[],iMaxLen){
	new iPos;

	strcpy(sDest,gsURL,iMaxLen);
	strtrim(sDest," ",2);

	if(strcasestr(sDest,".php")>=0){
		iPos=rindex(sDest,'/');
		sDest[iPos+1]=0;
	}
	else{
		iPos=strlen(sDest);
		if(iPos>0 && sDest[iPos-1]!='/'){
			strcat(sDest,"/",iMaxLen);
		}
	}

	return strlen(sDest);
}

open_motd(sUser[],sSearch[],iRank){
	new sText[2048]="<html>^n<head>^n<meta http-equiv=^"REFRESH^" content=^"0;url=%s^">^n</head>^n<body>^n<div>Searching...</div>^n</body>^n</html>";
	new sURL[MAX_DATA_LENGTH];
	new sBase[MAX_DATA_LENGTH];

	ps_baseurl(sBase,MAX_DATA_LENGTH);

	if(iRank){
		if(giPSVersion==2){
			snprintf(sURL,MAX_DATA_LENGTH,"%sindex.php?q=players",sBase);
		}
		else{
			snprintf(sURL,MAX_DATA_LENGTH,"%sindex.php",sBase);
		}
	}
	else{
		if(giPSVersion==2){
			snprintf(sURL,MAX_DATA_LENGTH,"%sindex.php?q=player&uniqueid=%s",sBase,sSearch);
		}
		else{
			/* 3.x searches on name, authid and ip and redirects straight
			 * to the player page when only one player matches. */
			snprintf(sURL,MAX_DATA_LENGTH,"%sindex.php?q=%s",sBase,sSearch);
		}
	}
	snprintf(sText,2048,sText,sURL);
	motd(sUser,sText);
	execclient(sUser,"escape");

	return PLUGIN_CONTINUE;
}

public plugin_init() {
	new sData[MAX_DATA_LENGTH];

	plugin_registerinfo("BKs Psychostats Plugin","Opens Psychostats in Motd-Window",STRING_VERSION);
	plugin_registercmd("admin_ps_set","admin_ps_set",ACCESS_CONFIG,"admin_ps_set <Option> <Value>: Sets the options of psychostats plugin");
	plugin_registercmd("admin_stats","admin_stats",ACCESS_ALL,"admin_stats <Name>: Shows the stats of a certain person.");
	plugin_registercmd("say","admin_stats",ACCESS_ALL,"");

	if(!get_vaultdata("BK_PS_STATSME",sData,MAX_DATA_LENGTH)){
		set_vaultdata("BK_PS_URL","http://www.example.com/psychostats/");
		set_vaultdata("BK_PS_STATSME","stats");
		strcpy(sData,"stats",MAX_DATA_LENGTH);
		set_vaultdata("BK_PS_STATSRANK","rank");
		set_vaultdata("BK_PS_PREFIX","/");
	}

	load_data();

	return PLUGIN_CONTINUE;
}

extract_string(sData[],sArray[][]){
	new iKeywords;
	new i;

	strtrim(sData," ");
	iKeywords=strcount(sData,' ');

	if(iKeywords!=0){
		for(i=0;i<iKeywords;i++){
			strbreak(sData,sArray[i],sData,MAX_DATA_LENGTH,MAX_COMMAND_LENGTH,MAX_DATA_LENGTH);
		}
	}
	strcpy(sArray[iKeywords],sData,MAX_COMMAND_LENGTH);
	iKeywords++;

	return iKeywords;
}

load_data(){
	new sData[MAX_DATA_LENGTH];

	get_vaultdata("BK_PS_STATSME",sData,MAX_DATA_LENGTH);
	giKeywordsMe=extract_string(sData,gsKeywordsMe);
	get_vaultdata("BK_PS_STATSRANK",sData,MAX_DATA_LENGTH);
	giKeywordsRank=extract_string(sData,gsKeywordsRank);
	get_vaultdata("BK_PS_PREFIX",sData,MAX_DATA_LENGTH);
	giPrefix=extract_string(sData,gsPrefix);
	get_vaultdata("BK_PS_URL",gsURL,MAX_DATA_LENGTH);

	/* Installs that were set up before this plugin knew about 3.x have no
	 * version stored, so assume the version this plugin is written for. */
	if(!get_vaultdata("BK_PS_VERSION",sData,MAX_DATA_LENGTH)){
		set_vaultdata("BK_PS_VERSION","3");
		giPSVersion=3;
	}
	else{
		giPSVersion=strtonum(sData);
		if(giPSVersion!=2){
			giPSVersion=3;
		}
	}

	return PLUGIN_CONTINUE;
}
