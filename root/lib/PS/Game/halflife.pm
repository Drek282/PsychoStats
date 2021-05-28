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
#	$Id: halflife.pm 540 2008-08-18 10:54:48Z lifo $
#
package PS::Game::halflife;

use strict;
use warnings;
use base qw( PS::Game );
use Scalar::Util qw( looks_like_number );

use util qw( :net :date bench print_r );
use Encode qw(encode decode);
use Time::Local qw( timelocal_nocheck );
use PS::Player;

our $VERSION = '1.10.' . (('$Rev: 540 $' =~ /(\d+)/) || '000')[0];

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

	# keep track of the last timestamp prefix string and time.
	# this allows the event loop to reuse the previous time value 
	# w/o having to do a regex or call timelocal().
	$self->{last_prefix} = "";
	$self->{last_timestamp} = 0;
	$self->{last_min} = undef;
	$self->{last_hour} = undef;
	$self->{last_day} = undef;
	$self->{min} = undef;
	$self->{hour} = undef;
	$self->{day} = undef;

	# keep track of when a round started.
	$self->{roundstart} = 0;

#	$self->{bans}{ipaddr} = {};	# Current 'permanent' bans from the current log by IP ADDR
#	$self->{bans}{worldid} = {};	# ... by worldid / steamid

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

# handle the event that comes in from the Feeder (log line)
use constant 'PREFIX_LENGTH' => 25;
sub event {
	my $self = shift;
	my ($src, $event, $line) = @_;
	my ($prefix, $timestamp);
	$event = decode('UTF-8',$event);		# HL logs are UTF-8 encoded
	chomp($event);
	return if length($event) < PREFIX_LENGTH;	#			"123456789*123456789*12345"
	$prefix = substr($event, 0, PREFIX_LENGTH);	# PREFIX (25 chars): 	"L MM/DD/YYYY - hh:mm:ss: "

	$self->{_src} = $src;
	$self->{_event} = $event;
	$self->{_line} = $line;

	# avoid performing the prefix regex as much as possible (possible performance gain).
	# In busy logs the timestamp won't change for several times at a time (several events per second).
	if ($prefix eq $self->{last_prefix}) {
		$timestamp = $self->{last_timestamp};
	} else {
		if ($prefix !~ /^L (\d\d)\/(\d\d)\/(\d\d\d\d) - (\d\d):(\d\d):(\d\d)/) {
			if ($self->{report_timestamps}) {
				# do not warn on lines with "unable to contact the authentication server, 31)."
				$self->warn("Invalid timestamp for source '$src' line $line event '$event'") unless substr($prefix,0,6) eq 'unable';
			}
			return;
		}
		$timestamp = timelocal_nocheck($6, $5, $4, $2, $1-1, $3-1900);
		
		$self->{last_timestamp} = $timestamp;
		$self->{last_prefix} = $prefix;
		$self->{last_min} = $self->{min};
		$self->{last_hour} = $self->{hour};
		$self->{last_day} = $self->{day};
		$self->{min} = $5;
		$self->{hour} = $4;
		$self->{day} = $2;
	}
	$self->{timestamp} = $timestamp;
	substr($event, 0, PREFIX_LENGTH, '');					# remove prefix from the event

	# SEARCH FOR A MATCH ON THE EVENT USING OUR LIST OF REGEX'S
	# If a match is found we dispatch the event to the proper event method 'event_{match}'
	my ($re, $params) = &{$self->{evregex}}($event);			# finds an EVENT match (fast)
	if ($re) {
		return if $self->{evconf}{$re}{ignore};				# should this match be ignored?
		$self->{re_match} = $re;					# keep track of the event that matched
		my $func = 'event_' . ($self->{evconf}{$re}{alias} || $re);	# use specified $event or 'event_$re'
		$self->$func($timestamp, $params);				# call event handler
	} else {
		$self->info("Unknown log event was ignored from source $src line $line: $event") if $self->{report_unknown};
	}

	# do some extra processing ...
=pod
	if (defined $self->{last_min} and $self->{last_min} != $self->{min}) {
		# every minute collect some player data
		$self->{last_min} = $self->{min};
		$self->{_plrtot} += $self->get_online_count;
		$self->{_plrcnt}++;
	}
	if (defined $self->{last_hour} and ($self->{last_hour} != $self->{hour} or $self->{last_day} != $self->{day})) {
		print "$self->{hour}: " . int($self->{_plrtot} / $self->{_plrcnt}) . "\n";
		$self->get_map->hourly('online', int($self->{_plrtot} / $self->{_plrcnt}));
		$self->{_plrcnt} = 0;
		$self->{_plrtot} = 0;
		$self->{last_hour} = $self->{hour};
		$self->{last_day} = $self->{day};
	}
=cut
}

sub process_feed {
	my ($self, $feeder) = @_;
	my @total = $self->SUPER::process_feed($feeder);

	# after the feed ends make sure all stats in memory are saved.
	# the logstartend event does everything we need to save all in-memory stats.
	$self->event_logstartend($self->{timestamp}, [ 'started' ]);	

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
#			print "SIMPLE: $str\n" if $p; # and $plrstr =~ /:2677794/;
#			$p->plrids;
			return $p;
		}
	}

	$str = $plrstr;
    
	# using multiple substr calls inplace of a single regex is a lot faster 
	$team = substr($str, rindex($str,'<'), 128, '');
	$team = $self->team_normal(substr($team, 1, -1));

	$worldid = substr($str, rindex($str,'<'), 128, '');
	$worldid = substr($worldid, 1, -1);
    $worldid =~ s/^\[//;
    $worldid =~ s/\]$//;

	$uid = substr($str, rindex($str,'<'), 128, '');
	$uid = substr($uid, 1, -1);
    
	if (!$worldid or $uid eq '-1') {		# ignore any players with an ID of -1 ... its invalid!
		$::ERR->debug1("Ignoring invalid player identifier from logsource '$self->{_src}' line '$self->{_line}': '$plrstr'",3);
		return undef;
	}

	$name = $str;
	$name =~ s/^\s+//;
	$name =~ s/\s+$//;
	$name = 'unnamed' if $name eq '';		# do not allow blank names, period.
    
#	$origname = $name;				# original name before any possible aliases are found
	$ipaddr = exists $self->{ipcache}{$uid} ? $self->{ipcache}{$uid} : 0;

	# For BOTS: replace STEAMID's with the player name otherwise all bots will be combined into the same STEAMID
    my $bot_check = substr($worldid,0,1);
	if ($worldid eq 'BOT' or $worldid eq '0' or looks_like_number( $bot_check )) {
		return undef if $self->{ignore_bots};
		$worldid = "BOT:" . lc substr($name, 0, 124);	# limit the total characters (128 - 4)
	}

	# completely ignore player events for players with STEAM_ID_PENDING, it just causes problems
	if ($worldid eq 'STEAM_ID_PENDING') {
		return undef;
	}

	# lookup the alias for the player's uniqueid
	if ($self->{uniqueid} eq 'worldid') {
		$worldid = $self->get_plr_alias($worldid);
	} elsif ($self->{uniqueid} eq 'name') {
		$name = $self->get_plr_alias($name);
	} elsif ($self->{uniqueid} eq 'ipaddr') {
		$ipaddr = ip2int($self->get_plr_alias(int2ip($ipaddr)));
	}

	# assign the player ID's. This is now an official hash of how to 
	# match the player in the database.
	$plrids = { name => $name, worldid => $worldid, ipaddr => $ipaddr };
	return { %$plrids, uid => $uid, team => $team } if $plrids_only;

	$p = undef;

	# If we get to this point the player signature did not match a current player in memory
	# So we need to try and figure out if they are really a new player or a known player that 
	# changed their name, teams or has reconnected within the same log file.

	# based on their UID the player already existed (changed teams or name since the last event)
	if ($p = $self->cached($uid, 'uid')) {
		$p->team($team);						# keep team up to date
		$self->delcache($p->signature($plrstr), 'signature');		# delete previous and set new sig
		$self->addcache($p, $plrstr, 'signature');			# update sig cache

	} elsif ($p = $self->cached($plrids->{$self->{uniqueid}}, 'uniqueid')) {
		# the only time the UIDs won't match is when a player has extra events that follow a disconnect event.
		# this happens with a couple of minor events like dropping the bomb in CS. The bomb drop event is triggered
		# after the player disconnect event and thus causes confusion with internal routines. So I cache the uniqueid
		# of the player and then fix the 'uid' if needed here...
#		print "UNIQUEID CACHE: $plrstr\n"; # if $plrstr =~ /:2677794/;
		if ($p->uid ne $uid) {
			$p->team($team);
#			$p->plrids($plrids);
			$self->delcache($p->uid($uid), 'uid');
			$self->delcache($p->signature($plrstr), 'signature');
			$self->addcache($p, $p->uid, 'uid');
			$self->addcache($p, $p->signature, 'signature');
		}

	} else {
		$p = new PS::Player($plrids, $self) || return undef;
		if (my $p1 = $self->cached($p->plrid, 'plrid')) {	# make sure this player isn't loaded already
			undef $p;
			$p = $p1;
			$self->delcache_all($p);
		}
		$p->active(1);						# we have to assume the player is active
		$p->signature($plrstr);
		$p->timerstart($self->{timestamp});
		$p->uid($uid);
		$p->team($team);
		# don't double the plrids when they enter (since get_plr will also call ->plrids).
		$p->plrids unless $self->{re_match} eq 'entered_game';

		$self->addcache_all($p);
		$self->scan_for_clantag($p) if $self->{clantag_detection} and !$p->clanid;
	}

	return $p;
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
	my $r1 = $self->get_role($p1->{role}, $p1->{team});
	my $r2 = $self->get_role($p2->{role}, $p2->{team});
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
#	$p2->{roundtime} = $self->{roundstart} ? $timestamp - $self->{roundstart} : undef;

	# most mods have a headshot property
	if ($props->{headshot}) {
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

	# check for spatial stats on this event
	if ($props->{attacker_position}) {
		$m->spatial(
			$self, 
			$p1, $props->{attacker_position}, 
			$p2, $props->{victim_position}, 
			$w, $props->{headshot}
		);
	}

	# allow mods to add their own stats for kills
	my $skill_handled = 0;
	if ($self->can('mod_event_kill')) {
		$skill_handled = $self->mod_event_kill($p1, $p2, $w, $m, $r1, $r2, $props);
	}
	# calculate new skill values for the players
	$self->calcskill_kill_func($p1, $p2, $w) unless $skill_handled;
}


sub event_spatial {
	my ($self, $timestamp, $args) = @_;
	my ($killer, $victim, $weapon, $propstr) = @$args;
	my $p1 = $self->get_plr($killer) || return;
	my $p2 = $self->get_plr($victim) || return;
#	return unless $self->minconnected;
	return if $self->isbanned($p1) or $self->isbanned($p2);

	my $m = $self->get_map;
	my $props = $self->parseprops($propstr);

	my $w = $self->get_weapon($weapon);
	$m->spatial(
		$self, 
		$p1, $props->{attacker_position}, 
		$p2, $props->{victim_position}, 
		$w, $props->{headshot}, 
	);
}

sub event_connected {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $ipstr, $props) = @$args;
	my $ip = lc((split(/:/,$ipstr,2))[0]);
	$ip = '127.0.0.1' if $ip eq 'localhost' or $ip eq 'loopback' or $ip !~ /(?:\d{1,3}\.){3}\d{1,3}/;

	# strip out the worldid/uid and do not use get_plr() since players will have a STEAM_ID_PENDING
	# I have to re-encode the player string here before stripping out the uid/worlid otherwise UTF8
	# seems to interfere with character positions and sometimes the uid is not parsed properly.
	# I'm not 100% sure why this is happening here, and not in the main get_plr() routine which uses
	# some of the same code...
	my $str = encode('UTF-8', $plrstr);
	substr($str, rindex($str,'<'), 128, '');				# remove the team
	my $worldid = substr(substr($str, rindex($str,'<'), 128, ''), 1, -1);
	my $uid = substr(substr($str, rindex($str,'<'), 128, ''), 1, -1);
#	print "$ip\t$worldid\t$uid\n";

	$self->{ipcache}{$uid} = ip2int($ipstr);				# save the IP addr
	return if index(uc $worldid, "PENDING") > 0;				# do nothing if it's STEAM_ID_PENDING
	$self->_do_connected($timestamp, $plrstr);
}

sub event_connected_steamid { # the regex definition is currently set to 'ignore'
# Since valve logs the $plrstr differently on the 'validated' events I'm going to simply ignore these events.
# I don't need to track the users connected state here. 
# The player will be marked as 'connected' in the 'entered' event instead.
#
#	my ($self, $timestamp, $args) = @_;
#	my ($plrstr, $validated) = @$args;
#	my $p1 = $self->get_plr($plrstr) || return;
#	$p1->plrids
#	$self->_do_connected($timestamp, $plrstr);
}

# the connected and connected_steamid events call this to increment the plr/map stats
sub _do_connected {
	my ($self, $timestamp, $plrstr) = @_;
	# $plrstr can be a reference to a player object or a player signature
	my $p1 = ref $plrstr ? $plrstr : $self->get_plr($plrstr) || return;
	my $m = $self->get_map;
	my $bot = $p1->is_bot;

	$p1->{_connected} = 1;
	if (!$bot or !$self->{ignore_bots_conn}) {
		$p1->{basic}{connections}++;
		if ($m) {
			$p1->{maps}{ $m->{mapid} }{connections}++;
			$m->{basic}{connections}++;
			$m->hourly('connections', $timestamp);
		}
	}
}

sub event_disconnected {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;

	# we do not remove the player from the caches (more events may occur after disconnect)
	$p1->disconnect($timestamp, $self->get_map);
#	print "SAVING PLAYER FROM DISCONNECT\n" if ($p1->worldid eq 'STEAM_0:0:1179775');
	$p1->save;
	$p1->active(0);
}

sub event_entered_game {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $props) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	my $m = $self->get_map;

#	$self->_do_connected($timestamp, $plrstr) unless $p1->{_connected};
	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};

	# update the plrids when we enter the game, instead of updating it on every event.
	$p1->plrids;

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

# normalize a role name
sub role_normal {
	my ($self, $role) = @_;
	return lc $role;
}

# normalize a team name
sub team_normal {
	my ($self, $teamstr) = @_;
	my $team = lc $teamstr;

	$team =~ tr/ /_/;					# remove spaces
	$team =~ tr/a-z0-9_//cs;				# remove all non-alphanumeric characters
	$team = 'spectator' if $team eq 'spectators';		# some MODS have a trailing 's'.
	$team = '' if $team eq 'unassigned';			# don't use ZERO

	return $team;
}

# player teams are now detected from their player signature for all events.
# the only reason we need this event now is to add 1 to the proper 'joined' stat.
sub event_joined_team {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $team, $props) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	my $m = $self->get_map;
	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};

	# do nothing if the player changed to the same team. 
	# this occurs at least on CSTRIKE servers. Not sure about others.
	my $normal_team = $self->team_normal($team);
	return if $p1->team eq $normal_team;
	$p1->team($normal_team);
	$p1->{basic}{lasttime} = $timestamp;

	# now for the all-important stat... how many times we joined this team.
	if ($normal_team) {
		$p1->{mod_maps}{ $m->{mapid} }{'joined' . $normal_team}++;
		$p1->{mod}{'joined' . $normal_team}++;
		$m->{mod}{'joined' . $normal_team}++;
	}
}

sub event_changed_name {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $name) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;

	# make sure the name is not all spaces
	$name =~ s/^\s+//;
	$name =~ s/\s+$//;
	$name = 'unnamed' if $name eq '';
	
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
#	print "BEFORE:  $plrstr\n";
	my $p1 = $self->get_plr($plrstr) || return;
#	print "AFTER:  $p1\n";
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
	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};

	return unless $self->minconnected;
	return if $self->isbanned($p1);
	my $m = $self->get_map;

	$weapon = $self->weapon_normal($weapon);

	if (lc $weapon ne 'world') {
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

# watch all chat events for player settings
sub event_chat {
	return; ############################################################
	my ($self, $timestamp, $args) = @_;
	return unless $self->{uniqueid} eq 'worldid';		# only allow user commands when we track by WORLDID
	return unless $self->{usercmds}{enabled};
	my ($plrstr, $teamonly, $msg, $props) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	return if $self->isbanned($p1);

#	$msg = encode('UTF-8', $msg);
	return unless $msg =~ /^$self->{usercmds}{prefix}(.+)\s+(.+)/o;
	my ($cmd, $param) = ($1, $2);

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

	# A log 'started' event is almost ALWAYS guaranteed to happen (unlike
	# 'closed' events) we use this time to close out any previous maps and
	# save all current player data in memory
	return unless lc $startedorclosed eq 'started';

	$self->save(1);
}

sub event_attacked {
	my ($self, $timestamp, $args) = @_;
	my ($killer, $victim, $weapon, $propstr) = @$args;
	my $p1 = $self->get_plr($killer) || return;
	my $p2 = $self->get_plr($victim) || return;
	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};
	$self->_do_connected($timestamp, $p2) unless $p2->{_connected};

	return unless $self->minconnected;
	return if $self->isbanned($p1) or $self->isbanned($p2);

	my $r1 = $self->get_role($p1->{role}, $p1->{team});
	my $r2 = $self->get_role($p2->{role}, $p1->{team});

	$p1->{basic}{lasttime} = $timestamp;
	$p2->{basic}{lasttime} = $timestamp;

	my $w = $self->get_weapon($weapon);
	my $props = $self->parseprops($propstr);

	no warnings;
	my $dmg = int($props->{damage} + $props->{damage_armor});

	if ($r1) {
		$r1->{basic}{shots}++;
		$r1->{basic}{hits}++;
		$r1->{basic}{damage} += $dmg;
	}
	if ($r2) {
		$r2->{basic}{shots}++;
		$r2->{basic}{hits}++;
		$r2->{basic}{damage} += $dmg;
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

	# HL2 started recording the hitbox information on attacked events
	if ($props->{hitgroup} and $props->{hitgroup} ne 'generic') {
		my $loc = $props->{hitgroup};
		$loc =~ s/\s+//g;
		$loc = "shot_$loc";
		$w->{basic}{$loc}++;
		$p1->{weapons}{ $w->{weaponid} }{$loc}++;
		$r1->{basic}{$loc}++ if $r1;
		$r2->{basic}{$loc}++ if $r2;
	}
}

# generic player trigger
sub event_plrtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $trigger, $props) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	return if $self->isbanned($p1);
	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};

	$p1->{basic}{lasttime} = $timestamp;
	return unless $self->minconnected;
	my $m = $self->get_map;

	$trigger = lc $trigger;
	$self->plrbonus($trigger, 'enactor', $p1);
	if ($trigger eq 'weaponstats' or $trigger eq 'weaponstats2') {
		$self->event_weaponstats($timestamp, $args);

	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown player trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
	}
}

sub event_weaponstats {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $trigger, $propstr) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;

	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};
	return if $self->isbanned($p1);

	$p1->{basic}{lasttime} = $timestamp;
	return unless $self->minconnected;
	
	my $props = $self->parseprops($propstr);
	return unless $props->{weapon};
	
	my $weapon = $props->{weapon};
	my $w = $self->get_weapon($weapon) || return;
	my $r1 = $self->get_role($p1->{role}, $p1->{team});

	# dereference once
	my $plrweapon = $p1->{weapons}{ $w->{weaponid} };
	my $plrrole = $r1 ? $p1->{roles}{ $r1->{roleid} } : undef;

	if ($trigger eq 'weaponstats') {
		no warnings;
		# dereference vars so we dont do it over and over below
		my ($hits, $shots, $dmg, $hs) = map { int($_ || 0) } @$props{ qw( hits shots damage headshots ) };

		$w->{basic}{hits} 			+= $hits; 
		$w->{basic}{shots} 			+= $shots;
		$w->{basic}{damage} 			+= $dmg; 
		$w->{basic}{headshotkills}		+= $hs;

		$p1->{basic}{hits} 			+= $hits; 
		$p1->{basic}{shots} 			+= $shots;
		$p1->{basic}{damage} 			+= $dmg;
		$p1->{basic}{headshotkills} 		+= $hs;

		$plrweapon->{hits} 			+= $hits;
		$plrweapon->{shots} 			+= $shots;
		$plrweapon->{damage} 			+= $dmg;
		$plrweapon->{headshotkills}		+= $hs;

		if ($r1) {
			$plrrole->{hits} 		+= $hits;
			$plrrole->{shots}	 	+= $shots;
			$plrrole->{damage} 		+= $dmg;
			$plrrole->{headshotkills}	+= $hs;

			$r1->{basic}{hits} 		+= $hits;
			$r1->{basic}{shots} 		+= $shots;
			$r1->{basic}{damage} 		+= $dmg;
			$r1->{basic}{headshotkills} 	+= $hs;
		}

	} elsif ($trigger eq 'weaponstats2') {
		no warnings;
		# dereference vars so we dont do it over and over below
		my ($head,$chest,$stomach,$leftarm,$rightarm,$leftleg,$rightleg) = map { int($_ || 0) } 
			@$props{ qw( head chest stomach leftarm rightarm leftleg rightleg ) };

#		print "($head,$chest,$stomach,$leftarm,$rightarm,$leftleg,$rightleg)\n";

		$w->{basic}{shot_head} 			+= $head;
		$w->{basic}{shot_chest} 		+= $chest;
		$w->{basic}{shot_stomach} 		+= $stomach;
		$w->{basic}{shot_leftarm} 		+= $leftarm;
		$w->{basic}{shot_rightarm} 		+= $rightarm;
		$w->{basic}{shot_leftleg} 		+= $leftleg;
		$w->{basic}{shot_rightleg} 		+= $rightleg;

		$plrweapon->{shot_head} 		+= $head;
		$plrweapon->{shot_chest} 		+= $chest;
		$plrweapon->{shot_stomach} 		+= $stomach;
		$plrweapon->{shot_leftarm} 		+= $leftarm;
		$plrweapon->{shot_rightarm} 		+= $rightarm;
		$plrweapon->{shot_leftleg} 		+= $leftleg;
		$plrweapon->{shot_rightleg} 		+= $rightleg;

		if ($r1) {
			$plrrole->{shot_head} 		+= $head;
			$plrrole->{shot_chest} 		+= $chest;
			$plrrole->{shot_stomach} 	+= $stomach;
			$plrrole->{shot_leftarm} 	+= $leftarm;
			$plrrole->{shot_rightarm}	+= $rightarm;
			$plrrole->{shot_leftleg} 	+= $leftleg;
			$plrrole->{shot_rightleg} 	+= $rightleg;

			$r1->{basic}{shot_head} 	+= $head;
			$r1->{basic}{shot_chest} 	+= $chest;
			$r1->{basic}{shot_stomach} 	+= $stomach;
			$r1->{basic}{shot_leftarm} 	+= $leftarm;
			$r1->{basic}{shot_rightarm} 	+= $rightarm;
			$r1->{basic}{shot_leftleg} 	+= $leftleg;
			$r1->{basic}{shot_rightleg} 	+= $rightleg;
		}

	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown weaponstats trigger '$trigger' from source $self->{_src} line $self->{_line}: $self->{_event}");
		}
	}

}

sub event_ban {
	my ($self, $timestamp, $args) = @_;
	my ($type, $plrstr, $duration, $who, $propstr) = @$args;

	return unless $self->{auto_plr_bans};

	$type = lc $type;
	if (substr($type,0,3) eq 'ban') {		# STEAMID
		my $plr = $self->get_plr($plrstr) || return;
		$self->addban($plr, reason => 'Auto Ban', 'ban_date' => $timestamp);
	} 
}

sub event_unban {
	my ($self, $timestamp, $args) = @_;
	my ($type, $plrstr, $who, $propstr) = @$args;

	return unless $self->{auto_plr_bans};

	$type = lc $type;
	if ($type eq 'id') {		# STEAMID
		my $plr = $self->get_plr($plrstr) || return;
		$self->unban($plr, 'reason' => 'Auto Unban', 'unban_date' => $timestamp);
	}
}

sub event_plugin {
	my ($self, $timestamp, $args) = @_;
	my ($plugin, $str, $propstr) = @$args;

#	print "[$plugin] $str\n";

#	if (lc $plugin eq 'statsme') {
#		$self->event_weaponstats($timestamp, [ ]);
#	}
}

#sub event_ipaddress {
#	my ($self, $timestamp, $args) = @_;
#	my ($plrstr, $propstr) = @$args;
#	my $plr = $self->get_plr($plrstr,1) || return;		# does not create a player object
#	my $props = $self->parseprops($propstr);
#	return unless $plr->{uid} and $props->{address};
#	$self->{ipcache}{$plr->{uid}} = ip2int($props->{address});	# save the IP address
#}

sub event_rcon {
	my ($self, $timestamp, $args) = @_;
	my ($bad, $challenge, $pw, $cmd, $ipport) = @$args;
}

sub event_kick {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $who, $propstr) = @$args;
}

sub event_cheated {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr) = @$args;
}

sub event_pingkick {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr) = @$args;
}

sub event_ffkick {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr) = @$args;
}

sub parseprops {
	my ($self, $str) = @_;
	my ($var, $val);     
	my $props = {};
	$str = '' if !defined $str;
#	$str =~ s/\s+$//;
	while ($str =~ s/^\s*\((\S+)(?:\s+"([^"]*|.*?(?:<[^>]*>))")?(\s)?\)//) {	# (variable "value")
		$var = $1;
		$val = (defined $2) ? $2 : 1;			# if "value" doesn't exist the var is a true 'boolean' 
		if (exists $props->{$var}) {
			# convert to array if its not already
			$props->{$var} = [ $props->{$var} ] unless ref $props->{$var};
			push(@{$props->{$var}}, $val);		# add to array
		} else {
			$props->{$var} = $val;
		}
	}
	return wantarray ? %$props : $props;
}

# sorting method that the Feeder class can use to sort a list of log filenames
# returns a NEW array reference of the sorted logs. Does not change original reference.
sub logsort {
	my $self = shift;
	my $list = shift;		# array ref to a list of log filenames
	return [ sort { $self->logcompare($a, $b) } @$list ];
}

# compare method that can compare 2 log files for the game and return (-1,0,1) depending on their order
# smart logic tries to account for logs from a previous year as being < instead of > this year
sub logcompare { 
	my ($self, $x, $y) = @_; 

	# Fast path -- $a and $b are in the same month 
	if ( substr($x, 0, 3) eq substr($y, 0, 3) ) { 
		return lc $x cmp lc $y; 
	} 

	# Slow path -- handle year wrapping. localtime returns the month offset by 1 so we add 2 to get the NEXT month
	my $month = (localtime())[4] + 2;

	return ( 
		substr($x, 1, 2) <= $month <=> substr($y, 1, 2) <= $month 
		or 
		lc $x cmp lc $y 
	); 
}

1;
