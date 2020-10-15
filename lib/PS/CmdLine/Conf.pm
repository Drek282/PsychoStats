package PS::CmdLine::Conf;
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
#	$Id: Conf.pm 450 2008-05-20 11:34:52Z lifo $
#

use strict;
use warnings;
use base qw( PS::CmdLine );

use Carp;
use Getopt::Long;
use Pod::Usage;

our $VERSION = '1.00.' . (('$Rev: 450 $' =~ /(\d+)/)[0] || '000');
our $AUTOLOAD;

# private: Loads command line parameters
sub _getOptions {
	my $self = shift;

	my $optok = GetOptions(
		# CONF SETTINGS
		'conftype|type=s'	=> \$self->{param}{conftype},
		'd|dump=s'		=> \$self->{param}{dump},
		'delete=s'		=> \$self->{param}{delete},
		's|save=s'		=> \$self->{param}{save},
		'u|update=s'		=> \$self->{param}{update},
		'file=s'		=> \$self->{param}{file},

		# BASIC SETTINGS
		'config=s'	=> \$self->{param}{config},
		'noconfig'	=> \$self->{param}{noconfig},
		'help|?'	=> \$self->{param}{help},
		'V|version'	=> \$self->{param}{version},

		# DATABASE SETTINGS
		'dbtype=s'	=> \$self->{param}{dbtype},
		'dbhost=s'	=> \$self->{param}{dbhost},
		'dbport=s'	=> \$self->{param}{dbpost},
		'dbname=s'	=> \$self->{param}{dbname},
		'dbuser=s'	=> \$self->{param}{dbuser},
		'dbpass=s'	=> \$self->{param}{dbpass},
		'dbtblprefix:s'	=> \$self->{param}{dbtblprefix},

		# grab extra params that are not options
		'<>'		=> sub { push(@PS::CmdLine::OPTS, shift) }
	);

	$self->{param}{debug} = 1 if defined $self->{param}{debug} and $self->{param}{debug} < 1;

	if (!$optok) {
#		die("Invalid parameters given. Insert help page");
		pod2usage({ -input => \*DATA, -verbose => 1 });
	}

	if ($self->{param}{help}) {
		pod2usage({ -input => \*DATA, -verbose => 2 });
	}

}


1;

__DATA__

=head1 NAME

PsychoStats - Comprehensive Statistics

Commandline Configuration Helper *** NOT COMPLETED ***

=head1 SYNOPSIS

=over 4 

=item B<Dump a config>

=over 4 

=item conf.pl -dump <conf_type> [filename]

=item conf.pl <conf_type> [filename]

=back 4

=item B<Update a single option>

=over 4 

=item conf.pl -u <conf_type> <section.variable> <value> 

=back 8

=head1 COMMANDS

=over 4

=item B<-dump> <conf_type> [filename]

Dumps the configuration from the database matching the <conf_type> 
specified (eg: main) into a plain text file. If no filename is specified 
then "<conf_type>.cfg" is used by default. And if a filename of "-" is 
used then the config is written to STDOUT.

