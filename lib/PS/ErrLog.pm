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
#	$Id: ErrLog.pm 493 2008-06-17 11:26:35Z lifo $
#
#       PS::ErrLog takes care of error reporting. All error messages are stored
#       in the database This class acts as a singleton. Only the 1st call to the
#       new constructor will create a new object. All successive calls to new
#       will return the 1st object w/o instantiating a new object.
package PS::ErrLog;

use strict;
use warnings;
use base qw( PS::Debug );
use util qw( iswindows );

use Carp;

our $VERSION = '1.01.' . (('$Rev: 493 $' =~ /(\d+)/)[0] || '000');

our $ANSI = 0;
#eval "use Term::ANSIColor";
#if (!$@) {
#	$ANSI = 1;
#}

my $ERRHANDLER = undef;		# only 1 Error handler is ever created by new{}

sub new {
	return $ERRHANDLER if defined $ERRHANDLER;
	my $proto = shift;
	my $conf = shift;
	my $class = ref($proto) || $proto;
	my $db = shift || croak("You must provide a database object to $class\->new");
	my $self = { debug => 0, class => $class, conf => $conf, db => $db };
	bless($self, $class);

	$ERRHANDLER = $self;

	if ($conf->can('get_opt')) {
		$self->{_verbose} = ($conf->get_opt('verbose') and !$conf->get_opt('quiet'));
	} else {
		$self->{_version} = 0;
	}

#	$self->debug($self->{class} . " initializing");
	return $self;
}

# If log() is called as a package method it will write to stats.log.
# If log() is called as a class method it will write to the database.
sub log {
	my $self = shift;
	my $msg = shift;
	my $severity = lc(shift || 'info');
	my $notrace = shift || 0;
	$severity = 'info' if $severity eq 'i';
	$severity = 'warning' if $severity eq 'w';
	$severity = 'fatal' if $severity eq 'f';
	$severity = 'info' unless $severity =~ /^info|warning|fatal$/;
	chomp($msg);				# remove newlines

	if ($severity eq 'fatal') {
		my $callerlevel = 6;
		my @trace;
		while ($callerlevel >= 0) {
			my ($pkg,$filename,$line) = caller($callerlevel);
			--$callerlevel && next unless defined $pkg and $line;
			push(@trace, "$pkg($line)") unless $pkg =~ /^PS::(ErrLog|Debug)/;
			$callerlevel--;
		}
		my $plaintrace = join("->", @trace);

		$msg = "Called from $plaintrace >>>\n" . $msg unless $notrace;
	}

	if (((ref $self and $self->{_verbose}) or !ref $self) or $severity ne 'info') {
		if ($ANSI) {
			print STDERR "[" . color('bold') . uc($severity) . color('reset') . "]" . (!ref $self ? '*' : '') . " $msg\n"
		} else {
			print STDERR "[" . uc($severity) . "]" . (!ref $self ? '*' : '') . " $msg\n"
		}
	}

	if (ref $self and ref $self->{db}) {
		my $nextid = $self->{db}->next_id($self->{db}->{t_errlog});
		$self->{db}->insert($self->{db}->{t_errlog}, { 'id' => $nextid, 'timestamp' => time, 'severity' => $severity, 'msg' => $msg });
		$self->truncate;
	} else {
		if (open(L, ">>stats.log")) {
			my @lines = split("\n", $msg);
			my $line1 = shift @lines;
			print L "[" . uc($severity) . "] $line1\n" . join("\n", map { " > $_" } @lines) . (@lines ? "\n" : "");
			close(L);
		}
	}

	if ($severity eq 'fatal') {
		main::exit();
	}
}

# shortcuts for logging info, warning or fatal messages
sub info { shift->log(shift, 'info', @_) }
sub warn { shift->log(shift, 'warning', @_) }
sub fatal { shift->log(shift, 'fatal', @_) }

# Simple verbose command. Only echos the output given if verbose is enabled in the config
sub verbose {
	my ($self, $msg, $no_newline) = @_;
	return unless $self->{_verbose};
	print $msg;
	print "\n" if (!$no_newline and $msg !~ /\n$/);
}

# never let the size of the errlog table grow too large. Truncate based on date and total rows
sub truncate {
	my $self = shift;
	my $maxrows = defined $_[0] ? shift : $self->{conf}->get_main('errlog.maxrows');
	my $maxdays = defined $_[0] ? shift : $self->{conf}->get_main('errlog.maxdays');
	my $db = $self->{db};
	$maxrows = 5000 unless defined $maxrows;
	$maxdays = 30 unless defined $maxdays;
	return if $maxrows eq '0' and $maxdays eq '0';		# nothing to do if both are disabled (not recommended)
	my $deleted = 0;
	if ($maxdays) {
		$db->delete($db->{t_errlog}, $db->qi('timestamp') . " < " . (time-60*60*24*$maxdays));
		$deleted++;
	}
	if ($maxrows) {
		my $total = $db->count($db->{t_errlog});
		return if $total < $maxrows;
		my $tbl = $db->{t_errlog};
		my $diff = $total - $maxrows;			# how many rows to delete
		my $id;
		my @ids;
		$db->query("SELECT " . $db->qi('id') . " FROM $tbl ORDER BY " . $db->qi('timestamp') . " LIMIT $diff");
		if ($db->{sth}) {
			$db->{sth}->bind_columns(\$id);
			while ($db->{sth}->fetch) {
				push(@ids, $id);
			}
			if (scalar @ids) {
				$db->query("DELETE FROM $tbl WHERE " . $db->qi('id') . " IN (" . join(',', @ids) . ")");
				$deleted++;
			}
		}
	}
	if ($deleted) {
		# I'd rather have this in a DESTORY block, but the database handle seems to be destroyed before the ErrLog is
		# so I have no valid DB handle at the time this object is destroyed. Owell.
		$db->optimize($db->{t_errlog}) if int(rand(20)) == 1;		# approximately 5% chance to optimize the table
	}
}

1;
