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
#	$Id: natural.pm 493 2008-06-17 11:26:35Z lifo $
#
package PS::Game::halflife::natural;

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

	# reset some tracking vars
#	map { undef $self->{$_} } qw( ns_commander );
	$self->{ns_commander} = undef;
	
    $self->save(1);
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

sub event_ns_teamtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($team, $trigger, $props) = @$args;
	my ($team2);

	return unless $self->minconnected;
	my $m = $self->get_map;

	my @vars = ();
	$team = lc $team;

	$trigger = lc $trigger;
	if ($trigger eq '') {
	} elsif ($trigger eq '') {
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

	} elsif ($trigger eq 'votedown') {
		$p2 = $self->get_plr($plrstr2);
		@vars2 = ( 'votedown' );
		$self->plrbonus($trigger, 'victim', $p2);	# current commander sucks

	} elsif ($trigger eq 'structure_built') {
		@vars1 = ( 'structuresbuilt' );

	} elsif ($trigger eq 'structure_destroyed') {
		@vars1 = ( 'structuresdestroyed' );

	} elsif ($trigger eq 'recycle') {
		@vars1 = ( 'structuresrecycled' );

	} elsif ($trigger eq 'research_start') {
		@vars1 = ( 'research' );

	} elsif ($trigger eq 'research_cancel') {
		@vars1 = ( 'research' );
		$value1 = -1;

#	} elsif ($trigger eq 'x') {
#		@vars = ( $p1->{team} . 'x', 'x' );

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

# this event is triggered after a round has been completed and a team won
sub event_ns_mapinfo {
	my ($self, $timestamp, $args) = @_;
	my ($mapname, $propstr) = @$args;
	my $props = $self->parseprops($propstr);
	my $m = $self->get_map;
	my $marine = $self->get_team('marine', 1);
	my $alien  = $self->get_team('alien', 1);
	my ($p1, $p2, $marinevar, $alienvar, $won, $lost);

	if ($props->{victory_team} eq 'marine') {
		$won  = $marine;
		$lost = $alien;
		$marinevar = 'marinewon';
		$alienvar = 'alienlost';
		# give a point to the current commander
		if ($self->{ns_commander} and $self->minconnected) {
			$p2 = $self->{ns_commander};
			$p2->{mod}{commanderwon}++;
			$self->plrbonus('commander_win', 'enactor', $p2);
		}
	} else {
		$won  = $alien;
		$lost = $marine;
		$marinevar = 'marinelost';
		$alienvar = 'alienwon';
	}
	$self->plrbonus('round_win', 'enactor_team', $won, 'victim_team', $lost);

	# assign won/lost points ...
	$m->{mod}{$marinevar}++;
	$m->{mod}{$alienvar}++;
	foreach (@$marine) {
		$_->{mod}{$marinevar}++;
		$_->{mod_maps}{ $m->{mapid} }{$marinevar}++;		
	}
	foreach (@$alien) {
		$_->{mod}{$alienvar}++;
		$_->{mod_maps}{ $m->{mapid} }{$alienvar}++;		
	}
}

# can't use the built in halflife::change_role since NS likes 
# to do things a little differently
sub event_changed_role {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $rolestr) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	$self->SUPER::event_changed_role($timestamp, $args);

	# keep track of who is the commander
	if ($rolestr eq 'commander') {
#		print "CHANGING COMMANDER\n";
		$self->{ns_commander} = $p1;
	}
}

sub team_normal {
	my ($self, $teamstr) = @_;
	my $team = lc $teamstr;

	# At some point this needs to be updated to allow for AvA and MvM maps.
	# Current support will cause all kills to be FFkill's on those maps.

	$team =~ tr/ /_/;					# convert spaces to underscore
	$team =~ tr/a-z0-9_//cs;				# remove all non-alphanumeric characters
	$team =~ s/\dteam$//;					# remove trailing '1team' from team names
	$team = 'spectator' if $team eq 'spectators';		# some MODS have a trailing 's'.
	$team = '' if $team eq 'unassigned';

	return $team;
}

# Remove the eject prefix and inject the event back into the queue.
# At least some versions of NS do not write a newline to the end of
# of the 'Eject' message causing the next log event to be appended
# to the end and it will otherwise get ignored.
sub event_ns_eject_fix {
	my ($self, $timestamp, $args) = @_;
	my ($event) = @$args;
	return if !$event or $event =~ /^\s*$/;
	$self->event($self->{_src}, $event, $self->{_line});
}

sub has_mod_tables { 1 }

1;
