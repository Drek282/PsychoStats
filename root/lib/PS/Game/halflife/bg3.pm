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
#	$Id: bg3.pm 556 2008-09-03 18:16:06Z lifo $
#
package PS::Game::halflife::bg3;

use strict;
use warnings;
use base qw( PS::Game::halflife );

use util qw( :net print_r );

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

# normalize a role name
sub role_normal {
	my ($self, $rolestr) = @_;
	my $role = lc $rolestr;
        $role =~ s/^a\s//;
        $role =~ s/\s/_/;
	return $role;
}

sub event_plrtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $trigger, $plrstr2, $propstr) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	my $p2 = undef;
	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};
	return if $self->isbanned($p1);

	$p1->{basic}{lasttime} = $timestamp;
	return unless $self->minconnected;
	my $m = $self->get_map;

	$trigger = lc $trigger;
	
	my @vars = ();
	
	if ($trigger eq 'weaponstats' or $trigger eq 'weaponstats2') {
		$self->event_weaponstats($timestamp, $args);

	} elsif ($trigger eq 'address') {	# PIP 'address' (ipaddress) events
		my $props = $self->parseprops($propstr);
		return unless $p1->{uid} and $props->{address};
		$self->{ipcache}{$p1->{uid}} = ip2int($props->{address});
		
	} elsif ($trigger eq 'ctf_flag_capture')  {	
		$p1 = $self->get_plr($plrstr);
        @vars = ( $p1->{team} . 'flagscaptured', 'flagscaptured' );
		$self->plrbonus('flag_captured', 'enactor', $p1);

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

	foreach my $var (@vars) {
		$p1->{mod_maps}{ $m->{mapid} }{$var}++;
		$p1->{mod}{$var}++;
		$m->{mod}{$var}++;
	}
}

sub event_teamtrigger {
        my ($self, $timestamp, $args) = @_;
        my ($team, $trigger, $propstr) = @$args;
        my ($team2);

        return unless $self->minconnected;
        my $m = $self->get_map;

        my @vars = ();
        $team = $self->team_normal($team);

        $trigger = lc $trigger;

	if ($trigger eq "map_win") {
        my ($team, $numplrs) = @$args;
        $team = lc $team;
        
        
        # if there's no team score known yet, record it and return.
        # there are always 2 'team scored' events per round.
        if (!$self->{map_win}) {
            $self->{map_win} = { team => $team, numplrs => $numplrs };
            return;
        }
        
        my $m = $self->get_map;
        my $teams = {
            british	=> $self->get_team('british', 1) || [],
            americans	=> $self->get_team('americans',   1) || [],
        };
        
        # increase everyone's rounds
        $m->{basic}{rounds}++;
        for (@{$teams->{americans}}, @{$teams->{british}}) {
            $_->{basic}{rounds}++;
            $_->{maps}{ $m->{mapid} }{rounds}++;
        }
        
        # determine who won and lost
        my ($won, $lost, $teamwon, $teamlost);
        if ($team eq 'americans') {
            $teamwon  = 'americans';
            $teamlost = 'british';
            $won  = $teams->{ $teamwon };
            $lost = $teams->{ $teamlost };
        } elsif ($team eq 'british') {
            $teamwon  = 'british';
            $teamlost = 'americans';
            $won  = $teams->{ $teamwon };
            $lost = $teams->{ $teamlost };
        }
        
        # clear the previous team score
        $self->{map_win} = undef;
        
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
        
	} else {
		print "Unknown team trigger: $trigger from src $self->{_src} line $self->{_line}: $self->{_event}\n";
	}
}

sub has_mod_tables { 1 }

1;
