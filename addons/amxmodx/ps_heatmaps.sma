/* - - - - - - - - - - -

        AMX Mod X script.

          | Author  : Stormtrooper
          | Plugin  : PsychoStats Plugin ( HL1 )
          | Version : $Id: ps_heatmaps.sma 412 2008-04-23 18:07:52Z lifo $

        (!) Adapted on HL1 : Arkshine

        This program is free software; you can redistribute it and/or modify it
        under the terms of the GNU General Public License as published by the
        Free Software Foundation; either version 2 of the License, or (at
        your option) any later version.

        This program is distributed in the hope that it will be useful, but
        WITHOUT ANY WARRANTY; without even the implied warranty of
        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
        General Public License for more details.

        You should have received a copy of the GNU General Public License
        along with this program; if not, write to the Free Software Foundation,
        Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA

        ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~
        
        Introduction :
        - - - - - - - -
        
            This plugin will add "Spatial" stats to mods (just like TF). This allows
            Heatmaps and trajectories to be created and viewed in the player stats.
 
- - - - - - - - - - - */
    
    #pragma semicolon 1

    #include <amxmodx>
    #include <fakemeta>
    
    
    #if !defined charsmax
       #define charsmax(%1)  sizeof ( %1 ) - 1
    #endif
    
    
    public plugin_init ()
    {
        register_plugin ( "PsychoStats Plugin", "1.1", "Stormtrooper" );
        register_forward ( FM_AlertMessage, "fwd_AlertMessage" );
    }


    public fwd_AlertMessage ( AlertType:i_Type, s_Message[] )
    {
        if ( i_Type != at_logged )
        {
            return FMRES_IGNORED;
        }

        static i_Pos;
        i_Pos = contain ( s_Message, ">^" committed suicide " );

        if ( i_Pos != -1 )
        {
            new i_Victim;
            GetUserId ( s_Message, i_Pos, _, i_Victim );

            new v_OriginKiller[ 3 ];
            get_user_origin ( find_player ( "k", i_Victim ), v_OriginKiller );

            new s_NewMessage[ 192 ]; trim ( s_Message );
            formatex ( s_NewMessage, charsmax ( s_NewMessage ), "%s (attacker_position ^"%d %d %d^")^n", s_Message, v_OriginKiller[ 0 ], v_OriginKiller[ 1 ], v_OriginKiller[ 2 ] );
            
            engfunc ( EngFunc_AlertMessage, AlertType:at_logged, s_NewMessage );
            return FMRES_SUPERCEDE;
        }

        i_Pos = contain ( s_Message, ">^" killed ^"" );

        if ( i_Pos != -1 )
        {
            new i_Killer, i_Victim;
            GetUserId ( s_Message, i_Pos, i_Killer, i_Victim );

            static v_OriginVictim[ 3 ], v_OriginKiller[ 3 ];
            get_user_origin ( find_player ( "k", i_Victim ), v_OriginVictim );
            get_user_origin ( find_player ( "k", i_Killer ), v_OriginKiller );

            static s_NewMessage[ 256 ]; trim ( s_Message );
            formatex ( s_NewMessage, charsmax ( s_NewMessage ), "%s (attacker_position ^"%d %d %d^") (victim_position ^"%d %d %d^")^n", s_Message, v_OriginKiller[ 0 ], v_OriginKiller[ 1 ], v_OriginKiller[ 2 ], v_OriginVictim[ 0 ], v_OriginVictim[ 1 ], v_OriginVictim[ 2 ] );
           
            engfunc ( EngFunc_AlertMessage, AlertType:at_logged, s_NewMessage );
            return FMRES_SUPERCEDE;
        }

        return FMRES_IGNORED;
    }


    GetUserId ( const s_Message[], const i_Pos, &i_Killer = 0, &i_Victim = 0 )
    {
        static s_TempMsg[ 256 ];
        CopyArray ( s_TempMsg, s_Message );

        new i_Len = GetTextLength ( s_Message );
        new i_Found;

        while ( i_Len-- )
        {
            if ( s_Message[ i_Len ] != '<' )
            {
                continue;
            }

            switch ( ++i_Found )
            {
                case 3 :
                {
                    s_TempMsg[ SearchCharPos ( s_Message, i_Len, '>' ) ] = '^0';
                    i_Victim = str_to_num ( s_TempMsg[ i_Len + 1 ] );
                }
                case 6 :
                {
                    s_TempMsg[ SearchCharPos ( s_Message, i_Len, '>' ) ] = '^0';
                    i_Killer = str_to_num ( s_TempMsg[ i_Len + 1 ] );
                }
                default :
                {
                    if ( i_Victim && i_Len > i_Pos )
                    {
                        --i_Found;
                    }
                }
            }
        }
    }


    SearchCharPos ( const s_Message[], const i_Pos, const s_Char )
    {
        new j = i_Pos + 1;
        while ( s_Message[ j++ ] != s_Char ) {}

        return j;
    }


    GetTextLength ( const s_Message[] )
    {
        new i;
        while ( s_Message[ i++ ] != '^0' ) {}

        return i - 1;
    }
    
    
    CopyArray ( s_Dest[], const s_Source[] )
    {
        new i;
        while ( s_Source[ i++ ] != '^0' )
        {
            s_Dest[ i ] = s_Source[ i ];
        }
    }
