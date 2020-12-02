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
package PS::Role::halflife::bg3;

use strict;
use warnings;
use base qw( PS::Role::halflife );

our $VERSION = '1.00.' . (('$Rev: 450 $' =~ /(\d+)/)[0] || '000');

our $TYPES = {
	assists			=> '+',
	dominations		=> '+',
	backstabkills		=> '+',
	backstabkillspct	=> [ percent => qw( backstabkills kills ) ],
	itemsbuilt		=> '+',
	itemsdestroyed		=> '+',
};

sub get_types { return { %{$_[0]->SUPER::get_types}, %$TYPES } }

sub _init {
	my $self = shift;
	$self->SUPER::_init(@_);

	$self->{mod} = {};

	return $self;
}


sub save {
	my $self = shift;
	my $db = $self->{db};
	my $dataid = $self->SUPER::save(@_) || return;

	$db->save_stats($db->{t_role_data_mod}, $self->{mod}, $TYPES, [ dataid => $dataid ]);
	$self->{mod} = {};

	return $dataid;
}

sub has_mod_tables { 1 }

1;
