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
#	$Id: cod.pm 497 2008-06-19 16:10:55Z lifo $
#
package PS::Player::cod;

use strict;
use warnings;
use base qw( PS::Player );

our $TYPES = {
	allieskills		=> '+',
	allieskillspct		=> [ percent2 => qw( allieskills axiskills ) ],
	axiskills		=> '+',
	axiskillspct		=> [ percent2 => qw( axiskills allieskills ) ],
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
};

# Player map stats are the same as the basic stats
our $TYPES_MAPS = { %$TYPES };

# override parent methods to combine types
sub get_types { return { %{$_[0]->SUPER::get_types}, %$TYPES } }
sub get_types_maps { return { %{$_[0]->SUPER::get_types_maps}, %$TYPES_MAPS } }
#sub get_types_roles { return { %{$_[0]->SUPER::get_types_roles}, %$TYPES_ROLES } }

# allows the parent to determine our local types
sub mod_types { $TYPES };
sub mod_types_maps { $TYPES_MAPS };
#sub mod_types_roles { $TYPES_ROLES };

sub _init {
	my ($self) = @_;
	$self->SUPER::_init;

#	$self->{role} = '';
#	$self->{roles} = {};
	$self->{mod} = {};
#	$self->{mod_roles} = {};

	$self->{holding_weapon} = '';

	return $self;
}

sub has_mod_tables { 1 }
sub has_roles { 0 }

# keeps track of what weapon the player is holding
sub weapon {
	my ($self, $w) = @_;
	$self->{holding_weapon} = $w;
}

1;
