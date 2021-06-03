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
#	$Id: tfc.pm 493 2008-06-17 11:26:35Z lifo $
#
package PS::Game::halflife::tfc;

use strict;
use warnings;
use base qw( PS::Game::halflife );

use util qw( :net );

our $VERSION = '1.00';


sub _init { 
	my $self = shift;
	$self->SUPER::_init;

	return $self;
}

# override default event so we can reset per-log variables
sub event_logstartend {
	my ($self, $timestamp, $args) = @_;
	my ($startedorclosed) = @$args;
	$self->SUPER::event_logstartend($timestamp, $args);

	return unless lc $startedorclosed eq 'started';
	
    $self->save(1);
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
		$self->event_weaponstats($timestamp, $args);

	} elsif ($trigger eq 'address') {	# PIP 'address' events
		my $props = $self->parseprops($propstr);
		return unless $p1->{uid} and $props->{address};
		$self->{ipcache}{$p1->{uid}} = ip2int($props->{address});
	
	# a bonus for regular medic heals was too much
	} elsif ($trigger eq 'medic_heal') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'bandage' );
		$self->plrbonus('medic_heal', 'enactor', $p1);

	} elsif ($trigger eq 'medic_cured_hallucinations') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'bandage' );
		$self->plrbonus('medic_heal', 'enactor', $p1);

	} elsif ($trigger eq 'medic_cured_infection') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'bandage' );
		$self->plrbonus('medic_heal', 'enactor', $p1);

	} elsif ($trigger eq 'medic_doused_fire') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'bandage' );
		$self->plrbonus('medic_heal', 'enactor', $p1);

	} elsif ($trigger eq 'built_dispenser') {
		@vars1 = ( 'structuresbuilt' );

	} elsif ($trigger eq 'teleporter_entrance_finished') {
		@vars1 = ( 'structuresbuilt' );

	} elsif ($trigger eq 'teleporter_exit_finished') {
		@vars1 = ( 'structuresbuilt' );

	} elsif ($trigger eq 'dispenser_destroyed' ) {
		@vars1 = ( 'structuresdestroyed' );

	} elsif ($trigger eq 'sentry_destroyed' ) {
		@vars1 = ( 'structuresdestroyed' );

	} elsif ($trigger eq 'teleporter_entrance_destroyed') {
		@vars1 = ( 'structuresdestroyed' );

	} elsif ($trigger eq 'teleporter_exit_destroyed') {
		@vars1 = ( 'structuresdestroyed' );
    
	} elsif ($trigger eq 'red 1 capture point') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq 'blue 1 capture point') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'red 2 capture point') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'blue 2 capture point') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'red 3 capture point') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'blue 3 capture point') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'capture point 1') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'capture point 2') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'capture point 3') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq 'team 1 dropoff') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'team 2 dropoff') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'red 1 on') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'blue 1 on') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'red 2 on') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'blue 2 on') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq 'red 3 on') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'blue 3 on') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq '#cz_rcap1') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq '#cz_bcap1') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq '#cz_rcap2') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq '#cz_bcap2') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq '#cz_rcap3') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq '#cz_bcap3') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq '#cz_rcap4') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq '#cz_bcap4') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq '#cz_rcap5') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq '#cz_bcap5') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq 'team 1 dropoff') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'team 2 dropoff') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'flag 1 red') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'flag 1 blue') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq 'flag 2 red') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'flag 2 blue') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq 'flag 3 red') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'flag 3 blue') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq '#set cp1 to red') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq '#set cp1 to blue') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq '#set cp2 to red') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq '#set cp2 to blue') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq '#set cp3 to red') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq '#set cp3 to blue') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq '#set cp4 to red') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq '#set cp4 to blue') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq '#set cp5 to red') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq '#set cp5 to blue') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'goalitem') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq 'rcave1') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'bcave1') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'rhand') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq 'bhand') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'rblock') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq 'bblock') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq 'rrise1') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'brise1') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'rrise2') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq 'brise2') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'rrise3') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);

	} elsif ($trigger eq 'brise3') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq '#dustbowl_blue_secures_one') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq '#dustbowl_blue_secures_two') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'capturepoint' );
		$self->plrbonus('capturepoint', 'enactor', $p1);
		
	} elsif ($trigger eq '#remove_rdebris_bit') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'mapspecial' );
		$self->plrbonus('mapspecial', 'enactor', $p1);
		
	} elsif ($trigger eq '#remove_bdebris_bit') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'mapspecial' );
		$self->plrbonus('mapspecial', 'enactor', $p1);
		
	} elsif ($trigger eq 'rholedet') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'mapspecial' );
		$self->plrbonus('mapspecial', 'enactor', $p1);
		
	} elsif ($trigger eq 'bholedet') {
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'mapspecial' );
		$self->plrbonus('mapspecial', 'enactor', $p1);
		
	} elsif ($trigger eq 'forced respawn') {
        my ($self, $timestamp, $args) = @_;
        my ($team, $score, $numplrs) = @$args;
        $team = lc $team;
		
        my $m = $self->get_map;
        my $teams = {
            red	=> $self->get_team('red', 1) || [],
            blue	=> $self->get_team('blue',   1) || [],
        };

        # increase everyone's rounds
        $m->{basic}{rounds}++;
        for (@{$teams->{red}}, @{$teams->{blue}}) {
            $_->{basic}{rounds}++;
            $_->{maps}{ $m->{mapid} }{rounds}++;
        }
        
        # determine who won and lost
        my ($won, $lost, $teamwon, $teamlost);
        if ($team eq 'red') {
            $teamwon  = 'red';
            $teamlost = 'blue';
            $won  = $teams->{ $teamwon };
            $lost = $teams->{ $teamlost };
        } else {
            $teamwon  = 'blue';
            $teamlost = 'red';
            $won  = $teams->{ $teamwon };
            $lost = $teams->{ $teamlost };
        }
        
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
		
	} elsif ($trigger eq '#red_wins_forcerespawn') {
        my ($self, $timestamp, $args) = @_;
        my ($team, $score, $numplrs) = @$args;
        $team = lc $team;
		
        my $m = $self->get_map;
        my $teams = {
            red	=> $self->get_team('red', 1) || [],
            blue	=> $self->get_team('blue',   1) || [],
        };

        # increase everyone's rounds
        $m->{basic}{rounds}++;
        for (@{$teams->{red}}, @{$teams->{blue}}) {
            $_->{basic}{rounds}++;
            $_->{maps}{ $m->{mapid} }{rounds}++;
        }
        
        # determine who won and lost
        my ($won, $lost, $teamwon, $teamlost);
		$teamwon  = 'red';
		$teamlost = 'blue';
		$won  = $teams->{ $teamwon };
		$lost = $teams->{ $teamlost };
        
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
		
	} elsif ($trigger eq '#blue_wins_forcerespawn') {
        my ($self, $timestamp, $args) = @_;
        my ($team, $score, $numplrs) = @$args;
        $team = lc $team;
		
        my $m = $self->get_map;
        my $teams = {
            red	=> $self->get_team('red', 1) || [],
            blue	=> $self->get_team('blue',   1) || [],
        };

        # increase everyone's rounds
        $m->{basic}{rounds}++;
        for (@{$teams->{red}}, @{$teams->{blue}}) {
            $_->{basic}{rounds}++;
            $_->{maps}{ $m->{mapid} }{rounds}++;
        }
        
        # determine who won and lost
        my ($won, $lost, $teamwon, $teamlost);
		$teamwon  = 'blue';
		$teamlost = 'red';
		$won  = $teams->{ $teamwon };
		$lost = $teams->{ $teamlost };
        
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
        
	} elsif ($trigger eq 'call red endgame check 3') {
        my ($self, $timestamp, $args) = @_;
        my ($team, $score, $numplrs) = @$args;
        $team = lc $team;
		
        my $m = $self->get_map;
        my $teams = {
            red	=> $self->get_team('red', 1) || [],
            blue	=> $self->get_team('blue',   1) || [],
        };

        # increase everyone's rounds
        $m->{basic}{rounds}++;
        for (@{$teams->{red}}, @{$teams->{blue}}) {
            $_->{basic}{rounds}++;
            $_->{maps}{ $m->{mapid} }{rounds}++;
        }
        
        # determine who won and lost
        my ($won, $lost, $teamwon, $teamlost);
		$teamwon  = 'red';
		$teamlost = 'blue';
		$won  = $teams->{ $teamwon };
		$lost = $teams->{ $teamlost };
        
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
		
	} elsif ($trigger eq 'call blue endgame check 3') {
        my ($self, $timestamp, $args) = @_;
        my ($team, $score, $numplrs) = @$args;
        $team = lc $team;
		
        my $m = $self->get_map;
        my $teams = {
            red	=> $self->get_team('red', 1) || [],
            blue	=> $self->get_team('blue',   1) || [],
        };

        # increase everyone's rounds
        $m->{basic}{rounds}++;
        for (@{$teams->{red}}, @{$teams->{blue}}) {
            $_->{basic}{rounds}++;
            $_->{maps}{ $m->{mapid} }{rounds}++;
        }
        
        # determine who won and lost
        my ($won, $lost, $teamwon, $teamlost);
		$teamwon  = 'blue';
		$teamlost = 'red';
		$won  = $teams->{ $teamwon };
		$lost = $teams->{ $teamlost };
        
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
		
    # this code is not working as hoped, a team bonus is not being assigned
	} elsif ($trigger eq '#hunted_target_scores') {
        
        # this gives the hunted a bonus
		$p1 = $self->get_plr($plrstr);
        @vars1 = ( 'huntedescapes' );
		$self->plrbonus('huntedescapes', 'enactor', $p1);
		
        my ($self, $timestamp, $args) = @_;
        my ($team, $score, $numplrs) = @$args;
        $team = lc $team;
        
        my $m = $self->get_map;
        my $teams = {
            red	=> $self->get_team('hunted_team1', 1) || [],
            blue	=> $self->get_team('hunted_team2',   1) || [],
        };

        # increase everyone's rounds
        $m->{basic}{rounds}++;
        for (@{$teams->{red}}, @{$teams->{blue}}) {
            $_->{basic}{rounds}++;
            $_->{maps}{ $m->{mapid} }{rounds}++;
        }
        
        # determine who won and lost
        my ($won, $lost, $teamwon, $teamlost);
		$teamwon  = 'red';
		$teamlost = 'blue';
		$won  = $teams->{ $teamwon };
		$lost = $teams->{ $teamlost };
        
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
        
#	} elsif ($trigger eq 'x') {
#		@vars = ( $p1->{team} . 'x', 'x' );
		
	# ignore the following triggers for now
	# with a lot of these there are multiple log events for the same game event
	# either that or its not an event you want to offer any bonuses for
	# for a few of them, I simply have no clue
	} elsif ($trigger eq 'info_player_teamspawn') {
	} elsif ($trigger eq 'spawn resupply') {
	} elsif ($trigger eq 'flag 1') {
	} elsif ($trigger eq 'flag 1 neutral') {
	} elsif ($trigger eq 'neutral 1 off') {
	} elsif ($trigger eq 'neutral 1 on') {
	} elsif ($trigger eq 'red 1 off') {
	} elsif ($trigger eq 'blue 1 off') {
	} elsif ($trigger eq 'flag 2') {
	} elsif ($trigger eq 'flag 2 neutral') {
	} elsif ($trigger eq 'neutral 2 off') {
	} elsif ($trigger eq 'neutral 2 on') {
	} elsif ($trigger eq 'red 2 off') {
	} elsif ($trigger eq 'blue 2 off') {
	} elsif ($trigger eq 'flag 3') {
	} elsif ($trigger eq 'flag 3 neutral') {
	} elsif ($trigger eq 'neutral 3 off') {
	} elsif ($trigger eq 'neutral 3 on') {
	} elsif ($trigger eq 'red 3 off') {
	} elsif ($trigger eq 'blue 3 off') {
	} elsif ($trigger eq 'flag 4') {
	} elsif ($trigger eq 'flag 4 neutral') {
	} elsif ($trigger eq 'neutral 4 off') {
	} elsif ($trigger eq 'neutral 4 on') {
	} elsif ($trigger eq 'red 4 off') {
	} elsif ($trigger eq 'blue 4 off') {
	} elsif ($trigger eq 'red flag 1') {
	} elsif ($trigger eq 'blue flag 1') {
	} elsif ($trigger eq 'red flag 2') {
	} elsif ($trigger eq 'blue flag 2') {
	} elsif ($trigger eq 'red flag 3') {
	} elsif ($trigger eq 'blue flag 3') {
	} elsif ($trigger eq 'red marker 1') {
	} elsif ($trigger eq 'blue marker 1') {
	} elsif ($trigger eq 'red marker 2') {
	} elsif ($trigger eq 'blue marker 2') {
	} elsif ($trigger eq 'red marker 3') {
	} elsif ($trigger eq 'blue marker 3') {
	} elsif ($trigger eq 'blue team spawn stuff') {
	} elsif ($trigger eq 'red team spawn stuff') {
	} elsif ($trigger eq 'passed_on_infection') {
	} elsif ($trigger eq 'discovered_spy') {
	} elsif ($trigger eq 'detpack_set') {
	} elsif ($trigger eq '#inital_spawn_equip') {
	} elsif ($trigger eq '#restore_reddet_goals') {
	} elsif ($trigger eq '#restore_bluedet_goals') {
	} elsif ($trigger eq '#restore_rdebris_logic') {
	} elsif ($trigger eq '#restore_bdebris_logic') {
	} elsif ($trigger eq '#rdebris_open_initial') {
	} elsif ($trigger eq '#bdebris_open_initial') {
	} elsif ($trigger eq '#rdebris_open') {
	} elsif ($trigger eq '#bdebris_open') {
	} elsif ($trigger eq '#rdebris_close') {
	} elsif ($trigger eq '#bdebris_close') {
	} elsif ($trigger eq '#restore red cp1 trig') {
	} elsif ($trigger eq '#restore blue cp1 trig') {
	} elsif ($trigger eq '#restore red cp2 trig') {
	} elsif ($trigger eq '#restore blue cp2 trig') {
	} elsif ($trigger eq '#restore red cp3 trig') {
	} elsif ($trigger eq '#restore blue cp3 trig') {
	} elsif ($trigger eq '#restore red cp4 trig') {
	} elsif ($trigger eq '#restore blue cp4 trig') {
	} elsif ($trigger eq '#restore red cp5 trig') {
	} elsif ($trigger eq '#restore blue cp5 trig') {
	} elsif ($trigger eq '#restore_rdebris_bit') {
	} elsif ($trigger eq '#restore_bdebris_bit') {
	} elsif ($trigger eq 'red flag') {
	} elsif ($trigger eq 'blue flag') {
	} elsif ($trigger eq '#rholedet_initial') {
	} elsif ($trigger eq '#bholedet_initial') {
	} elsif ($trigger eq 'red steals 1 result') {
	} elsif ($trigger eq 'blue steals 1 result') {
	} elsif ($trigger eq 'red 2 capture result') {
	} elsif ($trigger eq 'blue 2 capture result') {
	} elsif ($trigger eq 'red 3 capture result') {
	} elsif ($trigger eq 'blue 3 capture result') {
	} elsif ($trigger eq 'call red endgame check 1') {
	} elsif ($trigger eq 'call blue endgame check 1') {
	} elsif ($trigger eq 'call red endgame check 2') {
	} elsif ($trigger eq 'call blue endgame check 2') {
	} elsif ($trigger eq 'red 1 return result') {
	} elsif ($trigger eq 'blue 1 return result') {
	} elsif ($trigger eq '#red cap pt1') {
	} elsif ($trigger eq '#blue cap pt1') {
	} elsif ($trigger eq '#red cap pt2') {
	} elsif ($trigger eq '#blue cap pt2') {
	} elsif ($trigger eq '#red cap pt3') {
	} elsif ($trigger eq '#blue cap pt3') {
	} elsif ($trigger eq '#red cap pt4') {
	} elsif ($trigger eq '#blue cap pt4') {
	} elsif ($trigger eq '#red cap pt5') {
	} elsif ($trigger eq '#blue cap pt5') {
	} elsif ($trigger eq '#red cap, move red') {
	} elsif ($trigger eq '#red cap, move blue') {
	} elsif ($trigger eq '#blue cap, move blue') {
	} elsif ($trigger eq '#blue cap, move red') {
	} elsif ($trigger eq '#enemy_in_red') {
	} elsif ($trigger eq 'the hunted\'s notepad') {
	} elsif ($trigger eq 'lockred') {
	} elsif ($trigger eq 'lockblue') {
	
	# ignore sentry and repair triggers for engineers
	} elsif ($trigger eq 'sentry_built_level_1') {
	} elsif ($trigger eq 'sentry_upgrade_level_2') {
	} elsif ($trigger eq 'sentry_upgrade_level_3') {
	} elsif ($trigger eq 'dispenser_repaired') {
	} elsif ($trigger eq 'sentry_dismantle') {
	} elsif ($trigger eq 'sentry_repair') {
	} elsif ($trigger eq 'teleporter_entrance_repaired') {
	} elsif ($trigger eq 'teleporter_exit_repaired') {
	
	# ignore the weapons triggers that are handled through regular kill events
	} elsif ($trigger eq 'detpack_explode') {
	} elsif ($trigger eq 'caltrop_grenade') {
	} elsif ($trigger eq 'concussion_grenade') {
	} elsif ($trigger eq 'hallucination_grenade') {
	} elsif ($trigger eq 'medic_infection') {
	} elsif ($trigger eq 'spy_tranq') {

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

sub event_tfc_teamscore {
	my ($self, $timestamp, $args) = @_;
	my ($team, $score, $numplrs) = @$args;
	$team = lc $team;

	# if there's no team score known yet, record it and return.
	# there are always 2 'team scored' events per round.
	if (!$self->{tfc_teamscore}) {
		$self->{tfc_teamscore} = { team => $team, score => $score, numplrs => $numplrs };
		return;
	}

	my $m = $self->get_map;
	my $teams = {
		red	=> $self->get_team('red', 1) || [],
		blue	=> $self->get_team('blue',   1) || [],
	};

#	print "allies = " . scalar(@{$self->get_team('allies', 1)}) . "\n";
#	print "axis   = " . scalar(@{$self->get_team('axis', 1)}) . "\n";

	# increase everyone's rounds
	$m->{basic}{rounds}++;
	for (@{$teams->{red}}, @{$teams->{blue}}) {
		$_->{basic}{rounds}++;
		$_->{maps}{ $m->{mapid} }{rounds}++;
	}

	# determine who won and lost
	my ($won, $lost, $teamwon, $teamlost);
	if ($score > $self->{tfc_teamscore}{score}) {
		$teamwon  = $team;
		$teamlost = $team eq 'red' ? 'blue' : 'red';
		$won  = $teams->{ $teamwon };
		$lost = $teams->{ $teamlost };
	} elsif ($self->{tfc_teamscore}{score} > $score) {
		$teamwon  = $self->{tfc_teamscore}{team};
		$teamlost = $self->{tfc_teamscore}{team} eq 'blue' ? 'red' : 'blue';
		$won  = $teams->{ $teamwon };
		$lost = $teams->{ $teamlost };
	} else {
		# do mot count 'draws'
	}

	# clear the previous team score
	$self->{tfc_teamscore} = undef;

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
