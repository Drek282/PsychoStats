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
#	$Id: soldat.pm 500 2008-06-21 14:17:37Z lifo $
#
package PS::Map::soldat;

use strict;
use warnings;
use base qw( PS::Map );

our $VERSION = '1.00.' . (('$Rev: 500 $' =~ /(\d+)/)[0] || '000');

our $TYPES = {
	alphakills		=> '+',
	bravokills		=> '+',
	
	joinedalpha		=> '+',
	joinedbravo		=> '+',
	
	alphawon		=> '+',
	alphawonpct		=> [ percent2 => qw( alphawon bravowon ) ],
	alphalost		=> '+',
	bravowon		=> '+',
	bravowonpct		=> [ percent2 => qw( bravowon alphawon ) ],
	bravolost		=> '+',
	
	flagscaptured		=> '+',
	flagsdefended		=> '+',

	bravoflagscaptured	=> '+',
	bravoflagscapturedpct	=> [ percent => qw( bravoflagscaptured flagscaptured ) ],
	bravoflagsdefended	=> '+',
	bravoflagsdefendedpct	=> [ percent => qw( bravoflagsdefended flagsdefended ) ],
	bravoflagspickedup	=> '+',

	alphaflagscaptured	=> '+',
	alphaflagscapturedpct	=> [ percent => qw( alphaflagscaptured flagscaptured ) ],
	alphaflagsdefended	=> '+',
	alphaflagsdefendedpct	=> [ percent => qw( alphaflagsdefended flagsdefended ) ],
	alphaflagspickedup	=> '+',
};

# override parent methods to combine types
sub get_types { return { %{$_[0]->SUPER::get_types}, %$TYPES } }

# allows the parent to determine our local types
sub mod_types { $TYPES };

sub has_mod_tables { 1 }

1;
