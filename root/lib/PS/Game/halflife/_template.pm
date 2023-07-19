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
#	$Id: _template.pm 450 2008-05-20 11:34:52Z lifo $
#
#       Game template. Replace all occurances of {template} with the name of
#       this file w/o ".pm"
#

package PS::Game::halflife::{template};

use strict;
use warnings;
use base qw( PS::Game::halflife );

our $VERSION = '1.00.' . (('$Rev: 450 $' =~ /(\d+)/)[0] || '000');


sub _init { 
	my $self = shift;
	$self->SUPER::_init;

	# do special initializion here. Most mods won't need to do anything.

	return $self;
}

sub has_mod_tables { 0 }
sub has_roles { 0 }
sub has_mod_roles { 0 }

# event functions go here ...
# replace the function below with an actual event function

sub event_eventname {
	my ($self, $timestamp, $args) = @_;
	my ($match1, $match2, $match3) = @$args;
}

1;
