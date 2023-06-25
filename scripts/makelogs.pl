#!/usr/bin/perl
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
#	$Id: makelogs.pl 450 2008-05-20 11:34:52Z lifo $
#
#	This is a small script to generate a set of halflife logs to test
#       with. This allows for controlled and known testing results of logs (with
#       just a hint of randomness). This script is still in development and does
#       not account for all possible testing scenarios that I require (not yet).
#


use strict;
use warnings;
use Getopt::Long;
use IO::File;
use POSIX;
use Data::Dumper;

our $VERSION = '1.00.' . (('$Rev: 450 $' =~ /(\d+)/)[0] || '000');

my $opt = {};
Getopt::Long::Configure(qw( no_ignore_case auto_abbrev ));
GetOptions(
	'help'			=> \$opt->{help},
	'dir|o=s'		=> \$opt->{dir},	# directory to write logs to
	'start=s'		=> \$opt->{start},	# starting timestamp for logs
	'logs=i'		=> \$opt->{logs}, 	# number of logs to create
	'players|plrs=i'	=> \$opt->{players},	# number of players to create
	'kills=i'		=> \$opt->{kills},	# number of kills to record (per log)
	'kpr=i'			=> \$opt->{kpr},	# kills per round
	'modtype=s'		=> \$opt->{modtype},	# modtype (cstrike)
	'gametype=s'		=> \$opt->{gametype},	# gametype (halflife)
	'delay=f'		=> \$opt->{delay},	# random delay between events
	'idx=i'			=> \$opt->{idx},	# log index to start at
) or help();

$opt->{start} 	||= time() - (60*60*24*2);	# start 2 days ago
$opt->{logs}	||= 10;
$opt->{kills}	||= 300;
$opt->{kpr}	||= 50;
$opt->{modtype}	||= 'cstrike';
$opt->{gametype}||= 'halflife';
$opt->{delay}	||= '1.0';
$opt->{players}	||= 10;
$opt->{dir}	||= 'logs';
$opt->{idx}	||= 0;
#print Dumper($opt);

my $stats = { kills => 0, ffkills => 0, deaths => 0 };
my @players = create_players($opt->{players});
my $time = $opt->{start};

my $log;
my $total_logs = 0;

while ($total_logs++ < $opt->{logs}) {
	$time = inc_time($time);
	$log = open_log($time);
	start_log($time);
	event_startmap(random_map());
	start_round($time);
	my $kills = 0;
	while ($kills < $opt->{kills}) {
		no strict 'refs';
		$time = inc_time($time);
		my $trigger = random_trigger();
		my $p1 = random_player();
		my $p2 = random_player($p1);
		my $w  = random_weapon();
		event_connect($p1) unless $p1->{connected};
		event_connect($p2) unless $p2->{connected};
		event_entered($p1) unless $p1->{entered};
		event_entered($p2) unless $p2->{entered};
		&$trigger($p1, $p2, $w);
		$kills++ if $trigger eq 'event_killed';
		if ($kills % $opt->{kpr} == 0 and $kills < $opt->{kills}) {
			end_round($time);
			start_round($time);
		}
	}
	end_round($time);
	end_log($time);
	close_log($log);
	players_not_entered();
}

# print player stats for reference
my $headfmt = "%-16s %-12s %-7s %-9s %-7s\n";
my $statfmt = "%-16s %-12s %-7d %-9d %-7d\n";
printf($headfmt, "Player Name", "Team", "Kills", "FF Kills", "Deaths");
print "-" x 60, "\n";
foreach my $p (sort { $b->{kills} <=> $a->{kills}} @players) {
	printf($statfmt, $p->{name}, $p->{team}, $p->{kills}, $p->{ffkills}, $p->{deaths});
}
print "-" x 60, "\n";
printf($statfmt, "Totals", "", $stats->{kills}, $stats->{ffkills}, $stats->{deaths});

sub start_round {
	my ($time) = @_;
	if ($opt->{modtype} eq 'cstrike') {
		write_log("World triggered \"Round_Start\"")
	} elsif ($opt->{modtype} eq 'tf2') {
		write_log("World triggered \"Round_Start\"")
	}
}

sub end_round {
	my ($time) = @_;
	if ($opt->{modtype} eq 'cstrike') {
		write_log("World triggered \"Round_End\"")
	} elsif ($opt->{modtype} eq 'tf2') {
		write_log("World triggered \"Round_Win\" (winner \"" . random_team() . "\")")		
	}
}

sub event_startmap {
	my ($map) = @_;
	write_log("Started map \"$map\" (CRC \"-1\")");
}

sub event_killed {
	my ($p1, $p2, $weapon) = @_;
	my $headshot = (int rand(10) == 1);	# 10% chance to get a headshot
	write_log(plr($p1) . " killed " . plr($p2) . " with \"$weapon\"" . ($headshot ? ' (headshot)' : ''));
	$stats->{kills}++;
	$stats->{deaths}++;
	$stats->{headshots}++ if $headshot;
	$p1->{kills}++;
	$p2->{deaths}++;
	if ($p1->{team} eq $p2->{team}) {
		$stats->{ffkills}++;
		$p1->{ffkills}++;
		$p2->{ffdeaths}++;
	}
}

sub event_entered {
	my $p = shift;
	inc_time($time);
	write_log(plr($p,1) . " entered the game");
	$p->{entered} = 1;
}

sub event_joined {
	my $p = shift;
	write_log(plr($p,1) . " joined team \"$p->{team}\"");
}
sub event_connect {
	my $p = shift;
	$p->{connected}++;
	write_log(plr($p,1) . " connected, address \"127.0.0.$p->{uid}:27005\"");
}

sub random_player {
	my $exclude = shift;
	while (1) {
		my $i = int rand(scalar @players);
		my $p = $players[$i];
		# don't allow the excluded player to be selected
		next if $exclude and $exclude->{uid} eq $p->{uid};
		return $p;
	}
}

sub random_trigger {
	my $trigger = 'killed';
	return 'event_' . $trigger;
}

sub random_weapon {
	my $weapons = {
		'halflife' => {
			'cstrike' => [ qw( mp5navy awp usp p90 m4a1 ak47 knife ) ],
		}
	};
	my @list = @{$weapons->{$opt->{gametype}}{$opt->{modtype}}};
	@list = ${$weapons->{halflife}{cstrike}} unless scalar @list;
	return $list[ int rand( scalar @list ) ];
}

sub disconnect_players {
	foreach my $p (@players) {
		$p->{connected} = 0;
	}
}

sub players_not_entered {
	foreach my $p (@players) {
		$p->{entered} = 0;
	}
}

# returns a team name. If $team is true the 'good' team is returned, otherwise
# the 'bad' team is returned.
sub get_team {
	my ($team) = @_;
	if ($opt->{modtype} eq 'cstrike') {
		return $team ? 'CT' : 'TERRORIST';
	} elsif ($opt->{modtype} eq 'tf2') {
		return $team ? 'BLUE' : 'RED'
	} elsif ($opt->{modtype} eq 'dod') {
		return $team ? 'ALLIES' : 'AXIS'
	} elsif ($opt->{modtype} eq 'natural') {
		return $team ? 'MARINES' : 'ALIENS'
	} elsif ($opt->{modtype} eq 'tfc') {
		return $team ? 'BLUE' : 'RED'
	} elsif ($opt->{modtype} eq 'firearms') {
		return $team ? 'BLUEFORCE' : 'REDFORCE'
	}
}

sub random_map {
	my $maps = {
		'halflife' => {
			'cstrike' => [ qw( de_dust cs_italy de_nuke cs_assault de_prodigy ) ],
		}
	};
	my @list = @{$maps->{$opt->{gametype}}{$opt->{modtype}}};
	@list = ${$maps->{halflife}{cstrike}} unless scalar @list;
	return $list[ int rand( scalar @list ) ];
}

sub plr {
	my $p = shift;
	my $ignore_team = shift;
	if ($ignore_team) {
		return sprintf("\"%s<%d><%s><>\"", $p->{name}, $p->{uid}, $p->{worldid});
	} else {
		return sprintf("\"%s<%d><%s><%s>\"", $p->{name}, $p->{uid}, $p->{worldid}, $p->{team});
	}
}

sub open_log {
	my ($time) = @_;
	my $filename = sprintf(POSIX::strftime("L%m%d%%03d.log", localtime $time), $opt->{idx}++);
	if (!-e $opt->{dir}) {
		mkdir($opt->{dir}) or die "Error creating logs directory: $opt->{dir}: $!\n";
	}
	return new IO::File($opt->{dir} . '/' . $filename, '>')
		|| die "Error opening log file $filename for writting\n";
}

sub close_log {
	my ($fh) = @_;
	$fh->close if $fh;
}

sub start_log {
	write_log("Log file started (file \"logfile.log\") (game \"$opt->{gametype}/$opt->{modtype}\") (version \"3264\")");
}

sub end_log {
	write_log("Log file closed");
}

sub write_log {
	my ($event, $timestamp) = @_;
	$timestamp ||= $time;
	print $log log_timestamp($timestamp) . $event . "\n";
}

sub log_timestamp {
	my $timestamp = shift || $time;
	return POSIX::strftime("L %m/%d/%Y - %T: ", localtime $timestamp);
}

sub create_players {
	my ($total) = @_;
	my $half = int($total / 2);
	my @players = ();
	for (my $i=0; $i < $total; $i++) {
		$players[$i] = {
			'name'		=> 'Player ' . ($i+1),
			'uid'		=> 100 + $i,
			'worldid'	=> 'BOT',
			'ipaddr'	=> '127.0.0.' . (100 + $i),
			'team'		=> get_team($i < $half),
			'kills'		=> 0,
			'deaths'	=> 0,
			'ffkills'	=> 0,
			'connected'	=> 0,
		};
	}
	return @players;
}

sub inc_time {
	my $timestamp = shift;
	return $timestamp + ($opt->{delay} * rand(1));
}

sub help {
	warn "Usage:\n";
	warn "\t-help\t\tDisplay this help text.\n";
	exit(1);
}
