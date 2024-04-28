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
#	$Id: ftp.pm 530 2008-08-08 17:53:35Z lifo $
#
#	FTP support. Requires Net::FTP
#
package PS::Feeder::ftp;

use strict;
use warnings;
use base qw( PS::Feeder );
use Digest::MD5 qw( md5_hex );
use File::Spec::Functions qw( splitpath catfile );
use File::Path;
use File::Listing qw(parse_dir);
#use Data::Dumper;

our $VERSION = '1.10.' . (('$Rev: 530 $' =~ /(\d+)/)[0] || '000');

sub init {
	my $self = shift;

	eval "require Net::FTP";
	if ($@) {
		$::ERR->warn("Net::FTP not installed. Unable to load $self->{class} object.");
		return undef;
	}

	$self->{_logs} = [ ];
	$self->{_curline} = 0;
	$self->{_log_regexp} = qr/\.log$/io;
	$self->{_protocol} = 'ftp';
	$self->{_idle} = time;
	$self->{_last_saved} = time;

	$self->{max_idle} = 25;				# should be made a configurable option ...
	$self->{type} = $PS::Feeder::WAIT;
	$self->{reconnect} = 0;

	return undef unless $self->_connect;

	# if a savedir was configured and it's not a directory try to create it
=pod
	if ($self->{savedir} and !-d $self->{savedir}) {
		if (-e $self->{savedir}) {
			$::ERR->warn("Invalid directory configured for saving logs ('$self->{savedir}'): Is a file");
			$self->{savedir} = '';
		} else {
			eval { mkpath($self->{savedir}) };
			if ($@) {
				$::ERR->warn("Error creating directory for saving logs ('$self->{savedir}'): $@");
				$self->{savedir} = '';
			}
		}
	}
	if ($self->{savedir}) {
		$::ERR->info("Downloaded logs will be saved to: $self->{savedir}");
	}
=cut

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
				$self->_opennextlog($self->{state}{pos}, 1);
				# finally: fast-forward to the proper line
				if (int($self->{state}{pos} || 0) > 0) {	# FAST forward quickly
					$self->{_offsetbytes} = $self->{state}{pos};
					$self->{_curline} = $self->{state}{line};
					$::ERR->verbose("Resuming from source $self->{state}{file} (line: $self->{_curline}, pos: $self->{state}{pos})");
					return $self->{type};
				} else {					# move forward slowly
					my $fh = $self->{_loghandle};
					while (defined(my $line = <$fh>)) {
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
	#$self->{_logs} = [ grep { !/^\./ && !/WS_FTP/ && /$self->{_log_regexp}/ } $self->{ftp}->ls ];

	my $ls = $self->{ftp}->dir();
	my @ls_arr = parse_dir($ls);

	@ls_arr = grep { !/^\./ && !/WS_FTP/ && $self->{_log_regexp} } @ls_arr;

	# sort the list of files by modified time
	@{$self->{_logs}} = sort {
		$a->[3] <=> $b->[3] || $a->[0] cmp $b->[0]
	} @ls_arr;
	undef @ls_arr;

	# reduce the elements of the array to the file name
	foreach my $file (@{$self->{_logs}}) {
		$file = $file->[0];
	}

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

# establish a connection with the FTP host
sub _connect {
	my $self = shift;
	my $reconnect = shift;
	my $host = $self->{_opts}{Host};

	$self->{reconnect}++ if $reconnect;
	$self->info(($reconnect ? "Rec" : "C") . "onnecting to $self->{_protocol}://$self->{_user}\@$self->{_opts}{Host}:$self->{_opts}{Port} ...");

	$self->{ftp} = new Net::FTP($host, %{$self->{_opts}});
	if (!$self->{ftp}) {
		$::ERR->warn("$self->{class} error connecting to FTP server: $@");
		return undef;
	}

	if (!$self->{ftp}->login($self->{_user}, $self->{_pass})) {
		chomp(my $msg = $self->{ftp}->message);
		$::ERR->warn("Error logging into FTP server: $msg");
		return undef;
	}

	# get the current directory
	chomp($self->{_logindir} = $self->{ftp}->pwd);

	if ($self->{_dir} and !$self->{ftp}->cwd($self->{_dir})) {
		chomp(my $msg = $self->{ftp}->message);
		$::ERR->warn("$self->{class} error changing FTP directory: $msg");
		return undef;
	}

	# do transfers in binary. so we can use REST commands to fast forward
	# log files when needed from a previous state.
	$self->{ftp}->binary;
	
	$self->info(sprintf("Connected to %s://%s%s%s%s. HOME=%s, CWD=%s",
		$self->{_protocol},
		$self->{_user} ? $self->{_user} . '@' : '',
		$self->{_opts}{Host},
		$self->{_opts}{Port} ne '21' ? ':' . $self->{_opts}{Port} : '',
		$self->{_opts}{Passive} ? " (pasv)" : "",
		$self->{_logindir},
		$self->{ftp}->pwd
	));

	return 1;
}

# parse the logsource and strip off it's parts for connection options
sub parsesource {
	my $self = shift;
	my $db = $self->{db};
	my $log = $self->{logsource};

	$self->{_opts} = {};
	$self->{_opts}{Host} = 'localhost';
	$self->{_opts}{Port} = 21;
	$self->{_opts}{Timeout} = 120;
	$self->{_opts}{Passive} = $self->{conf}->get_opt('passive') ? 1 : 0;
	$self->{_opts}{Debug} = $self->{conf}->get_opt('debug') ? 1 : 0;
	$self->{_dir} = '';
	$self->{_user} = '';
	$self->{_pass} = '';

	if (ref $log) {
		$self->{_opts}{Host} = $log->{host} if defined $log->{host};
		$self->{_opts}{Port} = $log->{port} if defined $log->{port};
		# allow -passive to override the saved logsource setting; {Passive} is set a few lines above
		$self->{_opts}{Passive} = $log->{passive} if defined $log->{passive} and !$self->{_opts}{Passive};
		$self->{_user} = $log->{username};
		$self->{_pass} = $log->{password};
		$self->{_dir}  = $log->{path};
		$db->update($db->{t_config_logsources}, { lastupdate => time }, [ 'id' => $log->{id} ]);

	} elsif ($log =~ /^([^:]+):\/\/(?:([^:]+)(?::([^@]+))?@)?([^\/]+)\/?(.*)/) {
		# ftp://user:pass@hostname.com/some/path/
		my ($protocol,$user,$pass,$host,$dir) = ($1,$2,$3,$4,$5);
		if ($host =~ /^([^:]+):(.+)/) {
			$self->{_opts}{Host} = $1;
			$self->{_opts}{Port} = $2;
		} else {
			$self->{_opts}{Host} = $host;
		}

		# user & pass are optional
		$self->{_user} = $user if $user;
		$self->{_pass} = $pass if $pass;
		$self->{_dir}  = $dir  if $dir;

		$self->{_opts}{Passive} = $self->{conf}->get_opt('passive') ? 1 : 0;

		# see if a matching logsource already exists
		my $exists = $db->get_row_hash(sprintf("SELECT * FROM $db->{t_config_logsources} " . 
			"WHERE type='ftp' AND host=%s AND port=%s AND path=%s AND username=%s", 
			$db->quote($self->{_opts}{Host}),
			$db->quote($self->{_opts}{Port}),
			$db->quote($self->{_dir}),
			$db->quote($self->{_user})
		));

		if (!$exists) {
			# fudge a new logsource record and save it
			$self->{logsource} = {
				'id'		=> $db->next_id($db->{t_config_logsources}),
				'type'		=> 'ftp',
				'path'		=> $self->{_dir},
				'host'		=> $self->{_opts}{Host},
				'port'		=> $self->{_opts}{Port},
				'passive'	=> $self->{_opts}{Passive},
				'username'	=> $self->{_user},
				'password'	=> $self->{_pass},
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
		$::ERR->warn("Invalid logsource syntax. Valid example: ftp://user:pass\@host.com/path/to/logs");
		return undef;
	}
	return 1;
}

sub _opennextlog {
	my $self = shift;
	my $offset = shift;	# byte offset to fast-foward to
	my $fastforward = shift;
	
	# delete previous log if we had one, and we have 'delete' enabled in the logsource_ftp config
	if ($self->{delete} and $self->{_curlog}) {
		$self->debug2("Deleting log $self->{_curlog}");
		if (!$self->{ftp}->delete($self->{_curlog})) {
			chomp(my $msg = $self->{ftp}->message);
			$self->debug2("Error deleting log: $msg");
		}
	}

	undef $self->{_loghandle};				# close the previous log, if there was one
	return undef if !scalar @{$self->{_logs}};		# no more logs or directories to scan

	$self->{_offsetbytes} = defined $offset ? int($offset)+0 : 0;
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
	while (!$self->{_loghandle}) {
		$self->{_loghandle} = new_tmpfile IO::File;
		if (!$self->{_loghandle}) {
			$::ERR->warn("Error creating temporary file for download: $!");
			undef $self->{_loghandle};
			last;					# that's it, we give up
		}

#		if ($offset) {
#			# do not attempt to download the log if the position
#			# from the previous state (offset) is actually EOF.
#			# Just skip to the next instead.
#			my $size = $self->{ftp}->size($self->{_curlog}) || 0;
#			if (!$size or $offset >= $size) {
#				$self->debug2("Skipping log $self->{_curlog} (EOF from previous state)");
##				$self->{_curlog} = shift @{$self->{_logs}} || return undef;
#			}
#		}
		$self->debug2("Downloading log $self->{_curlog}");
		if (!$self->{ftp}->get( $self->{_curlog}, $self->{_loghandle}, $offset )) {
			undef $self->{_loghandle};
			chomp(my $msg = $self->{ftp}->message);
			$::ERR->warn("Error downloading file: $self->{_curlog}: " . ($msg ? $msg : "Unknown Error"));
			my $ok = undef;
#			unshift(@{$self->{_logs}}, $self->{_curlog});		# add current log back on stack
			$ok = $self->_connect(1) unless $self->{reconnect} > 3; # limit the times we reconnect
			last unless $ok;
#			last; # don't try and process any more logs if one fails
#			if (scalar @{$self->{_logs}}) {
#				$self->{_curlog} = shift @{$self->{_logs}};	# try next log
#			} else {
#				last;						# no more logs, we're done
#			}
		} else {
			if ($self->{reconnect}) {
				$self->{reconnect} = 0;		# we got a log successfully, so reset our reconnect flag
#				$::ERR->verbose("Reattmpting to process log $self->{_curlog}");
			}
			seek($self->{_loghandle},0,0);		# back up to the beginning of the file, so we can read it

			#if ($self->{savedir}) {			# save entire file to our local directory ...
			#	my $file = catfile($self->{savedir}, $self->{_curlog});
			#	my $path = (splitpath($file))[1] || '';
			#	eval { mkpath($path) } if $path and !-d $path;
			#	if (open(F, ">$file")) {
			#		my $fh = $self->{_loghandle};
			#		while (defined(my $line = <$fh>)) {
			#			print F $line;
			#		}
			#		close(F);
			#		seek($self->{_loghandle},0,0);
			#	} else {
			#		$::ERR->warn("Error creating local file for writting ($file): $!");
			#	}
			#}
		}
	}

	$self->save_state if time - $self->{_last_saved} > 60;

	$self->{_idle} = time;

	if ($self->{_loghandle}) {
		$self->{_totallogs}++ unless $fastforward;
		$self->{_filesize} = (stat $self->{_loghandle})[7];
	}
	return $self->{_loghandle};
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
	if (!$self->{_loghandle}) {
		$self->_opennextlog;
		if ($self->{_loghandle}) {
			$self->echo_processing;
		} else {
			$self->save_state;
			return undef;
		}
	}

	# read the next line, if it's undef (EOF), get the next log in the queue
	my $fh = $self->{_loghandle};
	while (!defined($line = <$fh>)) {
		$fh = $self->_opennextlog;
		if ($self->{_loghandle}) {
			$self->echo_processing;
		} else {
			$self->save_state;
			return undef;
		}
	}
	# skip the last line if we're at EOF and there are no more logs in the directory
	# do not increment the line counter, etc.
	if ($self->{logsource}{skiplastline} and eof($fh) and !scalar @{$self->{_logs}}) {
		$self->save_state;
		return undef;
	}
	$self->{_curline}++;
	$self->{_pos} = tell($fh);

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

#	my $logsrc = "ftp://" . $self->{_opts}{Host} . ($self->{_opts}{Port} ne '21' ? ':' . $self->{_opts}{Host} : '' ) . '/' . $self->{_dir};
#	my @ary = ( $logsrc, $line, ++$self->{_curline} );
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
	$self->{ftp}->quit if defined $self->{ftp};
	$self->{ftp} = undef;
}

# called in the next_event method for each event. Used as an anti-idle timeout for FTP
sub idle {
	my ($self) = @_;
	if (time - $self->{_idle} > $self->{max_idle}) {
		$self->{_idle} = time;
		$self->{ftp}->pwd;
#		$self->{ftp}->site("NOP");
		# sending a site NOP command will usually just send back a 500 error
		# but the server will see the connection as being active.
		# This has not been widely tested on various servers. Some servers
		# might be smart enough to see repeated commands... I'm not sure.
		# in which case the idle timeout may still disconnect us.
	}
}

1;
