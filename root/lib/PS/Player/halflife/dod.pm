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
#	$Id: dod.pm 450 2008-05-20 11:34:52Z lifo $
#
package PS::Player::halflife::dod;

use strict;
use warnings;
use base qw( PS::Player );

our $VERSION = '1.00.' . (('$Rev: 450 $' =~ /(\d+)/)[0] || '000');

our $TYPES = {
	allieskills		=> '+',
	axiskills		=> '+',
	alliesdeaths		=> '+',
	axisdeaths		=> '+',
	joinedallies		=> '+',
	joinedaxis		=> '+',
	allieswon		=> '+',
	allieswonpct		=> [ percent2 => qw( allieswon axiswon ) ],
	axiswon			=> '+',
	axiswonpct		=> [ percent2 => qw( axiswon allieswon ) ],
	allieslost		=> '+',
	axislost		=> '+',
#	tnt			=> '+',
#	tntused			=> '+',
	alliesflagscaptured	=> '+',
	alliesflagscapturedpct	=> [ percent => qw( alliesflagscaptured flagscaptured ) ],
	axisflagscaptured	=> '+',
	axisflagscapturedpct	=> [ percent => qw( axisflagscaptured flagscaptured ) ],
	flagscaptured		=> '+',

	alliesflagsblocked	=> '+',
	alliesflagsblockedpct	=> [ percent => qw( alliesflagsblocked flagsblocked ) ],
	axisflagsblocked	=> '+',
	axisflagsblockedpct	=> [ percent => qw( axisflagsblocked flagsblocked ) ],
	flagsblocked		=> '+',

	bombdefused		=> '+',
	bombplanted		=> '+',
	killedbombplanter	=> '+',
	alliesscore		=> '+',	
	alliesscorepct		=> [ percent2 => qw( alliesscore axisscore ) ],
	axisscore		=> '+',	
	axisscorepct		=> [ percent2 => qw( axisscore alliesscore ) ],

};

# Player map stats are the same as the basic stats
our $TYPES_MAPS = { %$TYPES };

our $TYPES_ROLES = { 
	# I don't save any extra dod role stats yet
};

# override parent methods to combine types
sub get_types { return { %{$_[0]->SUPER::get_types}, %$TYPES } }
sub get_types_maps { return { %{$_[0]->SUPER::get_types_maps}, %$TYPES_MAPS } }
sub get_types_roles { return { %{$_[0]->SUPER::get_types_roles}, %$TYPES_ROLES } }

# allows the parent to determine our local types
sub mod_types { $TYPES };
sub mod_types_maps { $TYPES_MAPS };
sub mod_types_roles { $TYPES_ROLES };

sub _init {
	my $self = shift;
	$self->SUPER::_init;

	$self->{role} = '';
	$self->{roles} = {};
	$self->{mod} = {};
	$self->{mod_roles} = {};

	return $self;
}

sub has_mod_tables { 1 }

sub has_roles { 1 }

1;
