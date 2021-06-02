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
#	$Id: tfc.pm 450 2008-05-20 11:34:52Z lifo $
#
package PS::Map::halflife::tfc;

use strict;
use warnings;
use base qw( PS::Map::halflife );

our $VERSION = '1.00.' . (('$Rev: 450 $' =~ /(\d+)/)[0] || '000');

our $TYPES = {
	bluekills		=> '+',
	redkills		=> '+',
	greenkills		=> '+',
	yellowkills		=> '+',
	joinedblue		=> '+',
	joinedred		=> '+',
	joinedgreen		=> '+',
	joinedyellow		=> '+',
	joinedspectator		=> '+',
	redwon		=> '+',
	redwonpct		=> [ percent2 => qw( redwon bluewon ) ],
	redlost		=> '+',
	bluewon		=> '+',
	bluewonpct		=> [ percent2 => qw( bluewon redwon ) ],
	bluelost		=> '+',
	greenwon		=> '+',
	greenlost		=> '+',
	yellowwon		=> '+',
	yellowlost		=> '+',
	dustbowl_team1kills		=> '+',
	dustbowl_team2kills		=> '+',
	hunted_team1kills		=> '+',
	hunted_team2kills		=> '+',
	joineddustbowl_team1		=> '+',
	joineddustbowl_team2		=> '+',
	joinedhunted_team1		=> '+',
	joinedhunted_team2		=> '+',
	dustbowl_team1won		=> '+',
	dustbowl_team2won		=> '+',
	hunted_team1won		=> '+',
	hunted_team2won		=> '+',
	dustbowl_team1lost		=> '+',
	dustbowl_team2lost		=> '+',
	hunted_team1lost		=> '+',
	hunted_team2lost		=> '+',
    structuresbuilt => '+',
    structuresdestroyed => '+',
    capturepoint    => '+',
    mapspecial      => '+',
    bandage         => '+',
};

# override parent methods to combine types
sub get_types { return { %{$_[0]->SUPER::get_types}, %$TYPES } }

# allows the parent to determine our local types
sub mod_types { $TYPES };

sub has_mod_tables { 1 }

1;
