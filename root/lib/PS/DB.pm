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
#	$Id: DB.pm 552 2008-09-01 17:08:04Z lifo $
#
package PS::DB;

use strict;
use warnings;
use base qw( PS::Debug );

use Carp;
use Data::Dumper;
use DBI;

our $VERSION = '1.00.' . (('$Rev: 552 $' =~ /(\d+)/)[0] || '000');
our $AUTOLOAD;

sub new {
	my $proto = shift;
	my $class = ref($proto) || $proto;
	my $self = { debug => 0 };
	my $dbconf = ref $_[0] ? $_[0] : { @_ };

	$self->{$_} = $dbconf->{$_} foreach (qw(dbh dbtype dbhost dbport dbname dbuser dbpass dbtblprefix dbcompress));
	
	$self->{dbtype} = 'mysql' unless defined $self->{dbtype};
	$self->{dbname} = 'psychostats' unless defined $self->{dbname};
	$self->{dbtblprefix} = '' unless defined $self->{dbtblprefix};
	$self->{dbtblcompiledprefix} = $self->{dbtblprefix} . "c_";
	$self->{dbcompress} = $self->{dbcompress} ? 1 : 0;

	$class .= "::" . $self->{dbtype};		# Change the base class that we're creating
	$self->{class} = $class;

	# add our subclass into the frey ...
	eval "require $class";
	if ($@) {
		die("Database subclass '$class' has compile time errors:\n$@\n");
	}

	bless($self, $class);
#	$self->debug($self->{class} . " initializing");

	# initialize the table names
	$self->{t_awards} 		= $self->{dbtblprefix} . 'awards';
	$self->{t_awards_plrs} 		= $self->{dbtblprefix} . 'awards_plrs';
	$self->{t_clan} 		= $self->{dbtblprefix} . 'clan';
	$self->{t_clan_profile} 	= $self->{dbtblprefix} . 'clan_profile';
	$self->{t_config} 		= $self->{dbtblprefix} . 'config';
	$self->{t_config_awards}	= $self->{dbtblprefix} . 'config_awards';
	$self->{t_config_clantags}	= $self->{dbtblprefix} . 'config_clantags';
	$self->{t_config_events}	= $self->{dbtblprefix} . 'config_events';
	$self->{t_config_layout}	= $self->{dbtblprefix} . 'config_layout';
	$self->{t_config_logsources}	= $self->{dbtblprefix} . 'config_logsources';
	$self->{t_config_overlays}	= $self->{dbtblprefix} . 'config_overlays';
	$self->{t_config_plrbans} 	= $self->{dbtblprefix} . 'config_plrbans';
	$self->{t_config_plrbonuses} 	= $self->{dbtblprefix} . 'config_plrbonuses';
	$self->{t_errlog} 		= $self->{dbtblprefix} . 'errlog';
	$self->{t_heatmaps} 		= $self->{dbtblprefix} . 'heatmaps';
	$self->{t_geoip_cc}		= $self->{dbtblprefix} . 'geoip_cc';
	$self->{t_geoip_ip}		= $self->{dbtblprefix} . 'geoip_ip';
	$self->{t_live_entities} 	= $self->{dbtblprefix} . 'live_entities';
	$self->{t_live_events}	 	= $self->{dbtblprefix} . 'live_events';
	$self->{t_live_games} 		= $self->{dbtblprefix} . 'live_games';
	$self->{t_map} 			= $self->{dbtblprefix} . 'map';
	$self->{t_map_data} 		= $self->{dbtblprefix} . 'map_data';
	$self->{t_map_hourly} 		= $self->{dbtblprefix} . 'map_hourly';
	$self->{t_map_spatial} 		= $self->{dbtblprefix} . 'map_spatial';
	$self->{t_plr} 			= $self->{dbtblprefix} . 'plr';
	$self->{t_plr_aliases} 		= $self->{dbtblprefix} . 'plr_aliases';
	$self->{t_plr_bans} 		= $self->{dbtblprefix} . 'plr_bans';
	$self->{t_plr_data} 		= $self->{dbtblprefix} . 'plr_data';
	$self->{t_plr_ids} 		= $self->{dbtblprefix} . 'plr_ids';
	$self->{t_plr_ids_ipaddr}	= $self->{dbtblprefix} . 'plr_ids_ipaddr';
	$self->{t_plr_ids_name}		= $self->{dbtblprefix} . 'plr_ids_name';
	$self->{t_plr_ids_worldid}	= $self->{dbtblprefix} . 'plr_ids_worldid';
	$self->{t_plr_maps} 		= $self->{dbtblprefix} . 'plr_maps';
	$self->{t_plr_profile} 		= $self->{dbtblprefix} . 'plr_profile';
	$self->{t_plr_roles} 		= $self->{dbtblprefix} . 'plr_roles';
	$self->{t_plr_sessions} 	= $self->{dbtblprefix} . 'plr_sessions';
	$self->{t_plr_spatial} 		= $self->{dbtblprefix} . 'plr_spatial';
	$self->{t_plr_victims} 		= $self->{dbtblprefix} . 'plr_victims';
	$self->{t_plr_weapons} 		= $self->{dbtblprefix} . 'plr_weapons';
	$self->{t_plugins} 		= $self->{dbtblprefix} . 'plugins';
	$self->{t_role}			= $self->{dbtblprefix} . 'role';
	$self->{t_role_data}		= $self->{dbtblprefix} . 'role_data';
	$self->{t_search_results}	= $self->{dbtblprefix} . 'search_results';
	$self->{t_sessions} 		= $self->{dbtblprefix} . 'sessions';
	$self->{t_state} 		= $self->{dbtblprefix} . 'state';
	$self->{t_themes} 		= $self->{dbtblprefix} . 'themes';
	$self->{t_user} 		= $self->{dbtblprefix} . 'user';
	$self->{t_weapon} 		= $self->{dbtblprefix} . 'weapon';
	$self->{t_weapon_data} 		= $self->{dbtblprefix} . 'weapon_data';

	# compiled tables
	$self->{c_plr_data}	= $self->{dbtblcompiledprefix} . 'plr_data';
	$self->{c_plr_maps}	= $self->{dbtblcompiledprefix} . 'plr_maps';
	$self->{c_plr_roles}	= $self->{dbtblcompiledprefix} . 'plr_roles';
	$self->{c_plr_victims}	= $self->{dbtblcompiledprefix} . 'plr_victims';
	$self->{c_plr_weapons}	= $self->{dbtblcompiledprefix} . 'plr_weapons';

	$self->{c_map_data}	= $self->{dbtblcompiledprefix} . 'map_data';
	$self->{c_role_data}	= $self->{dbtblcompiledprefix} . 'role_data';
	$self->{c_weapon_data}	= $self->{dbtblcompiledprefix} . 'weapon_data';

	$self->init;
#	$self->init_database;

	return $self;
}

sub init { 1 }
sub init_database { 1 }			# sub class can make sure all required tables are present

sub init_tablenames {
	my $self = shift;
	my $conf = shift;

	if ($conf->get_main('gametype') and $conf->get_main('modtype')) {
		$self->{dbtblsuffix} = '_' . $conf->get_main('gametype') . '_' . $conf->get_main('modtype');
	} elsif ($conf->get_main('gametype')) {
		$self->{dbtblsuffix} = '_' . $conf->get_main('gametype');
	} else {
		$self->{dbtblsuffix} = '';
	}

	# mod extension tables
	my @mod_ext = qw( role_data map_data plr_data plr_maps plr_roles );
	if ($self->{dbtblsuffix}) {
		for (@mod_ext) {
			$self->{'t_' . $_ . '_mod'} = $self->{dbtblprefix} . $_ . $self->{dbtblsuffix};
		}
	} else {
		for (@mod_ext) {
			$self->{'t_' . $_ . '_mod'} = '';
		}
	}
}

# create a table. This routine is only used within the context of the PS3 system. It's not meant
# as a generic CREATE TABLE routine to create tables outside of PS3. 
# note: indexes must be created with the separate "create_*_index" methods AFTER the table is created
# ->create(table, fields, order)
sub create {
	my $self = shift;
	my $tablename = shift;
	my $tbl = $self->{dbh}->quote_identifier( $tablename );
	my $fields = shift;
	my $order = shift || [ sort keys %$fields ];
	my $cmd = $self->_create_header($tbl);

	my $i = 0;
	foreach my $key (@$order) {
		my $type = "_type_" . $fields->{$key};
		my $def  = "_default_" . $fields->{$key};
		$cmd .= "\t" . $self->{dbh}->quote_identifier($key) . " " . $self->$type($key);
		$cmd .= " " . $self->_attrib_null(0);
		$cmd .= " " . $self->$def;
		$cmd .= (++$i == @$order) ? "\n" : ",\n";
	}

	$cmd .= $self->_create_footer($tbl);
#	print "$cmd\n";
	$self->query($cmd) or $self->fatal("Error creating table $tablename: " . $self->errstr);
}

sub _create_header { "CREATE TABLE $_[1] (\n" }
sub _create_footer { ")" }
sub _type_uint { "INT UNSIGNED" }
sub _type_int { "INT" }
sub _type_float { "FLOAT(10,2)" }
sub _type_date { "DATE" }
sub _default_uint { "DEFAULT '0'" }
sub _default_int { "DEFAULT '0'" }
sub _default_float { "DEFAULT '0.00'" }
sub _default_date { "DEFAULT '0000-00-00'" }
sub _attrib_null { $_[1] ? "NULL" : "NOT NULL" }

# ->create_primary_index(table, cols)
sub create_primary_index { }
sub create_unique_index { }
sub create_index { }

# optimize a table. An array of table names can be given as well.
# ->optimize(table, table2, ...)
sub optimize {
	my $self = shift;
	my @tables = map { $self->{dbh}->quote_identifier( $_ ) } ref $_[0] ? @$_[0] : @_;
	$self->query("OPTIMIZE TABLE " . join(', ', @tables)) if @tables;
}

# ABSTRACT method to determine if a table already exists
# ->table_exists(table)
sub table_exists {
	my $self = shift;
	my $tbl = shift;
	$self->fatal("Abstract method called: $self->{class}::table_exists");
}

# returns the column information of a table (EXPLAIN) as a hash
# ->tableinfo(table)
sub tableinfo {
	my $self = shift;
	my $tablename = shift;
	return $self->_explain($tablename);
}

# INTERNAL: sub-classes have to override this to provide a method to 'explain' the details of a table.
# the results are returned as a hash, or undef if the table doesn't exist.
# the table name will already have the proper table prefix prepended to it (but not quoted)
# ->_explain(table)
sub _explain { $_[0]->debug("Abstract method '_explain' called"); $_[0]->fatal("Abstract method '_explain' called"); };

# takes an array ref and returns a WHERE clause that matches on each key=value pair in the array 
# using AND or OR as the glue specified.
# note: an array is used so the order of the matches remains intact (and it's faster than a hash)
# ->where([ key => value, key => value ], 'and' || 'or')
sub where {
	my $self = shift;
	my $matches = ref $_[0] eq 'ARRAY' ? shift : return $_[0];		# assume it's a string and return it
	my $andor = shift || 'AND';
	my $where = '';
	for (my $i=0; $i < @$matches; $i+=2) {
		$where .= $self->{dbh}->quote_identifier($matches->[$i]) . (defined $matches->[$i+1] ? "=" . $self->{dbh}->quote($matches->[$i+1]) : " IS NULL");
		$where .= " $andor " if $i+2 < @$matches;
	}
	$where = '1' if $where eq '';			# match anything 
	return $where;
}

# inserts a row into the table. 2nd parameter is a hash of (field => value) elements. 
# Field names and values are automatically quoted before insertion
# ->insert('table', { fields })
sub insert {
	my $self = shift;
	my $tbl = shift;
	my $fields = ref $_[0] ? shift : { @_ };
	my $noquotes = shift;
	my $dbh = $self->{dbh};
	my $res;

	if (ref $fields eq 'HASH') {
		my @keys = keys %$fields;
		if ($noquotes) {
			$res = $self->query("INSERT INTO $tbl (" . join(', ', @keys) . ") " . 
				"VALUES (" . join(', ', map { $fields->{$_} } @keys) . ")"
			);
		} else {
			$res = $self->query("INSERT INTO $tbl (" . join(', ', map { $dbh->quote_identifier($_) } @keys) . ") " . 
				"VALUES (" . join(', ', map { $dbh->quote($fields->{$_}) } @keys) . ")"
			);
		}
	} else {	# ARRAY
		my $cmd1 = "INSERT INTO $tbl (";
		my $cmd2 = ") VALUES (";
		for (my $i=0; $i < @$fields; $i+=2) {
			if ($noquotes) {
				$cmd1 .= $fields->[ $i ];
				$cmd2 .= $fields->[ $i+1 ];
			} else {
				$cmd1 .= $dbh->quote_identifier($fields->[ $i ]);
				$cmd2 .= $dbh->quote($fields->[ $i+1 ]);
			}
			if ($i+2 < @$fields) {
				$cmd1 .= ", ";
				$cmd2 .= ", ";
			}
		}
		$res = $self->query($cmd1 . $cmd2 . ")");
	}

#	$self->querydone;
	return $res;
}

# updates an existing row in the table.
# ->update('table', { key=val...}, where, noquote 0|1)
sub update {
	my $self = shift;
	my $tbl = shift;
	my $fields = shift;				# must be HASH or ARRAY ref
	my $where = shift;
	my $noquotes = shift || 0;
	my $dbh = $self->{dbh};
	my $cmd = "UPDATE $tbl SET ";

	if (ref $fields eq 'HASH') {
		if ($noquotes) {
			$cmd .= join(', ', map { $_ . "=" . $fields->{$_}  } keys %$fields);
		} else {
			$cmd .= join(', ', map { $dbh->quote_identifier($_) . "=" . $dbh->quote($fields->{$_}) } keys %$fields);
		}
	} else {	# ARRAY
		my @keys = ();
		my @values = ();
		for (my $i=0; $i < @$fields; $i+=2) {
			push(@keys, $fields->[$i]);
			push(@values, $fields->[$i+1]);
		}
		if ($noquotes) {
			for (my $i=0; $i < @$fields; $i+=2) {
				$cmd .= $fields->[$i] . "=" . $fields->[$i+1];
				$cmd .= ", " if $i+2 != @$fields;
			};
		} else {
			for (my $i=0; $i < @$fields; $i+=2) {
				$cmd .= $dbh->quote_identifier($fields->[$i]) . "=" . $dbh->quote($fields->[$i+1]);
				$cmd .= ", " if $i+2 != @$fields;
			};
		}
	}
	$cmd .= " WHERE " . $self->where($where) if defined $where;
	my $res = $self->query($cmd);
	return $res;
}

# perform a simple select on a SINGLE table. For more complex selects you must roll your own queries.
# only the first row is returned (it's assumed that calls to this method only want the first row anyway).
# the values are returned in an array. No column keys are included. 
# ->select('table', [ keys ] || 'key', where, order)
sub select {
	my $self = shift;
	my $tbl = shift;
	my $fields = shift;				# must be an array ref or single string of a field name
	my $where = shift;
	my $order = shift;				# simple string. must be formatted properly before passing in
	my $cmd = "";
	my @row = ();

	$fields = [ $fields ] unless ref $fields eq 'ARRAY';

	$cmd  = "SELECT " . join(", ", map { ($_ ne '*') ? $self->{dbh}->quote_identifier($_) : '*' } @$fields) . " FROM $tbl ";
	$cmd .= "WHERE " . $self->where($where) if defined $where;
	$cmd .= " ORDER BY $order " if defined $order;
	$cmd .= " LIMIT 1";
	$self->query($cmd);
	if ($self->{sth}) {
		@row = $self->{sth}->fetchrow_array;
	} else {
#		$self->warn($self->errstr);
	}

	return $row[0] if (scalar @$fields == 1);
	return wantarray ? @row : [ @row ];
}

# returns all rows of data from the $cmd given as a hash for each row
# ->get_rows_hash(cmd)
sub get_rows_hash {
	my $self = shift;
	my $cmd = shift;
	my @rows;

	return undef unless $self->query($cmd);

	while (my $data = $self->{sth}->fetchrow_hashref) {
		push(@rows, { %$data });			# make a copy of the hash, do not keep original reference
	}
	return wantarray ? @rows : \@rows;
}

# returns the next row of data from the $cmd given (or from a previous query) as a hash
# ->get_row_hash(cmd)
sub get_row_hash {
	my $self = shift;
	my $cmd = shift;

	$self->query($cmd) if $cmd;
#	return undef unless $self->query($cmd);
	return $self->{sth}->fetchrow_hashref;
}

# returns all rows of data from the $cmd given as an array for each row
# ->get_rows_array(cmd)
sub get_rows_array {
	my $self = shift;
	my $cmd = shift;
	my @rows;

	return undef unless $self->query($cmd);

	while (my $data = $self->{sth}->fetchrow_arrayref) {
		push(@rows, [ @$data ]);			# make a copy of the array, do not keep original reference
	}
	return wantarray ? @rows : \@rows;
}

# returns the next row of data from the $cmd given (or from a previous query) as an array
# ->get_row_array(cmd)
sub get_row_array {
	my $self = shift;
	my $cmd = shift;

	$self->query($cmd) if $cmd;
#	return undef unless $self->query($cmd);
	my $row = $self->{sth}->fetchrow_arrayref;
	return wantarray ? defined $row ? @$row : () : $row;
}

# returns an array of items. All columns from the rows returned are combined into a single array.
# mainly useful when used to return a single column from multiple rows.
# ->get_list(cmd)
sub get_list {
	my $self = shift;
	my $cmd = shift;
	my @list;

	return undef unless $self->query($cmd);

	while (my $data = $self->{sth}->fetchrow_arrayref) {
		push(@list, @$data);
	}
	return wantarray ? @list : \@list;
}

# returns the total rows in a table, optionally matching on the given WHERE clause
# ->count(table, where)
sub count {
	my $self = shift;
	my $tbl = shift;
	my $where = shift;
	my $cmd;
	my $count;

	$cmd = "SELECT COUNT(*) FROM $tbl ";
	$cmd .= "WHERE " . $self->where($where) if defined $where;
	$self->query($cmd);
	if ($self->{sth}) {
		$self->{sth}->bind_columns(\$count);
		$self->{sth}->fetch;
	} else {
#		$self->errlog($self->errstr);
	}
#	$self->querydone;
	return $count;
}

# returns the MAX() value of a variable in a table
# $var defaults to 'id'
# ->max(table, var, where)
sub max {
	my $self = shift;
	my $tbl = shift;
	my $var = shift || 'id';
	my $where = shift;
	my $max;
	my $cmd;

	$cmd = "SELECT MAX($var) FROM $tbl ";
	$cmd .= "WHERE " . $self->where($where) if defined $where;
	$self->query($cmd);
	if ($self->{sth}) {
		$self->{sth}->bind_columns(\$max);
		$self->{sth}->fetch;
	}
#	$self->querydone;
	return $max;
}

# returns the MIN() value of a variable in a table
# $var defaults to 'id'
# ->min(table, var, where)
sub min {
	my $self = shift;
	my $tbl = shift;
	my $var = shift || 'id';
	my $where = shift;
	my $min;
	my $cmd;

	$cmd = "SELECT MIN($var) FROM $tbl ";
	$cmd .= "WHERE " . $self->where($where) if defined $where;
	$self->query($cmd);
	if ($self->{sth}) {
		$self->{sth}->bind_columns(\$min);
		$self->{sth}->fetch;
	}
#	$self->querydone;
	return $min;
}

# deletes a row from the table based on the WHERE clause. 
# An ORDER clause can be given as well (however, this is mysql specific and should be avoided or overridden in sub classes)
# ->delete(table, where, order)
sub delete {
	my $self = shift;
	my $tbl = shift;
	my $where = shift;
	my $order = shift;
	my $cmd;

	$cmd = "DELETE FROM $tbl ";
	$cmd .= "WHERE " . $self->where($where) if defined $where;
	$cmd .= "ORDER BY $order " if defined $order;		# MYSQL SPECIFIC, so I'm not actually using this
	my $res = $self->query($cmd);
#	$self->querydone;
	return $res;
}

sub droptable {
	my $self = shift;
	my $tbl = shift;
	return $self->{dbh}->do("DROP TABLE $tbl");
}

sub truncate {
	my $self = shift;
	my $tbl = shift;
	return $self->{dbh}->do("TRUNCATE TABLE $tbl");
}

# returns the next usable numeric ID for a table (since we're not using auto_increment on any tables)
# $var defaults to 'id'
# ->next_id(table, var)
sub next_id {
	my $self = shift;
	my $tbl = shift;
	my $var = shift || 'id';
	my $max = $self->max($tbl, $var) || 0;
	return $max + 1;
}

#sub begin { $_[0]->{dbh}->do("BEGIN"); }
#sub commit { $_[0]->{dbh}->do("COMMIT"); }
#sub rollback { $_[0]->{dbh}->do("ROLLBACK"); }

#sub begin { $_[0]->{dbh}->begin_work; }
#sub commit { $_[0]->{dbh}->commit; }
#sub rollback { $_[0]->{dbh}->rollback; }

sub begin { }
sub commit { }
sub rollback { }

# starts a new SQL query
# ->query(cmd)
sub query {
	my $self = shift;
	my $cmd = shift;
	my ($rv, $attempts, $done);
	$self->{lastcmd} = $cmd;

	$attempts = 0;
	do {
		$self->{sth} = $self->{dbh}->prepare($cmd);
		if (!$self->{sth}) {
			$self->fatal_safe("Error preparing DB query:\n$cmd\n" . $self->errstr . "\n--end--");
		} 

		$attempts++;
		$rv = $self->{sth}->execute;

		if (!$rv) {
			# 1040 = Too many connections
			# 1053 = Server shutdown in progress (can happen with a 'kill <pid>' is issued via the mysql client)
			# 2006 = Lost connection to MySQL server during query
			# 2013 = MySQL server has gone away
			if (grep { $self->errno eq $_ } qw( 2013 2006 1053 1040 )) {
				$self->warn_safe("DB connection was lost (errno " . $self->errno . "); Attempting to reconnect #$attempts");
				sleep(1);	# small delay

				my $connect_attempts = 0;
				do {
					$connect_attempts++;
					if ($connect_attempts > 1) {
						$self->warn_safe("Re-attempting to establish a DB connection (#$connect_attempts)");
						sleep(3);
					}
					$self->connect;
				} while (!ref $self->{dbh} and $connect_attempts <= 10);
				if (!ref $self->{dbh}) {
					$self->fatal_safe("Error re-connecting to database using dsn \"$self->{dsn}\":\n" . $DBI::errstr) unless ref $self->{dbh};
				}
			} else {
				# don't try to reconnect on most errors
				$done = 1;
			}
		}
	} while (!$rv and !$done and $attempts <= 10);

	if ($rv) {
		# do nothing, allow caller to work with the statement handle directly ....
		$self->debug5(join(" ", split(/\s*\015?\012\s*/, $cmd)),20) if $self->{DEBUG};
	} else {
		# for now: FATAL ANY BAD QUERY. I'll change this to 'warning' when things are stablized
		$self->fatal_safe("Error executing DB query:\n$cmd\n" . $self->errstr . "\n--end of error--");
#		$self->fatal("Error executing DB query:\n$cmd\n" . $self->errstr . "\n--end--");
	}
	return $self->{sth};
}

# DB only calls this when a database error occurs. By intercepting this, we can try to determine
# if the last fatal error was caused by a DB connection issue and then try to re-connect
#sub fatal_safe {
#	my ($self, $msg) = @_;
#	$self->SUPER($msg);
#}

# do performs a simple non-select query and we don't care about the result if it fails
sub do {
	my $self = shift;
	my $cmd = shift;
	$self->{lastcmd} = $cmd;
	my $res = $self->{dbh}->do($cmd);
	$self->debug5(join(" ", split(/\s*\015?\012\s*/, $cmd)),20);
	return $res;
}

# _expr_* methods are called with the prototype: ($self, $quoted_key, $key, $value)
sub _expr_max { "IF($_[1] > $_[3], $_[1], $_[3])" }
sub _expr_min { "IF($_[3] < $_[1], $_[3], $_[1])" }

# _calc_* methods are called with the prototype: ($self, $quoted_key1, $quoted_key2, ...)
sub _calc_percent 	{ "ROUND(IFNULL($_[1] / $_[2] * 100, 0.00), 2)" }
sub _calc_percent2 	{ "ROUND(IFNULL($_[1] / ($_[1] + $_[2]) * 100, 0.00), 2)" }
sub _calc_ratio 	{ "IFNULL($_[1] / $_[2], $_[1])" }
sub _calc_ratio_minutes { "IFNULL($_[1] / ($_[2] / 60), 0.00)" }

sub _calc {
	my ($self, $tbl, $type) = @_;
	my $func = "_calc_" . $type->[0];
	return $self->$func( map { $self->{dbh}->quote_identifier($_) } @$type[1 .. $#$type] );
}

# updates compiled stats data. this always assume a matching row exists, if not, it does nothing.
# the ONLY variable this does not update is 'lastdate' there's no way to determine what the variable is
# until the old historical rows are deleted (at least it's not easy to do)
sub update_stats {
	my ($self, $table, $data, $types, $where) = @_;
	my ($exists, $key, $qk, $func, $set, $calcset, $t);
	my $primary = 'dataid';
	my $dbh = $self->{dbh};
	my $ok = 1;
	return unless scalar keys %$data;		# nothing to do if the hash is empty

#	$exists = $self->select($table, $primary, $where);
#	if ($exists) {
		$set = [];
		foreach $key (keys %$data) {
			next unless exists $types->{$key};
			next if ref $types->{$key};
			$t = $types->{$key};
			$qk = $dbh->quote_identifier($key);

			if ($t eq '=') {
				push(@$set, $qk, $dbh->quote($data->{$key}));
			} elsif ($t eq '+') {
				push(@$set, $qk, $qk . " + " . $data->{$key});
			} elsif ($t eq '>') {
				push(@$set, $qk, $self->_expr_max($qk, $key, $dbh->quote($data->{$key})));
			} elsif ($t eq '<') {
				push(@$set, $qk, $self->_expr_min($qk, $key, $dbh->quote($data->{$key})));
			} else {
				# unknown TYPE 
			}
		}
		if (@$set) {
			foreach $key (grep { ref $types->{$_} } keys %$types) {
				push(@$set, $dbh->quote_identifier($key), $self->_calc($table,$types->{$key}));
			}
			$ok = $self->update($table, $set, $where, 1);		# 1 = no `quotes`
		}
#	}
	return $ok;
}

# saves stats data. $statdate is only included for compiled data sets and is used to determine the firstdate and lastdate.
sub save_stats {
	my ($self, $table, $data, $types, $where, $statdate, $primary) = @_;
	my ($exists, $key, $qk, $func, $set, $calcset, $t);
	my $docalc = (index($table, $self->{dbtblcompiledprefix}) == 0);	# do not use calc'd fields on non-compiled tables
	my $dbh = $self->{dbh};
	return unless scalar keys %$data;		# nothing to do if the hash is empty
	$primary ||= 'dataid';

	$exists = $where ? $self->select($table, $primary, $where) : undef;
	if ($exists) {
		$set = [];
		foreach $key (keys %$data) {
			next unless exists $types->{$key};
			next if ref $types->{$key};
			$t = $types->{$key};
			$qk = $dbh->quote_identifier($key);

			if ($t eq '=') {
				push(@$set, $qk, $dbh->quote($data->{$key}));
			} elsif ($t eq '+') {
				push(@$set, $qk, $qk . " + " . $data->{$key});
			} elsif ($t eq '>') {
				push(@$set, $qk, $self->_expr_max($qk, $key, $dbh->quote($data->{$key})));
			} elsif ($t eq '<') {
				push(@$set, $qk, $self->_expr_min($qk, $key, $dbh->quote($data->{$key})));
			} else {
				# unknown TYPE 
			}
		}
		if (@$set) {
			if ($docalc) {
				push(@$set, 'lastdate', $dbh->quote($statdate)) if $statdate;
				foreach $key (grep { ref $types->{$_} } keys %$types) {
					push(@$set, $dbh->quote_identifier($key), $self->_calc($table,$types->{$key}));
				}
			}
			$self->update($table, $set, $where, 1);		# 1 = no `quotes`
		}
	} else {	# INSERT NEW ROW
		$exists = $self->next_id($table, $primary);
		$set = [];
		# don't add the primary key if it already exists in the @where list
		push(@$set, $primary, $exists) unless grep { $_ eq $primary } @$where;
#		push(@$set, @$where, %$data);
		push(@$set, @$where) if $where;
		foreach my $key (keys %$types) {
			push(@$set, $key, $data->{$key}) if exists $data->{$key};
#			push(@$set, $key, $data->{$key}) if exists $data->{$key} or !ref $types->{$key};
		}
		# quote the current data in the set (since we don't want the insert() sub to do it)
		for (my $i=0; $i < @$set; $i+=2) {
			$set->[$i] = $dbh->quote_identifier($set->[$i]);
			$set->[$i+1] = $dbh->quote($set->[$i+1]);
		}
		if ($docalc) {
			if ($statdate) {
				my $sd = $dbh->quote($statdate);
				push(@$set, 'firstdate', $sd, 'lastdate', $sd);
			}
			foreach $key (grep { ref $types->{$_} } keys %$types) {
#				push(@$set, map { $dbh->quote_identifier($_), 0 } grep { !exists $data->{$_} } @{$types->{$key}}[1,2]) if $self->type eq 'sqlite';
				push(@$set, $dbh->quote_identifier($key), $self->_calc($table,$types->{$key}));
			}
		}
		$self->insert($table, $set, 1);				# 1 = no `quotes`
	}
#	print $self->lastcmd,"\n\n";
	return $exists;
}

sub limit {
	my ($self,$limit,$start) = @_;
	return "" unless defined ($limit && $start);
	my $sql = "";
	if ($limit and !$start) {
		$sql = "LIMIT $limit";
	} elsif ($limit and $start) {
		$sql = "LIMIT $start,$limit";
	}
}

# returns a SQL string to use for a statement loading historical stats
# ignores calculated type keys.
sub _values {
	my ($self, $types, $expr) = @_;
	my $values = "";
	$expr ||= '+ > < ~ $';

	foreach my $key (keys %$types) {
		my $type = $types->{$key};
		if (ref $type) {
			next unless index($expr, '$') >= 0;
			# ignoring calculated fields

		} elsif ($type eq '+') {
			$values .= "SUM($key) $key, " if index($expr, '+') >= 0;
		} elsif ($type eq '>') {
			$values .= "MAX($key) $key, " if index($expr, '>') >= 0;
		} elsif ($type eq '<') {
			$values .= "MIN($key) $key, " if index($expr, '<') >= 0;
		} elsif ($type eq '~') {
			$values .= "AVG($key) $key, " if index($expr, '~') >= 0;
		} 
	}
	$values = substr($values, 0, -2) if $values; 	# trim trailing comma: ", "
	return $values;
}

# returns true if the DB object allows queries with sub-selects
sub subselects { undef }

# returns the version of the DB being used. Up to 3 parts, ie: "4.1.12"
# do not include any non-numeric values.
sub version { "0.0.0" }

sub type { "" }

# cleans up the last used statement handle and free its memory
sub querydone { undef $_[0]->{sth} }

# returns the table name with the correct prefix on it
sub tbl { $_[0]->{dbtblprefix} . $_[1] }

# returns the table name with the correct compiled prefix and suffic
#sub ctbl { $_[0]->{dbtblcompiledprefix} . $_[1] . $_[0]->{dbcompiledsuffix} }
sub ctbl { $_[0]->{dbtblcompiledprefix} . $_[1] }

# returns the last query command given
sub lastcmd { $_[0]->{lastcmd} }

# Quotes an identifier (table column name, table name, database name, etc...)
sub qi { shift->{dbh}->quote_identifier(@_) }			# ALIAS to be used from outside modules

# Quotes a value of a key ( key => 'value' }
sub quote { shift->{dbh}->quote(@_) }
###sub q { shift->{dbh}->quote(@_) }

sub errstr { $_[0]->{dbh}->errstr }
sub errno { 0 }

sub _disabled_AUTOLOAD {
	my $self = ref($_[0]) =~ /::/ ? shift : undef;
	my $meth = $AUTOLOAD;
	$meth =~ s/.*:://;
	return if $meth eq 'DESTROY';

	print "AUTOLOAD = $meth($@) -- " . join(',',caller) . "\n"; 
	return;

	# no object? Then we're trying to call a normal function somewhere in this class file
	if (!defined $self) {
		my ($pkg,$filename,$line) = caller;
		die("Undefined subroutine $meth called at $filename line $line.\n");
	}

	# I don't really need this AUTOLOAD functionality, since it just makes me lazy. I should remove this.....
	if (defined $self->{dbh} and $self->{dbh}->can($meth)) {		# propagate method call to DBI
		print "AUTOLOAD method $meth\n";
		return $self->{dbh}->$meth(@_);
	} elsif (defined $self->{dbh} and exists $self->{dbh}{$meth}) {		# set/get a variable within the DBI
		print "AUTOLOAD attribute $meth\n";
		return @_ ? $self->{dbh}{$meth} = shift : $self->{dbh}{$meth};
	} else {
		$self->errlog("DB method '$meth' not defined!", 'fatal') if $self->can('errlog');
	}
}


1;

