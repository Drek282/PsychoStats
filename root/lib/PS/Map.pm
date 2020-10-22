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
#	$Id: Map.pm 554 2008-09-03 12:52:56Z lifo $
#
package PS::Map;

use strict;
use warnings;
use base qw( PS::Debug );
use POSIX qw( strftime );
use util qw( :date print_r );

our $VERSION = '1.00.' . (('$Rev: 554 $' =~ /(\d+)/)[0] || '000');
our $BASECLASS = undef;

our $GAMETYPE = '';
our $MODTYPE = '';

our $TYPES = {
	dataid		=> '=', 
	mapid		=> '=',
	statdate	=> '=',
	games		=> '+',
	rounds		=> '+',
	kills		=> '+',
	suicides	=> '+',
	ffkills		=> '+',
	ffkillspct	=> [ percent => qw( ffkills kills ) ],
	connections	=> '+',
	onlinetime	=> '+',	
	lasttime	=> '>',
};

our $TYPES_HOURLY = {
	dataid		=> '=', 
	mapid		=> '=',
	statdate	=> '=',
	hour		=> '=',
	online		=> '=',		# total players online in the hour
	games		=> '+',
	rounds		=> '+',
	kills		=> '+',
	connections	=> '+',
};

#our $TYPES_SPATIAL = {
#	mapid		=> '=',
#	weaponid	=> '=',
#	statdate	=> '=',
#	kid		=> '=',		# killer
#	kx		=> '=',
#	ky		=> '=',
#	kz		=> '=',
#	vid		=> '=',		# victim
#	vx		=> '=',
#	vy		=> '=',
#	vz		=> '=',
#};

sub new {
	my ($proto, $mapname, $conf, $db) = @_;
	my $baseclass = ref($proto) || $proto;
	my $self = { debug => 0, class => undef, mapname => $mapname, conf => $conf, db => $db };
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

		# still no class? create a basic PS::Map object and return that
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

# makes sure the compiled map data table is already setup
sub _init_table {
	my $self = shift;
	my $conf = $self->{conf};
	my $db = $self->{db};
	my $basetable = 'map_data';
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
	delete @$fields{ qw( statdate ) };

	# add extra keys
	my $alltypes = $self->get_types;
	$fields->{$_} = 'date' foreach qw( firstdate lastdate );
	$fields->{$_} = 'uint' foreach qw( dataid mapid );	# unsigned
	$fields->{$_} = 'float' foreach grep { ref $alltypes->{$_} } keys %$alltypes;

	# build the full set of keys for the table
	@order = (qw( dataid mapid firstdate lastdate ), sort grep { !/^((data|map)id|(first|last)date)$/ } keys %$fields );

	$db->create($table, $fields, \@order);
	$db->create_primary_index($table, 'dataid');
	$db->create_unique_index($table, 'mapid');
	$self->info("Compiled table $table was initialized.");
}

sub _init {
	my $self = shift;

	return unless $self->{mapname};

	$self->{basic} = {};
	$self->{mod} = {};
	$self->{hourly} = {};
	$self->{spatial} = {};

	$self->{basic}{lasttime} = 0;

	$self->{conf_maxdays} = $self->{conf}->get_main('maxdays');
	$self->{heatmap_maxdays} = $self->{conf}->get_main('heatmap.maxdays');

	$self->{mapid} = $self->{db}->select($self->{db}->{t_map}, 'mapid', 
		"uniqueid=" . $self->{db}->quote($self->{mapname})
	);
	# map didn't exist so we have to create it
	if (!$self->{mapid}) {
		$self->{mapid} = $self->{db}->next_id($self->{db}->{t_map},'mapid');
		my $res = $self->{db}->insert($self->{db}->{t_map}, { 
			mapid => $self->{mapid},
			uniqueid => $self->{mapname},
		});
		$self->fatal("Error adding map to database: " . $self->{db}->errstr) unless $res;
	}
}

sub name { $_[0]->{mapname} }

sub statdate {
	return $_[0]->{statdate} if @_ == 1;
	my $self = shift;
	my ($d,$m,$y) = (localtime(shift || return))[3,4,5];
	$m++;
	$y += 1900;
	$self->{statdate} = sprintf("%04d-%02d-%02d",$y,$m,$d);
}

sub timerstart {
	my $self = shift;
	my $timestamp = shift || return;
	my $prevtime = 0;
#	no warnings;						# don't want any 'undef' or 'uninitialized' errors

	# a previous timer was already started, get it's elapsed value
	if ($self->{firsttime}) {
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
		$t += 3600;	# add 1 hour
	}
	return $t > 0 ? $t : 0;
}

sub get_types { $TYPES }
sub get_types_hourly { $TYPES_HOURLY }
#sub get_types_spatial { $TYPES_SPATIAL }

sub save {
	my $self = shift;
	my $db = $self->{db};
	my $dataid;

	# save compiled stats ...
	$dataid = $db->save_stats( $db->{c_map_data}, { %{$self->{basic}}, %{$self->{mod}} }, $self->get_types, 
		[ mapid => $self->{mapid} ], $self->{statdate});

	# save basic and mod history
	if (diffdays_ymd(strftime("%Y-%m-%d", localtime), $self->{statdate}) <= $self->{conf_maxdays}) {
		$dataid = $db->save_stats($db->{t_map_data}, $self->{basic}, $TYPES, 
			[ mapid => $self->{mapid}, statdate => $self->{statdate} ]
		);
		if ($dataid and $self->{mod} and $self->has_mod_tables) {
			$db->save_stats($db->{t_map_data_mod}, $self->{mod}, $self->mod_types, [ dataid => $dataid ]);
			$self->{mod} = {};
		}
	}
	$self->{basic} = {};

	# save hourly map stats ...
	if (defined $self->{hourly}) {
		foreach my $date (keys %{$self->{hourly}}) {
			foreach my $hour (keys %{$self->{hourly}{$date}}) {
				$db->save_stats($db->{t_map_hourly}, $self->{hourly}{$date}{$hour}, $self->get_types_hourly, 
					[ mapid => $self->{mapid}, statdate => $date, hour => $hour ]
				);
			}
		}
		$self->{hourly} = {};
	}

	# save spatial stats
	$self->save_all_spatial;

	return $dataid;
}

sub save_all_spatial {
	my ($self) = @_;
	if (defined $self->{spatial}) {
		my $today = strftime("%Y-%m-%d", localtime);
		foreach my $date (keys %{$self->{spatial}}) {
			# save an entire day all at once
			if (diffdays_ymd($today, $date) <= $self->{heatmap_maxdays}) {
				$self->save_spatial($self->{spatial}{$date});
			}
		}
		$self->{spatial} = {};
	}
}

# spatial stat inserts are optimized to reduce the total inserts that need to be performed.
sub save_spatial {
	my ($self, $data) = @_;
	my ($cmd,$fields,@keys,$hdr);
	my $db = $self->{db};
	my $MAX = 1000*1024;		# a little less than 1MB
	if (ref $data eq 'HASH') {
		$data = [ { %$data } ];
	}

	@keys = keys %{$data->[0]};
	$fields = join(', ', map { $db->qi($_) } @keys);

	$hdr = "INSERT INTO $db->{t_map_spatial} ($fields) VALUES ";
	$cmd = '';
	foreach my $d (@$data) {
		if (length($cmd) < $MAX) {
			$cmd .= "(" . join(', ', map { $db->quote($d->{$_}) } @keys) . "),";
		} else {
			# run the query; the max length of $cmd will usually overrun $MAX just a tad, this is ok.
			$self->{db}->query($hdr.substr($cmd,0,-1));
			$cmd = '';
		}
	}
	# finish up and run the last query
	if ($cmd) {
		$self->{db}->query($hdr.substr($cmd,0,-1));
	}
}

# adds a spatial stat to the current date
sub spatial {
	my ($self, $game, $p1, $ap, $p2, $vp, $w, $headshot) = @_;
	return unless defined $ap && defined $vp;
#	return if 60*60*24*$self->{conf_maxdays} - time > $game->{timestamp};
	my $set = {
		mapid		=> $self->{mapid},
		weaponid	=> $w->{weaponid},
		statdate	=> strftime("%Y-%m-%d", localtime($game->{timestamp})), 
		hour		=> $game->{hour},
		roundtime	=> $game->{roundstart} ? $game->{timestamp} - $game->{roundstart} : 0,
		kid		=> $p1->{plrid},
		kteam		=> $p1->{team} || undef,
		vid		=> $p2->{plrid},
		vteam		=> $p2->{team} || undef,
		headshot	=> $headshot ? 1 : 0
	}; 
	@$set{qw( kx ky kz )} = ref $ap ? @$ap : split(' ', $ap);
	@$set{qw( vx vy vz )} = ref $vp ? @$vp : split(' ', $vp);
	push(@{$self->{spatial}{ $set->{statdate} }}, $set);
}

# adds an hourly stat to the current hour
sub hourly {
	my ($self, $var, $timestamp, $inc) = @_;
	my ($date, $hour) = split(' ', strftime("%Y-%m-%d %H", localtime($timestamp)));
	$inc = 1 unless defined $inc;
	if (ref $var eq 'ARRAY') {
		$self->{hourly}{$date}{$hour}{$_} += $inc for @$var;
	} else {
		$self->{hourly}{$date}{$hour}{$var} += $inc;
	}
}

sub has_mod_tables { 0 }

1;
