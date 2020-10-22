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
#	$Id: cod.pm 514 2008-07-07 18:19:50Z lifo $
#
package PS::Game::cod;

use strict;
use warnings;
use base qw( PS::Game );

use util qw( :net :date bench print_r );
use Encode;
use Time::Local qw( timelocal_nocheck );
use PS::Player;

our $VERSION = '1.00.' . ('$Rev: 514 $' =~ /(\d+)/ || '000')[0];

sub new {
	my $proto = shift;
	my $class = ref($proto) || $proto;
	my $self = { debug => 0, class => $class, conf => shift, db => shift };
	bless($self, $class);
	return $self->_init;
}

sub _init {
	my $self = shift;
	$self->SUPER::_init;

	# TODO: 'gamestart' is going to have to be user changable via config or command line.
	$self->{gamestart} = time;

	$self->{min} = undef;
	$self->{hour} = undef;
	$self->{day} = undef;

	# COD records more hitgroups than we want, so we merge certain groupings together
	$self->{hitgroups} = {
		'none'			=> undef,
		'head'			=> 'shot_head',
		'left_arm_lower'	=> 'shot_leftarm',
		'left_arm_upper'	=> 'shot_leftarm',
		'left_foot'		=> 'shot_leftleg',
		'left_hand'		=> 'shot_leftarm',
		'left_leg_lower'	=> 'shot_leftleg',
		'left_leg_upper'	=> 'shot_leftleg',
		'neck'			=> 'shot_head',
		'right_arm_lower'	=> 'shot_rightarm',
		'right_arm_upper'	=> 'shot_rightarm',
		'right_foot'		=> 'shot_rightleg',
		'right_hand'		=> 'shot_rightarm',
		'right_leg_lower'	=> 'shot_rightleg',
		'right_leg_upper'	=> 'shot_rightleg',
		'torso_lower'		=> 'shot_stomach',
		'torso_upper'		=> 'shot_chest'
	};

	# keep track of objects in memory
	$self->{maps} = {};		# loaded map objects, keyed on id
	$self->{weapons} = {};		# loaded weapon objects, keyed on id

	$self->initcache;
	$self->initipcache;

	$self->{auto_plr_bans} = $self->{conf}->get_main('auto_plr_bans');

	# default map will be determined from the log source, since each log source can have a different default.
	$self->{curmap} = 'unknown';

	return $self;
}

sub has_mod_tables { 1 }

# handle the event that comes in from the Feeder (log line)
sub event {
	my $self = shift;
	my ($src, $event_raw, $line) = @_;
	my ($time, $min, $sec);
	my $event = decode('UTF-8', $event_raw);
	chomp($event);
	$event =~ s/^\s+//;
	$event =~ s/\s+$//;
	return unless $event;

	$self->{_src} = $src;
	$self->{_event} = $event;
	$self->{_line} = $line;

	($time, $event) = split(' ', $event, 2);
	return unless $time;
	($min,$sec) = split(':', $time);
	return unless defined $sec;
	
	# Implicitly ignore all lines that start or end with a dash (-)
	# these happen so frequently it's more efficient to check for it here than adding an event to the config.
	# if a line ends with a dash, it's most likely a corrupted line where the previous
	# line didn't finish before a server crash.
	return if substr($event,0,1) eq '-' or substr($event,-1) eq '-';

	$self->{prev_timestamp} = $self->{timestamp} || $self->{gamestart};
	$self->{timestamp} = $self->{gamestart} + ($min * 60) + $sec;
	($self->{min}, $self->{hour}, $self->{day}) = (localtime($self->{timestamp}))[1,2,3];

	# server restarted so we need to reset the game start timestamp
	# there's no way to determine when the server restarted though, so we
	# can only guess and assume it restarted instantly.
	if ($self->{prev_timestamp} > $self->{timestamp}) {
		$self->{gamestart} = $self->{prev_timestamp};
		$self->info("COD server restart timestamp " . date("%Y-%m-%d %H:%i:%s\n", $self->{gamestart}));
	}

	# SEARCH FOR A MATCH ON THE EVENT USING OUR LIST OF REGEX'S
	# If a match is found we dispatch the event to the proper event method 'event_{match}'
	my ($re, $params) = &{$self->{evregex}}($event);			# finds an EVENT match (fast)
	if ($re) {
		return if $self->{evconf}{$re}{ignore};				# should this match be ignored?
		$self->{re_match} = $re;					# keep track of the event that matched
		my $func = 'event_' . ($self->{evconf}{$re}{alias} || $re);	# use specified $event or 'event_$re'
		$self->$func($self->{timestamp}, $params);			# call event handler
	} else {
		$self->info("Unknown log event was ignored from source $src line $line: $event") if $self->{report_unknown};
	}
}

sub process_feed {
	my ($self, $feeder) = @_;
	my @total = $self->SUPER::process_feed($feeder);

	# after the feed ends make sure all stats in memory are saved.
	# the logstartend event does everything we need to save all in-memory stats.
#	$self->event_logstartend($self->{timestamp}, [ 'started' ]);	

	return wantarray ? @total : $total[0];
}

# parses the player string and returns the player object matching the uniqueid. 
# creates a new player object if no current player matches.
sub get_plr {
	my ($self, $plrstr, $plrids_only) = @_;
	my ($p,$str,$plrids,$name,$uid,$worldid,$team,$ipaddr,$uniqueid);
	my ($origname);

	# return the cached player via their signature if they exist
	# this can potentially be a big performance gain since the rest of 
	# the function doesn't have to do anything.
	if (!$plrids_only) {
		if (defined($p = $self->cached($plrstr, 'signature'))) {
#			print "PLAYER CACHED!\n";
			return $p;
		}
	}

	$ipaddr = 0;
	($worldid, $uid, $team, $name) = split(';', $plrstr);

	# if the name is not defined then there was no team associated with this plrsig
	if (!defined $name) {
		$name = $team;
		$team = undef;
	}

	$name =~ s/^\s+//;
	$name =~ s/\s+$//;
	return undef if $name eq '';			# do not allow blank names, period.

	# lookup the alias for the player's uniqueid
	if ($self->{uniqueid} eq 'worldid') {
		$worldid = $self->get_plr_alias($worldid);
	} elsif ($self->{uniqueid} eq 'name') {
		$name = $self->get_plr_alias($name);
	}

	# assign the player ID's. This is now an official hash of how to 
	# match the player in the database.
	$plrids = { name => $name, worldid => $worldid, ipaddr => $ipaddr };
	return { %$plrids, uid => $uid, team => $team } if $plrids_only;

	$p = undef;

	# based on their UID the player already existed
	if ($p = $self->cached($uid, 'uid')) {
#		print "UID CACHED\n" if $plrstr =~ /SIC/;
		$p->team($team) if $team;					# keep team up to date
		$self->delcache($p->signature($plrstr), 'signature');		# delete previous and set new sig
		$self->addcache($p, $plrstr, 'signature');			# update sig cache

	} elsif ($p = $self->cached($plrids->{$self->{uniqueid}}, 'uniqueid')) {
		# the UID won't match on suicide events or where the player killed themself
		# It will be -1 when the 'victim' in the event is the same as the 'killer', in which case we ignore the uid
		if ($uid ne '-1' and $p->uid ne $uid) {
			$p->team($team) if $team;
			$self->delcache($p->uid($uid), 'uid');
			$self->delcache($p->signature($plrstr), 'signature');
			$self->addcache($p, $p->uid, 'uid');
			$self->addcache($p, $p->signature, 'signature');
		}

	} else {
#		print "NEW PLAYER\n" if $plrstr =~ /SIC/;
		$p = new PS::Player($plrids, $self) || return undef;
		if (my $p1 = $self->cached($p->plrid, 'plrid')) {	# make sure this player isn't loaded already
			undef $p;
			$p = $p1;
			$self->delcache_all($p);
		}
		$p->active(1);						# we have to assume the player is active
		$p->signature($plrstr);
		$p->timerstart($self->{timestamp});
		$p->uid($uid) if $uid ne '-1';
		$p->team($team) if $team;
		$p->plrids;

		$self->addcache_all($p);
		$self->scan_for_clantag($p) if $self->{clantag_detection} and !$p->clanid;
	}

	return $p;
}

# player trigger for COD4. This is a dispatch for all player events. COD logs are very simple.
sub event_cod_plrtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($trigger, $event) = @$args;
	my (@parts, $p1, $p2, $plrstr, $plrstr2);

	if ($trigger eq 'J') {			# player entered the server
		$self->event_connected($timestamp, [ $event ]);

	} elsif ($trigger eq 'Q') {		# player disconnected
		$self->event_disconnected($timestamp, [ $event ]);

	} elsif ($trigger eq 'K') {		# player killed someone (or themself)
		@parts = split(';', $event);
		return unless @parts >= 8;	# avoid corrupted lines
		$self->event_kill($timestamp, [ join(';', @parts[0..3]), join(';', @parts[4..7]), splice(@parts, 8) ]);

	} elsif ($trigger eq 'D') {		# player damaged someone else (or themself)
		@parts = split(';', $event);
		return unless @parts >= 8;	# avoid corrupted lines
		# only record the attack if it's against someone else.
		# if $parts[4] is blank then the player fell or caused dmg to themself and we don't care.
		if ($parts[4] ne '') {
			$self->event_attacked($timestamp, [ join(';', @parts[0..3]), join(';', @parts[4..7]), splice(@parts, 8) ]);
		}

	} elsif ($trigger eq 'Weapon') {	# player switched weapons?
		@parts = split(';', $event);
		return unless @parts >= 4;
		$p1 = $self->get_plr(join(';', @parts[0..2])) || return;
		$p1->weapon( $self->weapon_normal($parts[-1]) );

	} elsif ($trigger eq 'say') {		# player said something publicly
	} elsif ($trigger eq 'sayteam') {	# player said something to team

	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown player trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
	}
}

# COD "InitGame" event. Occurs when the server has been restarted or the map has changed.
sub event_cod_init {
	my ($self, $timestamp, $args) = @_;
	my ($propstr) = @$args;
	my $props = $self->parseprops($propstr);
	my $m;

	# a previous map was already loaded, save it now
	if ($m = $self->{maps}{ $self->{curmap} }) {
		my $time = $m->timer;
		$m->{basic}{onlinetime} += $time if ($time > 0);
		$m->save;
		delete $self->{maps}{ $self->{curmap} };
	}

	# previous event was a "ShutdownGame" event.
	#if ($self->{cod_shutdown}) {
	#	# If the shutdown time is greater then the current time then the
	#	# server was literally restarted. Unfortunately at this point we
	#	# don't know when the server was restarted so we have to use the
	#	# last known time stamp as the starting point.
	#	if ($self->{cod_shutdown} > $timestamp) {
	#		$self->{gamestart} = $self->{cod_shutdown};
	#		$timestamp = $self->{cod_shutdown};
	#		$self->info("COD server restart timestamp " . date("%Y-%m-%d %H:%i:%s\n", $timestamp));
	#	}
	#	$self->{cod_shutdown} = 0;
	#}

	# start up the new map in memory
	$self->{curmap} = $props->{mapname};
	$m = $self->get_map;
	$m->statdate($timestamp);
	$m->{basic}{games}++;
	$m->hourly('games', $timestamp);

	$self->{db}->begin;

	# SAVE PLAYERS
	foreach my $p ($self->get_plr_list) {
		$p->end_all_streaks;
		$p->disconnect($timestamp, $m);
		$p->save; # if $p->active;
		$p = undef;
	}
	$self->initcache;

	# SAVE WEAPONS
	while (my ($wid,$w) = each %{$self->{weapons}}) {
		$w->save;
		$w = undef;
	}
	$self->{weapons} = {};

	# SAVE ROLES
	while (my ($rid,$r) = each %{$self->{roles}}) {
		$r->save;
		$r = undef;
	}
	$self->{roles} = {};

	# SAVE MAPS
	while (my ($mid,$m) = each %{$self->{maps}}) {
		my $time = $m->timer;
#		print "$timestamp - $m->{basic}{lasttime}\n";
		$time ||= $timestamp - $m->{basic}{lasttime} if $timestamp - $m->{basic}{lasttime} > 0;
#		print $m->name . ": time=$time\n";
		$m->{basic}{onlinetime} += $time if ($time > 0);
		$m->save;
		$m = undef;
	}
	$self->{maps} = {};

	$self->{db}->commit;

}

# COD "ShutdownGame" event. Occurs when the server is shutdown or the map
# changes. If the server was literally shutdown then the next event will be an
# "InitGame" with a time of 00:00.
sub event_cod_shutdown {
	#my ($self, $timestamp, $args) = @_;
	## flag that a shutdown occured, the event() sub will handle the
	## game reset or round restart depending on what the new timestamp is
	## on the next event.
	#$self->{cod_shutdown} = $timestamp;
}

sub event_attacked {
	my ($self, $timestamp, $args) = @_;
	my ($victim, $killer, $weapon, $dmg, $type, $hitgroup) = @$args;
	my $p1 = $self->get_plr($killer) || return;
	my $p2 = $self->get_plr($victim) || return;

	my $melee = "MOD_MELEE";

	$p1->{basic}{lasttime} = $timestamp;
	$p2->{basic}{lasttime} = $timestamp;

	return unless $self->minconnected;
	return if $self->isbanned($p1) or $self->isbanned($p2);

	my $r1 = $self->get_role($p1->{role}, $p1->{team});
	my $w = $self->get_weapon($weapon);
	if ($type eq $melee) {
		#print " KNIFE DETECTED \n";
		$type = "knife";
		my $w = $self->get_weapon($type);
	}
	$self->_record_shot($hitgroup, $dmg, $p1, $r1, $w);
}

#### REVISED TO TRACK KNIFE KILLS.  COD USES KNIFE ONLY AS A DAMAGE TYPE, FOR THE CARRIED WEAPON
#### THIS SHOULD SUBSTITUTE KNIFE FOR THE CARRIED WEAPON, TO RECORD THE KILL.  [CDN] NIGHTWARE 2012.
sub event_kill {
	my ($self, $timestamp, $args) = @_;
	my ($victim, $killer, $weapon, $dmg, $type, $hitgroup) = @$args;
	my $p1 = $self->get_plr($killer) || return;
	my $p2 = $self->get_plr($victim) || return;
	my $melee = "MOD_MELEE";

	$p1->{basic}{lasttime} = $timestamp;
	$p2->{basic}{lasttime} = $timestamp;

	return unless $self->minconnected;
	return if $self->isbanned($p1) or $self->isbanned($p2);

	my $m = $self->get_map;
	my $r1 = $self->get_role($p1->{role}, $p1->{team});
	my $r2 = $self->get_role($p2->{role}, $p2->{team});
	my $w = $self->get_weapon($weapon);
	if ($type eq $melee) {
		$type = "knife";
		$w = $self->get_weapon($type);
		#print "knife kill\n";
	}
	#print "Kill  w is:$w->{weaponid}:$w->{uniqueid};\n";	

	# I directly access the player variables in the objects (bad OO design), 
	# but the speed advantage is too great to do it the "proper" way.

	$p1->update_streak('kills', 'deaths');
	$p1->{basic}{kills}++;
	$p1->{mod}{ $p1->{team} . "kills"}++ if $p1->{team};		# Kills while ON the team
#	$p1->{mod}{ $p2->{team} . "kills"}++;				# Kills against the team
	$p1->{mod_maps}{ $m->{mapid} }{ $p1->{team} . "kills"}++ if $p1->{team};
	$p1->{weapons}{ $w->{weaponid} }{kills}++;
	$p1->{maps}{ $m->{mapid} }{kills}++;
	$p1->{roles}{ $r1->{roleid} }{kills}++ if $r1;
	$p1->{victims}{ $p2->{plrid} }{kills}++;

	$p2->{isdead} = 1;
	$p2->update_streak('deaths', 'kills');
	$p2->{basic}{deaths}++;
	$p2->{mod}{ $p2->{team} . "deaths"}++ if $p2->{team};		# Deaths while ON the team
#	$p2->{mod}{ $p1->{team} . "deaths"}++;				# Deaths against the team
	$p2->{mod_maps}{ $m->{mapid} }{ $p2->{team} . "deaths"}++ if $p2->{team};
	$p2->{weapons}{ $w->{weaponid} }{deaths}++;
	$p2->{maps}{ $m->{mapid} }{deaths}++;
	$p2->{roles}{ $r2->{roleid} }{deaths}++ if $r2;
	$p2->{victims}{ $p1->{plrid} }{deaths}++;

	# record the headshot KILL
	if ($hitgroup and $hitgroup eq 'head') {
		$p1->{victims}{ $p2->{plrid} }{headshotkills}++;
		$p2->{victims}{ $p1->{plrid} }{headshotdeaths}++;
		$r1->{basic}{headshotkills}++ if $r1;
	}

	$m->{basic}{lasttime} = $timestamp;
	$m->{basic}{kills}++;
	$m->{mod}{ $p1->{team} . 'kills'}++ if $p1->{team};		# kills on the team
	$m->hourly('kills', $timestamp);

	$w->{basic}{kills}++;
	$r1->{basic}{kills}++ if $r1;
	$r2->{basic}{deaths}++ if $r1;

	# friendly-fire kills
	if (($p1->{team} and $p2->{team}) and ($p1->{team} eq $p2->{team})) {
		$p1->{maps}{ $m->{mapid} }{ffkills}++;
		$p1->{weapons}{ $w->{weaponid} }{ffkills}++;
		$p1->{basic}{ffkills}++;

		$p2->{weapons}{ $w->{weaponid} }{ffdeaths}++;
		$p2->{maps}{ $m->{mapid} }{ffdeaths}++;
		$p2->{basic}{ffdeaths}++;

		$m->{basic}{ffkills}++;
		$w->{basic}{ffkills}++;
		$r1->{basic}{ffkills}++ if $r1;

		$self->plrbonus('ffkill', 'enactor', $p1);
	}

	$self->_record_shot($hitgroup, $dmg, $p1, $r1, $w);

	# allow mods to add their own stats for kills
	my $skill_handled = 0;
	if ($self->can('mod_event_kill')) {
		$skill_handled = $self->mod_event_kill($p1, $p2, $w, $m, $r1, $r2, '');
	}
	# calculate new skill values for the players
	$self->calcskill_kill_func($p1, $p2, $w) unless $skill_handled;
}

# player has connected and 'entered the game' (unlike HALFLIFE games which do this in 2 separate steps)
sub event_connected {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr) = @$args;
	# $plrstr can be a reference to a PS::Player object or a player signature string
	my $p1 = ref $plrstr ? $plrstr : $self->get_plr($plrstr) || return;

	$p1->{_connected} = 1;
	if (!$p1->is_bot or !$self->{ignore_bots_conn}) {
		$p1->{basic}{connections}++;
		my $m = $self->get_map;
		if ($m) {
			$p1->{maps}{ $m->{mapid} }{connections}++;
			$m->{basic}{connections}++;
			$m->hourly('connections', $timestamp);
		}
	}

	my $m = $self->get_map;

	$p1->{basic}{games}++;
	$p1->{maps}{ $m->{mapid} }{games}++;
	$p1->{maps}{ $m->{mapid} }{lasttime} = $timestamp;

	# start new timer and save last timer if one was present
	if (my $time = $p1->timerstart($timestamp) and $p1->active) {
		if ($time > 0) {				# ignore negative values
			$p1->{basic}{onlinetime} += $time;
			$p1->{maps}{ $m->{mapid} }{onlinetime} += $time;
		}
	}
	$p1->active(1);
}

sub event_disconnected {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;

	$p1->disconnect($timestamp, $self->get_map);
	$p1->save;
#	$p1->active(0);

	# remove the player from memory
	$self->delcache_all($p1);
	undef $p1;
}

sub event_changed_name {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $name) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;

	if ($self->{uniqueid} eq 'name') {
		my $plrids = {
			name	=> $name,
			worldid => $p1->{worldid},
			ipaddr	=> $p1->{ipaddr},
			uid	=> $p1->{uid}
		};

		my $p2 = new PS::Player($plrids, $self) || return undef;
		$p2->active(1);
		$p2->signature(sprintf("%s<%s><%s><%s>", $name, $p1->{uid}, $p1->{worldid}, uc $p1->{team}));
		$p2->timerstart($self->{timestamp});
		$p2->team($p1->team);
		$p2->plrids;		# changing name counts as a 'reconnect' for the plrids

		$self->delcache_all($p1);
		$p1->disconnect($self->{timestamp}, $self->get_map);
		$p1->save;
		undef $p1;

		$self->addcache_all($p2);
		$self->scan_for_clantag($p2) if $self->{clantag_detection} and !$p2->clanid;
	} else {
		$p1->name($name);
		$p1->plrids({ name => $name });		# changing name should be counted
		$p1->{basic}{lasttime} = $timestamp;
	}
}

sub event_changed_role {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $role) = @$args;
#	print "BEFORE: $plrstr\n";
	my $p1 = $self->get_plr($plrstr) || return;
#	print "AFTER:  $plrstr\n";
	$role = $self->role_normal($role); 
	$p1->{role} = $role;
#	print_r($p1->{roles}) if $p1->worldid eq 'STEAM_0:0:1179775';

	my $r1 = $self->get_role($role, $p1->{team}) || return;
	$p1->{roles}{ $r1->{roleid} }{joined}++;
	$r1->{basic}{joined}++;
}

sub event_suicide {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $weapon, $props) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;

	return unless $self->minconnected;
	return if $self->isbanned($p1);
	my $m = $self->get_map;

	$weapon = $self->weapon_normal($weapon);

	if ($weapon ne 'world') {
		$p1->{basic}{lasttime} = $timestamp;
		$p1->{basic}{deaths}++;
		$p1->{basic}{suicides}++;
		$m->{basic}{suicides}++;
		$self->plrbonus('suicide', 'enactor', $p1);	# 'suicide' award bonus/penalty for killing yourself (idiot!)
#	} else {
#		# plr changed teams, do not count the suicide
	}

}

# standard round start/end for several mods
sub event_round {
	my ($self, $timestamp, $args) = @_;
	my ($trigger, $props) = @$args;
	my $m = $self->get_map;

	$trigger = lc $trigger;
	# mini_round_start is a TF2 trigger, but its more efficient to put it here instead of halflife/tf2.pm
	if ($trigger eq 'round_start' or $trigger eq 'mini_round_start') {
		$self->{roundstart} = $timestamp;
		$m->{basic}{rounds}++;
		$m->hourly('rounds', $timestamp);
		# make sure a game is recorded. Logs that do not start with a 'map started' event
		# will end up having 1 less game recorded than normal unless we fudge it here.
		if (!$m->{basic}{games}) {
			$m->{basic}{games}++;
			$m->hourly('games', $timestamp);
		}
		foreach my $p1 ($self->get_plr_list) {
			$p1->{basic}{lasttime} = $timestamp;
			$p1->is_dead(0);
			$p1->{basic}{rounds}++;
			$p1->{maps}{ $m->{mapid} }{rounds}++;
#			$p1->save if $self->{plr_save_on_round};
		}
	}
}

sub event_startedmap {
	my ($self, $timestamp, $args) = @_;
	my ($startorload, $mapname, $props) = @$args;
	my $m;

	# ignore 'map loaded' events, we only care about 'map started' events
	return unless lc $startorload eq 'started';

	# a previous map was already loaded, save it now
	if ($m = $self->{maps}{ $self->{curmap} }) {
		my $time = $m->timer;
		$m->{basic}{onlinetime} += $time if ($time > 0);
		$m->save;
		delete $self->{maps}{ $self->{curmap} };
	}

	# start up the new map in memory
	$self->{curmap} = $mapname;
	$m = $self->get_map;
	$m->statdate($timestamp);
	$m->{basic}{games}++;
	$m->hourly('games', $timestamp);
}

sub event_logstartend {
	my ($self, $timestamp, $args) = @_;
	my ($startedorclosed) = @$args;
	my $m = $self->get_map;

	# A log 'started' event is almost ALWAYS guaranteed to happen (unlike 'closed' events)
	# we use this time to close out any previous maps and save all current player data in memory
	return unless lc $startedorclosed eq 'started';

#	$self->show_cache;
	$self->save(1);
}

# normalize a weapon name
sub weapon_normal {
	my ($self, $weapon) = @_;
	$weapon = lc $weapon;
	# remove the weapon customizations... 
	$weapon =~ s/(?:_acog|_gl|_reflex|_silencer|_short|_bipod_crouch|_bipod_stand|_grip)?_mp$//;
#	$weapon =~ s/^\s//;
	# the following 'fixes' corrupted lines where a game server crashed and has
	# "  00:00 ------------------" logged at the end of an incomplete line.
	# we record the weapon as 'none' so its at least partially tracked.
#	$weapon = 'none' if substr($weapon,0,1) eq '0';
#	$weapon =~ s/_mp$//;
	return $weapon;
}

sub parseprops {
	my ($self, $str) = @_;
	my ($var, $val);     
	my $props = {};
	$str = '' if !defined $str;
	$str = substr($str,1) if substr($str,0,1) eq '\\';
	%$props = split(/\\/, $str);
	return wantarray ? %$props : $props;
}

# sorting method that the Feeder class can use to sort a list of log filenames
# returns a NEW array reference of the sorted logs. Does not change original reference.
sub logsort {
	my $self = shift;
	my $list = shift;		# array ref to a list of log filenames
	return [ sort { $a cmp $b } @$list ];
}

sub _record_shot {
	my ($self, $hitgroup, $dmg, $p1, $r1, $w) = @_;
	return unless $hitgroup and $dmg;

	if ($r1) {
		$r1->{basic}{shots}++;
		$r1->{basic}{hits}++;
		$r1->{basic}{damage} += $dmg;
	}

	$w->{basic}{shots}++;
	$w->{basic}{hits}++;
	$w->{basic}{damage} += $dmg;

	$p1->{basic}{shots}++;
	$p1->{basic}{hits}++;
	$p1->{basic}{damage} += $dmg;

	$p1->{weapons}{ $w->{weaponid} }{shots}++;
	$p1->{weapons}{ $w->{weaponid} }{hits}++;
	$p1->{weapons}{ $w->{weaponid} }{damage} += $dmg;

	if ($hitgroup and $hitgroup ne 'none') {
		my $loc = $self->{hitgroups}{$hitgroup};
		if ($loc) {
			$w->{basic}{$loc}++;
			$p1->{weapons}{ $w->{weaponid} }{$loc}++;
			$r1->{basic}{$loc}++ if $r1;
		}
	}
}

1;
