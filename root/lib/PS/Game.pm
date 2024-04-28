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
# 	$Id: Game.pm 552 2008-09-01 17:08:04Z lifo $
#
package PS::Game;

use strict;
use warnings;
use FindBin;
use base qw( PS::Debug );
use util qw( :date :time :numbers :net );
use PS::Config;			# for loadfile() function
use PS::Player;			# daily_clans(), daily_maxdays()
use PS::Weapon;			# daily_maxdays()
use PS::Map;			# daily_maxdays()
use PS::Award;			# daily_awards()
use PS::Role;
use Data::Dumper;
use File::Spec::Functions;
use POSIX qw(floor strftime mktime);
use Time::Local;
use Safe;

our $VERSION = '1.12.' . (('$Rev: 552 $' =~ /(\d+)/)[0] || '000');

our @DAILY = qw( all maxdays decay activity players clans ranks awards );

sub new {
	my $proto = shift;
	my $conf = shift;
	my $db = shift;
	my $class = ref($proto) || $proto;

	my $gametype = $conf->get_main('gametype');
	my $modtype  = $conf->get_main('modtype');

	$::ERR->fatal("No 'gametype' configured.") unless $gametype;
	$class .= "::$gametype";
	$class .= "::$modtype" if $modtype;

	# add our subclass into the frey ...
	eval "require $class";
	if ($@) {
		die("\n-----\nCompilation errors with Game class '$class':\n$@\n-----\n");
	}

	return $class->new($conf, $db);
}

sub _init {
	my $self = shift;
	my $db = $self->{db};
	my $conf = $self->{conf};

#	$::ERR->debug("Game->_init");

	$self->{evconf} = {};
	$self->{evorder} = [];
	$self->{evorder_idx} = 0;
	$self->{evregex} = sub {};
	$self->{evloaded} = {};			# keep track of which dynamic event methods were loaded already
	$self->load_events('','');		# load global events
	$self->load_events(undef,'');		# load events for the gametype
	$self->load_events(undef,undef);	# load events for the game:mod

	$self->{bonuses} = {};
	$self->load_bonuses('','');		# load global bonuses
	$self->load_bonuses(undef,'');		# load bonuses for the gametype
	$self->load_bonuses(undef,undef);	# load bonuses for the game:mod

	# DEBUG option to dump the events list to help verify the proper bonuses are being loaded
	if ($self->{conf}->get_opt('dumpbonuses')) {
		my $width = 0;
		foreach my $ev (keys %{$self->{bonuses}}) {
			$width = length($ev) if length($ev) > $width;
		}
		printf("%-${width}s %5s %5s %5s %5s\n", "Player Bonuses", "E","ET", "V", "VT");
		foreach my $ev (sort keys %{$self->{bonuses}}) {
			printf("%-${width}s %5d,%5d,%5d,%5d\n", 
				$ev,
				$self->{bonuses}{$ev}{enactor},
				$self->{bonuses}{$ev}{enactor_team},
				$self->{bonuses}{$ev}{victim},
				$self->{bonuses}{$ev}{victim_team}
			);
		}
		main::exit();
	}

	$self->{_plraliases} = {};		# stores a cache of player aliases fetched from the database
	$self->{_plraliases_age} = time();

	$self->{banned}{worldid} = {};
	$self->{banned}{ipaddr} = {};
	$self->{banned}{name} = {};
	$self->{banned_age} = time;

	# get() the option ONCE from the config instead of calling it over and over in the event code
	$self->{clantag_detection} = $conf->get_main('clantag_detection');
	$self->{report_unknown} = $conf->get_main('errlog.report_unknown');
	$self->{report_timestmaps} = $conf->get_main('errlog.report_timestamps');
	$self->{ignore_bots_conn} = $conf->get_main('ignore_bots_conn');
	$self->{ignore_bots} = $conf->get_main('ignore_bots');
	$self->{baseskill} = $conf->get_main('baseskill');
	$self->{uniqueid} = $conf->get_main('uniqueid');
	$self->{maxdays} = $conf->get_main('maxdays');
#	$self->{charset} = $conf->get_main('charset');
	$self->{usercmds} = $conf->get_main('usercmds');
	$self->{skillcalc} = $conf->get_main('skillcalc');
	$self->{minconnected} = $conf->get_main('minconnected');
	$self->{maxdays_exclusive} = $conf->get_main('maxdays_exclusive');
	$self->{plr_save_on_round} = $conf->get_main('plr_save_on_round');

	$self->add_calcskill_func('kill', $conf->get_main('calcskill_kill'));
	
	# initializes the clantags from the config.
	if ($self->{clantag_detection}) {
		$self->{clantags}{str} = $db->get_rows_hash("SELECT * FROM $db->{t_config_clantags} WHERE type='plain' ORDER BY idx");
		$self->{clantags}{regex} = $db->get_rows_hash("SELECT * FROM $db->{t_config_clantags} WHERE type='regex' ORDER BY idx");

		# build a sub-routine to scan the player for matching clantag regex's
		if (@{$self->{clantags}{regex}}) {
			my $code = '';
			my $idx = 0;
			my $env = new Safe;
			foreach my $ct (@{$self->{clantags}{regex}}) {
				my $regex = $ct->{clantag};

				# perform sanity check on user configured regex
				$env->reval("/$regex/");
				if ($@) {
					$::ERR->warn("Error in clantag definition #$ct->{id} (/$regex/): $@");
					next;
				}

				$code .= "  return [ $idx, \\\@m ] if \@m = (\$_[0] =~ /$regex/);\n";
				$idx++;
			}
#			print "sub {\n  my \@m = ();\n$code  return undef;\n}\n";
			$self->{clantags_regex_func} = eval "sub { my \@m = (); $code return undef }";
			if ($@) {
				$::ERR->fatal("Error in clantag regex function: $@");
			}
		} else {
			$self->{clantags_regex_func} = sub { undef };
		}
	}
	return $self;
}

# creates a skill function based on the type and configured function.
# creates functions named calcskill_$type_func() and calcskill_$type_init()
sub add_calcskill_func {
	my ($self, $type, $func) = @_;
	my $calcskill = 'calcskill_' . $type;		# calcskill_kill
	my $calcskill_func = $calcskill . '_' . $func;	# calcskill_kill_default
	my $calcskill_init = $calcskill_func . '_init';	# calcskill_kill_init

	# If the function exists there is no reason to redefine it...	
	if ($self->can($calcskill_func)) {
		return undef;	
	}
	
	# try to load the skill calculation code
	my $file = catfile($FindBin::Bin, 'lib', 'PS', 'Skill', $calcskill_func . '.pl');
	if (-f $file) {
		my $code = "";
		if (open(F, "<$file")) {
			$code = join('', <F>);
			close(F);
		} else {
			$self->fatal("Error reading skill code file ($file): $!");
		}

		# sanity check; if the eval fails then ignore this code
		my $eval = new Safe;
		$eval->permit(qw( sort ));
		$eval->reval($code);
		if ($@) {
			$self->fatal("Error in skill code '$calcskill': $@");
		} else {
			# eval it in the current scope
			# this has to be done since reval() makes it private in its own scope
			eval $code;
		}
	} else {
		$self->fatal(
			"Error reading skill code '" . $func . "' file $file\n" . 
			"File does not exist.\n" . 
			"Are you sure you're using the correct skill calculation?\n" . 
			"Try changing the 'Skill Calculation' config setting to 'default'."
		);
	}

	# if there is still no method available, we die ... 
	if (!$self->can($calcskill_func)) { # or $self->{$calcskill} eq 'func' or $self->{$calcskill} eq 'init') {
		$::ERR->fatal("Invalid skill function configured ($func) " . 
			"Try using 'default' or 'alternative' instead."
		);
	}

	{ 	# LOCALIZE BLOCK
		# make an alias in the object for the skill function. This way,
		# we don't have to constantly dereference a varaible to call the
		# function in the 'event_kill' routine.
		# $self->calcskill_kill_func will now work
		no strict 'refs';
		my $func = __PACKAGE__ . '::' . $calcskill . '_func';
		# only define the static method once.
		if (!$self->can($calcskill . '_func')) {
			*$func 	 = $self->can($calcskill_func) || sub { die "Abstract call to $calcskill_func\n" };
		}
		# create specific init sub. A generic function is not created.
		# $self->calcskill_kill_{$type}_init
		$func 	 = __PACKAGE__ . '::' . $calcskill_init;
		*$func 	 = $self->can($calcskill_init) || sub { };

		# run init code for skill calculation, if available
		$self->$calcskill_init;
	}

}

sub initipcache {
	my $self = shift;
	$self->{ipcache} = {};		# player IPADDR cache, keyed on UID
}

# there are anomalys that cause players to not always be detected by a single 
# criteria. So we cache each way we know we can reference a player. 
sub initcache {
	my $self = shift;

	$self->{c_signature} = {};	# players keyed on their signature string
	$self->{c_uniqueid} = {};	# players keyed on their uniqueid (not UID)
	$self->{c_uid} = {};		# players keyed on UID
	$self->{c_plrid} = {};		# players keyed on plrid
}

# add a player to all appropriate caches
sub addcache_all {
	my ($self, $p) = @_;
	$self->addcache($p, $p->signature, 'signature');
	$self->addcache($p, $p->uniqueid, 'uniqueid');
	$self->addcache($p, $p->uid, 'uid');
	$self->addcache($p, $p->plrid, 'plrid');
}

# remove player from all appropriate caches
sub delcache_all {
	my ($self, $p) = @_;
	$self->delcache($p->signature, 'signature');
	$self->delcache($p->uniqueid, 'uniqueid');
	$self->delcache($p->uid, 'uid');
	$self->delcache($p->plrid, 'plrid');
}

# add a plr to the cache
sub addcache {
	my ($self, $p, $sig, $cache) = @_;
	$cache ||= 'signature';
	$self->{'c_'.$cache}{$sig} = $p;
}

# remove a plr from the cache
sub delcache {
	my ($self, $sig, $cache) = @_;
	$cache ||= 'signature';
	delete $self->{'c_'.$cache}{$sig};
}

# return the cached plr or undef if not found
sub cached {
	my ($self, $sig, $cache) = @_;
	return undef unless defined $sig;
	$cache ||= 'signature';
	return exists $self->{'c_'.$cache}{$sig} ? $self->{'c_'.$cache}{$sig} : undef;
}

# debug method; prints out some information about the player caches
sub show_cache {
	my ($self) = @_;
#	printf("CACHE INFO: sig:% 3d  uniqueid: % 3d  uid:% 3d  plrid: % 3d\n",
#	printf("CACHE INFO: s:% 3d w: % 3d u:% 3d p: % 3d\n",
	printf("CACHE INFO: % 3d % 3d % 3d % 3d (s,w,u,p)\n",
		scalar keys %{$self->{'c_signature'}},
		scalar keys %{$self->{'c_uniqueid'}},
		scalar keys %{$self->{'c_uid'}},
		scalar keys %{$self->{'c_plrid'}}
	);
}

# normalize a role name
sub role_normal { defined $_[1] ? lc $_[1] : '' }

# normalize a team name
sub team_normal { defined $_[1] ? lc $_[1] : '' }

# normalize a weapon name
sub weapon_normal { defined $_[1] ? lc $_[1] : 'unknown' }

# returns a PS::Map object matching the map $name given
sub get_map {
	my ($self, $name) = @_;
	$name ||= $self->{curmap} || 'unknown';

	if (exists $self->{maps}{$name}) {
		return $self->{maps}{$name};
	}

	$self->{maps}{$name} = new PS::Map($name, $self->{conf}, $self->{db});
	$self->{maps}{$name}->timerstart($self->{timestamp});
	$self->{maps}{$name}->statdate($self->{timestamp});
	return $self->{maps}{$name};
}

# returns a PS::Weapon object matching the weapon $name given
sub get_weapon {
	my ($self, $name) = @_;
	$name = $self->weapon_normal($name);

	if (exists $self->{weapons}{$name}) {
		return $self->{weapons}{$name};
	}

	$self->{weapons}{$name} = new PS::Weapon($name, $self->{conf}, $self->{db});
	$self->{weapons}{$name}->statdate($self->{timestamp});
	return $self->{weapons}{$name};
}

# returns a PS::Role object matching the role $name given
sub get_role {
	my ($self, $name, $team) = @_;
	return undef unless $name;
	$name = $self->role_normal($name);

	if (exists $self->{roles}{$name}) {
		return $self->{roles}{$name};
	}

	$self->{roles}{$name} = new PS::Role($name, $team, $self->{conf}, $self->{db});
	$self->{roles}{$name}->statdate($self->{timestamp});
	return $self->{roles}{$name};
}

# returns all player references on a certain team that are not dead.
# if $all is true then dead players are included.
sub get_team {
	my ($self, $team, $all) = @_;
	my (@list, @ids);
	@ids = grep { $self->{c_plrid}{$_}->active and $self->{c_plrid}{$_}->{team} eq $team } keys %{$self->{c_plrid}};
	@ids = grep { !$self->{c_plrid}{$_}->{isdead} } @ids unless $all;
	@list = map { $self->{c_plrid}{$_} } @ids;
	return wantarray ? @list : \@list;
}

# we only want active players to count towards the minconnected value, 
# since players are not always removed from memory as soon as they disconnect.
# This function is kept is small as possible for speed. However, it's very slow
# to have to call ->active for all players every time this function is called. 
sub minconnected { 
	return 1 if $_[0]->{minconnected} == 0;
	# TODO: come up with a better way to track total players online so we don't have to do this loop.
	return grep($_[0]->{c_plrid}{$_}->active, keys %{$_[0]->{c_plrid}}) >= $_[0]->{minconnected};
}

# Add's a player BAN to the database. 
# Does nothing If the ban already exists unless $overwrite is true
# ->addban(plr, {extra})
sub addban {
	my $self = shift;
	my $plr = shift;
	my $matchtype = 'worldid';
	my $matchstr = $plr->worldid;
	my $opts = ref $_[0] ? shift : { @_ };
	my $overwrite = 0;
	if (exists $opts->{overwrite}) {
		$overwrite = $opts->{overwrite};
		delete $opts->{overwrite};
	}

#	$matchtype = 'worldid' unless $matchtype =~ /^(worldid|ipaddr|name)$/;

	my $db = $self->{db};
	my $str = $db->quote($matchstr);
	my ($exists,$enabled) = $db->get_row_array("SELECT id,enabled FROM $db->{t_config_plrbans} WHERE matchtype='$matchtype' AND matchstr=$str LIMIT 1");

	if (!$exists or $overwrite) {
		my $set = {
			'matchtype'	=> $matchtype,
			'matchstr'	=> $matchstr,
			'enabled'	=> defined $opts->{enabled} ? $opts->{enabled} : 1,
			'ban_date'	=> $opts->{ban_date} || time,
			'ban_reason'	=> $opts->{reason} || ''
		};

		if ($exists) {
			$db->update($db->{t_config_plrbans}, $set, 'id', $exists);
		} else {
			# add the ban to the config
			$set->{id} = $db->next_id($db->{t_config_plrbans});
			$db->insert($db->{t_config_plrbans}, $set);
		}

		# add a plrban record for historical purposes (only if there isn't already an active ban)
		my ($active) = $db->get_row_array("SELECT 1 FROM $db->{t_plr_bans} WHERE plrid=" . $plr->plrid . " AND unban_date IS NULL");
		if (!$active) {
			$set = {
				'plrid'		=> $plr->plrid,
				'ban_date'	=> $opts->{ban_date} || time,
				'ban_reason'	=> $opts->{reason}
			};
			$db->insert($db->{t_plr_bans}, $set);
		}

	} elsif (defined $opts->{enabled} and $opts->{enabled} ne $enabled) {
		$db->update($db->{t_config_plrbans}, { enabled => $opts->{enabled} }, [ id => $exists ]);
	}
}

# Removes a player BAN from the database. 
# ->unban(plr)
sub unban {
	my $self = shift;
	my $plr = shift;
	my $opts = ref $_[0] ? shift : { @_ };
	my $matchtype = 'worldid';
	my $matchstr = $plr->worldid;

#	$matchtype = 'worldid' unless $matchtype =~ /^(worldid|ipaddr|name)$/;

	my $db = $self->{db};
	my $str = $db->quote($matchstr);

	# delete the config record
	$db->query("DELETE FROM $db->{t_config_plrbans} WHERE matchtype='$matchtype' AND matchstr=$str");

	# update the active ban record for the player
	my ($active) = $db->get_row_array("SELECT ban_date FROM $db->{t_plr_bans} WHERE plrid=" . $plr->plrid . " AND unban_date IS NULL");
	if ($active) {
		$db->update($db->{t_plr_bans}, 
			{ unban_date => $opts->{unban_date} || time, 'unban_reason' => $opts->{reason} }, 
			[ plrid => $plr->plrid, 'ban_date' => $active ]
		);
	}
}

# returns true if any of the criteria given matches an enabled BAN record
# ->isbanned(worldid => '', ipaddr => '', name => '')
sub isbanned {
	my $self = shift;
	my $m = ref $_[0] eq 'HASH' ? shift : ref $_[0] ? $_[0]->{plrids} : { @_ };
	my $banned = 0;
#	either pass a hash of values, or a PS::Player record, or key => value pairs

	# clear the banned cache every X minutes (real-time)
	if (time - $self->{banned_age} > 60*5) {
		$::ERR->debug("CLEARING BANNED CACHE");
		$self->{banned}{worldid} = {};
		$self->{banned}{ipaddr} = {};
		$self->{banned}{name} = {};
		$self->{banned_age} = time;
	}

	my ($matchstr);
	foreach my $match (qw( worldid name ipaddr )) {
		next unless exists $m->{$match} and defined $m->{$match};
		return $self->{banned}{$match}{ $m->{$match} } if exists $self->{banned}{$match}{ $m->{$match} };

		$matchstr = ($match eq 'ipaddr') ? int2ip($m->{$match}) : $m->{$match}; 
		($banned) = $self->{db}->get_row_array(
			"SELECT id FROM $self->{db}{t_config_plrbans} " . 
			"WHERE enabled=1 AND matchtype='$match' AND " . $self->{db}->quote($matchstr) . " LIKE matchstr"
		);
		$self->{banned}{$match}{ $m->{$match} } = $banned;
		return $banned if $banned;
	}

	return 0;
}

# scans the given player name for a matching clantag from the database. creates
# a new clan+profile if it's a new tag. $p is either a PS::Player object, or a
# plain scalar string to match. if a PS::Player object is given it's updated
# directly. the clanid is always returned if a match is found.
sub scan_for_clantag {
	my ($self, $p) = @_;
	my ($ct, $tag, $id);
	my $name = ref $p ? $p->name : $p;

	# scan STRING clantags first (since they're faster and more specific)
	my $m = $self->clantags_str_func($name);
	if ($m) {
		$ct = $self->{clantags}{str}->[ $m->[0] ];
		$tag = ($ct->{overridetag} ne '') ? $ct->{overridetag} : $m->[1];
		$id = $self->get_clanid($tag);
		if ($id) {	# technically this should never be 0
			if (!$self->{db}->select($self->{db}->{t_clan}, 'locked', [ clanid => $id ])) {
				$p->clanid($id) if ref $p;
				return $id;
			}
		}
	}

	# scan REGEX clantags if we didn't find a match above ...
	$m = &{$self->{clantags_regex_func}}($name);
#	print Dumper($name, $m) if defined $m;
	if ($m) {
		$ct = $self->{clantags}{regex}->[ $m->[0] ];
		$tag = ($ct->{overridetag} ne '') ? $ct->{overridetag} : join('', @{$m->[1]});
		$id = $self->get_clanid($tag);
		if ($id) {	# technically this should never be 0
			if (!$self->{db}->select($self->{db}->{t_clan}, 'locked', [ clanid => $id ])) {
#				print Dumper($m);
				$p->clanid($id) if ref $p;
				return $id;
			}
		}
	}
	return undef;
}

# returns the clanid based on the clantag given. If no clan exists it is created.
sub get_clanid {
	my ($self, $tag) = @_;
	my $id = $self->{db}->select($self->{db}->{t_clan}, 'clanid', [ clantag => $tag ]);

	# create the clan if it didn't exist; Must default the clan to not be allowed to rank
#	$self->{db}->begin;
	if (!$id) {
		$id = $self->{db}->next_id($self->{db}->{t_clan}, 'clanid');
		$self->{db}->insert($self->{db}->{t_clan}, { clanid => $id, clantag => $tag, allowrank => 0 });
	}

	# create the clan profile if it didn't exist
	if (!$self->{db}->select($self->{db}->{t_clan_profile}, 'clantag', [ clantag => $tag ])) {
		$self->{db}->insert($self->{db}->{t_clan_profile}, { clantag => $tag, logo => '' });
	}
#	$self->{db}->commit;


	return $id;
}

sub clantags_str_func {
	my ($self, $name) = @_;
	my $idx = -1;
	my $tag = undef;
	foreach my $ct (@{$self->{clantags}{str}}) {
		$idx++;
		if ($ct->{pos} eq 'left') {
			if (index($name, $ct->{clantag}) == 0) {
				$tag = $ct->{clantag};
				last;
			}
		} else {	# right
			# reverse the name and clantag so that index() can accurately determine if the tag ONLY
			# starts at the END of the name. rindex could otherwise potentially find matching tags in the
			# middle of the player name instead. which is not what we want here.
			my $revtag  = reverse scalar $ct->{clantag};
			my $revname = reverse scalar $name;
			if (index($revname, $revtag) == 0) {
				$tag = $ct->{clantag};
				last;
			}
		}
	}
	return undef unless $tag;
	return wantarray ? ( $idx, $tag ) : [ $idx, $tag ];
}

# prepares the EVENT patterns
sub init_events {
	my $self = shift;
	return unless $self->{evregex};	# only need to initialize once
	# sort all event patterns in the order they were loaded
	$self->{evorder} = [ sort {$self->{evconf}{$a}{idx} <=> $self->{evconf}{$b}{idx}} keys %{$self->{evconf}} ];
	$self->{evregex} = $self->_build_regex_func;
}

sub _build_regex_func {
	my $self = shift;
	my $code = '';
	my $env = new Safe;

	foreach my $ev (@{$self->{evorder}}) {
		my $regex = $self->{evconf}{$ev}{regex};
		my $event = 'event_' . ($self->{evconf}{$ev}{alias} || $ev);
		# make sure a regex was configured
		unless ($regex) {
			$self->warn("Ignoring event '$ev' (No regex defined)");
			next;
		}
		# make sure regex is /surrounded/
		unless ($regex =~ m|^/.+/$|) {
			$self->warn("Ignoring event '$ev' (Invalid terminators)");
			next;
		}
		# verify a method exists (unless its configured to be ignored)
		if (!$self->{evconf}{$ev}{ignore} and !$self->can($event)) {
			$self->warn("Ignoring event '$ev' (No method available)");
			$self->{evconf}{$ev}{ignore} = 1;
		}

		# test the regex syntax (safely; avoid code injections)
		$env->reval($regex);
		if ($@) {
			$self->warn("Invalid regex for event '$ev' regex $regex:\n$@");
			next;
		}
		$code .= "  return ('$ev',\\\@parms) if \@parms = (\$_[0] =~ $regex);\n";
	}
	# debug: -dumpevents on command line to see the sub that is created
	if ($self->{conf}->get_opt('dumpevents')) {
		print "sub {\n  my \@parms = ();\n$code  return (undef,undef);\n}\n";
		main::exit();
	}
	return eval "sub { my \@parms = (); $code return (undef,undef) }";
}

# Takes a Feeder object and processes all events from it.
# in scalar context the total logs processed is returned, 
# in array context a two element array is returned including total (logs, lines)
sub process_feed {
	my $self = shift;
	my $feeder = shift;
	my $count = 0;
	my $start = time;
	my $stream_time = time;
	my $abs_lines = $self->{conf}->get_opt('maxlines');	# if these are reached, stats processing stops
	my $abs_logs  = $self->{conf}->get_opt('maxlogs');	# ...
	my $max_seconds = $self->{conf}->get_main('daily.maxminutes') * 60;	# if these are reached, updates are performed
	my $max_lines = $self->{conf}->get_main('daily.maxlines');		# ...
	my $per_day_ranks = $self->{conf}->get_main('daily.per_day_ranks');
	my $stream_save_seconds = $self->{conf}->get_main('daily.stream_save_seconds') || 60;

	$self->{last_ranked} = $self->{day} || 0;
	$self->{last_ranked_line} = 0;
	$self->{curmap} = $feeder->defaultmap;
	$self->init_events;

	# loop through all events that the feeder has in it's queue
	my $lastsrc = '';
	while (defined(my $ev = $feeder->next_event)) {
		my ($src, $event, $line, $game) = @$ev;
		$game ||= $self;
		if ($src ne $lastsrc) {
			$lastsrc = $src;
			$game->new_source($src);
		}

		$game->event($src, $event, $line);

		if (time - $stream_time >= $stream_save_seconds) {
			# save all stats in-memory (if the feeder supports it)
			# this is only used from stream feeders since a stream
			# can have multiple games stored within it, in memory.
			$feeder->save_games;
			$stream_time = time;
		}

		# every X lines we should recalc everything to help with really long processing passes
		$count++;
		if (($max_lines and ($count % $max_lines == 0)) or (time - $start >= $max_seconds)) {
			$count = 0;
			$start = time;

			# perform all daily updates (except awards)
			foreach my $d (@PS::Game::DAILY) {
				next if $d eq 'awards';				# we don't do awards (they take too long)
				#next if $per_day_ranks and $d eq 'ranks';	# don't do ranks if per_day_ranks is enabled
				my $f = 'daily_' . $d;
				$game->$f if $game->can($f);
			}
		}

		# If the day changes re-calculate player ranks, only if configured
		if ($per_day_ranks and defined $game->{last_day} and
		    $game->{last_day} != $game->{day} and
		    $game->{last_ranked} != $game->{day} and
		    $feeder->total_lines - $game->{last_ranked_line} >= 1000) {
			$game->daily_ranks;
			$game->{last_ranked} = $game->{day};
			$game->{last_ranked_line} = $feeder->total_lines;
		}
	}
	
	return wantarray ? ($feeder->total_logs, $feeder->total_lines) : $feeder->total_logs;
}

# abstact event method. All sub classes must override.
sub event { $_[0]->fatal($_[0]->{class} . " has no 'event' method implemented. HALTING.") }

# Called everytime the log source changes
sub new_source { }

# Loads the player bonuses config for the current game
# may be called several times to overload previous values
# game/mod type 'undef' means to use the current config settings. a blank string will load global values
sub load_bonuses {
	my $self = shift;
	my ($gametype, $modtype) = @_;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $g = defined $gametype ? $gametype : $self->{conf}->get_main('gametype');
	my $m = defined $modtype  ? $modtype  : $self->{conf}->get_main('modtype');
	my $match = '';
	my @bonuses = ();

	$match .= $g ? $db->quote($g) . " REGEXP CONCAT('^', gametype, '\$')" : "gametype=''";
	$match .= " AND ";
	$match .= $m ? $db->quote($m) . " REGEXP CONCAT('^', modtype, '\$')" : "modtype=''";
	@bonuses = $db->get_rows_hash("SELECT * FROM $db->{t_config_plrbonuses} WHERE $match");

	foreach my $b (@bonuses) {
		$self->{bonuses}{ $b->{eventname} } = $b;
	}
}

# Loads the event config for the current game
# may be called several times to overload previous values
# game/mod type 'undef' means to use the current config settings. a blank string will load global values
sub load_events {
	my $self = shift;
	my ($gametype, $modtype) = @_;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $g = defined $gametype ? $gametype : $self->{conf}->get_main('gametype');
	my $m = defined $modtype  ? $modtype  : $self->{conf}->get_main('modtype');
	my $path = catfile($FindBin::Bin, 'lib', 'PS', 'Events', '');
	my $match = '';
	my @events = ();

	$match .= $g ? $db->quote($g) . " REGEXP CONCAT('^', gametype, '\$')" : "gametype=''";
	$match .= " AND ";
	$match .= $m ? $db->quote($m) . " REGEXP CONCAT('^', modtype, '\$')" : "modtype=''";
	@events = $db->get_rows_hash("SELECT * FROM $db->{t_config_events} WHERE $match ORDER BY idx");

	foreach my $e (@events) {
		# load the event code if there is a file matching the event (and it hasn't been loaded already)
		my $event = 'event_' . ($e->{codefile} || $e->{eventname});
#		if (!exists $self->{evloaded}{$event}) {
			my $file = $event . '.pl';
			if (-f "$path$file") {
				# sanity check; do not allow files larger then 512k
				if (-s "$path$file" > 1024*512) {
					$self->warn("Error in event code '$event': File size too large (>512k)");
					next;
				}
	
				my $code = "";
				if (open(F, "<$path$file")) {
					$code = join('', <F>);
					close(F);
				} else {
					$self->warn("Error reading event code 'event': $!");
					next;
				}

				# sanity check; if the eval fails then ignore this code
				my $eval = new Safe;
				$eval->reval($code);
				if ($@) {
					$self->warn("Error in event code '$event': $@");
					next;
				} else {
					# eval it in the current scope
					# this has to be done since reval() makes it private in its own scope
					eval $code;
				}

				$self->{evloaded}{$event} = "$path$file";
			} else {
				my $func = $e->{alias} ? 'event_' . $e->{alias} : $event;
				# only error if a codefile was specified in the config
				if (!$self->can($func) and $e->{codefile}) {
					$self->warn("Error loading event code for '$event': $path$file: File does not exist");
					next;
				}
			}
#		}

		my $i = 0;
		if (exists $self->{evconf}{$e->{eventname}}) {
			$i = $self->{evconf}{$e->{eventname}}{idx};
			$self->{evorder_idx} = $i if $i > $self->{evorder_idx};
		} else {
			$i = ++$self->{evorder_idx};
		}
		$self->{evconf}{$e->{eventname}} = {
			alias		=> $e->{alias},
			regex		=> $e->{regex},
			idx		=> $i, 
			ignore		=> $e->{ignore},
			codefile	=> $e->{codefile}
		};
	}
}

# returns the 'alias' for the uniqueid given.
# If no alias exists the same uniqueid given is returned.
# caches results to speed things up.
sub get_plr_alias {
	my ($self, $uniqueid) = @_;
	my $alias;
	if (time - $self->{_plraliases_age} > 60*15) {		# clear the aliases cache after 15 mins (real-time)
		$self->{_plraliases} = {};
		$self->{_plraliases_age} = time;
	}
	if (exists $self->{_plraliases}{$uniqueid}) {
		$alias = $self->{_plraliases}{$uniqueid};
	} else {
		$alias = $self->{db}->select($self->{db}->{t_plr_aliases}, 'alias', [ uniqueid => $uniqueid ]);
		$self->{_plraliases}{$uniqueid} = $alias;
	}
	return (defined $alias and $alias ne '') ? $alias : $uniqueid;
}

# returns an array of all connected players (only active by default)
sub get_plr_list { 
	my ($self, $active_only) = @_;
	$active_only = 1 unless defined $active_only;
	my @list;
	if ($active_only) {
		@list = map { $self->{c_plrid}{$_} } grep { $self->{c_plrid}{$_}->active } keys %{$self->{c_plrid}};
	} else {
		@list = map { $self->{c_plrid}{$_} } keys %{$self->{c_plrid}};
	}
	return wantarray ? @list : \@list;
}

# returns a count of online players
sub get_online_count {
	my ($self) = @_;
	return ( grep { $self->{c_plrid}{$_}->active } keys %{$self->{c_plrid}} );
}

# The feeder object will call this method after it has loaded previous state information
# to load all players and other information that were in memory before the previous shutdown.
# ->restore_state($state)
sub restore_state {
	my $self = shift;
	my $state = shift || return;
	my $map;

	# restore the map and timestamp
	$self->{timestamp} = $state->{timestamp};
	if ($state->{map}) {
		$self->{curmap} = $state->{map};
		$map = $self->get_map;
	}

	# restore the IP cache 
	$self->{ipcache} = (defined $state->{ipaddrs} and scalar keys %{$state->{ipaddrs}}) ? { %{$state->{ipaddrs}} } : {};

	# restore the players that were online previously
	if ($state->{players}) {
		foreach my $plr (@{$state->{players}}) {
			my $plrids = { name => $plr->{name}, worldid => $plr->{worldid}, ipaddr => $plr->{ipaddr} };
			my $p = new PS::Player($plrids, $self) || next;
			$p->signature($plr->{plrsig});
			$p->timerstart($self->{timestamp});
			$p->uid($plr->{uid});
			$p->team($plr->{team});
			$p->role($plr->{role}) if $plr->{role};
			$p->is_dead($plr->{isdead});
			$p->active(1);
			$self->addcache_all($p);
		}
	}

#	print "state timestamp: 	" . localtime($state->{timestamp}) . "\n";
#	print "map restored: 		" . $map->name . "\n" if $map;
#	print "player IDs restored: 	", join(', ', map { $self->{c_plrid}{$_}->{plrid} } keys %{$self->{c_plrid}}), "\n";
#	print "total IP's restored: 	", scalar keys %{$self->{ipcache}}, "\n";
}

# resets the isdead status of all players
sub reset_isdead {
	my ($self, $isdead) = @_;
	map { $self->{c_plrid}{$_}->is_dead($isdead || 0) } keys %{$self->{c_plrid}};
}

=pod
use constant 'PI' => 3.1415926;
use constant 'T'  => 1;
our $KMAX = 100;
our $KCNT = 0;
our $MEAN = 0;
our $VARIANCE = 0;
our $NORMVAR = 0;
# http://www.gamasutra.com/features/20000209/kreimeier_pfv.htm
# Does not work yet... Still trying to figure out the calculations
sub calcskill_kill_new {
	my ($self,$k,$v,$w) = @_;
	my ($vskill, $kskill, $delta, $expectancy, $change, $kbonus, $vbonus, $result);
	my $T = 1;
	my $MAX_GAIN = 25;
	my ($kprob, $vprob);

	$kskill = $k->skill;
	$vskill = $v->skill;

	# determine current meadian and variance of skill value of players in memory
#	if ($KCNT == 0) {
#		my $sum = 0;
#		my $tot = scalar keys %{$self->{plrs}};
#		$sum += $self->{plrs}{$_}->skill for keys %{$self->{plrs}};
#		$MEAN = (1/$tot) * $sum;						# mean value of skill
#		$VARIANCE = 1 / ($tot-1) * (($MEAN)^2);
#		$NORMVAR = 1 / sqrt(2*PI * $VARIANCE);					# normalize
#		$kprob = $NORMVAR * exp(($kskill - $MEAN)^2 / (2*$VARIANCE) );		# probibility of killer killing victim
##		$vprob = $NORMVAR * exp(($vskill - $MEAN)^2 / (2*$VARIANCE) );		# probibility of victim killing killer
#		printf "KS: %0.2f, VK: %0.2f, MEAN: %0.7f, VAR: %0.7f, NVAR: %0.7f, kprob: %0.7f (%d plrs)\n", 
#			$kskill, $vskill, $MEAN, $VARIANCE, $NORMVAR, $kprob, $tot;
#		$KCNT = $KMAX;
#	} else {
#		--$KCNT;
#	}

	$result = 1;
	$delta = $vskill - $kskill;
	$expectancy = 1.0 / (1.0 + exp((-$delta) / T));			# Fermi function
	$kbonus = $vbonus = $change = $MAX_GAIN * ($result-$expectancy);

	print "kk: $kskill, vk: $vskill, delta: $delta, exp: $expectancy, change: $change\n"; # if $k->plrid eq 193 or $v->plrid eq 193;

	$kskill += $change;
	$vskill -= $change;

	$k->skill($kskill);
	$v->skill($vskill);

	return (
		$kskill,						# killers new skill value
		$vskill,						# victims ...
		$kbonus,						# total bonus points given to killer
		$vbonus							# ... victim
	);
}

# update population variance for current player IQs (PQ)
#sub bootstrap {
#	my $self = shift;
#	my $sum = 0;
#	my $tot = scalar keys %{$self->{plrs}};
#	$sum += $self->{plrs}{$_}->skill for keys %{$self->{plrs}};
#	$MEAN = (1/$tot) * $sum;						# mean value of skill
#	$VARIANCE = 1 / ($tot-1) * (($MEAN)^2);
#	$NORMVAR = 1 / sqrt(2*PI * $VARIANCE);					# normalize
#	$kprob = $NORMVAR * exp(($kskill - $MEAN)^2 / (2*$VARIANCE) );		# probibility of killer killing victim
##	$vprob = $NORMVAR * exp(($vskill - $MEAN)^2 / (2*$VARIANCE) );		# probibility of victim killing killer
#	printf "KS: %0.2f, VK: %0.2f, MEAN: %0.7f, VAR: %0.7f, NVAR: %0.7f, kprob: %0.7f (%d plrs)\n", 
#		$kskill, $vskill, $MEAN, $VARIANCE, $NORMVAR, $kprob, $tot;
#}
=cut

# assign bonus points to players
# ->plrbonus('trigger', 'enactor type', $PLR/LIST, ... )
sub plrbonus {
	my $self = shift;
	my $trigger = shift;
	return unless exists $self->{bonuses}{$trigger};		# bonus trigger doesn't exist

#	print "plrbonus: $trigger:\n";
	while (@_) {
		my $type = shift;
		my $entity = shift || next;
		my $val = $self->{bonuses}{$trigger}{$type} || next;
		my $list = (ref $entity eq 'ARRAY') ? $entity : [ $entity ];
#		print "plrbonus: $type\n";

		# assign bonus to players in our list
		my $newskill;
		foreach my $p (@$list) {
			next unless defined $p;
			$p->{basic}{totalbonus} += $val;
			$newskill = $p->skill + $val;
			$p->skill($newskill);
#			printf("\t%-32s received %3d points for %s ($type)\n", $p->name, $val, $trigger);
		}
	}
}

# daily process for awards
sub daily_awards {
	my $self = shift;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $lastupdate = $self->{conf}->getinfo('daily_awards.lastupdate');
	my $start = time;
	my $last = time;
	my $oneday = 60 * 60 * 24;
	my $oneweek = $oneday * 7;
	my $startofweek = $conf->get_main('awards.startofweek');
	my $weekcode = '%V'; #$startofweek eq 'monday' ? '%W' : '%U';
	my $dodaily = $conf->get_main('awards.daily');
	my $doweekly = $conf->get_main('awards.weekly');
	my $domonthly = $conf->get_main('awards.monthly');
	my $fullmonthonly = !$conf->get_main('awards.allow_partial_month');
	my $fullweekonly = !$conf->get_main('awards.allow_partial_week');
	my $fulldayonly = !$conf->get_main('awards.allow_partial_day');

	$::ERR->info(sprintf("Daily 'awards' process running (Last updated: %s)", 
		$lastupdate ? scalar localtime $lastupdate : 'never'
	));

	if (!$dodaily and !$doweekly and !$domonthly) {
		$::ERR->info("Awards are disabled. Aborting award calculations.");
		return;
	}

	# gather awards that match our gametype/modtype and are valid ...
	my $g = $db->quote($conf->get_main('gametype'));
	my $m = $db->quote($conf->get_main('modtype'));
	my @awards = $db->get_rows_hash("SELECT * FROM $db->{t_config_awards} WHERE enabled=1 AND (gametype=$g or gametype='' or gametype IS NULL) AND (modtype=$m or modtype='' or modtype IS NULL)");

	my ($oldest, $newest) = $db->get_row_array("SELECT MIN(statdate), MAX(statdate) FROM $db->{t_plr_data}");
	if (!$oldest and !$newest) {
		$::ERR->info("No historical stats available. Aborting award calculations.");
		return;
	}

	$oldest = ymd2time($oldest);
	$newest = ymd2time($newest);

	my $days = [];
	if ($dodaily) {
		my $curdate = $oldest;
		while ($curdate <= $newest) {
			last if $fulldayonly and $curdate + $oneday > $newest;
			push(@$days, $curdate);
#			$::ERR->verbose(strftime("daily: %Y-%m-%d\n", localtime($curdate)));
			$curdate += $oneday;						# go forward 1 day
		}
	}

	my $weeks = [];
	if ($doweekly) {
		# curdate will always start on the first day of the week
		my $curdate = $oldest - ($oneday * (localtime($oldest))[6]);
		$curdate += $oneday if $startofweek eq 'monday';
		while ($curdate <= $newest) {
			last if $fullweekonly and $curdate + $oneweek - $oneday > $newest;
			push(@$weeks, $curdate);
#			$::ERR->verbose(strftime("weekly:  #$weekcode: %Y-%m-%d\n", localtime($curdate)));
			$curdate += $oneweek;						# go forward 1 week
		}
	}

	my $months = [];
	if ($domonthly) {
		# curdate will always start on the 1st day of the month (@ 2am, so DST time changes will not affect values)
		my $curdate = timelocal(0,0,2, 1,(localtime($oldest))[4,5]);	# get oldest date starting on the 1st of the month
		while ($curdate <= $newest) {
			my $onemonth = $oneday * daysinmonth($curdate);
			last if $fullmonthonly and $curdate + $onemonth - $oneday > $newest;
			push(@$months, $curdate);
#			$::ERR->verbose(strftime("monthly: #$weekcode: %Y-%m-%d\n", localtime($curdate)));
			$curdate += $onemonth;						# go forward 1 month
		}
	}

	# loop through awards and calculate
	foreach my $a (@awards) {
		my $award = PS::Award->new($a, $self);
		if (!$award) {
			$::ERR->warn("Award '$a->{name}' can not be processed due to errors: $@");
			next;
		} 
		$award->calc('month', $months) if $domonthly;
		$award->calc('week', $weeks) if $doweekly;
		$award->calc('day', $days) if $dodaily;
	}

	$self->{conf}->setinfo('daily_awards.lastupdate', time);
	$::ERR->info("Daily process completed: 'awards' (Time elapsed: " . compacttime(time-$start,'mm:ss') . ")");
}

# daily process for updating player ranks. Assigns a rank to each player based on their skill
# ... this should be updated to allow for different criteria to be used to determine rank.
sub daily_ranks {
	my $self = shift;
	my $db = $self->{db};
	my $lastupdate = $self->{conf}->getinfo('daily_ranks.lastupdate') || 0;
	my $start = time;
	my ($sth, $cmd);

	$::ERR->info(sprintf("Daily 'ranks' process running (Last updated: %s)", 
		$lastupdate ? scalar localtime $lastupdate : 'never'
	));

	$cmd = "SELECT plrid,rank,skill FROM $db->{t_plr} WHERE allowrank ORDER BY skill DESC";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}

	$db->begin;

	my $newrank = 0;
	my $prevskill = -999999;
	# This will allow players with the SAME skill to receive the SAME rank. But this is slower
	while (my ($id,$rank,$skill) = $sth->fetchrow_array) {
		$cmd = "UPDATE $db->{t_plr} SET prevrank=rank, rank=" . ($prevskill == $skill ? $newrank : ++$newrank) . " WHERE plrid=$id";
		$db->query($cmd) if $rank ne $newrank;
		$prevskill = $skill;
	}
	$db->update($db->{t_plr}, { rank => 0 }, [ allowrank => 0 ]);

	# this is mysql specific and also does not allow same skill players to receive the same rank (but it's fast)
#	if ($db->type eq 'mysql') {
#		$db->query("SET \@newrank := 0");
#		$db->query("UPDATE $db->{t_plr} SET prevrank=rank, rank=IF(allowrank, \@newrank:=\@newrank+1, 0) ORDER BY skill DESC");
#	} elsif ($db->type eq 'sqlite') {
#
#	}

	$db->commit;

	$self->{conf}->setinfo('daily_ranks.lastupdate', time);

	$::ERR->info("Daily process completed: 'ranks' (Time elapsed: " . compacttime(time-$start,'mm:ss') . ")");
}

# updates the decay of all players
sub daily_decay {
	my $self = shift;
	my $conf = $self->{conf};
	my $db = $self->{db};
	my $lastupdate = $self->{conf}->getinfo('daily_decay.lastupdate') || 0;
	my $start = time;
	my $decay_hours = $conf->get_main('decay.hours');
	my $decay_type = $conf->get_main('decay.type');
	my $decay_value = $conf->get_main('decay.value');
	my ($sth, $cmd);

	if (!$decay_type) {
		$::ERR->info("Daily 'decay' process skipped, decay is disabled.");
		return;
	}

	$::ERR->info(sprintf("Daily 'decay' process running (Last updated: %s)", 
		$lastupdate ? scalar localtime $lastupdate : 'never'
	));

	# get the newest date available from the database
	my ($newest) = $db->get_list("SELECT MAX(lasttime) FROM $db->{c_plr_data}");
#	my $oldest = $newest - 60*60*$decay_hours;

	# return if no "lasttime" in c_plr_data
	if (!$newest) {
		$::ERR->info("Daily 'decay' process skipped, no data in dynamic player data table.");
		return;
	}

	$cmd = "SELECT plrid,lastdecay,skill,($newest - lastdecay) / ($decay_hours*60*60) AS length FROM $db->{t_plr} WHERE skill > " . $db->quote($self->{baseskill});
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}

	$db->begin;

	my ($newskill, $value);
	while (my ($id,$lastdecay,$skill,$length) = $sth->fetchrow_array) {
		next unless $length >= 1.0;
		$newskill = $skill;
		$value = $decay_value * $length;
		if ($decay_type eq 'flat') {
			$newskill -= $value;
		} else {	# decay eq 'percent'
			$newskill -= $newskill * $value / 100;
		}
		#$newskill = $self->{baseskill} if $newskill < $self->{baseskill};
#		print "id $id: len: $length, val: $value, old: $skill, new: $newskill\n";
		$db->update($db->{t_plr}, { lastdecay => $newest, skill => $newskill }, [ plrid => $id ]);
	}

	$db->commit;

	$self->{conf}->setinfo('daily_decay.lastupdate', time);

	$::ERR->info("Daily process completed: 'decay' (Time elapsed: " . compacttime(time-$start,'mm:ss') . ")");
}

# daily process for updating clans. Toggles clans from being displayed based on the clan config settings.
sub daily_clans {
	my $self = shift;
	my $db = $self->{db};
	my $lastupdate = $self->{conf}->getinfo('daily_clans.lastupdate');
	my $start = time;
	my $last = time;
	my $types = PS::Player->get_types;
	my ($cmd, $sth, $sth2, $rules, @min, @max, $allowed, $fields);

	$::ERR->info(sprintf("Daily 'clans' process running (Last updated: %s)", 
		$lastupdate ? scalar localtime $lastupdate : 'never'
	));

	return 0 unless $db->table_exists($db->{c_plr_data});

	# gather our min/max rules ...
	$rules = { %{$self->{conf}->get_main('ranking') || {}} };
#	delete @$rules{ qw(IDX SECTION) };
	@min = ( map { s/^clan_min_//; $_ } grep { /^clan_min_/ && $rules->{$_} ne '' } keys %$rules );
	@max = ( map { s/^clan_max_//; $_ } grep { /^clan_max_/ && $rules->{$_} ne '' } keys %$rules );

	# add extra fields to our query that match values in our min/max arrays
	# if the matching type in $types is a reference then we know it's a calculated field and should 
	# be an average instead of a summary.
	my %uniq = ( 'members' => 1 );
	$fields = join(', ', map { (ref $types->{$_} ? 'avg' : 'sum') . "($_) $_" } grep { !$uniq{$_}++ } (@min,@max));

	# load a clan list including basic stats for each
	$cmd  = "SELECT c.*, count(*) members ";
	$cmd .= ", $fields " if $fields;
	$cmd .= "FROM $db->{t_clan} c, $db->{t_plr} plr, $db->{c_plr_data} data ";
	$cmd .= "WHERE (c.clanid=plr.clanid AND plr.allowrank) AND data.plrid=plr.plrid ";
	$cmd .= "GROUP BY c.clanid ";
#	print "$cmd\n";	
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}

	$db->begin;
	my (@rank,@norank);
	while (my $row = $sth->fetchrow_hashref) {
		# does the clan meet all the requirements for ranking?
		$allowed = (
			((grep { ($row->{$_}||0) < $rules->{'clan_min_'.$_} } @min) == 0) 
			&& 
			((grep { ($row->{$_}||0) > $rules->{'clan_max_'.$_} } @max) == 0)
		) ? 1 : 0;
		if (!$allowed and $::DEBUG) {
			$self->info("Clan failed to rank \"$row->{clantag}\" => " . 
				join(', ', 
					map { "$_: " . $row->{$_} . " < " . $rules->{"clan_min_$_"} } grep { $row->{$_} < $rules->{"clan_min_$_"} } @min,
					map { "$_: " . $row->{$_} . " > " . $rules->{"clan_max_$_"} } grep { $row->{$_} > $rules->{"clan_max_$_"}} @max
				)
			);
		}

		# update the clan if their allowrank flag has changed
		if ($allowed != $row->{allowrank}) {
			# SQLite doesn't like it when i try to read/write to the database at the same time
			if ($db->type eq 'sqlite') {
				if ($allowed) {
					push(@rank, $row->{clanid});
				} else {
					push(@norank, $row->{clanid});
				}
			} else {
				$db->update($db->{t_clan}, { allowrank => $allowed }, [ clanid => $row->{clanid} ]);
			}
		}
	}

	# mass update clans if we didn't do it above
        $db->query("UPDATE $db->{t_plr} SET allowrank=1 WHERE plrid IN (" . join(',', @rank) . ")") if @rank;
        $db->query("UPDATE $db->{t_plr} SET allowrank=0 WHERE plrid IN (" . join(',', @norank) . ")") if @norank;
	$db->commit;

	$self->{conf}->setinfo('daily_clans.lastupdate', time);
	$::ERR->info("Daily process completed: 'clans' (Time elapsed: " . compacttime(time-$start,'mm:ss') . ")");
}

# daily process for updating player activity. this should be run before daily_players.
sub daily_activity {
	my $self = shift;
	my $db = $self->{db};
	my $lastupdate = $self->{conf}->getinfo('daily_activity.lastupdate');
	my $start = time;
	my $last = time;
	my ($cmd, $sth);

	return 0 unless $db->table_exists($db->{c_map_data}) and $db->table_exists($db->{c_plr_data});

	$::ERR->info(sprintf("Daily 'activity' process running (Last updated: %s)", 
		$lastupdate ? scalar localtime $lastupdate : 'never'
	));

	# the maps table is a small table and is a good target to determine the
	# most recent timestamp in the database.
	my $lasttime = $db->max($db->{c_map_data}, 'lasttime');
	my $min_act = $self->{conf}->get_main('plr_min_activity') || 5;
	$min_act *= 60*60*24;
	if ($lasttime) {
		# this query is smart enough to only update the players that have new
		# activity since the last time it was calculated.
		if ($db->type eq 'mysql') {
			$cmd = "UPDATE $db->{t_plr} p, $db->{c_plr_data} d SET " . 
				"p.lastactivity = $lasttime, " . 
				"p.activity = IF($min_act > $lasttime - d.lasttime, " . 
				"LEAST(100, 100 / $min_act * ($min_act - ($lasttime - d.lasttime)) ), 0) " . 
				"WHERE p.plrid=d.plrid AND $lasttime > p.lastactivity";
		} else {
			# need to figure something out for SQLite
			die("Unable to calculate activity for DB::" . $db->type);
		}
		my $ok = $db->query($cmd);
	}
	
	$self->{conf}->setinfo('daily_activity.lastupdate', time);

	$::ERR->info("Daily process completed: 'activity' (Time elapsed: " . compacttime(time-$start,'mm:ss') . ")");
}

# daily process for updating players. This should be run before daily_clans
# Toggles players from being displayed based on the players config settings.
sub daily_players {
	my $self = shift;
	my $db = $self->{db};
	my $lastupdate = $self->{conf}->getinfo('daily_players.lastupdate');
	my $start = time;
	my $last = time;
	my $types = PS::Player->get_types;
	my ($cmd, $sth, $sth2, $rules, @min, @max, $allowed, $fields);

	return 0 unless $db->table_exists($db->{c_plr_data});

	$::ERR->info(sprintf("Daily 'players' process running (Last updated: %s)", 
		$lastupdate ? scalar localtime $lastupdate : 'never'
	));

	# gather our min/max rules ...
	$rules = { %{$self->{conf}->get_main('ranking') || {}} };
	delete @$rules{ qw(IDX SECTION) };
	@min = ( map { s/^player_min_//; $_ } grep { /^player_min_/ && $rules->{$_} ne '' } keys %$rules );
	@max = ( map { s/^player_max_//; $_ } grep { /^player_max_/ && $rules->{$_} ne '' } keys %$rules );

        # add extra fields to our query that match values in our min/max arrays
	my %uniq = ( plrid => 1, uniqueid => 1, skill => 1 );
	$fields = join(', ', grep { !$uniq{$_}++ } (@min,@max));

	# first remove players (and their profile) that don't actually have any compiled stats	
	$cmd  = "DELETE FROM p, pp USING ($db->{t_plr} p, $db->{t_plr_profile} pp) ";
	$cmd .= "LEFT JOIN $db->{c_plr_data} c ON c.plrid=p.plrid WHERE c.plrid IS NULL AND p.uniqueid=pp.uniqueid";
	$db->query($cmd);	# don't care if it fails ...

	# load player list
	$cmd  = "SELECT plr.*, pp.name, $fields ";
	$cmd .= "FROM $db->{t_plr} plr, $db->{t_plr_profile} pp, $db->{c_plr_data} data ";
	$cmd .= "WHERE pp.uniqueid=plr.uniqueid AND data.plrid=plr.plrid ";
#	print "$cmd\n";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}

	$db->begin;
	my (@rank,@norank);
	while (my $row = $sth->fetchrow_hashref) {
		# does the plr meet all the requirements for ranking?
		$allowed = (
			((grep { ($row->{$_}||0) < $rules->{'player_min_'.$_} } @min) == 0) 
			&& 
			((grep { ($row->{$_}||0) > $rules->{'player_max_'.$_} } @max) == 0)
		) ? 1 : 0;
		if (!$allowed and $::DEBUG) {
			$self->info("Player failed to rank \"$row->{name}\" " . ($self->{uniqueid} ne 'name' ?  "($row->{uniqueid})" : "") . "=> " . 
				join(', ', 
					map { "$_: " . $row->{$_} . " < " . $rules->{"player_min_$_"} } grep { $row->{$_} < $rules->{"player_min_$_"} } @min,
					map { "$_: " . $row->{$_} . " > " . $rules->{"player_max_$_"} } grep { $row->{$_} > $rules->{"player_max_$_"}} @max
				)
			);
		}

		# update the plr if their allowrank flag has changed
		if ($allowed != $row->{allowrank}) {
			# SQLite doesn't like it when i try to read/write to the database at the same time
			if ($db->type eq 'sqlite') {
				if ($allowed) {
					push(@rank, $row->{plrid});
				} else {
					push(@norank, $row->{plrid});
				}
			} else {
				$db->update($db->{t_plr}, { allowrank => $allowed }, [ plrid => $row->{plrid} ]);
			}
		}
	}
	undef $sth;
	$db->query("UPDATE $db->{t_plr} SET allowrank=1 WHERE plrid IN (" . join(',', @rank) . ")") if @rank;
	$db->query("UPDATE $db->{t_plr} SET allowrank=0 WHERE plrid IN (" . join(',', @norank) . ")") if @norank;
	$db->commit;

	$self->{conf}->setinfo('daily_players.lastupdate', time);

	$::ERR->info("Daily process completed: 'players' (Time elapsed: " . compacttime(time-$start,'mm:ss') . ")");
}

sub _delete_stale_players {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my @delete;

	return 0 unless $db->table_exists($db->{c_plr_data});

	$db->begin;

	# keep track of what stats are being deleted 
	$db->do("INSERT INTO plrids SELECT DISTINCT plrid FROM $db->{t_plr_data} WHERE statdate <= $sql_oldest");
	my $total = $db->count('plrids');

	# delete basic data
	$db->do("INSERT INTO deleteids SELECT dataid FROM $db->{t_plr_data} WHERE statdate <= $sql_oldest");
	$db->do("DELETE FROM $db->{t_plr_data_mod} WHERE dataid IN (SELECT id FROM deleteids)") if $db->{t_plr_data_mod};
	$db->do("DELETE FROM $db->{t_plr_data} WHERE dataid IN (SELECT id FROM deleteids)");
	$db->truncate('deleteids');

	# delete player maps
	$db->do("INSERT INTO deleteids SELECT dataid FROM $db->{t_plr_maps} WHERE statdate <= $sql_oldest");
	$db->do("DELETE FROM $db->{t_plr_maps_mod} WHERE dataid IN (SELECT id FROM deleteids)") if $db->{t_plr_maps_mod};
	$db->do("DELETE FROM $db->{t_plr_maps} WHERE dataid IN (SELECT id FROM deleteids)");
	$db->truncate('deleteids');

	# delete player roles
	if ($self->has_roles) {
		$db->do("INSERT INTO deleteids SELECT dataid FROM $db->{t_plr_roles} WHERE statdate <= $sql_oldest");
		$db->do("DELETE FROM $db->{t_plr_roles_mod} WHERE dataid IN (SELECT id FROM deleteids)") if $db->{t_plr_roles_mod};
		$db->do("DELETE FROM $db->{t_plr_roles} WHERE dataid IN (SELECT id FROM deleteids)");
		$db->truncate('deleteids');
	}
	
	# delete remaining historical stats (no 'mod' tables for these)
	$db->do("DELETE FROM $db->{t_plr_victims} WHERE statdate <= $sql_oldest");
	$db->do("DELETE FROM $db->{t_plr_weapons} WHERE statdate <= $sql_oldest");

	# sessions are stored slightly differently
	$db->do("DELETE FROM $db->{t_plr_sessions} WHERE FROM_UNIXTIME(sessionstart,'%Y-%m-%d') <= $sql_oldest");

	# only delete the compiled data if maxdays_exclusive is enabled
	if ($self->{maxdays_exclusive}) {
		# Any player in deleteids hasn't played since the oldest date allowed, so get rid of them completely
		$db->do("INSERT INTO deleteids SELECT plrid FROM $db->{c_plr_data} WHERE lastdate <= $sql_oldest");
		$db->do("DELETE FROM $db->{t_plr} WHERE plrid IN (SELECT id FROM deleteids)");
		$db->do("DELETE FROM $db->{t_plr_ids_name} WHERE plrid IN (SELECT id FROM deleteids)");
		$db->do("DELETE FROM $db->{t_plr_ids_ipaddr} WHERE plrid IN (SELECT id FROM deleteids)");
		$db->do("DELETE FROM $db->{t_plr_ids_worldid} WHERE plrid IN (SELECT id FROM deleteids)");
		$db->truncate('deleteids');

		# delete the compiled data. 
		$db->do("DELETE FROM $db->{c_plr_data} WHERE lastdate <= $sql_oldest");
		$db->do("DELETE FROM $db->{c_plr_maps} WHERE lastdate <= $sql_oldest");
		$db->do("DELETE FROM $db->{c_plr_roles} WHERE lastdate <= $sql_oldest");
		$db->do("DELETE FROM $db->{c_plr_victims} WHERE lastdate <= $sql_oldest");
		$db->do("DELETE FROM $db->{c_plr_weapons} WHERE lastdate <= $sql_oldest");
	}

	$db->commit;

	return $total;
}

sub _delete_stale_maps {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my @delete;

	return 0 unless $db->table_exists($db->{c_map_data});

	$db->begin;

	# keep track of what stats are being deleted 
	$db->do("INSERT INTO mapids SELECT DISTINCT mapid FROM $db->{t_map_data} WHERE statdate <= $sql_oldest");
	my $total = $db->count('mapids');
	
	# delete basic data
	$db->do("INSERT INTO deleteids SELECT dataid FROM $db->{t_map_data} WHERE statdate <= $sql_oldest");
	$db->do("DELETE FROM $db->{t_map_data_mod} WHERE dataid IN (SELECT id FROM deleteids)") if $db->{t_map_data_mod};
	$db->do("DELETE FROM $db->{t_map_data} WHERE dataid IN (SELECT id FROM deleteids)");
	$db->truncate('deleteids');

	# only delete the compiled data if maxdays_exclusive is enabled
	if ($self->{maxdays_exclusive}) {
		$db->do("DELETE FROM $db->{c_map_data} WHERE lastdate <= $sql_oldest");
	}

	$db->commit;

	return $total;
}

sub _delete_stale_roles {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my @delete;

	return 0 unless $db->table_exists($db->{c_role_data});

	$db->begin;

	# keep track of what stats are being deleted 
	$db->do("INSERT INTO roleids SELECT DISTINCT roleid FROM $db->{t_role_data} WHERE statdate <= $sql_oldest");
	my $total = $db->count('roleids');
	
	# delete basic data
	$db->do("INSERT INTO deleteids SELECT dataid FROM $db->{t_role_data} WHERE statdate <= $sql_oldest");
	$db->do("DELETE FROM $db->{t_role_data_mod} WHERE dataid IN (SELECT id FROM deleteids)") if $db->{t_role_data_mod};
	$db->do("DELETE FROM $db->{t_role_data} WHERE dataid IN (SELECT id FROM deleteids)");
	$db->truncate('deleteids');

	# only delete the compiled data if maxdays_exclusive is enabled
	if ($self->{maxdays_exclusive}) {
		$db->do("DELETE FROM $db->{c_role_data} WHERE lastdate <= $sql_oldest");
	}

	$db->commit;

	return $total;
}

sub _delete_stale_weapons {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my @delete;

	return 0 unless $db->table_exists($db->{c_weapon_data});

	$db->begin;

	# keep track of what stats are being deleted 
	$db->do("INSERT INTO weaponids SELECT DISTINCT weaponid FROM $db->{t_weapon_data} WHERE statdate <= $sql_oldest");
	my $total = $db->count('weaponids');

	# delete basic data
	$db->do("DELETE FROM $db->{t_weapon_data} WHERE statdate <= $sql_oldest");

	# only delete the compiled data if maxdays_exclusive is enabled
	if ($self->{maxdays_exclusive}) {
		$db->do("DELETE FROM $db->{c_weapon_data} WHERE lastdate <= $sql_oldest");
	}

	$db->commit;

	return $total;
}

sub _delete_stale_hourly {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my @delete;

	return 0 unless $db->table_exists($db->{t_map_hourly});

	$db->begin;

	# keep track of what stats are being deleted 
	my $total = $db->count($db->{t_map_hourly}, "statdate <= $sql_oldest");

	# delete basic data
	$db->do("DELETE FROM $db->{t_map_hourly} WHERE statdate <= $sql_oldest");

	$db->commit;

	return $total;
}

sub _delete_stale_spatial {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my @delete;

	return 0 unless $db->table_exists($db->{t_map_spatial});

	$db->begin;

	# keep track of what stats are being deleted 
	my $total = $db->count($db->{t_map_spatial}, "statdate <= $sql_oldest");

	# delete basic data
	$db->do("DELETE FROM $db->{t_map_spatial} WHERE statdate <= $sql_oldest");

	$db->commit;

	return $total;
}

sub _update_player_stats {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->{maxdays_exclusive} and $db->table_exists($db->{c_plr_data});

	$o = PS::Player->new(undef, $self);
	$types = $o->get_types;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	$cmd  = "SELECT plrid, MIN(statdate) firstdate, MAX(statdate) lastdate, $fields FROM $db->{t_plr_data} data ";
	$cmd .=	"LEFT JOIN $db->{t_plr_data_mod} USING (dataid) " if $db->{t_plr_data_mod};
	$cmd .= "WHERE plrid IN (SELECT id FROM plrids) ";
	$cmd .= "GROUP BY plrid ";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}

	while (my $row = $sth->fetchrow_hashref) {
		$total++;
		map { !$row->{$_} ? delete $row->{$_} : 0 } keys %$row;		# remove undef/zero
		$db->delete($db->{c_plr_data}, [ 'plrid' => $row->{plrid} ]);
		$db->save_stats($db->{c_plr_data}, $row, $types);
		last if $::GRACEFUL_EXIT > 0;
	}

	$db->commit;

	# remove and update clans now that players were updated
	

	return $total;
}

sub _update_player_weapons {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->{maxdays_exclusive} and $db->table_exists($db->{c_plr_weapons});

	$o = PS::Player->new(undef, $self);
	$types = $o->get_types_weapons;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	$cmd  = "SELECT plrid,weaponid,MIN(statdate) firstdate, MAX(statdate) lastdate,$fields FROM $db->{t_plr_weapons} ";
	$cmd .= "WHERE plrid IN (SELECT id FROM plrids) ";
	$cmd .= "GROUP BY plrid,weaponid ";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}

	while (my $row = $sth->fetchrow_hashref) {
		$total++;
		map { !$row->{$_} ? delete $row->{$_} : 0 } keys %$row;		# remove undef/zero
		$db->delete($db->{c_plr_weapons}, [ 'plrid' => $row->{plrid}, 'weaponid' => $row->{weaponid} ]);
		$db->save_stats($db->{c_plr_weapons}, $row, $types);
		last if $::GRACEFUL_EXIT > 0;
	}

	$db->commit;

	return $total;
}

sub _update_player_roles {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->{maxdays_exclusive} and $db->table_exists($db->{c_plr_roles});

	$o = PS::Player->new(undef, $self);
	$types = $o->get_types_roles;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	$cmd  = "SELECT plrid,roleid,MIN(statdate) firstdate, MAX(statdate) lastdate,$fields FROM $db->{t_plr_roles} ";
	$cmd .=	"LEFT JOIN $db->{t_plr_roles_mod} USING (dataid) " if $db->{t_plr_roles_mod};
	$cmd .= "WHERE plrid IN (SELECT id FROM plrids) ";
	$cmd .= "GROUP BY plrid,roleid ";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}

	while (my $row = $sth->fetchrow_hashref) {
		$total++;
		map { !$row->{$_} ? delete $row->{$_} : 0 } keys %$row;		# remove undef/zero
		$db->delete($db->{c_plr_roles}, [ 'plrid' => $row->{plrid}, 'roleid' => $row->{roleid} ]);
		$db->save_stats($db->{c_plr_roles}, $row, $types);
		last if $::GRACEFUL_EXIT > 0;
	}

	$db->commit;

	return $total;
}

sub _update_player_victims {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->{maxdays_exclusive} and $db->table_exists($db->{c_plr_victims});

	$o = PS::Player->new(undef, $self);
	$types = $o->get_types_victims;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	$cmd  = "SELECT plrid,victimid,MIN(statdate) firstdate, MAX(statdate) lastdate,$fields FROM $db->{t_plr_victims} ";
	$cmd .= "WHERE plrid IN (SELECT id FROM plrids) ";
	$cmd .= "GROUP BY plrid,victimid ";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}
	while (my $row = $sth->fetchrow_hashref) {
		$total++;
		map { !$row->{$_} ? delete $row->{$_} : 0 } keys %$row;		# remove undef/zero
		$db->delete($db->{c_plr_victims}, [ 'plrid' => $row->{plrid}, 'victimid' => $row->{victimid} ]);
		$db->save_stats($db->{c_plr_victims}, $row, $types);
		last if $::GRACEFUL_EXIT > 0;
	}

	$db->commit;

	return $total;
}

sub _update_player_maps {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->{maxdays_exclusive} and $db->table_exists($db->{c_plr_maps});

	$o = PS::Player->new(undef, $self);
	$types = $o->get_types_maps;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	$cmd  = "SELECT plrid,mapid,MIN(statdate) firstdate, MAX(statdate) lastdate,$fields FROM $db->{t_plr_maps} ";
	$cmd .= "LEFT JOIN $db->{t_plr_maps_mod} USING (dataid) " if $db->{t_plr_maps_mod};
	$cmd .= "WHERE plrid IN (SELECT id FROM plrids) ";
	$cmd .= "GROUP BY plrid,mapid ";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}
	while (my $row = $sth->fetchrow_hashref) {
		$total++;
		map { !$row->{$_} ? delete $row->{$_} : 0 } keys %$row;		# remove undef/zero
		$db->delete($db->{c_plr_maps}, [ 'plrid' => $row->{plrid}, 'mapid' => $row->{mapid} ]);
		$db->save_stats($db->{c_plr_maps}, $row, $types);
		last if $::GRACEFUL_EXIT > 0;
	}

	$db->commit;

	return $total;
}

sub _update_map_stats {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->{maxdays_exclusive} and $db->table_exists($db->{c_map_data});

	$o = PS::Map->new(undef, $conf, $db);
	$types = $o->get_types;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	$cmd  = "SELECT mapid, MIN(statdate) firstdate, MAX(statdate) lastdate, $fields FROM $db->{t_map_data} data ";
	$cmd .=	"LEFT JOIN $db->{t_map_data_mod} USING (dataid) " if $db->{t_map_data_mod};
	$cmd .= "WHERE mapid IN (SELECT id FROM mapids) ";
	$cmd .= "GROUP BY mapid ";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}
	while (my $row = $sth->fetchrow_hashref) {
		$total++;
		map { !$row->{$_} ? delete $row->{$_} : 0 } keys %$row;		# remove undef/zero
		$db->delete($db->{c_map_data}, [ 'mapid' => $row->{mapid} ]);
		$db->save_stats($db->{c_map_data}, $row, $types);
		last if $::GRACEFUL_EXIT > 0;
	}

	$db->commit;

	return $total;
}

sub _update_weapon_stats {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->{maxdays_exclusive} and $db->table_exists($db->{c_weapon_data});

	$o = PS::Weapon->new(undef, $conf, $db);
	$types = $o->get_types;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	$cmd  = "SELECT weaponid, MIN(statdate) firstdate, MAX(statdate) lastdate, $fields FROM $db->{t_weapon_data} data ";
	$cmd .= "WHERE weaponid IN (SELECT id FROM weaponids) ";
	$cmd .= "GROUP BY weaponid ";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}
	while (my $row = $sth->fetchrow_hashref) {
		$total++;
		map { !$row->{$_} ? delete $row->{$_} : 0 } keys %$row;		# remove undef/zero
		$db->delete($db->{c_weapon_data}, [ 'weaponid' => $row->{weaponid} ]);
		$db->save_stats($db->{c_weapon_data}, $row, $types);
		last if $::GRACEFUL_EXIT > 0;
	}

	$db->commit;

	return $total;
}

sub _update_role_stats {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->{maxdays_exclusive} and $db->table_exists($db->{c_role_data});

	$o = PS::Role->new(undef, '', $conf, $db);
	$types = $o->get_types;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	# if exclusive update stats to remove old data
	$cmd  = "SELECT roleid, MIN(statdate) firstdate, MAX(statdate) lastdate, $fields FROM $db->{t_role_data} data ";
	$cmd .=	"LEFT JOIN $db->{t_role_data_mod} USING (dataid) " if $db->{t_role_data_mod};
	$cmd .= "WHERE roleid IN (SELECT id FROM roleids) ";
	$cmd .= "GROUP BY roleid ";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}
	while (my $row = $sth->fetchrow_hashref) {
		$total++;
		map { !$row->{$_} ? delete $row->{$_} : 0 } keys %$row;		# remove undef/zero
		$db->delete($db->{c_role_data}, [ 'roleid' => $row->{roleid} ]);
		$db->save_stats($db->{c_role_data}, $row, $types);
		last if $::GRACEFUL_EXIT > 0;
	}

	$db->commit;

	return $total;
}

# daily process for maxdays histories (this must be the longest and most complicated function in all of PS3)
sub daily_maxdays {
	my $self = shift;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $lastupdate = $self->{conf}->getinfo('daily_maxdays.lastupdate');
	my $start = time;
	my $last = time;
	my ($cmd, $sth, $ok, $fields, @delete, @ids, $o, $types, $total, $alltotal,%t);

	$::ERR->info(sprintf("Daily 'maxdays' process running (Last updated: %s)", 
		$lastupdate ? scalar localtime $lastupdate : 'never'
	));

	# determine the oldest date to delete
	my $oldest = strftime("%Y-%m-%d", localtime(time-60*60*24*($self->{maxdays}+1)));
	# I think it'll be better to use the newest date in the database instead
	# of the current time to determine where to trim stats. This way the
	# database won't lose stats if it stops getting new logs for a period of
	# time.
	#my ($oldest) = $db->get_list("SELECT MAX(statdate) - INTERVAL $self->{maxdays} DAY FROM $db->{t_plr_data}");
	goto MAXDAYS_DONE unless $oldest;	# will be null if there's no historical data available
	my $sql_oldest = $db->quote($oldest);

	$::ERR->verbose("Deleting stale stats older than $oldest ...");

	# delete the temporary tables if they exist
	$db->droptable($_) for (qw( deleteids plrids mapids roleids weaponids ));

	# first create temporary tables to store ids (dont want to use potentially huge arrays in memory)
	$ok = 1;
	$ok = ($ok and $db->do("CREATE TEMPORARY TABLE deleteids (id INT UNSIGNED PRIMARY KEY)"));
	$ok = ($ok and $db->do("CREATE TEMPORARY TABLE plrids (id INT UNSIGNED PRIMARY KEY)"));
	$ok = ($ok and $db->do("CREATE TEMPORARY TABLE mapids (id INT UNSIGNED PRIMARY KEY)"));
	$ok = ($ok and $db->do("CREATE TEMPORARY TABLE roleids (id INT UNSIGNED PRIMARY KEY)"));
	$ok = ($ok and $db->do("CREATE TEMPORARY TABLE weaponids (id INT UNSIGNED PRIMARY KEY)"));
	# temporary tables could not be created
	if (!$ok) {
		$::ERR->fatal("Error creating temporary tables for maxdays process: " . $db->errstr);
	}

	$t{plrs} = $total = $self->_delete_stale_players($oldest);
	$::ERR->info(sprintf("%s stale players deleted!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$t{maps} = $total = $self->_delete_stale_maps($oldest);
	$::ERR->info(sprintf("%s stale maps deleted!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$t{weapons} = $total = $self->_delete_stale_weapons($oldest);
	$::ERR->info(sprintf("%s stale weapons deleted!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$t{roles} = $total = $self->_delete_stale_roles($oldest);
	$::ERR->info(sprintf("%s stale roles deleted!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$t{hourly} = $total = $self->_delete_stale_hourly($oldest);
	$::ERR->info(sprintf("%s stale hourly stats deleted!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$t{hourly} = $total = $self->_delete_stale_spatial($oldest);
	$::ERR->info(sprintf("%s stale spatial stats deleted!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$::ERR->verbose("Recalculating compiled stats ...");
	$::ERR->verbose("This may take several minutes ... ");
	$total = 0;

	$total = $self->_update_map_stats($oldest) if $t{maps};
	$::ERR->info(sprintf("%s maps updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = $self->_update_role_stats($oldest) if $t{roles};
	$::ERR->info(sprintf("%s roles updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = $self->_update_weapon_stats($oldest) if $t{weapons};
	$::ERR->info(sprintf("%s weapons updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = $self->_update_player_stats($oldest) if $t{plrs};
	$::ERR->info(sprintf("%s players updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = 0;
	$total = $self->_update_player_maps($oldest) if $t{plrs};
	$::ERR->info(sprintf("%s player maps updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = $self->_update_player_roles($oldest) if $t{plrs};
	$::ERR->info(sprintf("%s player roles updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = $self->_update_player_victims($oldest) if $t{plrs};
	$::ERR->info(sprintf("%s player victims updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = $self->_update_player_weapons($oldest) if $t{plrs};
	$::ERR->info(sprintf("%s player weapons updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	# if NOTHING was updated then dont bother optimizing tables
	if ($alltotal) {
		$::ERR->info("Optimizing database tables ...");
		# optimize all the tables, since we probably just deleted a lot of data

		$db->optimize(map { $db->{$_} } grep { /^[ct]\_/ } keys %$db);	# do them ALL! muahahahah
	}

MAXDAYS_DONE:
	$db->droptable($_) for (qw( deleteids plrids mapids roleids weaponids ));

	$self->{conf}->setinfo('daily_maxdays.lastupdate', time);
	$::ERR->info("Daily process completed: 'maxdays' (Time elapsed: " . compacttime(time-$start,'mm:ss') . ")");
}

# row is updated directly
# no longer used. ..
sub _prepare_row {
	my ($self, $row, $types) = @_;
	foreach my $key (keys %$types) {
		next if ref $types->{$key};
		if ($types->{$key} eq '=') {			# do not update static fields
			delete $row->{$key};
		} elsif (!defined $row->{$key}) {		# delete keys that do not exist already
			delete $row->{$key};
		} elsif ($types->{$key} eq '+') {		# negate the value so it updates properly
			if ($row->{$key} eq '0') {
				delete $row->{$key};		# no sense in updating fields that are zero
			} else {
				$row->{$key} = -$row->{$key} if defined $row->{$key};
			}
		} else {
			# any value with ">" or "<" will update normally like a calculated field
		}
	}
}

# rescans for player -> clan relationships and rebuilds the clan database
sub rescan_clans {
	my $self = shift;
	my $db = $self->{db};
	my $total = $db->count($db->{t_plr}, [ allowrank => 1, clanid => 0 ]);
	$::ERR->info("$total ranked players will be scanned.");

	my $clanid;
	my $cur = 0;
	my $clans = {};
	my $members = 0;
	my $time = time - 1;
	my $sth = $db->query(
		"SELECT p.plrid,pp.uniqueid,pp.name " .
		"FROM $db->{t_plr} p, $db->{t_plr_profile} pp " .
		"WHERE p.uniqueid=pp.uniqueid and p.allowrank=1 and p.clanid=0"
	);
	while (my ($plrid,$uniqueid,$name) = $sth->fetchrow_array) {
		local $| = 1;	# do not buffer STDOUT
		$cur++;
		if ($time != time or $cur == $total) { # only update every second
			$time = time;
			$::ERR->verbose(sprintf("Scanning player %d / %d [%6.2f%%]\r", $cur, $total, $cur / $total * 100), 1);
		}
		$clanid = $self->scan_for_clantag($name) || next;
		$clans->{$clanid}++;
		$members++;
		$db->update($db->{t_plr}, { clanid => $clanid }, [ plrid => $plrid ]);
	}
	$::ERR->verbose("");
	$::ERR->info(sprintf("%d clans with %d members found.", scalar keys %$clans, $members));

	return ($clans, $members);
}

# delete's all clans and removes player relationships to them.
sub delete_clans {
	my $self = shift;
	my $profile_too = shift;
	my $db = $self->{db};
	$db->query("UPDATE $db->{t_plr} SET clanid=0 WHERE clanid <> 0");
	$db->truncate($db->{t_clan});
	$db->truncate($db->{t_clan_profile}) if $profile_too;
}

# resets all stats in the database. USE WITH CAUTION!
# reset(1) resets stats and all profiles
# reset(0 or undef) resets stats and NO profiles
# reset(player => 1, clans => 0, weapons => 0, heatmaps => 0) resets stats and only the profiles specified
sub reset {
	my $self = shift;
	my $del = @_ == 1 ? { players => $_[0], clans => $_[0], weapons => $_[0], heatmaps => $_[0] } : { @_ };
	my $db = $self->{db};
	my $gametype = $self->{conf}->get_main('gametype');
	my $modtype  = $self->{conf}->get_main('modtype');
	my $errors = 0;

	my @empty_c = qw( c_map_data c_plr_data c_plr_maps c_plr_victims c_plr_weapons c_weapon_data c_role_data c_plr_roles );
	my @empty_m = qw( t_map_data_mod t_role_data_mod t_plr_data_mod t_plr_maps_mod t_plr_roles_mod );
	my @empty = qw(
		t_awards t_awards_plrs
		t_clan
		t_errlog
		t_map t_map_data t_map_hourly t_map_spatial
		t_plr t_plr_data t_plr_ids_ipaddr t_plr_ids_name t_plr_ids_worldid 
		t_plr_maps t_plr_roles t_plr_sessions t_plr_victims t_plr_weapons
		t_role t_role_data
		t_search_results t_state 
		t_weapon_data
	);

	# only reset these tables if explicitly told to
	push(@empty, 't_plr_profile') if $del->{players};
	push(@empty, 't_clan_profile') if $del->{clans};
	push(@empty, 't_weapon') if $del->{weapons};
	push(@empty, 't_heatmaps') if $del->{heatmaps};

	# DROP compiled data (will be recreated the next time stats.pl is run)
	foreach my $t (@empty_c) {
		my $tbl = $db->{$t} || next;
		if (!$db->droptable($tbl) and $db->errstr !~ /unknown table/i) {
			$self->warn("Reset error on $tbl: " . $db->errstr);
			$errors++;
		}
	}

	# delete most of everything else
	foreach my $t (@empty) {
		my $tbl = $db->{$t} || next;
		if (!$db->truncate($tbl) and $db->errstr !~ /exist/) {
			$self->warn("Reset error on $tbl: " . $db->errstr);
			$errors++;
		}
	}

	# delete mod specific tables
	foreach my $t (@empty_m) {
		my $tbl = $db->{$t} || next;
		if (!$db->truncate($tbl) and $db->errstr !~ /exist/) {
			$self->warn("Reset error on $tbl: " . $db->errstr);
			$errors++;
		}
	}

	$self->info("Player stats have been reset!! (from command line)");

	return ($errors == 0);
}

# save all in-memory stats to the database for this game. Does not remove
# objects from memory. This is used when you want to save stats for players
# w/o having to wait for a player to disconnect, or a map to change, etc...
sub save {
	my ($self, $end) = @_;
	my $timestamp = $self->{timestamp};
	$self->{db}->begin;

	# SAVE PLAYERS
	my $m = $end ? $self->get_map : undef;
	foreach my $p ($self->get_plr_list) {
		if ($end) {
			# do not count streaks across map/log changes
			$p->end_all_streaks;
			$p->disconnect($timestamp, $m);
		}
		$p->save;
	}
	$self->initcache if $end;

	# SAVE WEAPONS
	while (my ($wid,$w) = each %{$self->{weapons}}) {
		$w->save;
	}
	$self->{weapons} = {} if $end;

	# SAVE ROLES
	while (my ($rid,$r) = each %{$self->{roles}}) {
		$r->save;
	}
	$self->{roles} = {} if $end;

	# SAVE MAPS
	while (my ($mid,$m) = each %{$self->{maps}}) {
		my $time = $m->timer;
		$time ||= $timestamp - $m->{basic}{lasttime} if $m->{basic}{lasttime} and $timestamp - $m->{basic}{lasttime} > 0;
		if ($m->{basic}{lasttime}) {
			$m->{basic}{onlinetime} += $time;
			$m->save;
		}
	}
	$self->{maps} = {} if $end;

	$self->{db}->commit;
}

# takes an array of log filenames and returns a sorted result
sub logsort {}

# return -1,0,1 (<=>), depending on outcome of comparison of 2 log files
sub logcompare { $_[1] cmp $_[2] }

sub has_mod_tables { 0 }
sub has_roles { 0 }

sub event_ignore { }

1;

