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
#	$Id: player.pm 493 2008-06-17 11:26:35Z lifo $
#
package PS::Award::player;

use base qw( PS::Award );
use strict;
use warnings;

use Data::Dumper;
use POSIX qw( strftime );
use util qw( :date :time :strings );

our $VERSION = '1.00.' . (('$Rev: 493 $' =~ /(\d+)/)[0] || '000');

sub init_award {
	my $self = shift;
	# do something useful here, if needed ...
	return $self;
}

sub calc { 
	my $self = shift;
	my $range = lc shift;				# 'month', 'week' or 'day'
	my $dates = $self->valid_dates($range, ref $_[0] ? shift : [ @_ ]);
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $a = $self->{award};
	my $gametype = $conf->get_main('gametype');
	my $modtype = $conf->get_main('modtype');
	my $allowpartial = $conf->get_main("awards.allow_partial_$range");
	my $tail = $gametype && $modtype ? "_${gametype}_$modtype" : $gametype ? "_$gametype" : "";
	my @mainkeys = keys %{$db->tableinfo($db->{t_plr_data})};
	my @modkeys = $db->table_exists($db->{t_plr_data} . $tail) ? keys %{$db->tableinfo($db->{t_plr_data} . $tail)} : ();
	my ($cmd, $fields);

	my ($newest) = $db->get_row_array("SELECT MAX(statdate) FROM $db->{t_plr_data}");

	# return if no "statdate" in t_plr_data
	if (!$newest) {
		$::ERR->info("Player award process skipped, no data in dynamic player data table.");
		return;
	}

	$fields = { 
		(map { $_ => "SUM(data.$_)" } @mainkeys), 
		(@modkeys ? map { $_ => "SUM(mdata.$_)" } @modkeys : ()),
		skill => "AVG(plr.skill)",
		dayskill => "AVG(data.dayskill)",
		dayrank => "AVG(data.dayrank)",
		lasttime => "MAX(data.lasttime)",
		kills_streak => "MAX(data.kills_streak)",
		deaths_streak => "MAX(data.deaths_streak)",
	};
	delete @$fields{ qw( dataid plrid statdate ) };
#	print Dumper $fields;

	foreach my $timestamp (@$dates) {
		my $start = strftime("%Y-%m-%d", localtime($timestamp));
		my $end = $self->end_date($range, $start);
		my $expr = simple_interpolate($a->{expr}, $fields);
		my $where = simple_interpolate($a->{where}, $fields);
		my $order = $a->{order} || 'desc';
		my $limit = 1; #$a->{limit} || '10';
		my $complete = ($end lt $newest) ? 1 : 0;
		# I use 'less then' (instead of 'less then or equal to' for
		# $complete above so that the award is only marked completed if
		# the newest date is the next day. Otherwise, awards would be
		# marked completed early in the morning and would not reflect
		# any stats from later in the day if awards were updated again
		# on that day.

		next if (!$complete and !$allowpartial);

		$cmd  = "SELECT $expr awardvalue, plr.*, pp.* ";
		$cmd .= "FROM ($db->{t_plr_data} data, $db->{t_plr} plr) ";
		$cmd .= "LEFT JOIN $db->{t_plr_profile} pp ON pp.uniqueid=plr.uniqueid ";
		$cmd .= "LEFT JOIN $db->{t_plr_data}$tail mdata ON mdata.dataid=data.dataid " if @modkeys;
		$cmd .= "WHERE plr.plrid=data.plrid ";
		$cmd .= "AND plr.allowrank " if $a->{rankedonly};
		$cmd .= "AND (statdate BETWEEN '$start' AND '$end') ";
		$cmd .= "GROUP BY data.plrid ";
		# must use 'having' and not 'where', since we're using expressions
		$cmd .= "HAVING $where " if $a->{where};
		$cmd .= "ORDER BY 1 $order ";
		$cmd .= "LIMIT $limit ";
#		print "$cmd\n";

		$::ERR->verbose("Calc " . ($complete ? 'complete' : 'partial ') . 
			" $a->{type} award on " . sprintf("%-5s",$range) . " $start for '$a->{name}'");
		my $plrs = $db->get_rows_hash($cmd) || next;

		# if all players have a 0 value ignore the award
		my $total = 0;
		$total += abs($_->{awardvalue} || 0) for @$plrs;
		$plrs = [] unless $total;

		$db->begin;
		my $id = $db->select($db->{t_awards}, 'id', [ awardid => $a->{id}, awarddate => $start, awardrange => $range ]);
		if ($id) {
			$db->delete($db->{t_awards}, [ id => $id ]);
			$db->delete($db->{t_awards_plrs}, [ awardid => $id ]);
		}

		if (!@$plrs) {	# do not add anything if we have no valid players
			$db->commit;
			next;
		}

		$id = $db->next_id($db->{t_awards});
		my $award = {
			id		=> $id,
			awardid		=> $a->{id},
			awardtype	=> 'player',
			awardname	=> $a->{name},
#			awardphrase	=> $a->{phrase}, #simple_interpolate($a->{phrase}, $interpolate),
			awarddate	=> $start,
			awardrange	=> $range,
			awardcomplete	=> $complete,
			topplrid	=> @$plrs ? $plrs->[0]{plrid} : 0,
			topplrvalue	=> @$plrs ? $plrs->[0]{awardvalue} : 0
#			topplrvalue	=> $self->format(@$plrs ? $plrs->[0]{awardvalue} : 0)
		};
		$db->insert($db->{t_awards}, $award);

=pod
		# we're not saving multiple players per award anymore. Only the top player above.
		my $idx = 0;
		foreach my $p (@$plrs) {
			next unless $p->{awardvalue};
			$db->insert($db->{t_awards_plrs}, {
				id	=> $db->next_id($db->{t_awards_plrs}),
				idx	=> ++$idx,
				awardid	=> $id,
				plrid	=> $p->{plrid},
				value	=> $self->format($p->{awardvalue})
			});
		}
=cut
		$db->commit;
	}

}

1;
