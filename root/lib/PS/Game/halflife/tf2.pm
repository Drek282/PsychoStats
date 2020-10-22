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
#	$Id: tf2.pm 556 2008-09-03 18:16:06Z lifo $
#
package PS::Game::halflife::tf2;

use strict;
use warnings;
use base qw( PS::Game::halflife );

use util qw( :net print_r );

our $VERSION = '1.00';


sub _init { 
	my $self = shift;
	$self->SUPER::_init;

	# load the kill assist calculation. used in plrtrigger().
	$self->add_calcskill_func('killassist', $self->{conf}->get_main('calcskill_kill'));

	return $self;
}

# add some extra stats from a kill (called from event_kill)
# p1 	= killer
# p2 	= victim
# w 	= weapon
# m 	= map
# r1 	= killer role (might be undef)
# r2 	= victim role (which could be the same object as killer)
# props = extra properties hash
sub mod_event_kill {
	my ($self, $p1, $p2, $w, $m, $r1, $r2, $props) = @_;

	# used for kill assists
	$self->{last_kill_weapon} = $w;
	$self->{last_kill_role} = $r1;
	
	my $custom = $props->{customkill};
	if ($custom) {	# headshot, backstab
		my $key = ($custom eq 'headshot') ? 'basic' : 'mod';

		$p1->{victims}{ $p2->{plrid} }{$custom . 'kills'}++;
		$p1->{mod_maps}{ $m->{mapid} }{$custom . 'kills'}++;
		$p1->{mod_roles}{ $r1->{roleid} }{$custom . 'kills'}++ if $r1;

		$p1->{$key}{$custom . 'kills'}++;
		$p2->{$key}{$custom . 'deaths'}++;
		$r1->{$key}{$custom . 'kills'}++ if $r1;
		$r2->{$key}{$custom . 'deaths'}++ if $r2;
		$m->{$key}{$custom  . 'kills'}++;
		$w->{$key}{$custom  . 'kills'}++;
	}

	return 0;
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
	my $r1 = $self->get_role($p1->{role}, $p1->{team});
	my $m = $self->get_map;

	$trigger = lc $trigger;
	
	my @vars = ();
	if ($trigger eq 'weaponstats' or $trigger eq 'weaponstats2') {
		$self->event_weaponstats($timestamp, $args);

	} elsif ($trigger eq 'address') {	# PIP 'address' (ipaddress) events
		my $props = $self->parseprops($propstr);
		return unless $p1->{uid} and $props->{address};
		$self->{ipcache}{$p1->{uid}} = ip2int($props->{address});

	} elsif ($trigger eq 'kill assist') {
		$p2 = $self->get_plr($plrstr2);
		if ($p2) {
			@vars = ( $p1->{team} . 'assists', 'assists' );
			$p1->{mod_roles}{ $r1->{roleid} }{assists}++ if $r1;
			$self->plrbonus('kill_assist', 'enactor', $p1);
			$self->calcskill_killassist_func($p1, $p2, $self->{last_kill_weapon});
		}
		
	} elsif ($trigger eq 'flagevent') {
		my $props = $self->parseprops($propstr);
		if ($props->{event} eq "defended") {
			@vars = ( $p1->{team} . 'flagsdefended', 'flagsdefended' );
			$self->plrbonus('flag_defended','enactor',$p1);

		} elsif ($props->{event} eq "picked up") {
			@vars = ( $p1->{team} . 'flagspickedup', 'flagspickedup' );

		} elsif ($props->{event} eq "dropped") {
			@vars = ( $p1->{team} . 'flagsdropped', 'flagsdropped' );

		} elsif ($props->{event} eq "captured") {
			@vars = ( $p1->{team} . 'flagscaptured', 'flagscaptured' );
			$self->plrbonus('flag_captured', 'enactor', $p1);
		}

	} elsif ($trigger eq 'killedobject') {
		my $props = $self->parseprops($propstr);
		$p2 = $props->{objectowner} ? $self->get_plr($props->{objectowner}) : undef;
		if ($props->{object} eq "OBJ_DISPENSER") {
			@vars = ( 'dispenserdestroy' );

		} elsif ($props->{object} eq "OBJ_SENTRYGUN") {
			@vars = ( 'sentrydestroy' );
			# do not give points to the object owner if they kill their own object
			if (!$p2 or $p1->plrid != $p2->plrid) {
				$self->plrbonus('killedsentry', 'enactor', $p1);	# depreciated; REMOVEME
				$self->plrbonus('sentrydestroy', 'enactor', $p1);
			}

		} elsif ($props->{object} eq "OBJ_TELEPORTER_ENTRANCE" || $props->{object} eq "OBJ_TELEPORTER_EXIT") {
			@vars = ( 'teleporterdestroy' );
			# do not give points to the object owner if they kill their own object
			$self->plrbonus('teleporterdestroy', 'enactor', $p1) if !$p2 or $p1->plrid != $p2->plrid;

		} elsif ($props->{object} eq "OBJ_ATTACHMENT_SAPPER") {
			@vars = ( 'sapperdestroy' );
			# do not give points to the object owner if they kill their own object
			$self->plrbonus('sapperdestroy', 'enactor', $p1) if !$p2 or $p1->plrid != $p2->plrid;

		}
		push(@vars, 'itemsdestroyed');

	} elsif ($trigger eq 'revenge') {
		@vars = ( 'revenge' );
		$p2 = $self->get_plr($plrstr2);
		$self->plrbonus($trigger, 'victim', $p2) if $p2;	# 'enactor' will get their bonus below...

	} elsif ($trigger eq 'builtobject') {
		@vars = ( 'itemsbuilt' );
		# player built something... good for them.

	} elsif ($trigger eq 'chargedeployed') {
		# ... something to do with the medic charge gun thingy ...
		@vars = ( 'chargedeployed' );

	} elsif ($trigger eq 'domination') {
		@vars = ( 'dominations' );
		$p2 = $self->get_plr($plrstr2);
		$self->plrbonus($trigger, 'victim', $p2) if $p2;	# 'enactor' will get their bonus below...

	} elsif ($trigger eq 'captureblocked') {
		@vars = ( $p1->{team} . 'captureblocked', 'captureblocked' );

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
		if ($r1) {
			$p1->{mod_roles}{ $r1->{roleid} }{$var}++;
			$r1->{mod}{$var}++;
		}
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

	if ($trigger eq "pointcaptured") {
		my $props = $self->parseprops($propstr);
		my $roles = {};
		my $players = [];
		my $list = [];
		my $i = 1;

		# old style (player "") (player "") ...
		if (ref $props->{player}) {			# array of player strings
			push(@$list, @{$props->{player}});
		} elsif (defined $props->{player}) {		# 1 player string
			push(@$list, $props->{player});
		}

		# new style (player1 "") (player2 "") ...
		while (exists $props->{'player' . $i}) {
			push(@$list, $props->{'player' . $i++});
		}

		return unless @$list;
		foreach my $plrstr (@$list) {
			my $p1 = $self->get_plr($plrstr) || next;
#			my $r1 = $self->get_role($p1->{roleid}, $team);
			$p1->{mod}{$trigger}++;
			$p1->{mod}{$team . $trigger}++;
			$p1->{mod_maps}{ $m->{mapid} }{$trigger}++;
			$p1->{mod_roles}{$trigger}++;
#			$roles->{ $r1->{roleid} } = $r1 if $r1;		# keep track of which roles are involved
			push(@$players, $p1);				# keep track of each player
		}
#		$roles->{$_}{mod}{$trigger}++ for keys %$roles;		# give point to each unique role
		$m->{mod}{$trigger}++;
		$m->{mod}{$team . $trigger}++;
		my $team1 = $self->get_team($team, 1);
		my $team2 = $self->get_team($team eq 'red' ? 'blue' : 'red', 1);
		$self->plrbonus($trigger, 'enactor', $players, 'enactor_team', $team1, 'victim_team', $team2);
	} elsif ($trigger eq 'intermission_win_limit') {
		# uhm.... what?
	} else {
		print "Unknown team trigger: $trigger from src $self->{_src} line $self->{_line}: $self->{_event}\n";
	}
}

sub event_round {
	my ($self, $timestamp, $args) = @_;
	my ($trigger, $propstr) = @$args;

	$trigger = lc $trigger;
	if ($trigger eq 'round_win' or $trigger eq 'mini_round_win') {
		my $m = $self->get_map;
		my $props = $self->parseprops($propstr);
		my $team = $self->team_normal($props->{winner}) || return;
		return unless $team eq 'red' or $team eq 'blue';
		my $team2 = $team eq 'red' ? 'blue' : 'red';
		my $winners = $self->get_team($team, 1);
		my $losers  = $self->get_team($team2, 1);
		my $var = $team . 'won';
		my $var2 = $team2 . 'lost';

		$self->plrbonus($trigger, 'enactor_team', $winners, 'victim_team', $losers);
		$m->{mod}{$var}++;
		$m->{mod}{$var2}++;
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
	} else {
		$self->SUPER::event_round($timestamp, $args);
	}

}

sub event_logstartend {
	my ($self, $timestamp, $args) = @_;
	$self->SUPER::event_logstartend($timestamp, $args);
	$self->{last_kill_weapon} = undef;
	$self->{last_kill_role} = undef;
}

sub has_mod_tables { 1 }
sub has_roles { 1 }

1;
