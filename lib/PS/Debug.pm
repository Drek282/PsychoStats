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
#	$Id: Debug.pm 493 2008-06-17 11:26:35Z lifo $
#
#       Parent class to most anything else. This just provides very basic
#       debugging methods to all classes Don't try and create objects from this
#       class directly.
package PS::Debug;

use strict;
use warnings;
use Data::Dumper;

our $ANSI = 0;
#eval "use Term::ANSIColor";
#if (!$@) {
#	$ANSI = 1;
#}

our $VERSION = '1.00.' . (('$Rev: 493 $' =~ /(\d+)/)[0] || '000');
our $DEBUG = 0;		# enable global debugging for everything if TRUE

# may be called as a class or package method
sub debug {
	my $self = shift;
	return if ((ref $self and !$self->{debug}) and !$DEBUG and !$::DEBUG);
	my $msg = shift;
	my $minlevel = shift || 1;
	my $maxcallers = shift || 5;
	my $reqlevel = $::DEBUG || $DEBUG || (ref $self ? $self->{debug} : 0);
	return if $reqlevel < $minlevel;	# ignore event if the verbosity isn't high enough
	$msg .= "\n" unless $msg =~ /\n$/;

	my @trace = ();
	my $plaintrace = "";
	my $callerlevel = $maxcallers; # $minlevel < 5 ? 20 : 2;
	$callerlevel-- if $callerlevel > 0;
	while ($callerlevel >= 0) {
		my ($pkg,$filename,$line) = caller($callerlevel--);
		next unless defined $pkg and $line;
		push(@trace, "$pkg($line)");
	}

	pop @trace while ($trace[-1] =~ /^PS::Debug/);	# remove the PS::Debug element from the end of the trace
	$plaintrace = join("->", @trace);

	if ($::DEBUGFILE) {
		if (!open(DF, ">>", $::DEBUGFILE)) {
			print STDERR "[WARNING]* Error opening debug file $::DEBUGFILE for writting: $!\n";
			$::DEBUGFILE = undef;		# disable the DEBUGFILE (to avoid further errors)
		} else {
			print DF ('-' x $minlevel) . "> [$plaintrace] $msg";
			close(DF);
		}
	}

	print STDERR ('-' x $minlevel) . "> [$plaintrace] $msg";
}

sub debug1 { shift->debug(shift,1,@_) }
sub debug2 { shift->debug(shift,2,@_) }
sub debug3 { shift->debug(shift,3,@_) }
sub debug4 { shift->debug(shift,4,@_) }
sub debug5 { shift->debug(shift,5,@_) }

sub errlog {
	my $self = shift;
	if (ref $::ERR) {
		$::ERR->log(@_);
	} else {
		PS::ErrLog->log(@_);
	}
}
sub info  { shift->errlog(shift, 'info') }
sub warn  { shift->errlog(shift, 'warning') }
sub fatal { shift->errlog(shift, 'fatal') }

# The *_safe methods are mainly for use within the PS::DB objects. Within certain methods (ie: query) using the normal
# errlog methods will cause a deep recursion error and the script will endlessly loop.
# These methods will output the message but will not store it to the database log.
sub errlog_safe { PS::ErrLog->log(@_[1,2]) }
sub info_safe   { shift->errlog_safe(shift, 'info') }
sub warn_safe   { shift->errlog_safe(shift, 'warning') }
sub fatal_safe  { shift->errlog_safe(shift, 'fatal') }

1; 

