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
#	$Id: bg3.pm 450 2008-05-20 11:34:52Z lifo $
#
package PS::Player::halflife::bg3;

use strict;
use warnings;
use base qw( PS::Player::halflife );

our $VERSION = '1.00.' . (('$Rev: 450 $' =~ /(\d+)/)[0] || '000');

our $TYPES = {
	redkills		=> '+',
	bluekills		=> '+',
	reddeaths		=> '+',
	bluedeaths		=> '+',

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
	redcaptureblockedpct	=> [ percent => qw( redcaptureblocked captureblocked ) ],
	redpointcaptured	=> '+',
	redpointcapturedpct	=> [ percent => qw( redpointcaptured pointcaptured ) ],
	redflagsdropped		=> '+',
	redflagspickedup	=> '+',

	blueflagscaptured	=> '+',
	blueflagscapturedpct	=> [ percent => qw( blueflagscaptured flagscaptured ) ],
	blueflagsdefended	=> '+',
	blueflagsdefendedpct	=> [ percent => qw( blueflagsdefended flagsdefended ) ],
	bluecaptureblocked	=> '+',
	bluecaptureblockedpct	=> [ percent => qw( bluecaptureblocked captureblocked ) ],
	bluepointcaptured	=> '+',
	bluepointcapturedpct	=> [ percent => qw( bluepointcaptured pointcaptured ) ],
	blueflagsdropped	=> '+',
	blueflagspickedup	=> '+',

	itemsbuilt		=> '+',
	itemsdestroyed		=> '+',
	dispenserdestroy	=> '+',
	sentrydestroy		=> '+',
	sapperdestroy		=> '+',
	teleporterdestroy	=> '+',

	dominations		=> '+',
	backstabkills		=> '+',
	backstabkillspct	=> [ percent => qw( backstabkills kills ) ],
	revenge			=> '+',
	chargedeployed		=> '+',

	joinedred		=> '+',
	joinedblue		=> '+',
};

# Player map stats are the same as the basic stats
our $TYPES_MAPS = { %$TYPES };

# Player roles only save a sub-set of stats
our $TYPES_ROLES = {
	assists			=> '+',
	dominations		=> '+',
	backstabkills		=> '+',
	backstabkillspct	=> [ percent => qw( backstabkills kills ) ],
	itemsbuilt		=> '+',
	itemsdestroyed		=> '+',
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
sub has_mod_roles { 1 }

1;
