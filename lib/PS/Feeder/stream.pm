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
#	$Id: stream.pm 522 2008-07-16 18:34:21Z lifo $
#
#	UDP Stream support for Half-Life servers.
#
package PS::Feeder::stream;

use strict;
use warnings;
use base qw( PS::Feeder );

use IO::Socket::INET;
use IO::Select;
use util qw( :net print_r expandlist );
use PS::DB;
use PS::ConfigHandler;
use PS::Game;

our $VERSION = '1.20.' . (('$Rev: 522 $' =~ /(\d+)/)[0] || '000');


sub init {
	my $self = shift;

	$self->{_opts} = {};
	$self->{_logs} = [ ];
	$self->{_curline} = 0;
	$self->{_log_regexp} = qr/\.log$/io;
	$self->{_protocol} = 'stream';

	$self->{debug} = $self->{conf}->get_opt('debug');
	$self->{bindip} = $self->{conf}->get_opt('ip');
	$self->{bindport} = $self->{conf}->get_opt('port');

	$self->{_curlog} = $self->defaultmap;
	$self->{_curline} = 0;
	$self->{_allowed_hosts} = undef;
	$self->{_allowed_cache} = {};
	$self->{_not_allowed} = {};
	$self->{_clients} = {};

	$self->{type}  = $PS::Feeder::WAIT;
	$self->{state} = $self->load_state;

	$self->init_acl;
	
	return $self->_connect ? $self->{type} : undef;
}

# initialize our ACL for allowed hosts
sub init_acl {
	my $self = shift;
	my $opts = $self->{logsource}{options} || return;
	my @allowed = ();
	my @lines = split(/\r?\n/, $opts);
	chomp(@lines);
	
	# each line is: CIDR/bits  list,of,ports
	while (my $line = shift @lines) {
		$line =~ s/^\s+//;
		$line =~ s/\s+$//;
		my ($cidr, $list) = split(/\s+/, $line, 2);
		my ($ipstr,$bits) = split(/\//, $cidr, 2);
		unless ($ipstr =~ /^[0-9\.]+$/) {
			$::ERR->warn("Ignoring invalid IP in access-list: '$ipstr'.");
			next;
		}
		my $ip = ip2int($ipstr);
		my $ports = expandlist($list);
		$bits = 32 if !$bits or $bits > 32 or $bits < 1;
		$ports = undef unless @$ports; 

		my $network = ip2int(ipnetwork($ip, $bits)) || next;
		my $broadcast = ip2int(ipbroadcast($ip, $bits)) || next;
		
		$self->{_allowed_hosts} ||= [];
		push(@{$self->{_allowed_hosts}}, {
			network 	=> $network,
			broadcast 	=> $broadcast,
			ports		=> $ports
		});
	}
}

# setup our socket for listening to incoming log streams
sub _connect {
	my $self = shift;

	# override listen IP:Port from command line	
	$self->{_host} = $self->{bindip} if $self->{bindip};
	$self->{_port} = $self->{bindport} if $self->{bindport};

	$self->{socket} = new IO::Socket::INET(
		Proto => 'udp', 
		LocalHost => ($self->{_host} eq 'localhost' or $self->{_host} eq '127.0.0.1')
				? undef : $self->{_host}, 
		LocalPort => $self->{_port}
	);
	if (!$self->{socket}) {
		$::ERR->warn("Error binding to local port $self->{_host}:$self->{_port}: $@");
		return undef;
	}

	$self->{select} = new IO::Select($self->{socket});

	$self->info("Listening on socket $self->{_protocol}://$self->{_host}:$self->{_port} ...");

	if ($self->{_allowed_hosts}) {
		$self->info("Access control enabled for " . (scalar @{$self->{_allowed_hosts}}) . " networks.");
		
	} else {
		$self->info("No ACL configured; All log streams will be allowed.");
	}

	return 1;
}


sub echo_processing {
	my ($self) = @_;
	my $total = $self->{_clients} ? keys %{$self->{_clients}} : 0;
	$::ERR->verbose("Processing $total streams on $self->{_host}:$self->{_port} (" .
			$self->lines_per_second . " lps / " .
			$self->bytes_per_second(1) . ")"
	);
}

sub parsesource {
	my $self = shift;
	my $db = $self->{db};
	my $log = $self->{logsource};

	$self->{_host} = 'localhost';
	$self->{_port} = 28000;

	if (ref $log) {
		$self->{_host} = $log->{host} if defined $log->{host};
		$self->{_port} = $log->{port} if defined $log->{port};
		$db->update($db->{t_config_logsources}, { lastupdate => time }, [ 'id' => $log->{id} ]);

	} elsif ($log =~ /^([^:]+):\/\/([^\/:]+)(?::(\d+))?\/?(.*)/) {
		my ($protocol,$host,$port,$dir) = ($1,$2,$3,$4);
		$self->{_protocol} = $protocol;
		$self->{_host} = $host;
		$self->{_port} = $port || 28000;
		$self->{_dir} = $dir;				# not used

		# see if a matching logsource already exists
		my $exists = $db->get_row_hash(sprintf("SELECT * FROM $db->{t_config_logsources} " . 
			"WHERE type='stream' AND host=%s AND port=%s ", 
			$db->quote($self->{_host}),
			$db->quote($self->{_port})
		));

		if (!$exists) {
			# fudge a new logsource record and save it
			$self->{logsource} = {
				'id'		=> $db->next_id($db->{t_config_logsources}),
				'type'		=> 'stream',
				'path'		=> $self->{_dir},
				'host'		=> $self->{_host},
				'port'		=> $self->{_port},
				'passive'	=> undef,
				'username'	=> undef,
				'password'	=> undef,
				'recursive'	=> undef,
				'depth'		=> undef,
				'skiplast'	=> 0,
				'delete'	=> 0,
				'options'	=> undef,
				'defaultmap'	=> 'unknown',
				'enabled'	=> 0,		# leave disabled since this was given from -log on command line
				'idx'		=> 0x7FFFFFFF,
				'lastupdate'	=> time
			};
			$db->insert($db->{t_config_logsources}, $self->{logsource});
		} else {
			$self->{logsource} = $exists;
			$db->update($db->{t_config_logsources}, { lastupdate => time }, [ 'id' => $exists->{id} ]);
		}

	} else {
		$::ERR->warn("Invalid logsource syntax. Valid example: stream://localhost:28000");
		return undef;
	}

	return 1;
}

sub next_event {
	my $self = shift;
	my $line;
	my ($peername, $port, $packedip, $ip, $head);

	# the stream never stops, except for GRACEFUL_EXIT, -maxlogs, -maxlines
	while (1) {
		if ($::GRACEFUL_EXIT > 0) {
			return undef;
		}

		$line = '';
		if (my ($s) = $self->{select}->can_read(1)) {
			$s->recv($line, 1500);
			next unless $line;
			$peername = $s->peername || next;
			($port, $packedip) = sockaddr_in($peername);
			$ip = inet_ntoa($packedip);
			if (!$self->allowed($ip,$port)) {
				# only report the unauthorized attempt once...
				if (!$self->{_not_allowed}{$ip.':'.$port}) {
					$::ERR->warn("Unauthorized log stream from '$ip:$port' will be ignored!");
					$self->{_not_allowed}{$ip.':'.$port} = 1;
				}
				next;
			}

			# each client IP:port has its own Game object.
			# This allows each stream to be treated as a separate
			# game and should not conflict with other stats being
			# collected at the same time from other streams.
			if (!exists $self->{_clients}{$ip.':'.$port}) {
				my $game;
				if (!keys %{$self->{_clients}}) {
					# The first client will use the original
					# game object already in memory.
					$game = $self->{game};
				} else {
					# any other client will use a new object.
					# we must create new DB handles, etc.
					my $dbconf = { map { $_ => $self->{db}{$_} } qw(dbtype dbhost dbport dbname dbuser dbpass dbtblprefix dbcompress) };
					my $db  = new PS::DB($dbconf);
					my $conf = new PS::ConfigHandler(new PS::CmdLine, $db);
					$db->init_tablenames($conf);
					$game = new PS::Game($conf, $db);
					$game->{last_ranked} = $game->{day} || 0;
					$game->{last_ranked_line} = 0;
					$game->{curmap} = $self->defaultmap;
					$game->init_events;
				}
				$self->{_clients}{$ip.':'.$port} = {
					game		=> $game,
					lastupdate	=> time
				};
				$::ERR->info("New log stream started for '$ip:$port'");
			} else {
				$self->{_clients}{$ip.':'.$port}{lastupdate} = time;				
			}

			$head  = substr($line,0,5,'');					# "....R" (hl2) or "....l" (hl1)
			$head .= substr($line,0,3,'') if substr($head,-1) ne 'R';	# HL1 (remove entire '....log.')
			$line = substr($line,0,-1);					# remove trailing NULL byte

			if ($self->{conf}->get_opt('echo')) {
				print sprintf("%-22s", $ip.':'.$port) . $line;
			}
			
			# keep track of the current log name
			if ($line =~ /^L .......... - ..:..:..: Log file started/) {
				if ($line =~ /file ".*(L\d+\.log)"/) {
					$self->{_curlog} = $1;
				}
			}

			if ($self->{_verbose}) {
				$self->{_totallines}++;
				$self->{_totalbytes} += length($line) + length($head) + 1;
				$self->{_lastprint_bytes} += length($line);
	
				if (time - $self->{_lastprint} > $self->{_lastprint_threshold}) {
					$self->echo_processing(1);
					$self->{_lastprint} = time;
				}
			}

			# if we received a line stop the loop and return
			last if length $line > 0;
		} else {
			if (time - $self->{_lastprint} > $self->{_lastprint_threshold}) {
				$self->echo_processing(1);
				$self->{_lastprint} = time;
			}
		}
	}

	my @ary = ( $self->{_curlog}, $line, ++$self->{_curline}, $self->{_clients}{$ip.':'.$port}{game} );
	return wantarray ? @ary : [ @ary ];
}

# returns true if the host IP and Port given is allowed.
# If no hosts are configured then all hosts are allowed.
sub allowed {
	my ($self, $ip, $port) = @_;
	if (!defined $self->{_allowed_hosts}) {
		return 1; # explicitly allow
	}

	# quickly check the cache for the IP port
	if (exists $self->{_allowed_cache}{$ip.':'.$port}) {
		return $self->{_allowed_cache}{$ip.':'.$port};
	}
	
	# loop through the ACL for a matching network and port
	$ip = ip2int($ip);
	my $matched = 0;
	foreach my $acl (@{$self->{_allowed_hosts}}) {
		if ($ip >= $acl->{network} and $ip <= $acl->{broadcast}) {
			if (!defined $acl->{ports}) {
				# if no ports are defined then allow all
				$matched = 1;
			} elsif (grep { $_ == $port } @{$acl->{ports}}) {
				# much match one of the ports configured
				$matched = 1;
			}
			last if $matched;
		}
	}

	# add the IP to the cache for quick and repetitive lookups
	$self->{_allowed_cache}{$ip.':'.$port} = $matched;
	return $matched;
}

# save game for each client currently streaming...
sub save_games {
	my ($self, $silent) = @_;
	return unless $self->{_clients};
	$self->info("Saving all streaming stats...") unless $silent;
	foreach my $ip (keys %{$self->{_clients}}) {
		my $c = $self->{_clients}{$ip};
		$c->{game}->save;
		# this client hasn't sent anything in an hour... drop it
		if (time - $c->{lastupdate} > 60*60) {
			$self->info("Removing idle stream '$ip' (more than 1 hour old)") unless $silent;
			undef $c->{game};
			delete $self->{_clients}{$ip};
		}
	}
}
sub done {
	my $self = shift;
	$self->save_games(1);
	$self->SUPER::done(@_);
}

1;
