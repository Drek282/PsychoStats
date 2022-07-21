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
#	$Id: _template.pm 514 2008-07-07 18:19:50Z lifo $
#
# 	Award plugin template. 
# 	Use this as a starting point for any new award class.
package PS::Award::award_type;	# change "award_type" to the basename of the file

# always have these 3 lines
use base qw( PS::Award );
use strict;
use warnings;

# add/remove modules needed for your code
use Data::Dumper;
use POSIX qw( strftime );
use util qw( :date :time :strings );

# ->init_award is called right after the object is created and before any calculations are done.
# ALWAYS return a reference to our object.
sub init_award {
	my $self = shift;
	# do something useful here, if needed ...
	return $self;
}

# ->calc is called to actually perform the award calculations
# This is where most of your code and processing happens.
sub calc { 
	my $self = shift;
	my $range = shift;	# 'month', 'week' or 'day'
	my $dates = ref $_[0] ? shift : [ @_ ];
	# ...
}


# always return a true value at the end of the file
1;
