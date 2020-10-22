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
#	$Id: gungame.pm 514 2008-07-07 18:19:50Z lifo $
#
package PS::Player::halflife::gungame;

use strict;
use warnings;
use base qw( PS::Player::halflife::cstrike );

our $VERSION = '1.00.' . (('$Rev: 514 $' =~ /(\d+)/)[0] || '000');

our $TYPES = {
	%$PS::Player::halflife::cstrike::TYPES, 
	lvlsgained		=> '+',
	lvlslost		=> '+',
	lvlsstolen		=> '+',
	lvlsgiven		=> '+',
	winsgained		=> '+',
	winsgiven		=> '+',
	killsperlvl		=> [ ratio => qw( kills lvlsgained ) ],
	killsperwin		=> [ ratio => qw( kills winsgained ) ],
	lvlsperwin		=> [ ratio => qw( lvlsgained winsgained ) ],
	winsgainedpct		=> [ percent => qw( winsgained games ) ],
	lvlsperminute		=> [ ratio_minutes => qw( lvlsgained onlinetime ) ],
	lvlspergame		=> [ ratio => qw( lvlsgained games ) ],
};

our $TYPES_MAPS = {
	%$PS::Player::halflife::cstrike::TYPES_MAPS, 
	lvlsgained		=> '+',
	lvlsgiven		=> '+',
	lvlsstolen		=> '+',
	lvlslost		=> '+',
	winsgained		=> '+',
	winsgiven		=> '+',
	winsgainedpct		=> [ percent => qw( winsgained games ) ],
	lvlsperminute		=> [ ratio_minutes => qw( lvlsgained onlinetime ) ],
	lvlspergame		=> [ ratio => qw( lvlsgained games ) ],
};

# override parent methods to combine types
sub get_types { return { %{$_[0]->SUPER::get_types}, %$TYPES } }
sub get_types_maps { return { %{$_[0]->SUPER::get_types_maps}, %$TYPES_MAPS } }

# allows the parent to determine our local types
sub mod_types { $TYPES };
sub mod_types_maps { $TYPES_MAPS };

1;
