#	This file is part of PsychoStats.
#
#	Written by Jason Morriss
#	Copyright 2008 Jason Morriss
#
#	PsychoStats is free software: you can redistribute it and/or modify
#	it under the terms of the GNU General Public License as published by
#	the Free Software Foundation, either version 3 of the License, or
#	(at your option) any later version.
#
#	PsychoStats is distributed in the hope that it will be useful,
#	but WITHOUT ANY WARRANTY; without even the implied warranty of
#	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#	GNU General Public License for more details.
#
#	You should have received a copy of the GNU General Public License
#	along with PsychoStats.  If not, see <http://www.gnu.org/licenses/>.
#
#	$Id: firearms.pm 493 2008-06-17 11:26:35Z lifo $
#
package PS::Game::halflife::firearms;

use strict;
use warnings;
use base qw( PS::Game::halflife );

use util qw( :net );

our $VERSION = '1.00';
our $RF_SCORE = 0;
our $BF_SCORE = 0;


sub _init { 
	my $self = shift;
	$self->SUPER::_init;

	return $self;
}

# player teams are now detected from their player signature for all events.
# the only reason we need this event now is to add 1 to the proper 'joined' stat.
sub event_joined_team {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $team, $props) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	my $m = $self->get_map;
	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};
	
	my $normal_team = $self->team_normal($team);
	
	$p1->team($normal_team);
	$p1->{basic}{lasttime} = $timestamp;

	# now for the all-important stat... how many times we joined this team.
	if ($normal_team) {
		$p1->{mod_maps}{ $m->{mapid} }{'joined' . $normal_team}++;
		$p1->{mod}{'joined' . $normal_team}++;
		$m->{mod}{'joined' . $normal_team}++;
	}
}

sub event_kill {
	my ($self, $timestamp, $args) = @_;
	my ($killer, $victim, $weapon, $propstr) = @$args;
	my $p1 = $self->get_plr($killer) || return;
	my $p2 = $self->get_plr($victim) || return;
	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};
	$self->_do_connected($timestamp, $p2) unless $p2->{_connected};

	$p1->{basic}{lasttime} = $timestamp;
	$p2->{basic}{lasttime} = $timestamp;
	return unless $self->minconnected;
	return if $self->isbanned($p1) or $self->isbanned($p2);

	my $m = $self->get_map;
	my $props = $self->parseprops($propstr);

	my $w = $self->get_weapon($weapon);

	# I directly access the player variables in the objects (bad OO design), 
	# but the speed advantage is too great to do it the "proper" way.

	$p1->update_streak('kills', 'deaths');
	$p1->{basic}{kills}++;
	$p1->{mod}{ $p1->{team} . "kills"}++ if $p1->{team};		# Kills while ON the team
#	$p1->{mod}{ $p2->{team} . "kills"}++;				# Kills against the team
	$p1->{mod_maps}{ $m->{mapid} }{ $p1->{team} . "kills"}++ if $p1->{team};
	$p1->{weapons}{ $w->{weaponid} }{kills}++;
	$p1->{maps}{ $m->{mapid} }{kills}++;
	$p1->{victims}{ $p2->{plrid} }{kills}++;

	$p2->{isdead} = 1;
	$p2->update_streak('deaths', 'kills');
	$p2->{basic}{deaths}++;
	$p2->{mod}{ $p2->{team} . "deaths"}++ if $p2->{team};		# Deaths while ON the team
#	$p2->{mod}{ $p1->{team} . "deaths"}++;				# Deaths against the team
	$p2->{mod_maps}{ $m->{mapid} }{ $p2->{team} . "deaths"}++ if $p2->{team};
	$p2->{weapons}{ $w->{weaponid} }{deaths}++;
	$p2->{maps}{ $m->{mapid} }{deaths}++;
	$p2->{victims}{ $p1->{plrid} }{deaths}++;
#	$p2->{roundtime} = $self->{roundstart} ? $timestamp - $self->{roundstart} : undef;

	$m->{basic}{lasttime} = $timestamp;
	$m->{basic}{kills}++;
	$m->{mod}{ $p1->{team} . 'kills'}++ if $p1->{team};		# kills on the team
	$m->hourly('kills', $timestamp);

	$w->{basic}{kills}++;

	# check for spatial stats on this event
	if ($props->{attacker_position}) {
		$m->spatial(
			$self, 
			$p1, $props->{attacker_position}, 
			$p2, $props->{victim_position},
			$w, $props->{headshot}
		);
	}
	
	# calculate new skill values for the players
	my $skill_handled = 0;
	$self->calcskill_kill_func($p1, $p2, $w) unless $skill_handled;
}

# Firearms specific team kill.
# This isn't working properly.
sub event_firearms_ffkill {
	my ($self, $timestamp, $args) = @_;
	my ($killer, $victim, $propstr) = @$args;
	my $p1 = $self->get_plr($killer) || return;
	my $p2 = $self->get_plr($victim) || return;
	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};
	$self->_do_connected($timestamp, $p2) unless $p2->{_connected};
	
	$p1->{basic}{lasttime} = $timestamp;
	$p2->{basic}{lasttime} = $timestamp;
	return unless $self->minconnected;
	return if $self->isbanned($p1) or $self->isbanned($p2);

	my $m = $self->get_map;
	my $props = $self->parseprops($propstr);

	# I directly access the player variables in the objects (bad OO design), 
	# but the speed advantage is too great to do it the "proper" way.

#	$p1->update_streak('kills', 'deaths');
#	$p1->{basic}{kills}++;
#	$p1->{mod}{ $p1->{team} . "kills"}++ if $p1->{team};		# Kills while ON the team
#	$p1->{mod}{ $p2->{team} . "kills"}++;				# Kills against the team
#	$p1->{mod_maps}{ $m->{mapid} }{ $p1->{team} . "kills"}++ if $p1->{team};
#	$p1->{maps}{ $m->{mapid} }{kills}++;
#	$p1->{victims}{ $p2->{plrid} }{kills}++;

	$p2->{isdead} = 1;
#	$p2->update_streak('deaths', 'kills');
#	$p2->{basic}{deaths}++;
#	$p2->{mod}{ $p2->{team} . "deaths"}++ if $p2->{team};		# Deaths while ON the team
#	$p2->{mod}{ $p1->{team} . "deaths"}++;				# Deaths against the team
#	$p2->{mod_maps}{ $m->{mapid} }{ $p2->{team} . "deaths"}++ if $p2->{team};
#   $p2->{maps}{ $m->{mapid} }{deaths}++;
#	$p2->{victims}{ $p1->{plrid} }{deaths}++;
#	$p2->{roundtime} = $self->{roundstart} ? $timestamp - $self->{roundstart} : undef;

	$m->{basic}{lasttime} = $timestamp;
#	$m->{basic}{kills}++;
#	$m->{mod}{ $p1->{team} . 'kills'}++ if $p1->{team};		# kills on the team
#	$m->hourly('kills', $timestamp);
    
	if (($p1->{team} and $p2->{team}) and ($p1->{team} eq $p2->{team})) {
        $p1->{maps}{ $m->{mapid} }{ffkills}++;
        $p1->{basic}{ffkills}++;

        $p2->{maps}{ $m->{mapid} }{ffdeaths}++;
        $p2->{basic}{ffdeaths}++;

        $m->{basic}{ffkills}++;

        $self->plrbonus('ffkill', 'enactor', $p1);
    }
}

sub event_plrtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $trigger, $plrstr2, $propstr) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	my $p2 = undef;
	return if $self->isbanned($p1);

	$p1->{basic}{lasttime} = $timestamp;
	return unless $self->minconnected;
	my $m = $self->get_map;

	my @vars1 = ();
	my @vars2 = ();
	my $value1 = 1;
	my $value2 = 1;
	$trigger = lc $trigger;
	$self->plrbonus($trigger, 'enactor', $p1);
	if ($trigger eq 'weaponstats' or $trigger eq 'weaponstats2') {
		$self->event_weaponstats($timestamp, [$plrstr, $trigger, $propstr]);

	} elsif ($trigger eq 'address') {	# PIP 'address' events
		my $props = $self->parseprops($propstr);
		return unless $p1->{uid} and $props->{address};
		$self->{ipcache}{$p1->{uid}} = ip2int($props->{address});

	} elsif ($trigger eq 'bandage') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'bandage' );
		$self->plrbonus('medic_heal', 'enactor', $p1);

	} elsif ($trigger eq 'adrenaline') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'bandage' );
		$self->plrbonus('medic_heal', 'enactor', $p1);

	} elsif ($trigger eq 'suture') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'bandage' );
		$self->plrbonus('medic_heal', 'enactor', $p1);

	} elsif ($trigger eq 'splint') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'bandage' );
		$self->plrbonus('medic_heal', 'enactor', $p1);

	} elsif ($trigger eq 'treat concussion') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'bandage' );
		$self->plrbonus('medic_heal', 'enactor', $p1);

	} elsif ($trigger eq 'medevac') {
		@vars1 = ( 'medevac' );

	} elsif ($trigger eq 'capturepoint') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'howitzer ammo') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'targetting pack') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'red intelligence') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'blue intelligence') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'secret documents') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'destroyobject') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	# ignore the following triggers for now
	} elsif ($trigger eq 'revive') {
	} elsif ($trigger eq 'pickupobjective') {
	} elsif ($trigger eq 'loseobjective') {
	} elsif ($trigger eq 'givebandage') {
	} elsif ($trigger eq 'hospice') {
	} elsif ($trigger eq 'buildmortar') {
	} elsif ($trigger eq 'artilerymarker') {
	} elsif ($trigger eq 'requestartilery') {
	} elsif ($trigger eq 'dismantle') {

# ---------

	# extra statsme / amx triggers
	} elsif ($trigger =~ /^(time|latency|amx_|game_idle_kick)/) {

	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown player trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
	}

	# allow bonuses on the raw trigger event
	$self->plrbonus($trigger, 'enactor', $p1);

	foreach my $var (@vars1) {
		$p1->{mod_maps}{ $m->{mapid} }{$var} += $value1;
		$p1->{mod}{$var} += $value1;
		$m->{mod}{$var} += $value1;
	}

	if (ref $p2) {
		foreach my $var (@vars2) {
			$p2->{mod_maps}{ $m->{mapid} }{$var} += $value2;
			$p2->{mod}{$var} += $value2;
			# don't bump global map stats here; do it for $p1 above
		}
	}
}

sub event_teamtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($team, $trigger, $props) = @$args;
	return unless $self->minconnected;
	my $m = $self->get_map;
	my $rf = $self->get_team('red_force', 1);
	my $bf = $self->get_team('blue_force', 1);
	my ($p1, $p2, $red_forcevar, $blue_forcevar, $enactor_team, $victim_team);

	$team = lc $team;
	$team =~ tr/ /_/;				# convert spaces to _ on team names (some mods are known to do this)
	$team =~ tr/a-z0-9_//cs;			# remove all non-alphanumeric characters
	$trigger = lc $trigger;
    
	# Ignore for now
	# getting somewhere
	if ($trigger eq 'teamgoal') {
		return unless $team eq 'red_force' or $team eq 'blue_force';
		
		if ($team eq 'red_force') {
            $RF_SCORE++;
        } else {
            $BF_SCORE++;
        }
		
	} elsif ($trigger eq "capturedallpoints") {
		return unless $team eq 'red_force' or $team eq 'blue_force';
		my $team2 = $team eq 'blue_force' ? 'red_force' : 'blue_force';
		my $winners = $self->get_team($team, 1);
		my $losers  = $self->get_team($team2, 1);
		my $var = $team . 'won';
		my $var2 = $team2 . 'lost';
		$self->plrbonus($trigger, 'enactor_team', $winners, 'victim_team', $losers);
		foreach my $p1 (@$winners) {
			$p1->{basic}{rounds}++;
			$p1->{maps}{ $m->{mapid} }{basic}{rounds}++;
			$p1->{mod_maps}{ $m->{mapid} }{$var}++;
			$p1->{mod}{$var}++;
		}
		foreach my $p1 (@$losers) {
			$p1->{basic}{rounds}++;
			$p1->{maps}{ $m->{mapid} }{basic}{rounds}++;
			$p1->{mod_maps}{ $m->{mapid} }{$var2}++;
			$p1->{mod}{$var2}++;
		}
		$m->{mod}{$var}++;
		$m->{mod}{$var2}++;
		$m->{basic}{rounds}++;
        
        # Reset round start.
        $self->{roundstart} = 0;
		
	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown team trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
		return;		# return here so we don't calculate the 'won/lost' points below
	}

	$self->plrbonus($trigger, 'enactor_team', $enactor_team, 'victim_team', $victim_team);
}

# override default event so we can reset per-log variables
sub event_logstartend {
	my ($self, $timestamp, $args) = @_;
	my ($startedorclosed) = @$args;
	$self->SUPER::event_logstartend($timestamp, $args);
	
	if (lc $startedorclosed eq 'started') {
        $self->save(1);
        return;
    }
    
	my ($team, $numplrs) = @$args;
	$team = lc $team;

	my $m = $self->get_map;
	my $teams = {
		red_force	=> $self->get_team('red_force', 1) || [],
		blue_force	=> $self->get_team('blue_force',   1) || [],
	};

#	print "allies = " . scalar(@{$self->get_team('allies', 1)}) . "\n";
#	print "axis   = " . scalar(@{$self->get_team('axis', 1)}) . "\n";

	# increase everyone's rounds
	$m->{basic}{rounds}++;
	for (@{$teams->{red_force}}, @{$teams->{red_force}}) {
		$_->{basic}{rounds}++;
		$_->{maps}{ $m->{mapid} }{rounds}++;
	}

	# determine who won and lost
	my ($won, $lost, $teamwon, $teamlost);
	if ($RF_SCORE > $BF_SCORE) {
		$teamwon  = 'red_force';
		$teamlost = 'blue_force';
		$won  = $teams->{ $teamwon };
		$lost = $teams->{ $teamlost };
	} elsif ($BF_SCORE > $RF_SCORE) {
		$teamwon  = 'blue_force';
		$teamlost = 'red_force';
		$won  = $teams->{ $teamwon };
		$lost = $teams->{ $teamlost };
	} else {
		# do mot count 'draws'
	}

	# clear the previous team scores
    $RF_SCORE = 0;
    $BF_SCORE = 0;

	return unless $teamwon;	# no one 'won'; it's a draw.

	# assign won/lost values to all players
	$m->{mod}{$teamwon  . 'won'}++;
	$m->{mod}{$teamlost . 'lost'}++;
	for (@$won) {
		$_->{mod}{$teamwon.'won'}++;
		$_->{mod_maps}{ $m->{mapid} }{$teamwon.'won'}++;
	}
	for (@$lost) {
		$_->{mod}{$teamlost.'lost'}++;
		$_->{mod_maps}{ $m->{mapid} }{$teamlost.'lost'}++;
	}
	$self->plrbonus('round_win', 'enactor_team', $won, 'victim_team', $lost);
}

sub has_mod_tables { 1 }

1;
