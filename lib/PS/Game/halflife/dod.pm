#
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
#	$Id: dod.pm 558 2008-09-03 18:37:25Z lifo $
#
package PS::Game::halflife::dod;

use strict;
use warnings;
use base qw( PS::Game::halflife );
use util qw( :net );

our $VERSION = '1.05.' . (('$Rev: 558 $' =~ /(\d+)/)[0] || '000');


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

	# reset some tracking vars
	$self->{dod_teamscore} = undef;
}

sub event_teamtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($team, $trigger, $props) = @$args;
	my ($team2);

	return unless $self->minconnected;
	my $m = $self->get_map;

	my @vars = ();
	$team = lc $team;

	$trigger = lc $trigger;
	if ($trigger eq 'tick_score') {
		my $val = $self->parseprops($props);
		return unless (($team eq 'allies' or $team eq 'axis') and $val->{score});
		my $plrs = $self->get_team($team, 1);			# dead players count too
		my $var = $team . 'score';
#		print scalar @$plrs, " $team members scored $val->{score} on map " . $m->name . "\n";
		foreach my $p1 (@$plrs) {
			$p1->{mod_maps}{ $m->{mapid} }{$var}++;
			$p1->{mod}{$var}++;
		}
		$m->{mod}{$var}++;

	} elsif ($trigger eq 'round_win') {
		return unless $team eq 'allies' or $team eq 'axis';
		my $team2 = $team eq 'axis' ? 'allies' : 'axis';
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

	} elsif ($trigger eq 'dod_capture_area') {
	} elsif ($trigger eq 'captured_loc') {		# is now the main way for dod to record flag captures (plr trigger isn't used anymore)
		my $props = $self->parseprops($props);
		my $plrs = ref $props->{player} ? $props->{player} : [ $props->{player} ];
		if (@$plrs) {
			my $list;
			foreach my $plrstr (@$plrs) {
				my $p1 = $self->get_plr($plrstr) || next;
				$p1->{mod_maps}{ $m->{mapid} }{$p1->{team} . 'flagscaptured'}++;
				$p1->{mod_maps}{ $m->{mapid} }{'flagscaptured'}++;
				$p1->{mod}{$p1->{team} . 'flagscaptured'}++;
				$p1->{mod}{'flagscaptured'}++;
				push(@$list, $p1);
			}
			if (@$list) {
				# Tracked under 'dod_control_point' to be
				# consistant in how it used to be tracked in the
				# original halflife::dod.
				$self->plrbonus('dod_control_point', 'enactor', $list);
			}
			$m->{mod}{'flagscaptured'}++;
			$m->{mod}{$self->team_normal($team) . 'flagscaptured'}++;
		}

	} elsif ($trigger eq 'team_scores') {
	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown team trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
	}

#	foreach my $var (@vars) {
#		$p1->{mod_maps}{ $m->{mapid} }{$var}++;
#		$p1->{mod}{$var}++;
#		$m->{mod}{$var}++;
#	}
}

sub event_plrtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $trigger, $propstr) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	return if $self->isbanned($p1);

	$p1->{basic}{lasttime} = $timestamp;
	return unless $self->minconnected;
	my $m = $self->get_map;

	my @vars = ();
	$trigger = lc $trigger;
	$self->plrbonus($trigger, 'enactor', $p1);
	if ($trigger eq 'weaponstats' or $trigger eq 'weaponstats2') {
		$self->event_weaponstats($timestamp, $args);

	} elsif ($trigger eq 'address') {	# PIP 'address' events
		my $props = $self->parseprops($propstr);
		return unless $p1->{uid} and $props->{address};
		$self->{ipcache}{$p1->{uid}} = ip2int($props->{address});

	} elsif ($trigger eq 'dod_object') {					# got TNT (pre source)
# these are useless, from the old days of the original DOD
#		@vars = qw( tnt );
	} elsif ($trigger eq 'dod_object_goal') {				# used TNT (pre source)
#		@vars = qw( tntused );

	} elsif ($trigger eq 'dod_control_point') {				# plr captured a flag
		@vars = ( $p1->{team} . 'flagscaptured', 'flagscaptured' );

	} elsif ($trigger eq 'dod_capture_area') {				# plr captured an area (pre source)
		@vars = ( $p1->{team} . 'flagscaptured', 'flagscaptured' );
### no longer counting 'areas' separately
###		@vars = ( $p1->{team} . 'areascaptured', 'areascaptured' );

	} elsif ($trigger eq 'bomb_plant') {	# props: flagindex, flagname
		@vars = ( $p1->{team} . 'bombplanted', 'bombplanted');

	} elsif ($trigger eq 'bomb_defuse') {
		# this can be used as a percentage against flagsblocked
		@vars = ( $p1->{team} . 'bombdefused', 'bombdefused' );

	} elsif ($trigger eq 'kill_planter') {	# props: ' against "<plrstr>"'
		@vars = ( 'killedbombplanter' );

	} elsif ($trigger eq 'dod_blocked_point') {
		@vars = ( $p1->{team} . 'flagsblocked', 'flagsblocked' );

	} elsif ($trigger eq 'capblock') {
		@vars = ( $p1->{team} . 'flagsblocked', 'flagsblocked' );

# ignore the following triggers, they're detected using other triggers above
	} elsif ($trigger eq 'axis_capture_flag') {
	} elsif ($trigger eq 'allies_capture_flag') {
	} elsif ($trigger eq 'allies_blocked_capture') {
	} elsif ($trigger eq 'axis_blocked_capture') {
# ---------

	# extra statsme / amx triggers
	} elsif ($trigger =~ /^(time|latency|amx_|game_idle_kick)/) {

	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown player trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
	}

	foreach my $var (@vars) {
		$p1->{mod_maps}{ $m->{mapid} }{$var}++;
		$p1->{mod}{$var}++;
		$m->{mod}{$var}++;
	}
}

# Original DOD 'team scored' event. The only way to tell which team won with old DOD
# is to compare the 2 'scored' events and see which team had more points.
# this can also be used to count 'rounds'
sub event_dod_teamscore {
	my ($self, $timestamp, $args) = @_;
	my ($team, $score, $numplrs) = @$args;
	$team = lc $team;

	# if there's no team score known yet, record it and return.
	# there are always 2 'team scored' events per round.
	if (!$self->{dod_teamscore}) {
		$self->{dod_teamscore} = { team => $team, score => $score, numplrs => $numplrs };
		return;
	}

	my $m = $self->get_map;
	my $teams = {
		allies	=> $self->get_team('allies', 1) || [],
		axis	=> $self->get_team('axis',   1) || [],
	};

#	print "allies = " . scalar(@{$self->get_team('allies', 1)}) . "\n";
#	print "axis   = " . scalar(@{$self->get_team('axis', 1)}) . "\n";

	# increase everyone's rounds
	$m->{basic}{rounds}++;
	for (@{$teams->{allies}}, @{$teams->{axis}}) {
		$_->{basic}{rounds}++;
		$_->{maps}{ $m->{mapid} }{rounds}++;
	}

	# determine who won and lost
	my ($won, $lost, $teamwon, $teamlost);
	if ($score > $self->{dod_teamscore}{score}) {
		$teamwon  = $team;
		$teamlost = $team eq 'axis' ? 'allies' : 'axis';
		$won  = $teams->{ $teamwon };
		$lost = $teams->{ $teamlost };
	} elsif ($self->{dod_teamscore}{score} > $score) {
		$teamwon  = $self->{dod_teamscore}{team};
		$teamlost = $self->{dod_teamscore}{team} eq 'axis' ? 'allies' : 'axis';
		$won  = $teams->{ $teamwon };
		$lost = $teams->{ $teamlost };
	} else {
		# do mot count 'draws'
	}

	# clear the previous team score
	$self->{dod_teamscore} = undef;

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

sub role_normal {
	my ($self, $rolestr) = @_;
	my $role = lc $rolestr;
        $role =~ s/^(?:#?class_)?//;
	return $role;
}

sub has_mod_tables { 1 }

1;
