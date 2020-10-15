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
#	$Id: convheat.pl 536 2008-08-14 12:43:44Z lifo $
#
#	Converts the heat.xml file and inserts it into ps_config_overlays
#

BEGIN { # FindBin isn't going to work on systems that run as SETUID
	use FindBin; 
	use lib $FindBin::Bin;
	use lib $FindBin::Bin . "/lib";
	use lib $FindBin::Bin . "/../lib";
}

use strict;
use warnings;
use XML::Simple;
use PS::ErrLog;
use PS::CmdLine::Heatmap;
use PS::DB;
use PS::Config;					# use'd here only for the loadfile() function
use PS::ConfigHandler;
use util qw( print_r );
use File::Spec::Functions qw( catfile splitpath );

our $VERSION = '1.00.' . (('$Rev: 536 $' =~ /(\d+)/)[0] || '000');

our $DEBUG = 0;					# Global DEBUG level
our $DEBUGFILE = undef;				# Global debug file to write debug info too
our $ERR;					# Global Error handler (PS::Debug uses this)
our $DBCONF = {};				# Global database config

my ($opt, $dbconf, $db, $conf);

$opt = new PS::CmdLine::Heatmap;		# Initialize command line paramaters (not that we use any here)

# Load the basic stats.cfg for database settings (unless 'noconfig' is specified on the command line)
# The config filename can be specified on the commandline, otherwise stats.cfg is used. If that file 
# does not exist then the config is loaded from the __DATA__ block of this file.
$dbconf = {};
if (!$opt->get('noconfig')) {
	if ($opt->get('config')) {
		PS::Debug->debug("Loading DB config from " . $opt->get('config'));
		$dbconf = PS::Config->loadfile( $opt->get('config') );
	} elsif (-e catfile($FindBin::Bin, 'stats.cfg')) {
		PS::Debug->debug("Loading DB config from stats.cfg");
		$dbconf = PS::Config->loadfile( catfile($FindBin::Bin, 'stats.cfg') );
	} else {
		PS::Debug->debug("Loading DB config from __DATA__");
		$dbconf = PS::Config->loadfile( *DATA );
	}
} else {
	PS::Debug->debug("-noconfig specified, No DB config loaded.");
}

# Initialize the primary Database object
# Allow command line options to override settings loaded from config
$DBCONF = {
	dbtype		=> $opt->dbtype || $dbconf->{dbtype},
	dbhost		=> $opt->dbhost || $dbconf->{dbhost},
	dbport		=> $opt->dbport || $dbconf->{dbport},
	dbname		=> $opt->dbname || $dbconf->{dbname},
	dbuser		=> $opt->dbuser || $dbconf->{dbuser},
	dbpass		=> $opt->dbpass || $dbconf->{dbpass},
	dbtblprefix	=> $opt->dbtblprefix || $dbconf->{dbtblprefix}
};
$db = new PS::DB($DBCONF);

my $mapxml  = $opt->mapinfo || catfile($FindBin::RealBin, 'heat.xml');
my $mapinfo = XMLin($mapxml, NormaliseSpace => 2, SuppressEmpty => undef)->{map};

# massage our data a little bit for insertion
foreach my $m (keys %$mapinfo) {
	my $prefix = substr($m, 0, 3);
	$mapinfo->{$m}{gametype} ||= 'halflife';
	if ($prefix eq 'dod') {
		$mapinfo->{$m}{modtype} = 'dod';
	} elsif ($prefix eq 'ctf' or $prefix eq 'cp_') {
		$mapinfo->{$m}{modtype} = 'tf2';
	} else {
		$mapinfo->{$m}{modtype} = 'cstrike';
	}
	@{$mapinfo->{$m}}{qw( width height )} = split(/x/, $mapinfo->{$m}{res});
	$mapinfo->{$m}{map} = $m;
}

# build query for insertion
my $id = 1;
my $cmd = "INSERT INTO ps_config_overlays VALUES ";
foreach my $m (sort sorter keys %$mapinfo) {
	$mapinfo->{$m}{id} = $id++;
	$cmd .= sprintf("(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s), ",
		map { $db->quote($_) } @{$mapinfo->{$m}}{qw( id gametype modtype map minx miny maxx maxy width height flipv fliph )}
	);
}
$cmd = substr($cmd, 0, -2);	# remove trailing comma (, )

$db->truncate('ps_config_overlays');
$db->query($cmd);
$db->query("ALTER TABLE ps_config_overlays ORDER BY modtype, map");

print "Converted " . scalar(keys %$mapinfo) . " heat.xml overlays and inserted into ps_config_overlays.\n";

sub sorter {
	my $c;
	$c = $mapinfo->{$a}{gametype} cmp $mapinfo->{$b}{gametype};
	if ($c == 0) {
		$c = $mapinfo->{$a}{modtype} cmp $mapinfo->{$b}{modtype};
		if ($c == 0) {
			$c = $a cmp $b;
		}
	}
	return $c;
}

__DATA__

