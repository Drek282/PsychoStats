#!/usr/bin/perl
#
#	This file is part of PsychoStats.
#
#	Written by Jason Morriss <stormtrooper@psychostats.com>
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
#	$Id: heat.pl 546 2008-08-24 22:51:53Z lifo $
#

BEGIN { # FindBin isn't going to work on systems that run as SETUID
	use FindBin; 
	use lib $FindBin::Bin;
	use lib $FindBin::Bin . "/lib";
}

use strict;
use warnings;

use File::Spec::Functions qw( catfile splitpath );
use Digest::SHA1 qw( sha1_hex );
use PS::CmdLine::Heatmap;
use PS::DB;
use PS::Config;					# use'd here only for the loadfile() function
use PS::ConfigHandler;
use PS::ErrLog;
use PS::Heatmap;
use util qw( expandlist print_r abbrnum );

our $VERSION = '1.00.' . (('$Rev: 546 $' =~ /(\d+)/)[0] || '000');

our $DEBUG = 0;					# Global DEBUG level
our $DEBUGFILE = undef;				# Global debug file to write debug info too
our $ERR;					# Global Error handler (PS::Debug uses this)
our $DBCONF = {};				# Global database config
our $GRACEFUL_EXIT = 0; #-1;			# (used in CATCH_CONTROL_C)

my ($opt, $dbconf, $db, $conf);

$opt = new PS::CmdLine::Heatmap;		# Initialize command line paramaters
#$DEBUG = $opt->get('debug') || 0;		# sets global debugging for ALL CLASSES

# display our version and exit
if ($opt->get('version')) {
	print "PsychoHeat version $VERSION\n";
	print "Website: http://www.psychostats.com/\n";
	print "Perl version " . sprintf("%vd", $^V) . " ($^O)\n";
	exit;
}


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

$conf = new PS::ConfigHandler($opt, $db);
my $total = $conf->load(qw( main ));
$ERR = new PS::ErrLog($conf, $db);			# Now all error messages will be logged to the DB

# -------------------------------- HEATMAP CODE STARTS HERE -----------------------------------------------------

# read in our map info XML file that defines heatmap dimensions, etc...
my $mapxml  = $opt->mapinfo || undef; #catfile($FindBin::RealBin, 'heat.xml');
my $mapinfo;

if ($mapxml) {
	# load mapinfo from XML file; only 'use' the XML::Simple if needed
	eval "use XML::Simple";
	$mapinfo = XMLin($mapxml, NormaliseSpace => 2, SuppressEmpty => undef)->{map};
} else {
	# load mapinfo from database
	$mapinfo = {};
	my $gametype = $opt->gametype; #$conf->get_main('gametype');
	my $modtype  = $opt->modtype; #$conf->get_main('modtype');
	my $cmd = "SELECT * FROM $db->{t_config_overlays} ";
	if ($gametype and $modtype) {
		$cmd .= sprintf("WHERE gametype=%s AND modtype=%s ", $db->quote($gametype), $db->quote($modtype));
	} elsif ($gametype) {
		$cmd .= sprintf("WHERE gametype=%s ", $db->quote($gametype));
	} elsif ($modtype) {
		$cmd .= sprintf("WHERE modtype=%s ", $db->quote($modtype));
	}
	my @list = $db->get_rows_hash($cmd);
	foreach my $m (@list) {
		$m->{res} = $m->{width} . 'x' . $m->{height};
		$mapinfo->{$m->{map}} = $m;
	}
}
#use Data::Dumper; print Dumper($mapinfo); exit;

# Create a list of maps to generate heat images for. If no map is specified we assume 'all'
my $maplist = {};

if ($opt->mapname and lc $opt->mapname ne 'all') {
	my $mapname = $opt->mapname;
	my $mapid;
	if ($mapname !~ /^\d+$/) {
		$mapid = $db->select($db->{t_map}, 'mapid', "uniqueid=" . $db->quote($mapname));
	} else {
		$mapid = $mapname;
		$mapname = $db->select($db->{t_map}, 'uniqueid', "mapid=" . $db->quote($mapid));
		$mapid = undef unless $mapname;
	}
	die("Map '$mapname' not found in database.\n") unless $mapid;
	$maplist->{$mapname} = $mapid;
} else {
	my @list = $db->get_rows_hash("SELECT mapid,uniqueid FROM $db->{t_map} WHERE uniqueid <> 'unknown' ORDER BY uniqueid");
	foreach my $m (@list) {
		$maplist->{$m->{uniqueid}} = $m->{mapid};
	}
}

{ # private scope
	my ($xml, $file);
	my @ignored = ();
	my $xmlpath = defined $opt->xmlpath ? $opt->xmlpath : '.';
	foreach my $mapname (keys %$maplist) {
		# try to load an XML file for each map (which will override any values found in heat.xml)
		$file = catfile($xmlpath, $mapname . '.xml');
		$xml = -f $file ? XMLin($file, NormaliseSpace => 2, SuppressEmpty => undef) : undef;
		if ($xml) {
			$mapinfo->{$mapname} = $xml;
		}
		unless (exists $mapinfo->{$mapname}) {
			delete $maplist->{$mapname};
			push(@ignored, $mapname);
		}
	}
	if (@ignored) {
#		warn("Ignoring maps with no mapinfo available: " . join(", ", map { "'$_'" } @ignored) . ".\n") unless $opt->quiet;
		warn(sprintf("Ignoring %d maps with no mapinfo available.\n", scalar @ignored));
	}
}

# make sure we have at least 1 map to process
if (keys %$maplist) {
	my $total = scalar keys(%$maplist);
	warn(sprintf("%d map%s ready to be processed.\n", $total, $total == 1 ? '' : 's')) unless $opt->quiet;
} else {
	die("No maps available. Exiting.\n") unless $opt->quiet;
}

# do not echo SQL statements if quiet is specified
if ($opt->quiet and $opt->sql) {
	$opt->del('sql');
}

# default to oldest date ...
if (!defined $opt->statdate) {
	my $date = $db->min($db->{t_map_spatial}, 'statdate');
	$opt->statdate($date) if $date;
	die "No spatial stats are available! Aborting!\n" if !$date;
}

# default to newest date ...
if (!defined $opt->enddate) {
	my $date = $db->max($db->{t_map_spatial}, 'statdate');
	$opt->enddate($date) if $date;
	die "No spatial stats are available! Aborting!\n" if !$date;
}

# if enddate is not specified default to statdate
if (!defined $opt->enddate and defined $opt->statdate) {
	$opt->enddate($opt->statdate);
}

# if enddate is < statdate reverse them
if (defined($opt->startdate && $opt->enddate) and $opt->enddate lt $opt->statdate) {
	my $tmp = $opt->statdate;
	$opt->statdate($opt->enddate);
	$opt->enddate($tmp);
}

# if a weapon is specified, find its ID value
if ($opt->weapon and $opt->weapon !~ /^\d+$/) {
	my $weaponid = $db->select($db->{t_weapon}, 'weaponid', "uniqueid=" . $db->quote($opt->weapon));
	die("Weapon '" . $opt->weapon . "' not found in database.\n") unless $weaponid;
	$opt->weapon($weaponid);
}

if ($opt->player and ($opt->killer or $opt->victim)) {
	warn "Notice: parameter -player overrides -killer and -victim parameters.\n";
	$opt->del('killer');
	$opt->del('victim');
}

# if a player is specified and is not numeric, find their ID based off their uniqueid
if ($opt->player and $opt->player !~ /^\d+$/) {
	my $plrid = $db->select($db->{t_plr}, 'plrid', "uniqueid=" . $db->quote($opt->player));
	die("Player '" . $opt->player . "' not found in database.\n") unless $plrid;
	$opt->player($plrid);
}

# if a killer is specified and is not numeric, find their ID based off their uniqueid
if ($opt->killer and $opt->killer !~ /^\d+$/) {
	my $plrid = $db->select($db->{t_plr}, 'plrid', "uniqueid=" . $db->quote($opt->killer));
	die("Player '" . $opt->killer . "' not found in database.\n") unless $plrid;
	$opt->killer($plrid);
}

# if a victim is specified and is not numeric, find their ID based off their uniqueid
if ($opt->victim and $opt->victim !~ /^\d+$/) {
	my $plrid = $db->select($db->{t_plr}, 'plrid', "uniqueid=" . $db->quote($opt->victim));
	die("Player '" . $opt->victim . "' not found in database.\n") unless $plrid;
	$opt->victim($plrid);
}

# if 'team' is specified then override kteam and vteam
if ($opt->team and ($opt->kteam or $opt->vteam)) {
	warn "Notice: Parameter -team overrides -kteam and -vteam paramters.\n";
	$opt->del('kteam');
	$opt->del('vteam');
} 

# setup some defaults and command overrides for our config that will generate the heatmap
my $hc = $conf->get_main('heatmap');				# 'heatmap.*' config from database (hc = heatmap config)
delete @$hc{qw( SECTION IDX )};					# remove some variables
$hc->{limit} 	= $opt->limit if $opt->exists('limit');
$hc->{brush} 	= $opt->brush if $opt->exists('brush');
$hc->{scale} 	= $opt->scale if $opt->exists('scale');
$hc->{format} 	= $opt->format if $opt->exists('format');
$hc->{overlay} 	= $opt->overlay if $opt->exists('overlay');
$hc->{statdate}	= $opt->statdate if $opt->exists('statdate');
$hc->{enddate}	= $opt->enddate if $opt->exists('enddate');
$hc->{weaponid} = $opt->weapon if $opt->exists('weapon');
$hc->{pid} 	= $opt->player if $opt->exists('player');
$hc->{kid} 	= $opt->killer if $opt->exists('killer');
$hc->{vid} 	= $opt->victim if $opt->exists('victim');
$hc->{team} 	= $opt->team if $opt->exists('team');
$hc->{kteam} 	= $opt->kteam if $opt->exists('kteam');
$hc->{vteam} 	= $opt->vteam if $opt->exists('vteam');
$hc->{headshot} = defined $opt->headshot ? $opt->headshot : undef;	# allow for undef, 0, 1
$hc->{hourly} 	= (defined $opt->hourly and !$opt->nohourly);
$hc->{dir}	= $opt->dir if $opt->exists('dir');

# if hourly is enabled, change this option to the hours to generate (all 24 hours by default)
if ($hc->{hourly}) {
	$hc->{hourly} = defined $opt->hourly ? $opt->hourly : '0-23';
} else {
	# since hourly can be '0' we need to determine if its defined ...
	$hc->{hourly} = undef;
}

# if no format is specified default it to something useful (depending if hourly heatmaps are being created)
if (!$hc->{format}) {
	$hc->{format} = $hc->{hourly} ? "%m_%h.png" : "%m.png";
}

# 'who' defines what set of coordinates will be plotted on the heatmap (killer or victim)
$hc->{wkey} = 'who';	# map to a key name to use later in get_data (used when who2 is needed)
$hc->{who} = $opt->who || 'victim';
if (substr($hc->{who},0,1) eq 'v') {
	$hc->{who} = 'victim';
	$hc->{who_x} = 'vx';
	$hc->{who_y} = 'vy';
} else {
	$hc->{who} = 'killer';
	$hc->{who_x} = 'kx';
	$hc->{who_y} = 'ky';
}

# second set of plots based on killer or victim (for combo heatmaps)
if ($opt->who2) {
	$hc->{who2} = $opt->who2;
	if (substr($hc->{who2},0,1) eq 'v') {
		$hc->{who2} = 'victim';
		$hc->{who2_x} = 'vx';
		$hc->{who2_y} = 'vy';
	} else {
		$hc->{who2} = 'killer';
		$hc->{who2_x} = 'kx';
		$hc->{who2_y} = 'ky';
	}
}

my $where = '';

# build a specific where clause if extra parameters are given
if (($hc->{statdate} and $hc->{enddate}) and $hc->{statdate} ne $hc->{enddate}) {
	$where .= "AND (statdate BETWEEN " . $db->quote($hc->{statdate}) . " AND " . $db->quote($hc->{enddate}) . ") ";
} else {
	$where .= "AND statdate=" . $db->quote($hc->{statdate}) . " " if $hc->{statdate};
}
$where .= "AND (kid=$hc->{pid} OR vid=$hc->{pid}) " if $hc->{pid};
$where .= "AND kid=$hc->{kid} " if $hc->{kid};
$where .= "AND vid=$hc->{vid} " if $hc->{vid};
$where .= "AND (kteam=" . $db->quote($hc->{team}) . " OR vteam=" . $db->quote($hc->{team}) . ") " if $hc->{team};
$where .= "AND vteam=" . $db->quote($hc->{vteam}) . " " if $hc->{vteam};
$where .= "AND kteam=" . $db->quote($hc->{kteam}) . " " if $hc->{kteam};
$where .= "AND weaponid=$hc->{weaponid} " if $hc->{weaponid};
$where .= "AND headshot=$hc->{headshot} " if defined $hc->{headshot};

# loop through our map list and process each map
while (my ($mapname, $mapid) = each(%$maplist)) {
	my $idx = 0;
	my $info = $mapinfo->{$mapname};
	my $datax = [];
	my $datay = [];
	my $datax2;
	my $datay2;
	my $png;
	my $res = get_resolution($mapname, $info->{res});
	my $heatmap_opts = {
		debug		=> $opt->get('debug'),
		width		=> $res->{width} || 200,
		height		=> $res->{height} || 200,
		scale		=> $hc->{scale} || 0.5,
		brush		=> $hc->{brush} || 'medium',
		minx		=> $info->{minx},
		miny		=> $info->{miny},
		maxx		=> $info->{maxx},
		maxy		=> $info->{maxy},
		flip_vertical 	=> $info->{flipv},
		flip_horizontal => $info->{fliph},
		rotate		=> $info->{rotate},
	};
	my $heat = new PS::Heatmap($heatmap_opts);

	$hc->{mapname} = $mapname;
	$hc->{mapid} = $mapid;

	if (defined $hc->{hourly}) {
		my @hours = expandlist($hc->{hourly});
		my $w;
		foreach my $hour (@hours) {
			$hc->{idx} = ++$idx;
			$hc->{hour} = sprintf('%02d', $hour);
			$w = "AND hour=$hour $where";
			if (!$hc->{who2}) {	# single heatmap (warm)
				$hc->{wkey} = 'who';
				get_data($hc, $datax, $datay, $w);
				warn "Creating heatmap for $mapname (hour $hc->{hour}) ...\n" unless $opt->quiet;
				$png = $heat->render(undef, [$datax,$datay,$opt->cold]);
				$heat->clear;
			} else {
				my $datax2 = [];
				my $datay2 = [];
				$hc->{wkey} = 'who';
				get_data($hc, $datax, $datay, $w);
				$hc->{wkey} = 'who2';
				get_data($hc, $datax2, $datay2, $w);
				warn "Creating heatmap for $mapname (hour $hc->{hour}) ...\n" unless $opt->quiet;
				$png = $heat->render(undef, [$datax,$datay,$opt->cold], [$datax2,$datay2,!$opt->cold]);
				$heat->clear;
			}
			save_png($png, $hc);
		}
	} else {
		$hc->{hour} = undef;
		$hc->{idx} = ++$idx;
		if (!$hc->{who2}) {	# single heatmap (warm)
			$hc->{wkey} = 'who';
			get_data($hc, $datax, $datay, $where);
			warn "Creating heatmap for $mapname ...\n" unless $opt->quiet;
			$png = $heat->render(undef, [$datax,$datay,$opt->cold]);
		} else {		# combo heatmap (warm / cold)
			my $datax2 = [];
			my $datay2 = [];
			$hc->{wkey} = 'who';
			get_data($hc, $datax, $datay, $where);
			$hc->{wkey} = 'who2';
			get_data($hc, $datax2, $datay2, $where);
			warn "Creating heatmap for $mapname ...\n" unless $opt->quiet;
			$png = $heat->render(undef, [$datax,$datay,$opt->cold], [$datax2,$datay2,!$opt->cold]);
		}
		save_png($png, $hc);
	}
}

sub get_resolution {
	my ($map, $info) = @_;
	my $res = { width => 200, height => 200};
	if (defined $info) {
		($res->{width}, $res->{height}) = split(/x/, $info);
	} else {
		# if we know where the overlay images are then use Image::Size to determine the size
		warn "Unable to determine resolution for map $map.\n";
	}
	return $res;
}

# save the PNG data into the DB or as a file
sub save_png {
	my ($data, $hc) = @_;
	my $out = $opt->file || $hc->{dir} || 'DB';
	my @vars = qw(mapid weaponid statdate enddate hour headshot who pid kid vid team kteam vteam);	
	my $set = { map {$_ => $hc->{$_}} @vars };
	$set->{who} = 'both' if exists $hc->{who2} and $hc->{who2} ne $hc->{who};
	$set->{enddate} = undef if $set->{enddate} eq $set->{statdate};
	if (uc $out eq 'DB') {
		$set->{datatype} = 'blob';
		$set->{datablob} = $data;
		warn "Saving heatmap for $hc->{mapname} directly to database (" . abbrnum(length($data)) . ")\n";
	} else {
		my $file = $out ? file_format($out, $hc) : file_format($hc->{format}, $hc);
		if (-d $file) {
			$file = catfile($file, file_format($hc->{format}, $hc));
		}
		warn "Saving heatmap for $hc->{mapname} to $file (" . abbrnum(length($data)) . ")\n";
		if (open(OUT, ">$file")) {
			binmode(OUT);
			print OUT $data;
			close(OUT);
		} else {
			warn "Error opening file '$file' for output: $!";
			exit;
		}
		$set->{datatype} = 'file';
		$set->{datafile} = $file;
	}

	# delete any heatmap already matching the current criteria
	my $key = heatmap_key($hc);
	warn "$hc->{mapname} heatkey='$key'\n" unless $opt->quiet;
	$db->do(sprintf("DELETE FROM $db->{t_heatmaps} WHERE heatkey=%s AND statdate=%s AND enddate%s AND hour%s AND who=%s", 
		$db->quote($key),
		$db->quote($set->{statdate}),
		$hc->{enddate} ne $hc->{statdate} ? '='.$db->quote($set->{enddate}) : ' IS NULL',
		defined $set->{hour} ? '='.$set->{hour} : ' IS NULL',
		$db->quote($set->{who}),
	));
	warn ">> [SQL] " . $db->lastcmd . "\n" if $opt->sql;

	# insert the new heatmap, since we're inserting binary data I have to roll my own insert here
	$set->{heatid} = $db->next_id($db->{t_heatmaps}, 'heatid');
	$set->{heatkey} = $key;
	my @keys = keys %$set;
	my $cmd = "INSERT INTO $db->{t_heatmaps} (" . join(',',@keys) . ") VALUES (". substr('?,' x @keys,0,-1) .")";
	my $st = $db->{dbh}->prepare($cmd);

	if (!$st->execute(map($set->{$_}, @keys))) {
		warn "Error saving heatmap to database: " . $st->errstr . "\n";
	}
}

# generate a unique heatmap key based on the criteria given.
# the key must be easily reproducable. so PHP code can lookup heatmaps on this key too
# a SHA1 key is used and should be sufficient
sub heatmap_key {
	my ($hc) = @_;
	my $hc2 = { %$hc };
	$hc2->{who} = 'both' if exists $hc->{who2} and $hc->{who2} ne $hc->{who};
	my $key = join('-', map { defined $_ ? $_ : 'NULL' } 
		# this order must be maintained! (its the same order as the DB fields, so its easy to remember)
		# note: 'statdate', 'enddate', 'hour' and 'who' are not included
		@$hc2{qw(mapid weaponid pid kid team kteam vid vteam headshot)}
	);
	$key .= '-hourly' if defined $hc2->{hourly};
	$key = sha1_hex($key);
	return $key;
}

sub get_data {
	my ($hc, $datax, $datay, $where) = @_;
	$where ||= '';
	@$datax = ();	# clear the data arrays
	@$datay = ();
	my $limit = $hc->{limit} || 10000;
	my $cmd = "SELECT " . $hc->{$hc->{wkey}.'_x'} . "," . $hc->{$hc->{wkey}.'_y'} . " FROM $db->{t_map_spatial} WHERE mapid=$hc->{mapid} ";
	$cmd .= $where if $where;
	$cmd .= "LIMIT $limit";

	warn ">> [SQL] $cmd\n" if $opt->sql;
	my $st = $db->query($cmd);
	while (my ($x1,$y1) = $st->fetchrow_array) {
		push(@$datax, $x1);
		push(@$datay, $y1);
	}
	warn @$datax . " events fetched.\n" if $opt->sql;
	undef $st;
}

sub file_format {
	my ($fmt, $hc) = @_;
	my $str = $fmt;
	$str =~ s/%%/%z/g;
	$str =~ s/%m/$hc->{mapname}/ge;
	$str =~ s/%i/$hc->{idx}/ge;
	$str =~ s/%d/$hc->{statdate}/ge;
	$str =~ s/%e/$hc->{enddate}/ge;
	$str =~ s/%h/defined $hc->{hour} ? $hc->{hour} : ''/ge;
	$str =~ s/%z/%/g;
	return $str;
}

sub main::exit { CORE::exit(@_) }

__DATA__

# If no stats.cfg exists then this config is loaded instead

dbtype = mysql
dbhost = localhost
dbport = 
dbname = psychostats
dbuser = 
dbpass = 
dbtblprefix = ps_
