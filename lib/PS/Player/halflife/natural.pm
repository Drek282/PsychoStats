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
#	$Id: natural.pm 450 2008-05-20 11:34:52Z lifo $
#
package PS::Player::halflife::natural;

use strict;
use warnings;
use base qw( PS::Player );

our $VERSION = '1.00.' . (('$Rev: 450 $' =~ /(\d+)/)[0] || '000');

use PS::Role;

our $TYPES = {
	marinekills		=> '+',
	alienkills		=> '+',
	marinedeaths		=> '+',
	aliendeaths		=> '+',
	joinedmarine		=> '+',
	joinedalien		=> '+',
	joinedspectator		=> '+',
	alienwon		=> '+',
	alienwonpct		=> [ percent2 => qw( alienwon marinewon ) ],
	alienlost		=> '+',
	marinewon		=> '+',
	marinewonpct		=> [ percent2 => qw( marinewon alienwon ) ],
	marinelost		=> '+',
	commander		=> '+',
	commanderwon		=> '+',
	commanderwonpct		=> [ percent => qw( commanderwon commander ) ],
	votedown		=> '+',
	structuresbuilt		=> '+',
	structuresdestroyed	=> '+',
	structuresrecycled	=> '+',
};

# Player map stats are the same as the basic stats
our $TYPES_MAPS = { %$TYPES };

our $TYPES_ROLES = { 
	'plrid'		=> '=',
	%$PS::Role::TYPES 
};

# override parent methods to combine types
sub get_types { return { %{$_[0]->SUPER::get_types}, %$TYPES } }
sub get_types_maps { return { %{$_[0]->SUPER::get_types_maps}, %$TYPES_MAPS } }
sub get_types_roles { return { %{$_[0]->SUPER::get_types_roles}, %$TYPES_ROLES } }

# allows the parent to determine our local types
sub mod_types { $TYPES };
sub mod_types_maps { $TYPES_MAPS };
sub mod_types_roles { $TYPES_ROLES };

sub has_mod_tables { 1 }

sub has_roles { 1 }

1;
