package PS::DB::sqlite;
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
#	$Id: sqlite.pm 450 2008-05-20 11:34:52Z lifo $
#

# docs on sqlite sql functions/expressions
# http://www.sqlite.org/lang_expr.html

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

	if ($self->{dbname} !~ /\..+$/) {	# no extension? add one
		$self->{dbname} .= ".db";
	}

	# setup our database connection
	if (!$self->{dbh}) {
		my $dsn = 'DBI:SQLite:dbname=' . $self->{dbname};
		$self->{dbh} = DBI->connect($dsn, "", "", {PrintError => 0, RaiseError => 0, AutoCommit => 1});
#		DBI->trace(1, 'trace.dbi');
		croak("Error connecting to database using dsn \"$dsn\":\n" . $DBI::errstr) unless ref $self->{dbh};
	}

	# add REGEXP expression to sqlite
	$self->{dbh}->func('regexp', 2, sub {
		my ($regex, $string) = @_;
		return $string =~ /$regex/;
	}, 'create_function');

	return 1; # unless $self->{conf}->get('dbinit');

	open(SQL, "<lib/PS/DB/sqlite/basic.txt") or die $!;
	my @sql = split(";", join("", <SQL>));
	close(SQL);
#	open(SQL, "<lib/PS/DB/sqlite/") or die $!;
#	push(@sql, split(";", join("", <SQL>)));
#	close(SQL);
	foreach my $cmd (@sql) {
		$cmd =~ s/^\s+//;
		$cmd =~ s/\s+$//;
		next unless $cmd;
#		print "$cmd\n";
		$self->{dbh}->do($cmd) or die("SQLite: " . $self->{dbh}->errstr);
	}
}

sub init_database {
	my $self = shift;
#	my $drh = DBI->install_driver('mysql');

#	print Dumper(scalar $self->tableinfo('plr'));
}

# override query so it can check for parens () around table names in the FROM clause and remove them.
# mysql 4.1.11+ needs () around multiple table names in the FROM clause IF there is also a JOIN clause in the query.
# sqlite doesn't seem to like this and the query fails.
sub query {
	my ($self, $cmd) = @_;
	$cmd =~ s/FROM\s+\((.+?)\)\s+(LEFT|RIGHT|NATURAL|JOIN|WHERE|GROUP|ORDER|LIMIT)/FROM $1 $2/i;
	return $self->SUPER::query($cmd);
}

sub optimize {
	my $self = shift;
	$self->query("VACUUM");		# no table names accepted. This optimizes the entire database
}

# duplicate of PS::DB::create except I disable the attrib_null call. SQLite diffs slightly from MYSQL on "NOT NULL" columns.
# When inserting a row in SQLite, you HAVE to specify values for any column that was created with "NOT NULL" otherwise you get
# a "field may not be NULL" error. At least thats what's been happening with my tests. MYSQL on the other hand works fine.
sub create {
	my $self = shift;
	my $tablename = shift; #$self->tbl( shift );
	my $tbl = $self->{dbh}->quote_identifer( $tablename );
	my $fields = shift;
	my $order = shift || [ sort keys %$fields ];
	my $cmd = $self->_create_header($tbl);

	my $i = 0;
	foreach my $key (@$order) {
		my $type = "_type_" . $fields->{$key};
		$cmd .= "\t" . $self->{dbh}->quote_identifer($key) . " " . $self->$type($key);
#		$cmd .= " " . $self->_attrib_null(0);
		$cmd .= (++$i == @$order) ? "\n" : ",\n";
	}

	$cmd .= $self->_create_footer($tbl);
	$self->query($cmd) or $self->fatal("Error creating table $tablename: " . $self->errstr);
}

sub table_exists {
	my $self = shift;
	my $tablename = shift;
	my $list = $self->get_list("SELECT name FROM sqlite_master WHERE type='table' AND name='$tablename'");
	foreach (@$list) {
		return 1 if $_ eq $tablename;
	}
	return 0;
}

sub _explain {
	my $self = shift;
	my $tbl = $self->{dbh}->quote_identifer(shift);			# table will already have its prefix
	my $fields = {};
	my $rows = $self->get_rows_hash("PRAGMA table_info($tbl)");

	foreach my $row (@$rows) {
		$fields->{ $row->{name} } = {
			field	=> $row->{name},
			type	=> $row->{type},
			default	=> $row->{dflt_value},
			null	=> $row->{notnull},
		};
	}

#	my $indexes = $self->get_rows_hash("PRAGMA index_list($tbl)");
#	foreach my $in (@$indexes) {
#		print Dumper($self->get_rows_hash("PRAGMA index_info($in->{name})"));
#	}

	return wantarray ? %$fields : $fields;
}


# returns the SQL syntax to get the MAX value from 2 expressions
# used in DB->save_data
sub _expr_max { "max($_[1], $_[3])" }
sub _expr_min { "min($_[1], $_[3])" }

sub type { 'sqlite' }
sub subselects { $_[0]->{subselects} }
sub version { '0.0' }

1;

#CREATE TABLE ex2(
#  a VARCHAR(10),
#  b NVARCHAR(15),
#  c TEXT,
#  d INTEGER,
#  e FLOAT,
#  f BOOLEAN,
#  g CLOB,
#  h BLOB,
#  i TIMESTAMP,
#  j NUMERIC(10,5)
#  k VARYING CHARACTER (24),
#  l NATIONAL VARYING CHARACTER(16)
#);
