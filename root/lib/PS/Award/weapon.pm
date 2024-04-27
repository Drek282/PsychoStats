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
#	$Id: weapon.pm 493 2008-06-17 11:26:35Z lifo $
#
package PS::Award::weapon;

use base qw( PS::Award );
use strict;
use warnings;

use Data::Dumper;
use POSIX qw( strftime );
use util qw( :date :time :strings );
use serialize;

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
	my $allowpartial = $conf->get_main("awards.allow_partial_$range");
	my $weapons = $db->get_rows_hash("SELECT * FROM $db->{t_weapon} ORDER BY name,uniqueid");
	my ($cmd, $fields);

	my ($newest) = $db->get_row_array("SELECT MAX(statdate) FROM $db->{t_plr_weapons}");

	# return if no "statdate" in t_plr_weapons
	if (!$newest) {
		$::ERR->info("Weapon award process skipped, no data in dynamic weapons data table.");
		return;
	}

	$fields = { 
		(map { $_ => "SUM(data.$_)" } keys %{$db->tableinfo($db->{t_plr_weapons})}), 
		skill => "AVG(plr.skill)",
	};
	delete @$fields{ qw( dataid plrid weaponid statdate ) };

	foreach my $weapon (@$weapons) {
		# ignore weapons with the following unique names since they're not really weapons.
		if (grep { $weapon->{uniqueid} eq $_ }
		    qw( world flashbang smokegrenade_projectile )) {
			next;
		}
		$weapon->{name} = $weapon->{uniqueid} unless $weapon->{name};
		my $interpolate = {
			weapon => $weapon,
		};
		foreach my $timestamp (@$dates) {
			my $start = strftime("%Y-%m-%d", localtime($timestamp));
			my $end = $self->end_date($range, $start);
			my $expr = simple_interpolate($a->{expr}, $fields);
			my $order = $a->{order} || 'desc';
			my $limit = 1; #$a->{limit} || '10';
			my $awardname = simple_interpolate($a->{name}, $interpolate);
			my $complete = ($end lt $newest) ? 1 : 0;

			next if (!$complete and !$allowpartial);

			$cmd  = "SELECT $expr awardvalue, plr.plrid ";
			$cmd .= "FROM ($db->{t_plr_weapons} data, $db->{t_plr} plr) ";
			$cmd .= "WHERE plr.plrid=data.plrid AND weaponid=" . $db->quote($weapon->{weaponid}) . " ";
			$cmd .= "AND plr.allowrank ";
			$cmd .= "AND (statdate BETWEEN '$start' AND '$end') ";
			$cmd .= "GROUP BY data.plrid ";
			# must use 'having' and not 'where', since we're using expressions
			$cmd .= "HAVING $a->{where} " if $a->{where};
			$cmd .= "ORDER BY 1 $order ";
			$cmd .= "LIMIT $limit ";
#			print "$cmd\n";

			$::ERR->verbose("Calc " . ($complete ? 'complete' : 'partial ') . 
				" $a->{type} award on " . sprintf("%-5s",$range) . " $start for '$awardname'");
			my $plrs = $db->get_rows_hash($cmd) || next;
#			next unless @$plrs;

			# if all players have a 0 value ignore the award
			my $total = 0;
			$total += abs($_->{awardvalue} || 0) for @$plrs;
#			next unless $total;
			$plrs = [] unless $total;

			$db->begin;
			my $id = $db->select($db->{t_awards}, 'id', 
				[ awardid => $a->{id}, awarddate => $start,
				 awardrange => $range, awardweapon => $weapon->{uniqueid} ]
			);
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
				awardtype	=> 'weapon',
				awardweapon	=> $weapon->{uniqueid},
				awardname	=> $a->{name},
				awarddate	=> $start,
				awardrange	=> $range,
				awardcomplete	=> $complete,
				interpolate	=> serialize($interpolate),
				topplrid	=> @$plrs ? $plrs->[0]{plrid} : 0,
				topplrvalue	=> @$plrs ? $plrs->[0]{awardvalue} : 0
#				topplrvalue	=> $self->format(@$plrs ? $plrs->[0]{awardvalue} : 0)
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
					value	=> $p->{awardvalue}
				});
			}
=cut
			$db->commit;
		}
	}

}


1;
