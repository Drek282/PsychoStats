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
#	$Id: stats.pl 564 2008-10-10 12:26:35Z lifo $
#

BEGIN { # FindBin isn't going to work on systems that run the stats.pl as SETUID
	use strict;
	use warnings;

	use FindBin; 
	use lib $FindBin::Bin;
	use lib $FindBin::Bin . "/lib";
}

BEGIN { # make sure we're running the minimum version of perl required
	my $minver = 5.08;
	my $curver = 0.0;
	my ($major,$minor,$release) = split(/\./,sprintf("%vd", $^V));
	$curver = sprintf("%d.%02d",$major,$minor);
	if ($curver < $minver) {
		print "Perl v$major.$minor.$release is too old to run PsychoStats.\n";
		print "Minimum version $minver is required. You must upgrade before continuing.\n";
		if (lc substr($^O,0,-2) eq "mswin") {
			print "\nPress ^C or <enter> to exit.\n";
			<>;
		}
		exit 1;
	}
}

BEGIN { # do checks for required modules
	our %PM_LOADED = ();
	my @modules = qw( DBI DBD::mysql );
	my @failed_at_life = ();
	my %bad_kitty = ();
	foreach my $module (@modules) {
		my $V = '';
		eval "use $module; \$V = \$${module}::VERSION;";
		if ($@) {	# module not found
			push(@failed_at_life, $module);
		} else {	# module loaded ok; store for later, if -V is used for debugging purposes
			$PM_LOADED{$module} = $V;
		}
	}

	# check the version of modules
	# DBD::mysql needs to be 3.x at a minimum
	if ($PM_LOADED{'DBD::mysql'} and substr($PM_LOADED{'DBD::mysql'},0,1) lt '3') {
		$bad_kitty{'DBD::mysql'} = '3.0008';
	}

	# if anything failed, kill ourselves, life isn't worth living.
	if (@failed_at_life or scalar keys %bad_kitty) {
		print "PsychoStats failed initialization!\n";
		if (@failed_at_life) {
			print "The following modules are required and could not be loaded.\n";
			print "\t" . join("\n\t", @failed_at_life) . "\n";
			print "\n";
		} else {
			print "The following modules need to be upgraded to the version shown below\n";
			print "\t$_ v$bad_kitty{$_} or newer (currently installed: $PM_LOADED{$_})\n" for keys %bad_kitty;
			print "\n";
		}

		if (lc substr($^O,0,-2) eq "mswin") {	# WINDOWS
			print "You can install the modules listed by using the Perl Package Manager.\n";
			print "Typing 'ppm' at the Start->Run menu usually will open it up. Enter the module\n";
			print "name and have it install. Then rerun PsychoStats.\n";
			print "\nPress ^C or <enter> to exit.\n";
			<>;
		} else {				# LINUX
			print "You can install the modules listed using either CPAN or if your distro\n";
			print "supports it by installing a binary package with your package manager like\n";
			print "'yum' (fedora / redhat), 'apt-get' or 'aptitude' (debian).\n";
		}
		exit 1;
	}
}

use POSIX qw( :sys_wait_h setsid );
use File::Spec::Functions qw(catfile);
use PS::CmdLine;
use PS::DB;
use PS::Config;					# use'd here only for the loadfile() function
use PS::ConfigHandler;
use PS::ErrLog;
use PS::Feeder;
use PS::Game;
use util qw( :win compacttime );

# The $VERSION and $PACKAGE_DATE are automatically updated via the packaging script.
our $VERSION = '3.2';
our $PACKAGE_DATE = time;
our $REVISION = ('$Rev: 564 $' =~ /(\d+)/)[0] || '000';

our $DEBUG = 0;					# Global DEBUG level
our $DEBUGFILE = undef;				# Global debug file to write debug info too
our $ERR;					# Global Error handler (PS::Debug uses this)
our $DBCONF = {};				# Global database config
our $GRACEFUL_EXIT = 0; #-1;			# (used in CATCH_CONTROL_C)

$SIG{INT} = \&CATCH_CONTROL_C;

my ($opt, $dbconf, $db, $conf);
my $starttime = time;
my $total_logs = 0;
my $total_lines = 0;

eval { binmode(STDOUT, ":encoding(utf8)"); };

$opt = new PS::CmdLine;				# Initialize command line paramaters
$DEBUG = $opt->get('debug') || 0;		# sets global debugging for ALL CLASSES

# display our version and exit
if ($opt->get('version')) {
	print "PsychoStats version $VERSION (rev $REVISION)\n";
	print "Packaged on " . scalar(localtime $PACKAGE_DATE) . "\n";
#	print "Author:  Jason Morriss\n";
	print "Perl version " . sprintf("%vd", $^V) . " ($^O)\n";
	print "Loaded Modules:\n";
	my $len = 1;
	foreach my $pm (keys %PM_LOADED) {	# get max length first, so we can be pretty
		$len = length($pm) if length($pm) > $len;
	}
	$len += 2;
	foreach my $pm (keys %PM_LOADED) {
		printf("  %-${len}sv%s\n", $pm, $PM_LOADED{$pm});
	}
	exit;
}

if (defined(my $df = $opt->get('debugfile'))) {
	$df = 'debug.txt' unless $df;		# if filename is empty
	$DEBUGFILE = $df;
	$DEBUG = 1 unless $DEBUG;		# force DEBUG on if we're specifying a file
	$opt->debug("DEBUG START: " . scalar(localtime) . " (level $DEBUG) File: $DEBUGFILE");
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
	dbtblprefix	=> $opt->dbtblprefix || $dbconf->{dbtblprefix},
	dbcompress	=> $opt->dbcompress || $dbconf->{dbcompress}
};
$db = new PS::DB($DBCONF);

$conf = new PS::ConfigHandler($opt, $db);
my $total = $conf->load(qw( main ));
$ERR = new PS::ErrLog($conf, $db);			# Now all error messages will be logged to the DB

$db->init_tablenames($conf);
$db->init_database;

# if a gametype was specified update the config
my $confupdated = 0;
if (defined $opt->get('gametype') and $conf->getconf('gametype','main') ne $opt->get('gametype')) {
	my $old = $conf->getconf('gametype', 'main');
	$db->update($db->{t_config}, { value => $opt->get('gametype') }, [ conftype => 'main', section => undef, var => 'gametype' ]);
	$conf->set('gametype', $opt->get('gametype'), 'main');
	$ERR->info("Changing gametype from '$old' to '" . $conf->getconf('gametype') . "' (per command line)");
	$confupdated = 1;
}

# if a modtype was specified update the config
if (defined $opt->get('modtype') and $conf->getconf('modtype','main') ne $opt->get('modtype')) {
	my $old = $conf->getconf('modtype', 'main');
	$db->update($db->{t_config}, { value => $opt->get('modtype') }, [ conftype => 'main', section => undef, var => 'modtype' ]);
	$conf->set('modtype', $opt->get('modtype'), 'main');
	$ERR->info("Changing modtype from '$old' to '" . $conf->getconf('modtype') . "' (per command line)");
	$confupdated = 1;
}

# reinitialize the tables if the config was updated above...
if ($confupdated) {
	$db->init_tablenames($conf);
	$db->init_database;	
}

# handle a 'stats reset' request
if (defined $opt->get('reset')) {
	my $game = new PS::Game($conf, $db);
	my $res = $opt->get('reset');
	my $all = (index($opt->get('reset'),'all') >= 0);
	my %del = (
		players 	=> ($all || (index($res,'player') >= 0)),
		clans   	=> ($all || (index($res,'clan') >= 0)),
		weapons 	=> ($all || (index($res,'weapon') >= 0)),
		heatmaps	=> ($all || (index($res,'heat') >= 0)),
	);
	$game->reset(%del);
	&main::exit;
}

$ERR->debug2("$total config settings loaded.");
$ERR->fatal("No 'gametype' configured.") unless $conf->get_main('gametype');
$ERR->info("PsychoStats v$VERSION initialized.");

# if -unknown is specified, temporarily enable report_unknown
if ($opt->get('unknown')) {
	$conf->set('errlog.report_unknown', 1, 'main');
}

# ------------------------------------------------------------------------------
# rescan clantags
if (defined $opt->get('scanclantags')) {
	my $game = new PS::Game($conf, $db);
	my $all = lc $opt->get('scanclantags') eq 'all' ? 1 : 0;
	$::ERR->info("Rescanning clantags for ranked players.");
	if ($all) {
		$::ERR->info("Removing ALL player to clan relationships.");
		$::ERR->info("All clans will be deleted except profiles.");
		$game->delete_clans(0);
	}

	$game->rescan_clans;

	# force a daily 'clans' update to verify what clans rank
	$opt->set('daily', ($opt->get('daily') || '') . ',clans');
}

# ------------------------------------------------------------------------------
# PERFORM DAILY OPERATIONS and exit if we did any (no logs should be processed)
if ($opt->get('daily')) {
	&main::exit if do_daily($opt->get('daily'));
}

# ------------------------------------------------------------------------------
# process log sources ... the endless while loop is a placeholder.
my $more_logs = !$opt->get('nologs');
while ($more_logs) { # infinite loop
	my $logsource = load_logsources();
	if (!defined $logsource or @$logsource == 0) {
		$ERR->fatal("No log sources defined! You must configure a log source (or use -log on command line)!");
	}

	my @total;
	my $game = new PS::Game($conf, $db);
	foreach my $source (@$logsource) {
		my $feeder = new PS::Feeder($source, $game, $conf, $db);
		next unless $feeder;

		# Let Feeder initialize (read directories, establish remote connections, etc).
		my $type = $feeder->init;	# 1=wait; 0=error; -1=nowait;
		next unless $type;		# ERROR

		$conf->setinfo('stats.lastupdate', time) unless $conf->get_info('stats.lastupdate');
		@total = $game->process_feed($feeder);
		$total_logs  += $total[0];
		$total_lines += $total[1];
		$conf->setinfo('stats.lastupdate', time);
		$feeder->done;

		last if $GRACEFUL_EXIT > 0;
	}
	&main::exit if $GRACEFUL_EXIT > 0;

	last;
}

# check to make sure we don't need to do any daily updates before we exit
check_daily($conf) unless $opt->get('nodaily');

END {
	$ERR->info("PsychoStats v$VERSION exiting (elapsed: " . compacttime(time-$starttime) . ", logs: $total_logs, lines: $total_lines)") if defined $ERR;
	$opt->debug("DEBUG END: " . scalar(localtime) . " (level $DEBUG) File: $DEBUGFILE") if $DEBUGFILE and defined $opt;
}

# ------- FUNCTIONS ------------------------------------------------------------

# returns a list of log sources
sub load_logsources {
	my $list = [];
	if ($opt->get('logsource')) {
		my $game = new PS::Game($conf, $db);
		my $log = new PS::Feeder($opt->get('logsource'), $game, $conf, $db);
		if (!$log) {
			$ERR->fatal("Error loading logsource from command line.");
		}
		push(@$list, $log->{logsource});
	} else {
		$list = $db->get_rows_hash("SELECT * FROM $db->{t_config_logsources} WHERE enabled=1 ORDER BY idx");
	}
	return wantarray ? @$list : [ @$list ];
}

# do daily updates, if needed
sub check_daily {
	my ($conf) = @_;
	my @dodaily = ();
	do_daily(join(',', @PS::Game::DAILY));
}

sub do_daily {
	my ($daily) = @_;
	$daily = lc $opt->get('daily') unless defined $daily;
	return 0 unless $daily;

	my %valid = map { $_ => 0 } @PS::Game::DAILY;
	my @badlist = ();
	foreach (split(/,/, $daily)) {
		if (exists $valid{$_}) {
			$valid{$_}++ 
		} else {
			push(@badlist, $_) if $_ ne '';
		}
	}
	$ERR->warn("Ignoring invalid daily options: " . join(',', map { "'$_'" } @badlist)) if @badlist;
	$daily = join(',', $valid{all} ? @PS::Game::DAILY[1..$#PS::Game::DAILY] : grep { $valid{$_} } @PS::Game::DAILY);

	if (!$daily) {
		$ERR->fatal("-daily was specified with no valid options. Must have at least one of the following: " . join(',', @PS::Game::DAILY), 1);
	}
	$ERR->info("Daily updates about to be performed: $daily");

	my $game = new PS::Game($conf, $db);
	foreach (split(/,/, $daily)) {
		my $func = "daily_" . $_;
		if ($game->can($func)) {
			$game->$func;
		} else {
			$ERR->warn("Ignoring daily update '$_': No game support");
		}
	}

	return 1;
}

sub run_as_daemon {
	my ($pid_file) = @_;
	defined(my $pid = fork) or die "Can't fork process: $!";
	exit if $pid;   # the parent exits

	# 1st generation child
	open(STDIN, '/dev/null');
	open(STDOUT, '>>/dev/null') unless $DEBUG;
	open(STDERR, '>>/dev/null') unless $DEBUG;
	chdir('/');     # run from root so we don't lock other potential mounts or directories
	setsid();       # POSIX; sets us as the process leader (our parent PID is 1)
	umask(0);

	# 2nd generation child (for SysV; avoids re-acquiring a controlling terminal)
	# setsid() needs to be done before this, see above.
	defined($pid = fork) or die "Can't fork sub-process: $!";
	exit if $pid;
	# now we're no longer the process leader but are in process group 1.

	if ($pid_file) {
		open(F, ">$pid_file") or warn("Can not write PID $$ to file: $pid_file: $!\n");
		print F $$;
		close(F);
		chmod 0644, $pid_file;
	}
}

# PS::ErrLog points to this to actually exit on a fatal error, incase I need to do some cleanup
sub main::exit { 
#	<> if iswindows();
	CORE::exit(@_) 
}

sub CATCH_CONTROL_C {
	$GRACEFUL_EXIT++;
	if ($GRACEFUL_EXIT == 0) {		# WONT HAPPEN (GRACEFUL_EXIT defaults to 0 now)
		if ($opt->get('daemon')) {
		        $GRACEFUL_EXIT++;
			goto C_HERE;
		} 
		syswrite(STDERR, "Caught ^C -- Are you sure? One more will attempt a gracefull exit.\n");
	} elsif ($GRACEFUL_EXIT == 1) {
C_HERE:
		syswrite(STDERR, "Caught ^C -- Please wait while I try to exit gracefully.\n");
	} else {
		syswrite(STDERR, "Caught ^C -- Alright! I'm done!!! (some data may have been lost)\n");
		&main::exit;
	}
	$SIG{INT} = \&CATCH_CONTROL_C;
}

__DATA__

# If no stats.cfg exists then this config is loaded instead

dbtype = mysql
dbhost = localhost
dbport = 
dbname = psychostats
dbuser = 
dbpass = 
dbtblprefix = ps_
