package PS::DB::pg;
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
#	$Id: pg.pm 450 2008-05-20 11:34:52Z lifo $
#

use strict;
use warnings;
use base qw( PS::DB );
use DBI;
use Data::Dumper;
use Carp;

our $VERSION = '1.00.' . (('$Rev: 450 $' =~ /(\d+)/)[0] || '000');

sub init {
	my $self = shift;
	return unless $self->SUPER::init;

	# setup our database connection
	if (!$self->{dbh}) {
		my $dsn = 'DBI:Pg:dbname=' . $self->{dbname};
		$dsn .= ';host=' . $self->{dbhost} if $self->{dbhost} and $self->{dbhost} ne 'localhost';
		$dsn .= ';port=' . $self->{dbport} if $self->{dbport};
#		$dsn .= ';' . $self->{dbopts} if defined $self->{dbopts};
		$self->{dbh} = DBI->connect($dsn, $self->{dbuser}, $self->{dbpass}, {PrintError => 0, RaiseError => 0, AutoCommit => 1});
		$self->fatal("Error connecting to database using dsn \"$dsn\":\n" . $DBI::errstr) unless ref $self->{dbh};
	}

	$self->query("SET datestyle TO ISO, YMD");
#	$self->query("SET search_path TO $self->{dbname},public");
}

sub init_database {
	my $self = shift;
}

sub _explain {
	my $self = shift;
	my $tbl = shift;
	my $sth = $self->{dbh}->column_info('', '', $tbl, '%');
	my $fields;
	while (my $row = $sth->fetchrow_hashref) {
		my $key = lc $row->{COLUMN_NAME};
		$fields->{$key} = {
			field	=> $key,
			null	=> int $row->{NULLABLE} ? 1 : 0,
			default => '',
		};
		if ($row->{TYPE_NAME} =~ /varying/) {
			$fields->{$key}{type} = 'varchar(' . $row->{COLUMN_SIZE} . ')';
		} else {
			$fields->{$key}{type} = $row->{TYPE_NAME};
#			$fields->{$key}{type} .= "($row->{COLUMN_SIZE})" if $row->{COLUMN_SIZE};
		}
	}
	return wantarray ? %$fields : $fields;
}

sub optimize {
	my $self = shift;
	my @tables = map { $self->{dbh}->quote_identifer($_) } ref $_[0] ? @$_[0] : @_;
	foreach (@tables) {
		$self->query("VACUUM $_");
	} 
}

sub table_exists {
	my $self = shift;
	my $tablename = shift;
	my @list = $self->{dbh}->tables('', '', '', '', {pg_noprefix => 1});
	foreach (@list) {
		return 1 if $_ eq $tablename;
	}
	return 0;
}

sub limit {
	my ($self,$limit,$start) = @_;
	return "" unless defined ($limit && $start);
	my $sql = "";
	$sql .= "LIMIT $limit" if $limit;
	$sql .= " OFFSET $start" if $start;
	return $sql;
}

sub _type_uint { "INT" }
sub _type_float { "REAL" }
sub _attrib_null { "" }

# _expr_* methods are called with the prototype: ($self, $quoted_key, $key, $value)
sub _expr_max { "CASE WHEN $_[1] > $_[3] THEN $_[1] ELSE $_[3] END" }
sub _expr_min { "CASE WHEN $_[3] < $_[1] THEN $_[3] ELSE $_[1] END" }

# _calc_* methods are called with the prototype: ($self, $quoted_key1, $quoted_key2, ...)
sub _calc_percent 	{ "COALESCE($_[1] / $_[2] * 100, 0.00)" }
sub _calc_ratio 	{ "COALESCE($_[1] / $_[2], $_[1])" }
sub _calc_ratio_minutes { "COALESCE($_[1] / ($_[2] / 60), 0.00)" }

sub _calc {
	my ($self, $tbl, $type) = @_;
	my $func = "_calc_" . $type->[0];
	return $self->$func( map { $tbl.'.'.$self->{dbh}->quote_identifier($_) } @$type[1 .. $#$type] );
}

sub type { 'pg' }
sub subselects { 1 }
sub version { '0.0' }

1;
