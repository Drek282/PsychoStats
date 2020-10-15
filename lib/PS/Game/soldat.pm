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
#	$Id: soldat.pm 524 2008-07-18 11:22:33Z lifo $
#
#	SOLDAT logs are based on the Half-Life logging standard perfectly.
#
package PS::Game::soldat;

use strict;
use warnings;
use base qw( PS::Game::halflife );

use util qw( :net );

our $VERSION = '1.00.' . (('$Rev: 524 $' =~ /(\d+)/)[0] || '000');


sub _init { 
	my $self = shift;
	$self->SUPER::_init;
	
	# load halflife:cstrike events since soldat uses the same events
	$self->load_events('halflife','');
	$self->load_events('halflife','cstrike');
	
	return $self;
}

sub get_role { undef }
sub has_mod_tables { 1 }

# override default event so we can reset per-log variables
sub event_logstartend {
	my ($self, $timestamp, $args) = @_;
	my ($startedorclosed) = @$args;
	$self->SUPER::event_logstartend($timestamp, $args);

	return unless lc $startedorclosed eq 'started';

	# reset some tracking vars
#	map { undef $self->{$_} } qw( ... );
}

sub event_plrtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $trigger, $propstr) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};
	return if $self->isbanned($p1);

	$p1->{basic}{lasttime} = $timestamp;
	return unless $self->minconnected;
	my $m = $self->get_map;
	my @vars = ();

	$trigger = lc $trigger;
	if ($trigger eq 'weaponstats' or $trigger eq 'weaponstats2') {
		$self->event_weaponstats($timestamp, $args);

	} elsif ($trigger eq 'address') {	# PIP 'address' (ipaddress) events
		my $props = $self->parseprops($propstr);
		return unless $p1->{uid} and $props->{address};
		$self->{ipcache}{$p1->{uid}} = ip2int($props->{address});

	} elsif ($trigger eq 'flagevent') {
		my $props = $self->parseprops($propstr);
		if ($props->{event} eq "returned") {
			@vars = ( $p1->{team} . 'flagsdefended', 'flagsdefended' );
			$self->plrbonus('flag_defended','enactor',$p1);

		} elsif ($props->{event} eq "grabbed") {
			@vars = ( $p1->{team} . 'flagspickedup', 'flagspickedup' );

		} elsif ($props->{event} eq "captured") {
			@vars = ( $p1->{team} . 'flagscaptured', 'flagscaptured' );
			$self->plrbonus('flag_captured', 'enactor', $p1);
		}

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
	my ($team, $trigger, $props) = @$args;
	return unless $self->minconnected;
	my $m = $self->get_map;
	my $alphateam = $self->get_team('alpha', 1);
	my $bravoteam = $self->get_team('bravo', 1);
	my ($p1, $p2, $alphavar, $bravovar, $enactor_team, $victim_team);

	$team = $self->team_normal($team);
	$trigger = lc $trigger;

	if ($trigger eq "terrorists_win" or $trigger eq "bravo_wins") {
		$bravovar  = 'bravowon';
		$alphavar = 'alphalost';
		$enactor_team = $bravoteam;
		$victim_team = $alphateam;

	} elsif ($trigger eq "cts_win" or $trigger eq "alpha_wins") {
		$bravovar  = 'bravolost';
		$alphavar = 'alphawon';
		$enactor_team = $alphateam;
		$victim_team = $bravoteam;

	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown team trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
		return;		# return here so we don't calculate the 'won/lost' points below
	}

	$self->plrbonus($trigger, 'enactor_team', $enactor_team, 'victim_team', $victim_team);

	# assign won/lost points ...
	$m->{mod}{$alphavar}++;
	$m->{mod}{$bravovar}++;
	foreach (@$alphateam) {
		$_->{mod}{$alphavar}++;
		$_->{mod_maps}{ $m->{mapid} }{$alphavar}++;		
	}
	foreach (@$bravoteam) {
		$_->{mod}{$bravovar}++;
		$_->{mod_maps}{ $m->{mapid} }{$bravovar}++;		
	}
}

# prevent 'unknown event' warning
sub event_cs_teamscore { }

sub weapon_normal {
	my ($self, $weapon) = @_;
	$weapon = lc $weapon;
	$weapon =~ tr/ /_/;
	$weapon =~ s/-//;
	return $weapon;
}

sub team_normal {
	my ($self, $team) = @_;
	$team = lc $team;
	if ($team eq 'a' or $team eq 'ct') { 	# 'ct' is legacy from early logs
		return 'alpha';
	} else {				# anything else is bravo
		return 'bravo';
	}
	return $team;
}

sub logsort {
	my $self = shift;
	my $list = shift;
	return [ sort { $a cmp $b } @$list ];
}

1;
