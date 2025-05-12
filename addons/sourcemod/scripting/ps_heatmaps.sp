/**
 * =============================================================================
 * SourceMod PsychoStats Plugin
 * Implements support for PsychoStats and enhances game logging to provide more
 * statistics. 
 *
 * This plugin will add "Spatial" stats to mods (just like TF). This allows
 * Heatmaps and trajectories to be created and viewed in the player stats.
 *
 * =============================================================================
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, version 3.0, as published by the
 * Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Version: $Id: ps_heatmaps.sp 411 2008-04-23 18:07:12Z lifo $
 * Author:  Stormtrooper <http://www.psychostats.com/>
 */

#pragma semicolon 1

#include <sourcemod>
#include <logging>
#include <sdktools>

public Plugin:myinfo = 
{
	name = "PsychoStats Spatial Plugin",
	author = "Stormtrooper, K1ller",
	description = "Logs spatial statstics on kill events so Heatmaps can be created.",
	version = "1.1",
	url = "http://www.psychostats.com/"
};

new bool:ignoreKill = true;

public OnPluginStart()
{
	// do not enable on TF2 servers. TF2 natively supports spatial stats
	new String:gameFolder[64];
	GetGameFolderName(gameFolder, sizeof(gameFolder));
	new bool:logSpatial = !(StrEqual(gameFolder, "tf"));

	if (logSpatial) {
		HookEvent("player_death", Event_PlayerDeath);
		AddGameLogHook(LogEvent);
	}
}


// grab all log events as they are written to the game logs ...
public Action:LogEvent(const String:message[]) {
	// lookout for "killed" and "committed suicide" events.
	// This is not the desired way to do this, but I can't find another way
	// to more accurately do it. We can't stop a log event from logging
	// within the log event itself, so we have to override it here.
	if (StrContains(message, ">\" killed \"") > 0 ||
	    StrContains(message, "\" committed suicide with \"") > 0) {
		if (ignoreKill) {
			// Do not log the current event.
			// Event_PlayerDeath will trigger next and log a 'killed' event instead
			return Plugin_Handled;
		} else {
			// Ignore the next kill event that comes in.
			ignoreKill = true;
		}
	}
	return Plugin_Continue;
}

public Action:Event_PlayerDeath(Handle:event, const String:name[], bool:dontBroadcast)
{
	// Reset our state so we don't ignore the next kill.
	ignoreKill = false;

	/* Get player IDs */
        new victimId = GetEventInt(event, "userid");
        new attackerId = GetEventInt(event, "attacker");
	new bool:suicide = false;

	suicide = (victimId == attackerId);

	/* Get both players' location coordinates */
        new Float:victimLocation[3];
        new Float:attackerLocation[3];
        new victim = GetClientOfUserId(victimId);
        new attacker = GetClientOfUserId(attackerId);

	if(!attacker) return Plugin_Continue; // World is the attacker

        GetClientAbsOrigin(victim, victimLocation);
        GetClientAbsOrigin(attacker, attackerLocation);

	/* Get weapon */
        decl String:weapon[64];
        GetEventString(event, "weapon", weapon, sizeof(weapon));

	/* Is headshot? */
        new bool:headshot = GetEventBool(event, "headshot");

	/* Get both players' name */
	decl String:attackerName[64];
	decl String:victimName[64];
	GetClientName(attacker, attackerName, sizeof(attackerName));
	GetClientName(victim, victimName, sizeof(victimName));

	/* Get both players' SteamIDs */
	decl String:attackerSteamId[64];
	decl String:victimSteamId[64];
	GetClientAuthString(attacker, attackerSteamId, sizeof(attackerSteamId));
	GetClientAuthString(victim, victimSteamId, sizeof(victimSteamId));

	/* Get both players' teams */
	decl String:attackerTeam[64];
	decl String:victimTeam[64];
	GetTeamName(GetClientTeam(attacker), attackerTeam, sizeof(attackerTeam));
	GetTeamName(GetClientTeam(victim), victimTeam, sizeof(victimTeam));

	if (suicide) {
	       	LogToGame("\"%s<%d><%s><%s>\" committed suicide with \"%s\" (attacker_position \"%d %d %d\")", 
	 		victimName,
	 		victimId,
	 		victimSteamId,
	 		victimTeam,
	 		weapon,
	       		RoundFloat(attackerLocation[0]),
	       		RoundFloat(attackerLocation[1]),
	       		RoundFloat(attackerLocation[2])
		);
	} else {
	       	LogToGame("\"%s<%d><%s><%s>\" killed \"%s<%d><%s><%s>\" with \"%s\" %s(attacker_position \"%d %d %d\") (victim_position \"%d %d %d\")", 
			attackerName,
			attackerId,
	 		attackerSteamId,
	 		attackerTeam,
	 		victimName,
	 		victimId,
	 		victimSteamId,
	 		victimTeam,
	 		weapon,
			((headshot) ? "(headshot) " : ""),
	 		RoundFloat(attackerLocation[0]),
	       		RoundFloat(attackerLocation[1]),
	       		RoundFloat(attackerLocation[2]),
	       		RoundFloat(victimLocation[0]),
	       		RoundFloat(victimLocation[1]),
	       		RoundFloat(victimLocation[2])
		);
	}
	return Plugin_Continue;
}
