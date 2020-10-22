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
#	$Id: Role.pm 512 2008-07-06 23:44:07Z lifo $
#
package PS::Role;

use strict;
use warnings;
use base qw( PS::Debug );
use POSIX;
use util qw( :date );

our $VERSION = '1.10.' . (('$Rev: 512 $' =~ /(\d+)/)[0] || '000');
our $BASECLASS = undef;

our $GAMETYPE = '';
our $MODTYPE = '';

our $TYPES = {
	dataid		=> '=', 
	roleid		=> '=',
	statdate	=> '=',
	deaths		=> '+',
	kills		=> '+',
	ffkills		=> '+',
	ffkillspct	=> [ percent => qw( ffkills kills ) ],
	headshotkills	=> '+',
	headshotkillspct=> [ percent => qw( headshotkills kills ) ],
	damage		=> '+',
	hits		=> '+',
	shots		=> '+',
#	shot_chest	=> '+',
#	shot_head	=> '+',
#	shot_leftarm	=> '+',
#	shot_leftleg	=> '+',
#	shot_rightarm	=> '+',
#	shot_rightleg	=> '+',
#	shot_stomach	=> '+',
	accuracy	=> [ percent => qw( hits shots ) ],
	shotsperkill	=> [ ratio => qw( shots kills ) ],
	joined		=> '+',
};

sub new {
	my ($proto, $uniqueid, $team, $conf, $db) = @_;
	my $baseclass = ref($proto) || $proto;
	my $self = { debug => 0, class => undef, team => $team, uniqueid => $uniqueid, conf => $conf, db => $db };
	my $class;

	# determine what kind of player we're going to be using the first time we're created
	if (!$BASECLASS) {
		$GAMETYPE = $conf->get('gametype');
		$MODTYPE = $conf->get('modtype');

		my @ary = ($MODTYPE) ? ($MODTYPE, $GAMETYPE) : ($GAMETYPE);
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

		# still no class? create a basic PS::Role object and return that
		$class = $baseclass if !$class;
	} else {
		$class = $BASECLASS;
	}

	$self->{class} = $class;

	bless($self, $class);
#	$self->debug($self->{class} . " initializing");

	$self->_init;

	if (!$BASECLASS) {
		$self->_init_table;
		$BASECLASS = $class;
	}

	return $self;
}

# makes sure the compiled player data table is already setup
sub _init_table {
	my $self = shift;
	my $conf = $self->{conf};
	my $db = $self->{db};
	my $basetable = 'role_data';
	my $table = $db->ctbl($basetable);
	my $tail = '';
	my $fields = {};
	my @order = ();
	$tail .= "_$GAMETYPE" if $GAMETYPE;
	$tail .= "_$MODTYPE" if $GAMETYPE and $MODTYPE;
	return if $db->table_exists($table);

	# get all keys used in the 2 tables so we can combine them all into a single table
	$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable))};
	if ($tail and $self->has_mod_tables) {
		$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable . $tail))};
	}

	# remove unwanted/special keys
	delete @$fields{ qw( statdate ) };

	# add extra keys
	my $alltypes = $self->get_types;
	$fields->{$_} = 'date' foreach qw( firstdate lastdate );
	$fields->{$_} = 'uint' foreach qw( dataid roleid );	# unsigned
	$fields->{$_} = 'float' foreach grep { ref $alltypes->{$_} } keys %$alltypes;

	# build the full set of keys for the table
	@order = (qw( dataid roleid firstdate lastdate ), sort grep { !/^((data|role)id|(first|last)date)$/ } keys %$fields );

	$db->create($table, $fields, \@order);
	$db->create_primary_index($table, 'dataid');
	$db->create_unique_index($table, 'roleid');
	$self->info("Compiled table $table was initialized.");
}

sub _init {
	my $self = shift;
	my $db = $self->{db};

	$self->{basic} = {};
	$self->{mod} = {};
	return unless $self->{uniqueid};

	$self->{conf_maxdays} = $self->{conf}->get_main('maxdays');

#	my $team = $self->{team};
	($self->{roleid}, $self->{name}, $self->{team}) = $db->select($db->{t_role}, [qw( roleid name team )], 
		"uniqueid=" . $db->quote($self->{uniqueid})
	);
	# role didn't exist so we have to create it
	if (!$self->{roleid}) {
		$self->{roleid} = $db->next_id($db->{t_role}, 'roleid');
		my $res = $db->insert($db->{t_role}, { 
			roleid 		=> $self->{roleid},
			uniqueid 	=> $self->{uniqueid},
			team		=> $self->{team} || undef,
		});
		$self->fatal("Error adding role to database: " . $db->errstr) unless $res;
#	} elsif (!$self->{team} and $team) {	# if the team was previously unknown, update it now
#		$db->update($db->{t_role}, { team => $team }, [ uniqueid => $self->{uniqueid} ]);
#		$self->{team} = $team;
	}

	return $self;
}

sub name { $_[0]->{name} || $_[0]->{uniqueid} }

sub statdate {
	return $_[0]->{statdate} if @_ == 1;
	my $self = shift;
	my ($d,$m,$y) = (localtime(shift))[3,4,5];
	$m++;
	$y += 1900;
	$self->{statdate} = sprintf("%04d-%02d-%02d",$y,$m,$d);
}

sub get_types { $TYPES }

sub save {
	my $self = shift;
	my $db = $self->{db};
	my $dataid;

	# save basic+mod role stats ...
	$dataid = $db->save_stats( $db->{c_role_data}, { %{$self->{basic}}, %{$self->{mod}} }, $self->get_types, 
		[ roleid => $self->{roleid} ], $self->{statdate});

	if (diffdays_ymd(POSIX::strftime("%Y-%m-%d", localtime), $self->{statdate}) <= $self->{conf_maxdays}) {
		$dataid = $self->{db}->save_stats($db->{t_role_data},  $self->{basic}, $TYPES, [ roleid => $self->{roleid}, statdate => $self->{statdate} ]);
	}
	$self->{basic} = {};

	return $dataid;
}

sub has_mod_tables { 0 }

1;
