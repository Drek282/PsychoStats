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
#	$Id: Player.pm 554 2008-09-03 12:52:56Z lifo $
#
#       Base Player class. This is a basic factory class that creates a Player
#       object based on the current gametype. If a subclass is detected for the
#       current gametype it will be created and returned. 
#       Order of class detection:
#
#	PS::Player::{gametype}::{modtype}
#	PS::Player::{gametype}
#	PS::Player
#
#       The first time a player object is created it's baseclass is saved so all
#       other player objects will be created in the same way w/o trying to
#       search for subclasses (small performance gain). This means you can not
#	load two different game classes in the same program.
#
package PS::Player;

use strict;
use warnings;
use base qw( PS::Debug );
use Data::Dumper;
use Scalar::Util qw( looks_like_number );
use POSIX;
use util qw( :date print_r );

our $VERSION = '1.15.' . (('$Rev: 554 $' =~ /(\d+)/)[0] || '000');
our $BASECLASS = undef;

our $GAMETYPE = '';
our $MODTYPE = '';

# static object variables for all objects created for configuration values
our (
	$INITIALIZED,
	$PLR_PRIMARY_NAME, $PLR_PNAME_COL, $PLR_DEFAULT_NAME, $UNIQUEID, $MAXDAYS, $PLR_SESSIONS_MAX, $PLR_SAVE_VICTIMS,
	$BASESKILL, $DECAY_TYPE, $DECAY_HOURS, $DECAY_VALUE, $DECAY
);

# variable types that can be stored in the table.
# Any variable not defined here is ignored when saving to the DB
our $TYPES = {
	dataid		=> '=', 
	plrid		=> '=',
	dayskill	=> '=',
	dayrank		=> '=',
	statdate	=> '=',
	onlinetime	=> '+',
	kills		=> '+',
	deaths		=> '+', 
	killsperdeath	=> [ ratio => qw( kills deaths ) ],
	killsperminute	=> [ ratio_minutes => qw( kills onlinetime ) ],
	headshotkills	=> '+',
	headshotkillspct=> [ percent => qw( headshotkills kills ) ],
#	headshotdeaths	=> '+',
	ffkills		=> '+',
	ffkillspct	=> [ percent => qw( ffkills kills ) ],
	ffdeaths	=> '+',
	ffdeathspct	=> [ percent => qw( ffdeaths deaths ) ],
	kills_streak	=> '>',
	deaths_streak	=> '>',
	damage		=> '+',
	shots		=> '+',
	hits		=> '+',
	shotsperkill	=> [ ratio => qw( shots kills ) ],
	accuracy	=> [ percent => qw( hits shots ) ],
	suicides	=> '+', 
	games		=> '+',
	rounds		=> '+',
	kicked		=> '+',
	banned		=> '+',
	cheated		=> '+',
	connections	=> '+',
	totalbonus	=> '+',
	lasttime	=> '>',
};

our $TYPES_PLRSESSIONS = {
	plrid		=> '=',
	mapid		=> '=',
	sessionstart	=> '=',
	sessionend	=> '=',
	skill		=> '=',
	prevskill	=> '=',
	kills		=> '+',
	deaths		=> '+', 
	headshotkills	=> '+',
	ffkills		=> '+',
	ffdeaths	=> '+',
	damage		=> '+',
	shots		=> '+',
	hits		=> '+',
	suicides	=> '+', 
	totalbonus	=> '+',
};

our $TYPES_WEAPONS = {
	dataid		=> '=',
	plrid		=> '=',
	weaponid	=> '=',
	statdate	=> '=',
	kills		=> '+',
	deaths		=> '+',
	headshotkills	=> '+',
	headshotkillspct=> [ percent => qw( headshotkills kills ) ],
#	headshotdeaths	=> '+',
	damage		=> '+',
	shots		=> '+',
	hits		=> '+',
	shot_head	=> '+',
	shot_chest	=> '+',
	shot_stomach	=> '+',
	shot_leftarm	=> '+',
	shot_rightarm	=> '+',
	shot_leftarm	=> '+',
	shot_rightleg	=> '+',
	shot_leftleg	=> '+',
	shotsperkill	=> [ ratio => qw( shots kills ) ],
	accuracy	=> [ percent => qw( hits shots ) ],
};

our $TYPES_MAPS = {
	dataid		=> '=',
	plrid		=> '=',
	mapid		=> '=',
	statdate	=> '=',
	games		=> '+',
	rounds		=> '+',
	kills		=> '+',
	deaths		=> '+', 
	killsperdeath	=> [ ratio => qw( kills deaths ) ],
	killsperminute	=> [ ratio_minutes => qw( kills onlinetime ) ],
	ffkills		=> '+',
	ffkillspct	=> [ percent => qw( ffkills kills ) ],
	ffdeaths	=> '+',
	ffdeathspct	=> [ percent => qw( ffdeaths deaths ) ],
	connections	=> '+',
	onlinetime	=> '+',
	lasttime	=> '>',
};

our $TYPES_VICTIMS = {
	dataid		=> '=',
	plrid		=> '=',
	victimid	=> '=',
	statdate	=> '=',
	kills		=> '+',
	deaths		=> '+', 
	killsperdeath	=> [ ratio => qw( kills deaths ) ],
	headshotkills	=> '+',
	headshotkillspct=> [ percent => qw( headshotkills kills ) ],
#	headshotdeaths	=> '+',
};

our $TYPES_ROLES = { 
	dataid		=> '=',
	plrid		=> '=',
	roleid		=> '=',
	statdate	=> '=',
	kills		=> '+',
	deaths		=> '+',
	ffkills		=> '+',
	ffkillspct	=> [ percent => qw( ffkills kills ) ],
	headshotkills	=> '+',
	headshotkillspct=> [ percent => qw( headshotkills kills ) ],
	damage		=> '+',
	hits		=> '+',
	shots		=> '+',
	accuracy	=> [ percent => qw( hits shots ) ],
	shotsperkill	=> [ ratio => qw( shots kills ) ],
	joined		=> '+',
};

our $_config_cache = {};

sub new {
	my ($proto, $plrids, $game) = @_;
	my $baseclass = ref($proto) || $proto;
	$plrids ||= {};
	my $self = {
		_plrids		=> {},
		skip_init	=> 0,
		plrid 		=> 0, 
		worldid 	=> $plrids->{worldid}, 
		uniqueid	=> $plrids->{worldid},
		name 		=> $plrids->{name}, 
		origname	=> $plrids->{name},
		ipaddr 		=> $plrids->{ipaddr}, 
		uid 		=> $plrids->{uid},
		firstseen	=> $game->{timestamp},
		game 		=> $game, 
		conf 		=> $game->{conf}, 
		db 		=> $game->{db},
		debug 		=> 0, 
		saved 		=> 0,			# has the plr been saved since marked active?
		active 		=> 0			# is the plr active?
	};
	my $class = _determine_class($self, $baseclass);

	$self->{class} = $class;
	bless($self, $class);

	if (!$BASECLASS) {
		$self->_init_table;
		$self->_init_table_maps;
		$self->_init_table_roles;
		$self->_init_table_victims;
		$self->_init_table_weapons;
		$BASECLASS = $class;
	}

	return $self->_init;
}

# Not a class method; private use only.
sub _determine_class {
	my $self = shift;
	my $baseclass = shift;
	my $class = '';

	# determine what kind of player we are going to be using the first time we're created
	if (!$BASECLASS) {
		$GAMETYPE = $self->{conf}->get('gametype');
		$MODTYPE = $self->{conf}->get('modtype');

		my @ary = $MODTYPE ? ($MODTYPE, $GAMETYPE) : ($GAMETYPE);
		while (@ary) {
			$class = join("::", $baseclass, reverse(@ary));
			eval "require $class";
			if ($@) {
				if ($@ !~ /^Can't locate/i) {
					$::ERR->warn("Compile error in class $class:\n$@\n");
					return undef;
				} 
				undef $class;
				shift @ary;
			} else {
				last;
			}
		}

		# STILL nothing? -- We give up, nothing more to try (using PS::Player directly will do us no good) ...
#		$::ERR->fatal("No suitable Player class found. HALTING") if !$class;
		$class = $baseclass if !$class;
	} else {
		$class = $BASECLASS;
	}
	return $class;
}

sub _init { 
	my $self = shift;
	my $db = $self->{db};

	# load the configuration values once for all player objects created.
	if (!$INITIALIZED) {
		$INITIALIZED 		= 1;
		$PLR_PRIMARY_NAME 	= $self->{conf}->get_main('plr_primary_name');
		$PLR_DEFAULT_NAME 	= $self->{conf}->get_main('plr_default_name');
		$UNIQUEID 		= $self->{conf}->get_main('uniqueid');
		$MAXDAYS 		= $self->{conf}->get_main('maxdays');
		$PLR_SESSIONS_MAX 	= $self->{conf}->get_main('plr_sessions_max');
		$PLR_SAVE_VICTIMS 	= $self->{conf}->get_main('plr_save_victims');
		$BASESKILL 		= $self->{conf}->get_main('baseskill');
		$DECAY_TYPE 		= $self->{conf}->get_main('decay.type');
		$DECAY_HOURS 		= $self->{conf}->get_main('decay.hours');
		$DECAY_VALUE 		= $self->{conf}->get_main('decay.value');
		$DECAY       		= $DECAY_TYPE ? 1 : 0;
	}

	$self->{team} = '';
	$self->{isdead} = 0;
	$self->{streaks} = {};
	$self->{pq} = 0;		# player IQ (strength) used in skill calculations

	# don't do any queries or work if we don't have a uniqueid (PS::Game::daily_maxdays)
	return $self unless $self->{uniqueid};

	$self->{basic} = {};
	$self->{weapons} = {};
	$self->{victims} = {};
	$self->{roles} = {};
	$self->{maps} = {};
	$self->{mod} = {};

	$self->uniqueid($self->{$UNIQUEID});

	$self->{plrid} = $db->select($db->{t_plr}, 'plrid', [ uniqueid => $self->uniqueid ]);
	# player does not exist so we create a new record for them
	if (!$self->{plrid}) {
		$db->begin;
		$self->{plrid} = $db->next_id($db->{t_plr}, 'plrid');
		$self->skill($BASESKILL);
		my $res = $db->insert($db->{t_plr}, { 
			plrid 		=> $self->plrid,
			uniqueid 	=> $self->uniqueid,
			firstseen	=> $self->{game}->{timestamp},
			lastdecay	=> $self->{game}->{timestamp},
			skill 		=> $self->skill,
			prevskill 	=> $self->skill,
			activity	=> 100
		});
		$self->fatal("Error adding player to database: " . $db->errstr) unless $res;

		# make sure the players profile is present
		if (!$db->select($db->{t_plr_profile}, 'uniqueid', [ uniqueid => $self->uniqueid ])) {
			$db->insert($db->{t_plr_profile}, { 
				uniqueid => $self->uniqueid,
				name => $self->name
			});
		}
		$db->commit;
	}

	# load the players basic information; RE-EVALUATE this, is it really needed
	$self->load_info;

	# load current stats for player
	$self->load_stats;

	# decay player stats 
	$self->decay if $DECAY;

	return $self; 
}

sub decay {
	my ($self, $_type, $_hours, $_value) = @_;
	return if $self->skill < $BASESKILL;	# don't do anything if we are already too low
	my $type 	= $_type  || $DECAY_TYPE  || return;
	my $maxhours 	= $_hours || $DECAY_HOURS || return;
	my $value 	= $_value || $DECAY_VALUE || return;
	my $seconds 	= $maxhours * 60 * 60;
	my $diff 	= $self->{game}{timestamp} - $self->lastdecay;
	my $length 	= $diff / $seconds;
	return unless $length >= 1.0;
	$value *= $length;

	my $newskill;
	$type = lc $type;
	if ($type eq 'flat') {
		$newskill = $self->skill - $value;
	} else { # $type eq 'percent'
		$newskill = $self->skill - ($self->skill * $value / 100);
	}

	# update the players skill value (can drop below base skill value)
	#$self->skill($newskill > $BASESKILL ? $newskill : $BASESKILL);
	$self->skill($newskill);
	$self->lastdecay($self->{game}{timestamp});

#	$self->{db}->update($self->{db}{t_plr}, $set, [ plrid => $self->{plrid} ]);
#	print "before: " , $self->skill, " :: ", $self->{db}->lastcmd,"\n";
}

# loads the current player stats from the _compiled_ player data
# This data is generally used as a snapshot for other various routines.
# not sure i like this anymore.... RE-EVALUATE 
sub load_stats {
	my $self = shift;
	my $db = $self->{db};
	$self->{_stats} = $db->get_row_hash("SELECT * FROM $db->{c_plr_data} WHERE plrid=" . $self->plrid);
	$self->{_stats} = {} unless $self->{_stats}; # make sure it's not undef
}

# load the players basic information 
# IS THIS REALLY NEEDED? -- RE-EVALUATE THIS LOGIC
sub load_info {
	my $self = shift;
	my $db = $self->{db};
	$self->{_plr} = $db->get_row_hash("SELECT clanid,prevrank,rank,prevskill,skill,allowrank,lastdecay " . 
		"FROM $db->{t_plr} WHERE plrid=" . $self->plrid
	);
}

sub load_profile {
	my ($self, $keys) = @_;
	my $db = $self->{db};
	$keys ||= '*';
	$self->{_profile} = $db->get_row_hash("SELECT $keys FROM $db->{t_plr_profile} WHERE uniqueid=" . $db->quote($self->uniqueid));
}

# loads a single days worth of stats for the player
sub load_day_stats {
	my $self = shift;
	my $statdate = shift || $self->{statdate} || time;
	my $db = $self->{db};
	my $cmd;
	$self->{_daystats} = {};

	$cmd  = "SELECT * FROM $db->{t_plr_data}";
	$cmd .=	" LEFT JOIN $db->{t_plr_data_mod} USING (dataid)" if $db->{t_plr_data_mod};
	$cmd .= " WHERE plrid=" . $db->quote($self->{plrid}) . " AND statdate=" . $db->quote($statdate);
	$self->{_daystats} = $db->get_row_hash($cmd) || {};
	return $self->{_daystats};
}

# updates the player signature Ids and increments the usage count (name, worldid, ipaddr)
# If no plrids are provided then the current ID's will be incremented
sub plrids {
	my $self = shift;
	my $newids = shift || { name => $self->{name}, ipaddr => $self->{ipaddr}, worldid => $self->{worldid} };
	my $inc = @_ ? shift : 1;

	# save the counter for each plr_ids variable into memory for now...
	# counters will be saved when player is saved.
	foreach my $column (keys %$newids) {
		my $var = $newids->{$column};
		$self->{_plrids}{$column}{$var}{totaluses} += $inc;
		$self->{_plrids}{$column}{$var}{lastseen} = $self->{game}{timestamp};
		if (!$self->{_plrids}{$column}{$var}{firstseen}) {
			$self->{_plrids}{$column}{$var}{firstseen} = $self->{game}{timestamp};
		}
	}
}

# saves the player signature Ids to the database and clears the current counters in memory
sub save_plrids {
	my ($self) = @_;
	return unless ref $self->{_plrids};	# do nothing if hash doesn't exist
	
	# update plr_ids_name plr_ids_ipaddr plr_ids_worldid
	if ($self->{db}->type eq 'mysql') {
		foreach my $column (keys %{$self->{_plrids}}) {
			foreach my $var (keys %{$self->{_plrids}{$column}}) {
				$self->{db}->do(
					"INSERT INTO " . $self->{db}{'t_plr_ids_' . $column} . " " .
					"(plrid,$column,totaluses,firstseen,lastseen) " . 
					"VALUES ($self->{plrid}," .
					$self->{db}->quote($var) .
					"," . $self->{_plrids}{$column}{$var}{totaluses} . 
					",FROM_UNIXTIME(" . $self->{_plrids}{$column}{$var}{firstseen} . ")" . 
					",FROM_UNIXTIME(" . $self->{_plrids}{$column}{$var}{lastseen} . ")) " . 
					"ON DUPLICATE KEY UPDATE totaluses=totaluses+" .
					$self->{_plrids}{$column}{$var}{totaluses} . ",lastseen=FROM_UNIXTIME($self->{game}{timestamp})" 
				);
			}
		}
	} else {
		# abstract; this needs to be updated when SQLite starts to be used
		die "Can not update plr_ids; I don't know how for DB::" . $self->{db}->type;
	}
}

# makes sure the compiled player data table is already setup
sub _init_table {
	my $self = shift;
	my $conf = $self->{conf};
	my $db = $self->{db};
	my $basetable = 'plr_data';
	my $table = $db->ctbl($basetable);
	my $tail = '';
	my $fields = {};
	my @order = ();
	$tail .= "_$GAMETYPE" if $GAMETYPE;
	$tail .= "_$MODTYPE" if $GAMETYPE and $MODTYPE;
	return if $db->table_exists($table);

	# get all keys used in the 2 tables so we can combine them all into a single table
	$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable))};
	if ($self->has_mod_tables and $tail) {
		$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable . $tail))};
	}

	# remove unwanted/special keys
	delete @$fields{ qw( statdate dayskill dayrank firstdate lastdate ) };

	# add extra keys
	my $alltypes = $self->get_types;
	$fields->{$_} = 'date' foreach qw( firstdate lastdate ); 
	$fields->{$_} = 'uint' foreach qw( dataid plrid ); 	# unsigned
	$fields->{$_} = 'float' foreach grep { ref $alltypes->{$_} } keys %$alltypes;

	# build the full set of keys for the table
	@order = (qw( dataid plrid firstdate lastdate ), sort grep { !/^((data|plr)id|(first|last)date)$/ } keys %$fields );

	$db->create($table, $fields, \@order);
	$db->create_primary_index($table, 'dataid');
	$db->create_unique_index($table, 'plrid', 'plrid');
	$self->info("Compiled table $table was initialized.");
}

sub _init_table_maps {
	my $self = shift;
	my $conf = $self->{conf};
	my $db = $self->{db};
	my $basetable = 'plr_maps';
	my $table = $db->ctbl($basetable);
	my $tail = '';
	my $fields = {};
	my @order = ();
	$tail .= "_$GAMETYPE" if $GAMETYPE;
	$tail .= "_$MODTYPE" if $GAMETYPE and $MODTYPE;
	return if $db->table_exists($table);

	# get all keys used in the 2 tables so we can combine them all into a single table
	$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable))};
	if ($self->has_mod_tables and $tail) {
		$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable . $tail))};
	}

	# remove unwanted/special keys
	delete @$fields{ qw( statdate firstdate lastdate ) };

	# add extra keys
	my $alltypes = $self->get_types_maps;
	$fields->{$_} = 'date' foreach qw( firstdate lastdate ); 
	$fields->{$_} = 'uint' foreach qw( dataid plrid mapid );	# unsigned
	$fields->{$_} = 'float' foreach grep { ref $alltypes->{$_} } keys %$alltypes;

	# build the full set of keys for the table
	@order = (qw( dataid plrid mapid firstdate lastdate ), sort grep { !/^((data|plr|map)id|(first|last)date)$/ } keys %$fields );

	$db->create($table, $fields, \@order);
	$db->create_primary_index($table, 'dataid');
	$db->create_unique_index($table, 'plrmaps', qw( plrid mapid ));
	$self->info("Compiled table $table was initialized.");
}

sub _init_table_victims {
	my $self = shift;
	my $conf = $self->{conf};
	my $db = $self->{db};
	my $basetable = 'plr_victims';
	my $table = $db->ctbl($basetable);
	my $tail = '';
	my $fields = {};
	my @order = ();
	$tail .= "_$GAMETYPE" if $GAMETYPE;
	$tail .= "_$MODTYPE" if $MODTYPE;
	return if $db->table_exists($table);

	# get all keys used in the 2 tables so we can combine them all into a single table
	$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable))};
# victims do not currently allow for game/modtype extensions
#	if ($self->has_mod_tables and $tail) {
#		$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable . $tail))};
#	}

	# remove unwanted/special keys
	delete @$fields{ qw( statdate firstdate lastdate ) };

	# add extra keys
	my $alltypes = $self->get_types_victims;
	$fields->{$_} = 'date' foreach qw( firstdate lastdate ); 
	$fields->{$_} = 'uint' foreach qw( dataid plrid victimid );	# unsigned
	$fields->{$_} = 'float' foreach grep { ref $alltypes->{$_} } keys %$alltypes;

	# build the full set of keys for the table
	@order = (qw( dataid plrid victimid firstdate lastdate ), sort grep { !/^((data|plr|victim)id|(first|last)date)$/ } keys %$fields );

	$db->create($table, $fields, \@order);
	$db->create_primary_index($table, 'dataid');
	$db->create_unique_index($table, 'plrvictims', qw( plrid victimid ));
	$self->info("Compiled table $table was initialized.");
}

sub _init_table_roles {
	my $self = shift;
	return unless $self->has_roles;		# do nothing if we don't use roles
	my $conf = $self->{conf};
	my $db = $self->{db};
	my $basetable = 'plr_roles';
	my $table = $db->ctbl($basetable);
	my $tail = '';
	my $fields = {};
	my @order = ();
	$tail .= "_$GAMETYPE" if $GAMETYPE;
	$tail .= "_$MODTYPE" if $MODTYPE;
	return if $db->table_exists($table);

	# get all keys used in the 2 tables so we can combine them all into a single table
	$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable))};
	if ($self->has_mod_roles and $tail) {
		$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable . $tail))};
	}

	# remove unwanted/special keys
	delete @$fields{ qw( statdate firstdate lastdate ) };

	# add extra keys
	my $alltypes = $self->get_types_roles;
	$fields->{$_} = 'date' foreach qw( firstdate lastdate ); 
	$fields->{$_} = 'uint' foreach qw( dataid plrid roleid );	# unsigned
	$fields->{$_} = 'float' foreach grep { ref $alltypes->{$_} } keys %$alltypes;

	# build the full set of keys for the table
	@order = (qw( dataid plrid roleid firstdate lastdate ), sort grep { !/^((data|plr|role)id|(first|last)date)$/ } keys %$fields );

	$db->create($table, $fields, \@order);
	$db->create_primary_index($table, 'dataid');
#	$db->create_index($table, 'plrroles', 'plrid', 'roleid');
	$db->create_unique_index($table, 'plrroles', qw( plrid roleid ));
	$self->info("Compiled table $table was initialized.");
}

sub _init_table_weapons {
	my $self = shift;
	my $conf = $self->{conf};
	my $db = $self->{db};
	my $basetable = 'plr_weapons';
	my $table = $db->ctbl($basetable);
	my $tail = '';
	my $fields = {};
	my @order = ();
	$tail .= "_$GAMETYPE" if $GAMETYPE;
	$tail .= "_$MODTYPE" if $MODTYPE;
	return if $db->table_exists($table);

	# get all keys used in the 2 tables so we can combine them all into a single table
	$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable))};
# weapons do not currently allow for game/modtype extensions
#	if ($self->has_mod_tables and $tail) {
#		$fields->{$_} = 'int' foreach keys %{$db->tableinfo($basetable . $tail)};
#	}

	# remove unwanted/special keys
	delete @$fields{ qw( statdate firstdate lastdate ) };

	# add extra keys
	my $alltypes = $self->get_types_weapons;
	$fields->{$_} = 'date' foreach qw( firstdate lastdate ); 
	$fields->{$_} = 'uint' foreach qw( dataid plrid weaponid );	# unsigned
	$fields->{$_} = 'float' foreach grep { ref $alltypes->{$_} } keys %$alltypes;

	# build the full set of keys for the table
	@order = (qw( dataid plrid weaponid firstdate lastdate ), sort grep { !/^((data|plr|weapon)id|(first|last)date)$/ } keys %$fields );

	$db->create($table, $fields, \@order);
	$db->create_primary_index($table, 'dataid');
	$db->create_unique_index($table, 'plrweapons', qw( plrid weaponid ));
	$self->info("Compiled table $table was initialized.");
}


sub origname { 
	if (@_ == 1) {
		return $_[0]->{origname};
	} else {
		my $old = $_[0]->{origname};
		$_[0]->{origname} = $_[1];
		return $old;
	}
}

sub ipaddr { 
	if (@_ == 1) {
		return $_[0]->{ipaddr};
	} else {
		my $old = $_[0]->{ipaddr};
		$_[0]->{ipaddr} = $_[1];
		return $old;
	}
}

sub name { 
	if (@_ == 1) {
		return $_[0]->{name};
	} else {
		my $old = $_[0]->{name};
		$_[0]->{name} = $_[1];
#		$_[0]->{origname} = $_[1];		# must set original name too
		return $old;
	}
}

sub uid { 
	if (@_ == 1) {
		return $_[0]->{uid};
	} else {
		my $old = $_[0]->{uid};
		$_[0]->{uid} = $_[1];
		return $old;
	}
}

sub uniqueid { 
	if (@_ == 1) {
		return $_[0]->{uniqueid};
	} else {
		my $old = $_[0]->{uniqueid};
		$_[0]->{uniqueid} = $_[1];
		return $old;
	}
}

sub worldid { 
	if (@_ == 1) {
		return $_[0]->{worldid};
	} else {
		my $old = $_[0]->{worldid};
		$_[0]->{worldid} = $_[1];
		return $old;
	}
}

sub statdate {
	return $_[0]->{statdate} if @_ == 1;
	my $self = shift;
	my ($d,$m,$y) = (localtime(shift))[3,4,5];
	my $newdate = sprintf("%04d-%02d-%02d",$y+1900,$m+1,$d);
#	if ($newdate ne $self->{statdate}) {
#		$self->save;
		$self->{statdate} = $newdate;
#	}
}

sub is_dead { 
	if (@_ == 1) {
		return $_[0]->{isdead};
	} else {
		my $old = $_[0]->{isdead};
		$_[0]->{isdead} = $_[1];
		return $old;
	}
}

sub timerstart {
	my $self = shift;
	my $timestamp = shift;
	my $prevtime = 0;
#	no warnings;						# don't want any "undef" or "uninitialized" errors

	# a previous timer was already started, get its elapsed value
	if ($self->active) { # && $self->{firsttime} && $self->{firsttime} != $self->{basic}{lasttime}) {
		$prevtime = $self->timer; #$self->{basic}{lasttime} - $self->{firsttime};
	}
	$self->{firsttime} = $self->{basic}{lasttime} = $timestamp;	# start new timer with current timestamp
	$self->statdate($timestamp) unless $self->statdate;		# set the statdate if it wasn't set already
	return $prevtime;
}

# return the total time that has passed since the timer was started
sub timer {
	my $self = shift;
	return 0 unless $self->{firsttime} and $self->{basic}{lasttime};
	my $t = $self->{basic}{lasttime} - $self->{firsttime};
	# If $t is negative then there's a chance that DST "fall back" just occured, so the timestamp is going to be -1 hour.
	# I try to compensate for this here by fooling the routines into thinking the time hasn't actually changed. this will
	# cause minor timing issues but the result is better then the player receiving NO time at all.
	if ($t < 0) {
		$t += 3600;	# add 1 hour. 
	}
	return $t > 0 ? $t : 0;
}

# returns the total online time for the player; current time + compiled time. used in skill calculations
sub totaltime {
	my $self = shift;
	return $self->timer + ($self->{_stats}{onlinetime} || 0);
}

sub update_streak {
	my $self = shift;
	my $type = shift;
	$self->end_streak(@_) if @_;
	$self->{streaks}{$type}++;
}

sub end_streak {
	my $self = shift;
	my $type;
	no warnings;
	while (@_) {
		$type = shift;
		next unless defined $self->{streaks}{$type};
		next unless $self->{streaks}{$type} > $self->{basic}{"${type}_streak"};
		$self->{basic}{"${type}_streak"} = $self->{streaks}{$type};
		delete $self->{streaks}{$type};
	}
}

sub end_all_streaks {
	my $self = shift;
	$self->end_streak(keys %{$self->{streaks}});
}

sub plrid { $_[0]->{plrid} }

sub clanid {
	my $self = shift;
	my $old = $self->{_plr}{clanid};
	$self->{_plr}{clanid} = shift if @_;
	return $old;
}

sub prevskill {
	my $self = shift;
	my $old = $self->{_plr}{prevskill} || 0;
	$self->{_plr}{prevskill} = shift if @_;
	return $old;
}

sub skill {
	my $self = shift;
	my $old = $self->{_plr}{skill} || 0;
	$self->{_plr}{skill} = shift if @_;
	return $old;
}

sub prevrank {
	my $self = shift;
	my $old = $self->{_plr}{prevrank} || 0;
	$self->{_plr}{prevrank} = shift if @_;
	return $old;
}

sub rank {
	my $self = shift;
	my $old = $self->{_plr}{rank} || 0;
	$self->{_plr}{rank} = shift if @_;
	return $old;
}

sub pq {
	return $_[0]->{pq} if @_ == 1;
	my $old = $_[0]->{pq};
	$_[0]->{pq} = $_[1];
	return $old;
}

sub team {
	return $_[0]->{team} if @_ == 1;
	my $old = $_[0]->{team};
	$_[0]->{team} = $_[1];
	return $old;
}

sub role {
	return $_[0]->{role} if @_ == 1;
	my $old = $_[0]->{role};
	$_[0]->{role} = $_[1];
	return $old;
}


sub allowrank {
	my $self = shift;
	my $old = $self->{_plr}{allowrank};
	$self->{_plr}{allowrank} = shift if @_;
	return $old;
}

sub active {
	my $self = shift;
	my $old = $self->{active};
	$self->{active} = shift if @_;
	return $old;
}

sub saved {
	my $self = shift;
	my $old = $self->{saved};
	$self->{saved} = shift if @_;
	return $old;
}

sub lastdecay {
	my $self = shift;
	my $old = $self->{_plr}{lastdecay} || 0;
	$self->{_plr}{lastdecay} = shift if @_;
	return $old;
}


# sets/gets the current signature. 
# this is mainly used by the PS::Game caching routines
sub signature { 
	my $self = shift;
	return $self->{signature} unless scalar @_;
	my $old = $self->{signature};
	$self->{signature} = shift;
	return $old;
}

# Sets the players profile name to be the name that has currently been used the most.
# this function does not check the 'namelocked' player profile variable.
sub update_name {
	my $self = shift;
	my $db = $self->{db};
	my $sort = 'firstseen';
	if ($PLR_PRIMARY_NAME eq 'most') {
			$sort = "totaluses";
		} elsif ($PLR_PRIMARY_NAME eq 'last') {
			$sort = "lastseen";
		}
	my ($name) = $db->select($db->{t_plr_ids_name}, 'name', "plrid=" . $self->plrid . " AND name <> '" . $PLR_DEFAULT_NAME . "'", $sort . " DESC");
	if (!defined $name) {
		$name = $PLR_DEFAULT_NAME;
	}
	$db->update($db->{t_plr_profile}, { name => $name }, [ uniqueid => $self->uniqueid ]);
	$self->name($name);
#	$self->clanid(0);
	return $name;
}

# player is considered disconnected from the server, so do any cleanup that is required
# the player is not actually deleted (or undef'd from memory) or saved.
sub disconnect {
	my ($self, $timestamp, $map) = @_;

#	if ($self->uniqueid eq 'STEAM_0:1:6048454') {
#		print "disconnected: \t" . date("%H:%i:%s", $timestamp) . " (" . $self->timer . ")\n";
#	}

	if ($self->active) {
		my $time = $self->timer;
		$self->{basic}{onlinetime} += $time;
		$self->{maps}{ $map->{mapid} }{onlinetime} += $time if defined $map;
	}
#	$self->timerstart($timestamp);
}

sub get_types { $TYPES }
sub get_types_maps { $TYPES_MAPS }
sub get_types_roles { $TYPES_ROLES }
sub get_types_weapons { $TYPES_WEAPONS }
sub get_types_victims { $TYPES_VICTIMS }

sub mod_types { {} };
sub mod_types_maps { {} };
sub mod_types_roles { {} };

sub save {
	my $self = shift;
	my $nocommit = shift;
	my $db = $self->{db};
	my $dataid;
	my $plrid = $self->plrid;
	my $worldid = $self->worldid;
	my $ipaddr = $self->ipaddr;

	$self->{save_history} = (diffdays_ymd(POSIX::strftime("%Y-%m-%d", localtime), $self->{statdate}) <= $MAXDAYS);
#	print "$self->{statdate} = " . POSIX::strftime("%Y-%m-%d", localtime) . " == " . diffdays_ymd(POSIX::strftime("%Y-%m-%d", localtime), $self->{statdate}) . "\n";

	# save basic+mod compiled player stats
	$db->save_stats( $db->{c_plr_data}, { %{$self->{basic}}, %{$self->{mod}} }, $self->get_types, 
		[ plrid => $plrid ], $self->{statdate});

	# the 'dayskill' and 'dayrank' are explictly added to the saved data (but not to the compiled data above)
	if ($self->{save_history}) {
		$dataid = $db->save_stats($db->{t_plr_data}, 
			{ dayskill => $self->skill, dayrank => $self->rank, %{$self->{basic}} }, $TYPES, 
			[ plrid => $plrid, statdate => $self->{statdate} ]);
		if ($dataid and $self->{mod} and $self->has_mod_tables) {
			$dataid = $self->{db}->save_stats($self->{db}->{t_plr_data_mod}, $self->{mod}, $self->mod_types, [ dataid => $dataid ]);
		}
	}

	# save player roles
	if ($self->has_roles) {
#		print_r($self->{roles}) if $self->worldid eq 'STEAM_0:0:1179775';
		while (my($id,$data) = each %{$self->{roles}}) {
			$self->save_role($id, $data);
		}
	}

	# save player weapons
	while (my($id,$data) = each %{$self->{weapons}}) {
		$self->save_weapon($id, $data);
	}

	# save player victims (only if configured to do so)
	if ($PLR_SAVE_VICTIMS) {
		while (my($id,$data) = each %{$self->{victims}}) {
			$self->save_victim($id, $data);
		}
	}

	# save player maps
	while (my($id,$data) = each %{$self->{maps}}) {
		$self->save_map($id, $data);
	}

	# save the current session separately
	if ($PLR_SESSIONS_MAX and $self->{basic}{lasttime}) {
		my $session = { 'mapid' => $self->{game}->get_map()->{mapid} };
		# get most recent player session
		my ($sid, $start, $end, $prevskill) = $db->get_row_array(
			"SELECT dataid,sessionstart,sessionend,skill FROM $db->{t_plr_sessions} " .
			"WHERE plrid=$plrid " . 
			"ORDER BY sessionstart DESC LIMIT 1"
		);
		# if there was no session or the last session was too old start a new one.
		# the previous session is "too old" if more than X mins has gone by since the previous session ended.
		# This grace period allows players to be disconnected between maps and still have a single session.
		if (!$sid or ($self->{firsttime} - $end > 60*15)) {
			$session->{dataid} = $sid = $db->next_id($db->{t_plr_sessions}, 'dataid');	# update $sid too
			$session->{plrid} = $plrid;
			$session->{prevskill} = defined $prevskill ? $prevskill : $self->skill;
			$session->{sessionstart} = $start = $self->{firsttime};				# update $start too
		}
		$session->{sessionend} = $self->{basic}{lasttime};
		$session->{skill} = $self->skill;

		# if the session length is negative we assume DST "fall back" has occured and compensate.
		# the end of the session will not actually be accurate, but it will be sufficient.
		if ($session->{sessionend} - $start < 0) {
			$session->{sessionend} += 3600;
		}

		# save the session (only if the session is more than 5 seconds)
		if ($session->{sessionend} - $start > 5) {
			$db->save_stats($db->{t_plr_sessions}, { %$session, %{$self->{basic}} }, $TYPES_PLRSESSIONS, [ dataid => $sid ] );
			# remove old sessions
			my $numsessions = $db->count($db->{t_plr_sessions}, [ plrid => $plrid ]);
			if ($numsessions > $PLR_SESSIONS_MAX) {
				my @del = $db->get_list(
					"SELECT dataid FROM $db->{t_plr_sessions} " . 
					"WHERE plrid=$plrid " . 
					"ORDER BY sessionstart " .		# oldest first
					"LIMIT " . ($numsessions - $PLR_SESSIONS_MAX)
				);
				if (@del) {
					$db->query("DELETE FROM $db->{t_plr_sessions} WHERE dataid IN (" . join(',', @del) . ")");
				}
			}
		}
	}

	# clear all stats in memory
	$self->{basic} = {};
	$self->{mod} = {};
	$self->{weapons} = {};
	$self->{mod_weapons} = {};
	$self->{victims} = {};
	$self->{mod_victims} = {};
	$self->{maps} = {};
	$self->{mod_maps} = {};
	$self->{roles} = {};
	$self->{mod_roles} = {};

	# ----------------------------------------------------------------
	# all info below here is player info and not stats. It's possible I could code a method to 
	# only save the info below only when absolutely necesary, instead of every time.
	# ----------------------------------------------------------------

	# grab some profile info
	my ($namelocked, $cc) = $db->select($db->{t_plr_profile}, [qw( namelocked cc )], [ uniqueid => $self->uniqueid ]);

	$db->begin unless $nocommit;

	# save basic player information (plr table); RE-EVALUATE
	$self->lastdecay($self->{basic}{lasttime} || $self->{game}->{timestamp}) unless $self->lastdecay;
	$db->update($db->{t_plr}, $self->{_plr}, [ plrid => $plrid ]);

	# update the prevskill for the player (from the previous day)
	my $id = $db->quote($plrid);
	if ($db->subselects) {
		$db->query("UPDATE $db->{t_plr} SET prevskill=IFNULL(" . 
			"(SELECT dayskill FROM $db->{t_plr_data} WHERE plrid=$id ORDER BY statdate DESC " . $db->limit(1,1) . ")" . 
			",prevskill) WHERE plrid=$id");
	} else {
		my ($prevskill) = $db->get_list("SELECT dayskill FROM $db->{t_plr_data} WHERE plrid=$id ORDER BY statdate DESC " . $db->limit(1,1));
		$db->update($db->{t_plr}, { prevskill => $prevskill }, [ plrid => $plrid ]) if defined $prevskill;
	}

	# save the plrids counters
	$self->save_plrids;
	
	# update most/least used name if the name is not locked
	if (!$namelocked and $UNIQUEID ne 'name') {
		$self->update_name;
	};
            
    # if player is a bot assign country code
    if (substr($worldid, 0, 3) eq 'BOT') {
        $cc = 'A2';
		$db->update($db->{t_plr_profile}, [ cc => $cc ], [ uniqueid => $self->uniqueid ]) if defined $cc and $cc ne '';
    }

	# update the player's country code if one is not already set
    if ((!defined $cc or $cc eq '') and $ipaddr) {
		$cc = $db->select($db->{t_geoip_ip}, 'cc', "$ipaddr BETWEEN " . $db->qi('start') . " AND " . $db->qi('end'));
		$db->update($db->{t_plr_profile}, [ cc => $cc ], [ uniqueid => $self->uniqueid ]) if defined $cc and $cc ne '';
    }

	$db->commit unless $nocommit;

	return $dataid;		# return the ID of the 'basic' data that was saved
}

sub save_weapon {
	my ($self, $id, $data) = @_;
	my $dataid;
	# save compiled stats
	$self->{db}->save_stats( $self->{db}->{c_plr_weapons}, $data, $TYPES_WEAPONS, 
		[ plrid => $self->{plrid}, weaponid => $id ], $self->{statdate});

	# save basic history
	if ($self->{save_history}) {
		$dataid = $self->{db}->save_stats( $self->{db}->{t_plr_weapons}, $data, $TYPES_WEAPONS, [ plrid => $self->{plrid}, weaponid => $id, statdate => $self->{statdate} ]);
	}
	return $dataid;
}

sub save_victim {
	my ($self, $id, $data) = @_;
	my $dataid;
	# save compiled stats
	$self->{db}->save_stats( $self->{db}->{c_plr_victims}, $data, $TYPES_VICTIMS, 
		[ plrid => $self->{plrid}, victimid => $id ], $self->{statdate});

	# save basic history
	if ($self->{save_history}) {
		$dataid = $self->{db}->save_stats( $self->{db}->{t_plr_victims},  $data, $TYPES_VICTIMS, [ plrid => $self->{plrid}, victimid => $id, statdate => $self->{statdate} ]);
	}
	return $dataid;
}

sub save_role {
	my ($self, $id, $data) = @_;
	my $dataid;
	# save compiled stats
	$self->{db}->save_stats( $self->{db}->{c_plr_roles}, { %$data, %{$self->{mod_roles}{$id} || {}} }, $self->get_types_roles, 
		[ plrid => $self->{plrid}, roleid => $id ], $self->{statdate});

	# save basic and mod history
	if ($self->{save_history}) {
		$dataid = $self->{db}->save_stats( $self->{db}->{t_plr_roles}, $data, $TYPES_ROLES, [ plrid => $self->{plrid}, roleid => $id, statdate => $self->{statdate} ]);
		if ($dataid and $self->{mod_roles}{$id}) {
			$self->{db}->save_stats($self->{db}->{t_plr_roles_mod}, $self->{mod_roles}{$id}, $self->mod_types_roles, [ dataid => $dataid ]);
		}
	}
	return $dataid;
}

sub save_map {
	my ($self, $id, $data) = @_;
	my $dataid;
	# save compiled stats
	$self->{db}->save_stats( $self->{db}->{c_plr_maps}, { %$data, %{$self->{mod_maps}{$id} || {}} }, 
		$self->get_types_maps, [ plrid => $self->{plrid}, mapid => $id ], $self->{statdate});

	# save basic and mod history
	if ($self->{save_history}) {
		$dataid = $self->{db}->save_stats($self->{db}->{t_plr_maps},  $data, $TYPES_MAPS, [ plrid => $self->{plrid}, mapid => $id, statdate => $self->{statdate} ]);
		if ($dataid and $self->{mod_maps}{$id} and $self->has_mod_tables) {
			$self->{db}->save_stats($self->{db}->{t_plr_maps_mod}, $self->{mod_maps}{$id}, $self->mod_types_maps, [ dataid => $dataid ]);
		}
	}
	return $dataid;
}

# returns true if the player is a bot
sub is_bot {
    my $bot_check = substr($_[0]->uniqueid,0,1);
    
    return 1 if substr($_[0]->uniqueid,0,3) eq 'BOT';
    return 1 if looks_like_number( $bot_check );
}

# returns true if the gametype:modtype has extra mod tables
sub has_mod_tables { 0 }

# returns if the gametype:modtype has role based player stats
sub has_roles { 0 }
sub has_mod_roles { 0 }

# returns the result: 0.0, 0.5, 1.0 if the player is stronger than another
# 0 = weaker, 0.5 = even, 1 = stronger
sub winresult {
	return 1 if $_[0]->{pq} > $_[1];
	return 0 if $_[0]->{pq} < $_[1];
	return 0.5;
}

1;

