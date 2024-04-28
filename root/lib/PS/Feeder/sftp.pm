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
#	$Id: sftp.pm 530 2008-08-08 17:53:35Z lifo $
#
#	SFTP Feeder support. Requires Net::SFTP
#
package PS::Feeder::sftp;

use strict;
use warnings;
use base qw( PS::Feeder );
use Digest::MD5 qw( md5_hex );
use File::Spec::Functions qw( splitpath catfile );
use File::Path;
#use Data::Dumper;

our $VERSION = '1.00.' . (('$Rev: 530 $' =~ /(\d+)/)[0] || '000');

my $FH = undef;

sub init {
	my $self = shift;

	eval "require Net::SFTP";
	if ($@) {
		$::ERR->warn("Net::SFTP not installed. Unable to load $self->{class} object.");
		return undef;
	}

	$self->{_logs} = [ ];
	$self->{_curline} = 0;
	$self->{_log_regexp} = qr/\.log$/io;
	$self->{_protocol} = 'sftp';
	$self->{_last_saved} = time;
	$self->{type} = $PS::Feeder::WAIT;

	return undef unless $self->_connect;

	# if a savedir was configured and it's not a directory try to create it
	if ($self->{logsource}{savedir} and !-d $self->{logsource}{savedir}) {
		if (-e $self->{logsource}{savedir}) {
			$::ERR->warn("Invalid directory configured for saving logs ('$self->{logsource}{savedir}'): Is a file");
			$self->{logsource}{savedir} = '';
		} else {
			eval { mkpath($self->{logsource}{savedir}) };
			if ($@) {
				$::ERR->warn("Error creating directory for saving logs ('$self->{logsource}{savedir}'): $@");
				$self->{logsource}{savedir} = '';
			}
		}
	}
	if ($self->{logsource}{savedir}) {
		$::ERR->info("Downloaded logs will be saved to: $self->{logsource}{savedir}");
	}

	return undef unless $self->_readdir;

	$self->{state} = $self->load_state;
	$self->{_pos} = $self->{state}{pos};

	# we have a previous state to deal with. We must "fast-forward" to the log we ended with.
	if ($self->{state}{file}) {
		my $statelog = $self->{state}{file};

		# backup $self->{_logs}
		my @ll_bu = @{$self->{_logs}};

		# first: find the log that matches our previous state in the current log directory
		while (scalar @{$self->{_logs}}) {
			my $cmp = $self->{game}->logcompare($self->{_logs}[0], $statelog);
			if ($cmp == 0) { # ==
				$self->_opennextlog(1);
				# finally: fast-forward to the proper line
				if (int($self->{state}{pos} || 0) > 0) {	# FAST forward quickly
					seek($FH, $self->{state}{pos}, 0);
					$self->{_offsetbytes} = $self->{state}{pos};
					$self->{_curline} = $self->{state}{line};
					$::ERR->verbose("Resuming from source $self->{state}{file} (line: $self->{_curline}, pos: $self->{state}{pos})");
					return $self->{type};
				} else {					# move forward slowly
					while (defined(my $line = <$FH>)) {				
						$self->{_offsetbytes} += length($line);
						if (++$self->{_curline} >= $self->{state}{line}) {
							$::ERR->verbose("Resuming from source $self->{_curlog} (line: $self->{_curline})");
							return $self->{type};
						}
					}
				}
			} else { # <
				shift @{$self->{_logs}};
			}
		}

		# second: if the log that matches previous state is not found parse the logs that are present
		@{$self->{_logs}} = @ll_bu;
		while (scalar @{$self->{_logs}}) {
			$::ERR->warn("Previous log from state '$statelog' not found. Continuing from " . $self->{_logs}[0] . " instead ...");
			return $self->{type};
		}

		if (!$self->{_curlog}) {
			$::ERR->warn("Unable to find log $statelog from previous state in $self->{_dir}. Ignoring directory.");
		}
	}

	return scalar @{$self->{_logs}} ? $self->{type} : 0;
}

# reads the contents of the current directory
sub _readdir {
	my $self = shift;
	#$self->{_logs} = [ 
	#	map {
	#		( $_->{filename} )
	#	}
	#	grep { 
	#		$_->{filename} !~ /^\./ && 
	#		$_->{filename} !~ /WS_FTP/ && 
	#		$_->{filename} =~ /$self->{_log_regexp}/ 
	#	} 
	#	$self->{sftp}->ls($self->{_dir})
	#];

	$self->{_logs} = [
		map {
			( $_->{filename} )
		}
		sort {
			$a->{a}->{mtime} <=> $b->{a}->{mtime} || $a->{filename} cmp $b->{filename}
		}
		grep { 
			$_->{filename} !~ /^\./ && 
			$_->{filename} !~ /WS_FTP/ && 
			$_->{filename} =~ /$self->{_log_regexp}/ 
		} 
		$self->{sftp}->ls($self->{_dir})
	];

	#print Dumper($self->{_logs});
	#exit();

	#if (scalar @{$self->{_logs}}) {
	#	$self->{_logs} = $self->{game}->logsort($self->{_logs});
	#}
	
	# skip the last log in the directory
	if ($self->{logsource}{skiplast}) {
		my $log = pop(@{$self->{_logs}});
		$::ERR->verbose("Last log '$log' in '$self->{_dir}' will be skipped.");
	}
	$::ERR->verbose(scalar(@{$self->{_logs}}) . " logs found in $self->{_dir}");
	return scalar @{$self->{_logs}};
}

# establish a connection with the SSH server
sub _connect {
	my $self = shift;

	$self->info("Connecting to sftp://$self->{_host} ...");
	eval {
		$self->{sftp} = new Net::SFTP($self->{_host}, %{$self->{_opts}});
	};
	if (!$self->{sftp}) {
		$self->warn("Error connecting to SFTP server: $@");
		return undef;
	}

	# get the current directory
	$self->{_logindir} = $self->{sftp}->do_realpath('.');

	$self->info(sprintf("Connected to %s://%s%s%s. HOME=%s",
		$self->{_protocol},
		$self->{_opts}{user} ? $self->{_opts}{user} . '@' : '',
		$self->{_host},
		$self->{_opts}{ssh_args}{port} ne '22' ? ':' . $self->{_opts}{ssh_args}{port} : '',
		$self->{_logindir}
	));

	return 1;
}

# parse the logsource and strip off it's parts for connection options
sub parsesource {
	my $self = shift;
	my $db = $self->{db};
	my $log = $self->{logsource};

	$self->{_host} = 'localhost';
	$self->{_opts}{user} = '';
	$self->{_opts}{password} = '';
	$self->{_opts}{ssh_args} = { port => 22, protocol => '1,2', identity_files => [ "/home/$ENV{USER}/.ssh/id_dsa", "/home/$ENV{USER}/.ssh/id_rsa" ] };
	$self->{_dir} = '';

	if (ref $log) {
		$self->{_host} = $log->{host} if defined $log->{host};
		$self->{_opts}{ssh_args}{port} = $log->{port} if defined $log->{port};
		$self->{_opts}{user} = $log->{username};
		$self->{_opts}{password} = $log->{password};
		$self->{_opts}{debug} = $self->{conf}->get_opt('debug') ? 1 : 0;	# VERY LOUD!!!
		$self->{_dir} = $log->{path};
		$db->update($db->{t_config_logsources}, { lastupdate => time }, [ 'id' => $log->{id} ]);

	} elsif ($self->{logsource} =~ /^([^:]+):\/\/(?:([^:]+)(?::([^@]+))?@)?([^\/]+)\/?(.*)/) {
		my ($protocol,$user,$pass,$host,$dir) = ($1,$2,$3,$4,$5);
		if ($host =~ /^([^:]+):(.+)/) {
			$self->{_host} = $1;
			$self->{_opts}{ssh_args}{port} = $2;
		} else {
			$self->{_host} = $host;
		}

		# user & pass are optional
		$self->{_opts}{user} = $user if $user;
		$self->{_opts}{password} = $pass if $pass;
		$self->{_dir} = $dir if defined $dir;

		# see if a matching logsource already exists
		my $exists = $db->get_row_hash(sprintf("SELECT * FROM $db->{t_config_logsources} " . 
			"WHERE type='sftp' AND host=%s AND port=%s AND path=%s AND username=%s", 
			$db->quote($self->{_host}),
			$db->quote($self->{_opts}{ssh_args}{port}),
			$db->quote($self->{_dir}),
			$db->quote($self->{_opts}{user})
		));

		if (!$exists) {
			# fudge a new logsource record and save it
			$self->{logsource} = {
				'id'		=> $db->next_id($db->{t_config_logsources}),
				'type'		=> 'sftp',
				'path'		=> $self->{_dir},
				'host'		=> $self->{_host},
				'port'		=> $self->{_opts}{ssh_args}{port},
				'passive'	=> undef,
				'username'	=> $self->{_opts}{user},
				'password'	=> $self->{_opts}{password},
				'recursive'	=> 0,
				'depth'		=> 0,
				'skiplast'	=> 1,
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
		$self->warn("Invalid logsource syntax. Valid example: sftp://user:pass\@host.com/path/to/logs");
		return undef;
	}
	return 1;
}

# not a class method. Only used as a sftp->get() callback function
sub _get_callback {
	my ($sftp, $data, $offset, $size) = @_;
	print $FH $data;
}

sub _opennextlog {
	my $self = shift;
	my $fastforward = shift;
	
	# delete previous log if we had one, and we have 'delete' enabled in the config
	if ($self->{logsource}{delete} and $self->{_curlog}) {
		$self->debug2("Deleting log $self->{_curlog}");
		eval { $self->{sftp}->do_remove($self->{_dir} . "/" . $self->{_curlog}) };
		if ($@) {
			$self->debug2("Error deleting log: $@");
		}
	}

	undef $FH;						# close the previous log, if there was one
	return undef if !scalar @{$self->{_logs}};		# no more logs or directories to scan

	$self->{_offsetbytes} = 0;
	$self->{_lastprint} = time;
	$self->{_lastprint_bytes} = 0;

	# we're done if the maximum number of logs has been reached
	if (!$fastforward and $self->{_maxlogs} and $self->{_totallogs} >= $self->{_maxlogs}) {
		$self->save_state;
		return undef;
	}

	$self->{_curlog} = shift @{$self->{_logs}};
	$self->{_curline} = 0;	
	$self->{_filesize} = 0;

	# keep trying logs until we get one that works (however, chances are if 1 log fails to load they all will)
	while (!$FH) {
		$FH = new_tmpfile IO::File;
		if (!$FH) {
			$self->warn("Error creating temporary file for download: $!");
			undef $FH;
			undef $self->{_curlog};
			last;					# that's it, we give up
		}
#		binmode($FH, ":encoding(UTF-8)");
		$self->debug2("Downloading log $self->{_curlog}");
		if (!$self->{sftp}->get( $self->{_dir} . "/" . $self->{_curlog}, undef, \&_get_callback)) {
			my @status = $self->{sftp}->status;
			# if a file is empty (or not found) get() will fail but the status will not actually be errored
			if ($status[0]) {
				$self->fatal("Error downloading file $self->{_dir}/$self->{_curlog}: " . $status[1]);
				last; # don't download any more logs if we fail
			}
			undef $FH;
			if (@{$self->{_logs}}) {
				$self->{_curlog} = shift @{$self->{_logs}};
			} else {
				last;
			}
		} else {
			seek($FH,0,0);		# back up to the beginning of the file, so we can read it

			if ($self->{logsource}{savedir}) {			# save entire file to our local directory ...
				my $file = catfile($self->{logsource}{savedir}, $self->{_curlog});
				my $path = (splitpath($file))[1] || '';
				eval { mkpath($path) } if $path and !-d $path;
				if (open(F, ">$file")) {
					while (defined(my $line = <$FH>)) {
						print F $line;
					}
					close(F);
					seek($FH,0,0);	# back up again; since we still need to process it
				} else {
					$::ERR->warn("Error creating local file for writting ($file): $!");
				}
			}
		}
	}
	
	if ($FH) {
		$self->{_totallogs}++ unless $fastforward;
		$self->{_filesize} = (stat $FH)[7];
	}

	return $FH;
}

sub next_event {
	my $self = shift;
	my $line;

	$self->idle;
	
	# User is trying to ^C out, try to exit cleanly (save our state)
	# Or we've reached our maximum allowed lines
	if ($::GRACEFUL_EXIT > 0 or ($self->{_maxlines} and $self->{_totallines} >= $self->{_maxlines})) {
		$self->save_state;
		return undef;
	}

	# No current loghandle? Get the next log in the queue
	if (!$FH) {
		$self->_opennextlog;
		if ($FH) {
			$self->echo_processing;
		} else {
			$self->save_state;
			return undef;
		}
	}

	# read the next line, if it's undef (EOF), get the next log in the queue
	while (!defined($line = <$FH>)) {
		$self->_opennextlog;
		if ($FH) {
			$self->echo_processing;
		} else {
			$self->save_state;
			return undef;
		}
	}
	# skip the last line if we're at EOF and there are no more logs in the directory
	# do not increment the line counter, etc.
	if ($self->{logsource}{skiplastline} and eof($FH) and !scalar @{$self->{_logs}}) {
		$self->save_state;
		return undef;
	}
	$self->{_curline}++;
	$self->{_pos} = tell($FH);

	if ($self->{_verbose}) {
		$self->{_totallines}++;
		$self->{_totalbytes} += length($line);
		$self->{_lastprint_bytes} += length($line);
#		$self->{_prevlines} = $self->{_totallines};
#		$self->{_prevbytes} = $self->{_totalbytes};
#		$self->{_lasttime} = time;

		if (time - $self->{_lastprint} > $self->{_lastprint_threshold}) {
			$self->echo_processing(1);
			$self->{_lastprint} = time;
		}
	}

	$self->save_state if time - $self->{_last_saved} > 60;

	my @ary = ( $self->{_curlog}, $line, $self->{_curline} );
	return wantarray ? @ary : [ @ary ];
}

sub save_state {
	my $self = shift;

	$self->{state}{file} = $self->{_curlog};
	$self->{state}{line} = $self->{_curline};
	$self->{state}{pos}  = $self->{_pos};

	$self->{_last_saved} = time;

	$self->SUPER::save_state;
}

sub done {
	my $self = shift;
	$self->SUPER::done(@_);
#	$self->{sftp}->quit if defined $self->{sftp};
	$self->{sftp} = undef;
}

1;
