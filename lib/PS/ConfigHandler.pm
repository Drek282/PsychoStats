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
#	$Id: ConfigHandler.pm 493 2008-06-17 11:26:35Z lifo $
#
#       PS::Config is responsible for loading a single 'conftype' from the
#       database. PS::ConfigHandler is used to load and keep track of several
#       'conftype' configs using PS::Config objects PS::ConfigHandler is the
#       class that is generally interfaced with directly, not PS::Config.
package PS::ConfigHandler;

use strict;
use warnings;
use base qw( PS::Debug );

use Carp;
use Data::Dumper;
use PS::Config;

our $VERSION = '1.10.' . (('$Rev: 493 $' =~ /(\d+)/)[0] || '000');
our $AUTOLOAD;

sub new {
	my $proto = shift;
	my $class = ref($proto) || $proto;
	my $self = { debug => 0, class => $class, config => undef, configs => {}, order => [], db => undef };
	bless($self, $class);

#	$self->debug($self->{class} . " initializing");

	# Was a PS:CmdLine object passed in? If {opt} is undef then config settings are never overridden
	$self->{opt} = shift;

	# Was a PS::DB object passed in? If not, we'll try figuring out the proper settings to make a new object
	# arg will either be a reference to a PS::DB object, or a scalar string to a config file to load DB settings from
	my $arg = shift || croak("No database handle or connection settings supplied to $class\->new");

	# if we were given a hash of values, assume their connection settings for the database
	if (ref $arg eq 'HASH' or !ref $arg) {
		$arg = PS::Config->loadfile($arg) unless ref $arg;	# assume $arg was a scalar filename
		# set some sane defaults for the database
		$arg->{dbtype} 		= 'mysql' 	unless defined $arg->{dbtype};
		$arg->{dbname} 		= 'psychostats' unless defined $arg->{dbname};
		$arg->{dbhost} 		= 'localhost' 	unless defined $arg->{dbhost};
		$arg->{dbtblprefix} 	= 'ps_' 	unless defined $arg->{dbtblprefix};
		$self->{db} = new PS::DB($arg);
	} elsif (ref $arg) {
		# if it's a reference (but not a HASH) then assume we were given a valid PS::DB object
		$self->{db} = $arg;
	} else {
		croak("Unknown database object reference given to $class\->new");
	}

	return $self;
}

# saves an 'info' variable directly to the database config
sub setinfo {
	my ($self, $var, $value) = @_;
	my $db = $self->{db};
	my $section = '';

	my @parts = split(/\./, $var, 2);
	($section, $var) = @parts if @parts == 2;

	my ($id) = $db->select($db->{t_config}, 'id', [ 'conftype' => 'info', 'section' => $section, 'var' => $var ]);
	my $set = { 'conftype' => 'info', 'section' => $section, 'var' => $var, 'value' => $value };
	if ($id) {
		$db->update($db->{t_config}, $set, [ 'id' => $id ]);
	} else {
		$set->{id} = $db->next_id($db->{t_config});
		$db->insert($db->{t_config}, $set);
	}
}

# gets an 'info' variable from the database directly (does not cache results)
sub getinfo {
	my ($self, $var) = @_;
	my $db = $self->{db};
	my $section = '';

	my @parts = split(/\./, $var, 2);
	($section, $var) = @parts if @parts == 2;

	my ($value) = $db->select($db->{t_config}, 'value', [ 'conftype' => 'info', 'section' => $section, 'var' => $var ]);
	return $value;
}


sub load {
	my $self = shift;
	my $total = 0;
	while (my $ct = shift) {
		next if exists $self->{configs}{$ct};				# only load a config type ONCE
#		if (!exists $self->{configs}{$ct}) {
			push(@{$self->{order}}, $ct);				# add conftype to the ordered list
			$self->{configs}{$ct} = new PS::Config($self->{db});	# create the new config object
#		}
		$total += $self->{configs}{$ct}->load($ct);			# load the config
	}
	return $total;
}

# reloads all configs previously loaded
sub reload {
	my $self = shift;
	my @configs = @{$self->{order}};
	$self->{configs} = {};
	$self->{order} = [];
	$self->load(@configs);
}

sub get_opt {
	my $self = shift;
	my $var = shift;
	return $self->{opt}->get($var) if defined $var and defined $self->{opt} and $self->{opt}->exists($var);
	return undef;
}

sub get {
	my $self = shift;
	my $var = shift;
	my $config = shift || $self->config || $self->{order}->[0] || return undef;

	# If the config type key doesn't exist yet, it hasn't been loaded so we load it here now
	if (!exists $self->{configs}{$config}) {
		$self->load($config);
	}

	return $self->{opt}->get($var) if defined $var and defined $self->{opt} and $self->{opt}->exists($var);
	if (exists $self->{configs}{$config}) {
		return $self->{configs}{$config}->get($var);
	} else {
		return undef;
	}
} 

# ignores command line parameters
sub getconf {
	my $self = shift;
	my $var = shift;
	my $config = shift || $self->config || $self->{order}->[0] || return undef;

	# If the config type key doesn't exist yet, it hasn't been loaded so we load it here now
	if (!exists $self->{configs}{$config}) {
		$self->load($config);
	}

	if (exists $self->{configs}{$config}) {
		return $self->{configs}{$config}->get($var);
	} else {
		return undef;
	}
} 

sub get_main { return $_[0]->get($_[1], 'main') }

sub set { 
	my $self = shift;
	my $var = shift;
	my $value = shift;
	my $config = shift || $self->config || $self->{order}->[0] || return undef;
	if (!exists $self->{configs}{$config}) {
		$self->{configs}{$config} = new PS::Config($self->{db});
	}
	$self->{opt}->set($var, $value) if defined $self->{opt} and $self->{opt}->exists($var);
	return $self->{configs}{$config}->set($var,$value);
}

sub config { 
	return $_[0]->{config} if @_ == 1;
	my $self = shift;
	my $new = shift;
	if (!exists $self->{configs}{$new}) {
		$self->warn("Invalid config type specified ($new). Ignoring.");
		$self->debug("Invalid config type specified ($new). Ignoring.");
		return $self->{config};
	} else {
		my $old = $self->{config};
		$self->{config} = $new;
		return $old;
	}
}

sub AUTOLOAD {
	my $self = ref($_[0]) =~ /::/ ? shift : undef;
	my $var = $AUTOLOAD;
	$var =~ s/.*:://;
	return if $var eq 'DESTROY';

#	print "AUTOLOAD = $AUTOLOAD(@_)\n";

	# no object? Then we're trying to call a normal function somewhere in this class file
	if (!defined $self) {
		my ($pkg,$filename,$line) = caller;
		die("Undefined subroutine $var called at $filename line $line.\n");
	}

	# autoload get_*() function calls to call get() with the corasponding config type
	if ($var =~ /^get_(.+)/) {
		return $self->get($_[0], $1);
	}

#	return scalar @_ ? $self->set($var, @_) : $self->get($var, @_);
	return $self->get($var, $_[0]);
}

1;
