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
#	$Id: cstrike.pm 450 2008-05-20 11:34:52Z lifo $
#
package PS::Game::halflife::cstrike;

use strict;
use warnings;
use base qw( PS::Game::halflife );

use util qw( :net );

our $VERSION = '1.10.' . (('$Rev: 450 $' =~ /(\d+)/)[0] || '000');


sub _init { 
	my $self = shift;
	$self->SUPER::_init;

#	$self->{cs_hostages} = {};

	return $self;
}

# cstrike doesn't have character roles (classes) so just return a false value
sub get_role { undef }

# override default event so we can reset per-log variables
sub event_logstartend {
	my ($self, $timestamp, $args) = @_;
	my ($startedorclosed) = @$args;
	$self->SUPER::event_logstartend($timestamp, $args);

	return unless lc $startedorclosed eq 'started';

	# reset some tracking vars
	map { undef $self->{$_} } qw( cs_bombplanter cs_spawned_with_bomb cs_vip );
#	$self->{cs_hostages} = {};
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

	$trigger = lc $trigger;
	$self->plrbonus($trigger, 'enactor', $p1);
	if ($trigger eq 'weaponstats' or $trigger eq 'weaponstats2') {
		$self->event_weaponstats($timestamp, $args);

	} elsif ($trigger eq 'address') {	# PIP 'address' (ipaddress) events
		my $props = $self->parseprops($propstr);
		return unless $p1->{uid} and $props->{address};
		$self->{ipcache}{$p1->{uid}} = ip2int($props->{address});

	} elsif ($trigger =~ /^(killed|touched|rescued)_a_hostage/) {
		$p1->{mod_maps}{ $m->{mapid} }{$1.'hostages'}++;
		$p1->{mod}{$1.'hostages'}++;
		$m->{mod}{$1.'hostages'}++;

	} elsif ($trigger =~ /^begin_bomb_defuse/) {						# ignore: _with_kit, _without_kit
		$p1->{mod_maps}{ $m->{mapid} }{bombdefuseattempts}++;
		$p1->{mod}{bombdefuseattempts}++;
		$m->{mod}{bombdefuseattempts}++;

	} elsif ($trigger =~ /^(planted|defused|spawned_with|got|dropped)_the_bomb/) {
		if ($1 eq 'planted') {
			$p1->{mod_maps}{ $m->{mapid} }{bombplanted}++;
			$p1->{mod}{bombplanted}++;
			$m->{mod}{bombplanted}++;
			$self->{cs_bombplanter} = $p1;						# keep track of who planted bomb

		} elsif ($1 eq 'defused') {
			$p1->{mod_maps}{ $m->{mapid} }{bombdefused}++;
			$p1->{mod}{bombdefused}++;
			$m->{mod}{bombdefused}++;
			 # bomb planter should lose points
			$self->plrbonus($trigger, 'victim', $self->{cs_bombplanter}) if $self->{cs_bombplanter};

		} elsif ($1 eq 'spawned_with') {
			$p1->{mod_maps}{ $m->{mapid} }{bombspawned}++;
			$p1->{mod}{bombspawned}++;
			$self->{cs_spawned_with_bomb} = $p1;					# keep track of spawned bomber
#			print "DEBUG: BOMB SPAWNED\n";

		} elsif ($1 eq 'dropped') {
			no warnings;
			if ($self->{cs_spawned_with_bomb} and $self->{cs_spawned_with_bomb}->{plrid} eq $p1->{plrid}) {
#				print "DEBUG: BOMB DROPPED\n";
				$self->{cs_spawned_with_bomb} = undef;
			}
		}

	} elsif ($trigger =~ /^(became|escaped_as|assassinated_the)_vip/) {
		if ($1 eq 'became') {
			$p1->{mod_maps}{ $m->{mapid} }{vip}++;
			$p1->{mod}{vip}++;
			$self->{cs_vip} = $p1;							# keep track of current CT VIP
		} elsif ($1 eq 'escaped_as') {
			$p1->{mod_maps}{ $m->{mapid} }{vipescaped}++;
			$p1->{mod}{vipescaped}++;
			$m->{mod}{vipescaped}++;
		} else {
			$p1->{mod_maps}{ $m->{mapid} }{vipkilled}++;
			$p1->{mod}{vipkilled}++;
			$m->{mod}{vipkilled}++;
			$self->plrbonus($trigger, 'victim', $self->{cs_vip});			# VIP should lose points (also clears current VIP)
		}

	} elsif ($trigger eq 'terrorist_escaped') {

	# extra statsme / amx triggers
	} elsif ($trigger =~ /^(time|latency|amx_|game_idle_kick)/) {
		# The time trigger might be usefull...
	} elsif ($trigger eq 'camped') {
		# I'm not sure what plugin provides this trigger...

	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown player trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
	}

}


sub event_teamtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($team, $trigger, $props) = @$args;
	return unless $self->minconnected;
	my $m = $self->get_map;
	my $ct = $self->get_team('ct', 1);
	my $terr = $self->get_team('terrorist', 1);
	my ($p1, $p2, $ctvar, $terrvar, $enactor_team, $victim_team);

	$team = lc $team;
	$team =~ tr/ /_/;				# convert spaces to _ on team names (some mods are known to do this)
	$team =~ tr/a-z0-9_//cs;			# remove all non-alphanumeric characters
	$trigger = lc $trigger;

	if ($trigger eq 'target_bombed') {
TARGET_BOMBED:
		$terrvar  = 'terroristwon';
		$ctvar = 'ctlost';
		$enactor_team = $terr;
		$victim_team = $ct;

		$p1 = $self->{cs_bombplanter};
		if (defined $p1) {
			$self->{cs_bombplanter} = undef;
			$m->{mod}{bombexploded}++;
			$p1->{mod}{bombexploded}++;
			$p1->{mod_maps}{ $m->{mapid} }{bombexploded}++;
			$self->plrbonus($trigger, 'enactor', $p1);
			undef $self->{cs_bombplanter};
		}

		$p2 = $self->{cs_spawned_with_bomb};
		undef $self->{cs_spawned_with_bomb};
#		print "DEBUG: P1=$p1->{plrid} P2=" . ($p2->{plrid}||'') . "\n";
		if (defined($p1 && $p2) and ($p1->{plrid} eq $p2->{plrid})) {	# if planter matches the spawner then bombrunner++ 
#			print "DEBUG: BOMBRUNNER++\n";
			$m->{mod}{bombrunner}++;
			$p2->{mod}{bombrunner}++;
			$p2->{mod_maps}{ $m->{mapid} }{bombrunner}++;
			$self->plrbonus('bomb_runner', 'enactor', $p1);
		}

	} elsif ($trigger eq "hostages_not_rescued") {
		$terrvar  = 'terroristwon';
		$ctvar = 'ctlost';
		$enactor_team = $terr;
		$victim_team = $ct;

	} elsif ($trigger eq "vip_assassinated") {
		$terrvar  = 'terroristwon';
		$ctvar = 'ctlost';
		$enactor_team = $terr;
		$victim_team = $ct;

	} elsif ($trigger eq "vip_not_escaped") {
		$terrvar  = 'terroristwon';
		$ctvar = 'ctlost';
		$enactor_team = $terr;
		$victim_team = $ct;

	} elsif ($trigger eq "terrorists_win") {
		$terrvar  = 'terroristwon';
		$ctvar = 'ctlost';
		$enactor_team = $terr;
		$victim_team = $ct;
		# make sure to count the 'explosion' even though the round ended before it actually did.
		goto TARGET_BOMBED if $self->{cs_bombplanter};

	} elsif ($trigger eq "terrorists_escaped") {
		$terrvar  = 'terroristwon';
		$ctvar = 'ctlost';
		$enactor_team = $terr;
		$victim_team = $ct;

	} elsif ($trigger eq "terrorists_not_escaped") {
		$terrvar  = 'terroristlost';
		$ctvar = 'ctwon';
		$enactor_team = $ct;
		$victim_team = $terr;

	} elsif ($trigger eq "all_hostages_rescued") {
		$terrvar  = 'terroristlost';
		$ctvar = 'ctwon';
		$enactor_team = $ct;
		$victim_team = $terr;

	} elsif ($trigger eq "bomb_defused") {
		$terrvar  = 'terroristlost';
		$ctvar = 'ctwon';
		$enactor_team = $ct;
		$victim_team = $terr;

	} elsif ($trigger eq "target_saved") {
		$terrvar  = 'terroristlost';
		$ctvar = 'ctwon';
		$enactor_team = $ct;
		$victim_team = $terr;

	} elsif ($trigger eq "vip_escaped") {
		$terrvar  = 'terroristlost';
		$ctvar = 'ctwon';
		$enactor_team = $ct;
		$victim_team = $terr;

	} elsif ($trigger eq "cts_preventescape") {
		$terrvar  = 'terroristlost';
		$ctvar = 'ctwon';
		$enactor_team = $ct;
		$victim_team = $terr;

	} elsif ($trigger eq "cts_win") {
		$terrvar  = 'terroristlost';
		$ctvar = 'ctwon';
		$enactor_team = $ct;
		$victim_team = $terr;

	} elsif ($trigger eq 'intermission_win_limit') {
		# uhm.... what?
		return;
	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown team trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
		return;		# return here so we don't calculate the 'won/lost' points below
	}

	$self->plrbonus($trigger, 'enactor_team', $enactor_team, 'victim_team', $victim_team);

	# assign won/lost points ...
	$m->{mod}{$ctvar}++;
	$m->{mod}{$terrvar}++;
	foreach (@$ct) {
		$_->{mod}{$ctvar}++;
		$_->{mod_maps}{ $m->{mapid} }{$ctvar}++;		
	}
	foreach (@$terr) {
		$_->{mod}{$terrvar}++;
		$_->{mod_maps}{ $m->{mapid} }{$terrvar}++;		
	}
}

sub event_cs_teamscore {
	my ($self, $timestamp, $args) = @_;
	my ($team, $score, $totalplrs, $props) = @$args;

#	$self->info("$team scored $score with $totalplrs players\n");
}

sub has_mod_tables { 1 }

1;
