"""
* =============================================================================
* EventScripts PsychoStats Plugin
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
* FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
* details.
*
* You should have received a copy of the GNU General Public License along with
* this program. If not, see <http://www.gnu.org/licenses/>.
*
* Version: $Id: ps_heatmaps.py 411 2008-04-23 18:07:12Z lifo $
* Author:  K1ller
"""

import es

spat_team1 = "Team1"
spat_team2 = "Team2"
spat_team3 = "Team3"
spat_team4 = "Team4"
spat_game = ""

def initGameTeams(spat_game):
 # Look gametypes.txt for reference
 # Counter-Strike & Counter-Strike: Source
 global spat_team1, spat_team2
 if (spat_game.find("Counter-Strike") != -1):
  spat_team1 = "TERRORIST" # Terrorist team index 3
  spat_team2 = "CT" # CT team index 4

 # Day of Defeat & Day of Defeat: Source
 if (spat_game.find("Day of Defeat") != -1):
  spat_team1 = "Allies" # Allies team index 3
  spat_team2 = "Axis" # Axis team index 4

 # Firearms
 if (spat_game.find("Firearms") != -1):
  spat_team1 = "Red Force" # Red Force team index 3
  spat_team2 = "Blue Force" # Blue Force team index 4

 # Team Fortress Classic
 if (spat_game.find("Team Fortress Classic") != -1):
  spat_team1 = "Red" # Red team index 3
  spat_team2 = "Blue" # Blue team index 4
  spat_team3 = "Green" # Red team index 5
  spat_team4 = "Yellow" # Blue team index 6

 # Deathmatch & Team Deathmatch
 if (spat_game.find("Deathmatch") != -1):
  if (es.mp_teamplay == 1):
   spat_team1 = "REBEL" # Team index 0 for all players
   spat_team2 = "REBEL" # Team index 0 for all players
  else:
   spat_team1 = "COMBINE" # Team index 2
   spat_team2 = "REBEL" # Team index 3

 # Half-Life 2 CTF
 if (spat_game.find("Half-Life 2 CTF") != -1):
   spat_team1 = "Combine" # Combine team index 3
   spat_team2 = "Rebel" # Rebel team index 4

 # Team Fortress
 if (spat_game.find("Team Fortress") != -1):
   spat_team1 = "Blue" # Blue team index 3
   spat_team2 = "Red" # Red team index 4


def load():
  global spat_game
  spat_game = es.getgame()
  initGameTeams(spat_game)
  es.msg(initGameTeams(spat_game))


def player_death(event_var):
 # Log event samples for player_death events
 # [KTRAJ] "k1ller<9><STEAM_ID_LAN><CT>" killed "Alfred<15><BOT><TERRORIST>" with "deagle" (headshot) (killer_position "926 1286 32") (victim_position "204 1179 83")
 # [KTRAJ] "k1ller<8><STEAM_ID_LAN><CT>" committed suicide with "hegrenade"

 global spat_game

 # If TF2 then do nothing as TF2 has this logging by default
 if (spat_game == "Team Fortress"):
   return 0

 # Get attacker userid
 spat_attackerId = es.event_var['attacker']

 # Get victim userid
 spat_victimId = event_var['userid']

 # Get attacker name
 spat_attackerName = es.getplayername(event_var['attacker'])

 # Get victim name
 spat_victimName = es.getplayername(event_var['userid'])

 # Get attacker SteamID
 spat_attackerSteamId = event_var['es_attackersteamid']

 # Get victim SteamID
 spat_victimSteamId = event_var['es_steamid']

 # Get attacker team index
 spat_attackerTeamId = event_var['es_attackerteam']

 # Get victim team index
 spat_victimTeamId = event_var['es_userteam']

 # Set team names according to current mod
 if(spat_attackerTeamId == 1):
   spat_attackerTeam = spat_team1
   spat_victimTeam = spat_team2
 else:
   spat_attackerTeam = spat_team2
   spat_victimTeam = spat_team1

 # Get weapon
 spat_weapon = event_var['weapon']

 # Get headshot
 spat_headshot = event_var['headshot']

 # Get location for attacker
 spat_attackerX, spat_attackerY, spat_attackerZ = es.getplayerlocation(spat_attackerId)

 # Get location for victim
 spat_victimX, spat_victimY, spat_victimZ = es.getplayerlocation(spat_victimId)

 # Form log event
 if(spat_attackerTeamId != spat_victimTeamId):
  logLine = "[KTRAJ] \"%s<%s><%s><%s>\" killed \"%s<%s><%s><%s>\" with \"%s\" %s(attacker_position \"%d %d %d\") (victim_position \"%d %d %d\")" % (spat_attackerName,
        spat_attackerId,
        spat_attackerSteamId,
        spat_attackerTeam,
        spat_victimName,
        spat_victimId,
        spat_victimSteamId,
        spat_victimTeam,
        spat_weapon,
        ('', '(headshot) ')[spat_headshot == '1'],
        spat_attackerX,
        spat_attackerY,
        spat_attackerZ,
        spat_victimX,
        spat_victimY,
        spat_victimZ)
 else:
  logLine = "[KTRAJ] \"%s<%s><%s><%s>\" committed suicide with \"%s\" (attacker_position \"%d %d %d\")" % (spat_attackerName,
        spat_attackerId,
        spat_attackerSteamId,
        spat_attackerTeam,
        spat_weapon,
        spat_attackerX,
        spat_attackerY,
        spat_attackerZ)

 es.server.cmd('es_xlogq '+logLine)
