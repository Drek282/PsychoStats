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
#	$Id: tf2.pm 450 2008-05-20 11:34:52Z lifo $
#
package PS::Map::halflife::tf2;

use strict;
use warnings;
use base qw( PS::Map::halflife );

our $VERSION = '1.00.' . (('$Rev: 450 $' =~ /(\d+)/)[0] || '000');

our $TYPES = {
	redkills		=> '+',
	bluekills		=> '+',

	redwon			=> '+',
	redwonpct		=> [ percent2 => qw( redwon bluewon ) ],
	bluewon			=> '+',
	bluewonpct		=> [ percent2 => qw( bluewon redwon ) ],
	redlost			=> '+',
	bluelost		=> '+',

	assists			=> '+',
	redassists		=> '+',
	blueassists		=> '+',

	flagscaptured		=> '+',
	flagsdefended		=> '+',
#	flagsdropped		=> '+',
	captureblocked		=> '+', 
	pointcaptured		=> '+',

	redflagscaptured	=> '+',
	redflagscapturedpct	=> [ percent => qw( redflagscaptured flagscaptured ) ],
	redflagsdefended	=> '+',
	redflagsdefendedpct	=> [ percent => qw( redflagsdefended flagsdefended ) ],
	redcaptureblocked	=> '+',
	redpointcaptured	=> '+',
	redflagsdropped		=> '+',
	redflagspickedup	=> '+',

	bluecaptureblocked	=> '+',
	bluepointcaptured	=> '+',
	blueflagsdefended	=> '+',
	blueflagsdropped	=> '+',
	blueflagspickedup	=> '+',
	blueflagscaptured	=> '+',
	blueflagscapturedpct	=> [ percent => qw( blueflagscaptured flagscaptured ) ],

	itemsdestroyed		=> '+',
	dispenserdestroy	=> '+',
	sentrydestroy		=> '+',
	sapperdestroy		=> '+',
	teleporterdestroy	=> '+',

	dominations		=> '+',
	backstabkills		=> '+',

	joinedred		=> '+',
	joinedblue		=> '+',
};

# override parent methods to combine types
sub get_types { return { %{$_[0]->SUPER::get_types}, %$TYPES } }

# allows the parent to determine our local types
sub mod_types { $TYPES };

sub has_mod_tables { 1 }

1;
