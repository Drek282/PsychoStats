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
#	$Id: Award.pm 493 2008-06-17 11:26:35Z lifo $
#
package PS::Award;

use base qw( PS::Debug );
use strict;
use warnings;

use FindBin;
use File::Spec::Functions;
use POSIX qw( strftime );
use util qw( :all );
use Safe;

our $VERSION = '1.00.' . (('$Rev: 493 $' =~ /(\d+)/)[0] || '000');

sub new {
	my ($proto, $award, $game) = @_;
	my $baseclass = ref($proto) || $proto;
	my $self = { 
		award => $award,
		game => $game, conf => $game->{conf}, db => $game->{db},
		debug => 0, 
		safe => undef
	};
	my $class;
	my $path = catfile($FindBin::Bin,'lib','PS','Award');

	# find the required plugin object for this award
	my $awardclass = $self->{award}{class} || $self->{award}{type};
	$self->{file} = catfile($path, $awardclass . ".pm");
	if (!-f $self->{file}) {
		$@ = "Award plugin file $self->{file} does not exist!";
		return undef;
	}

	$class = "PS::Award::" . $self->{award}{type};
	eval "require $class";
	return undef if $@;

	$self->{class} = $class;
	bless($self, $class);
	return $self->_init;
}


sub _init { 
	my $self = shift;
	return $self->init_award;
}

# award plugins need to override this to initialize themselves
# return a reference to the award object ($self)
sub init_award { $_[0] }

# returns the end date relative to the start date according to the range given
sub end_date {
	my ($self, $range, $date) = @_;
	my $start = ymd2time($date);
	my $end = $start;
	if (lc $range eq 'month') {
		$end += 60*60*24 * (daysinmonth(split('-', strftime("%Y-%m", localtime))) - 1);
	} elsif (lc $range eq 'week') {
		$end += 60*60*24 * 6;
	} else {
		$end += 60*60*24;
	}
	return strftime("%Y-%m-%d", localtime($end));
}

# calculates the award with the dates and range specified
# all subclasses will want to override this
sub calc { 
	my $self = shift;
	my $range = shift;	# 'month', 'week' or 'day'
	my $dates = ref $_[0] ? shift : [ @_ ];
	# ...
}

# takes an array of dates and returns the dates that are not already marked as complete.
sub valid_dates {
	my $self = shift;
	my $range = shift;	# 'month', 'week' or 'day'
	my $dates = ref $_[0] ? shift : [ @_ ];
	my $db = $self->{db};
	my $a = $self->{award};
	my @valid;

	foreach my $date (@$dates) {
		next if $db->select($db->{t_awards}, 'awardcomplete', 
			[ awardid => $a->{id}, awarddate => time2ymd($date), awardrange => $range ]
		);
		push(@valid, $date);
	}

	return wantarray ? @valid : [ @valid ];
}

# returns the award value given with the proper formatting configured
sub format {
	my ($self, $value) = @_;
	my $format = $self->{award}{format};
	if ($format =~ /^[a-zA-Z]+$/) {		# code
		if ($format eq 'date') {
			$value = date($self->{conf}->get_theme('format.date'), $value);
		} elsif ($format eq 'datetime') {
			$value = date($self->{conf}->get_theme('format.datetime'), $value);
		} else { # commify, compacttime, ...
			if (!$self->{safe}) {
				$self->{safe} = new Safe;
				$self->{safe}->share_from('util', [qw( &commify &compacttime &int2ip &abbrnum )]);
			}
			my $ret = $self->{safe}->reval("$format('\Q$value\E')");
			if ($@) {
				$::ERR->warn("Error in award #$self->{award}{id} format '$format': $@");
			} else {
				$value = $ret;
			}
		}
		return $value;
	} elsif (index($format, '%') > -1) {	# sprintf
		return sprintf($format, $value);
	} else {				# unknown/invalid format
		return $value;
	}
}

1;

