#!/usr/bin/perl

# FindBin isn't going to work on systems that run the stats.pl as SETUID
BEGIN { 
  use FindBin; 
  use lib $FindBin::Bin;
  use lib $FindBin::Bin . "/lib";
  use lib $FindBin::Bin . "/../lib";
#  use lib "./lib";
}

use strict;
use warnings;
use Data::Dumper;
use File::Spec::Functions qw(catfile);
use PS::CmdLine::Conf;				# special 'conf' object for this script
use PS::DB;
use PS::Config;					# use'd here only for the loadfile() function
use PS::ConfigHandler;
use PS::ErrLog;
use PS::Debug;

our $VERSION = '1.0';

our $ERR;					# Global Error handler (PS::Debug uses this)
our $DBCONF = {};				# Global database config

my ($opt, $dbconf, $db, $conf, $file);

binmode STDOUT, ":utf8";

$opt = new PS::CmdLine::Conf;				# Initialize command line paramaters

#$opt->set('conftype', 'main') unless $opt->conftype;

# display our version and exit
if ($opt->version) {
	print "PsychoStats config helper version $VERSION\n";
	print "Author:  Jason Morriss\n";
	exit;
}

# Load the basic stats.cfg for database settings (unless 'noconfig' is specified on the command line)
# The config filename can be specified on the commandline, otherwise stats.cfg is used. If that file 
# does not exist then the config is loaded from the __DATA__ block of this file.
$dbconf = {};
if (!$opt->noconfig) {
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
$db = PS::DB->new($DBCONF);

if ($opt->dump) {
	$file = $opt->file || $opt->pop_opt;
	$file = $opt->dump . ".cfg" unless $file;
	$conf = init($db, $opt->dump);
	err("Error creating file: $!") unless open(F, ">$file");

	my $config = $conf->get;
	err("No '" . $opt->dump . "' config found.") unless defined $config and keys %$config;
	PS::Config->savefile( 
		filename => *F,
		config => $config,
		header => "# Config dumped on " . scalar(localtime) . "\n#\$TYPE = " . $opt->dump . "\n\n", 
	);
	close(F);

	if ($file ne '-') {
		print "Config '" . $opt->dump . "' written to file $file\n";
	}
} elsif ($opt->save) {

} elsif ($opt->update) {
	$conf = init($db, $opt->update);
	my $config = $conf->get;
	err("No '" . $opt->update . "' config found.") unless defined $config and keys %$config;

}

sub init {
	my ($db, $type) = @_;
	my $conf = new PS::Config($db);
	my $total = $conf->load($type);
	$ERR = new PS::ErrLog($conf, $db);			# Now all error messages will be logged to the DB
	return $conf;
}

sub err {
	my $msg = shift;
	warn $msg . "\n";
	exit();
}

# PS::ErrLog points to this to actually exit on a fatal error, incase I need to do some cleanup
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
