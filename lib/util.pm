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
#	$Id: util.pm 514 2008-07-07 18:19:50Z lifo $
#
package util;

use 5.006;
use strict;
#use warnings;
use POSIX qw( strftime );
use Time::HiRes qw( gettimeofday tv_interval );
use Time::Local;
use Data::Dumper;

require Exporter;

our $VERSION = '1.20.' . (('$Rev: 514 $' =~ /(\d+)/)[0] || '000');

our @ISA = qw(Exporter);

our %EXPORT_TAGS = ( 
	'all' => [ qw(
		&ip2int	&int2ip &ipwildmask &ipnetmask &ipnetwork &ipbroadcast 
		&abbrnum &commify
		&date &diffdays_ymd &ymd2time &time2ymd &daysinmonth &isleapyear &dayofyear
		&compacttime
		&simple_interpolate &expandlist &trim
		&iswindows
		&bench &print_r
	) ],

	'win' => [ qw(
		&iswindows
	) ],

	# :net exports functions dealing with network ipaddrs
	'net' => [ qw(
		&ip2int	&int2ip &ipwildmask &ipnetmask &ipnetwork &ipbroadcast
	) ],

	# :strings exports functions dealing with strings
	'strings' => [ qw(
		&simple_interpolate &expandlist &trim
	) ],

	# :numbers exports functions dealing with numbers
	'numbers' => [ qw(
		&abbrnum &commify
	) ], 

	# :date exports functions dealing with dates
	'date' => [ qw(
		&date &diffdays_ymd &daysinmonth &isleapyear &dayofyear 
		&time2ymd &ymd2time
	) ],

	# :time exports functions dealing with time
	'time' => [ qw(
		&compacttime 
		&ymd2time &time2ymd
		&bench
	) ],
);

our @EXPORT_OK = ( @{$EXPORT_TAGS{'all'}} );

our @EXPORT = qw( );

# Converts an IP "1.2.3.4" into a 32bit integer. Ignores any :port on the IP
sub ip2int {
	my ($ip, $port) = split(/:/, shift, 2);		# strip off any port if it's present
	my ($i1,$i2,$i3,$i4) = split(/\./, $ip);
	return ($i4) | ($i3 << 8) | ($i2 << 16) | ($i1 << 24);
}

# Converts a 32bit integer into its IP "1.2.3.4" representation
sub int2ip {
	my $num = shift;
	return join(".", 
		($num & 0xFF000000) >> 24,
		($num & 0x00FF0000) >> 16,
		($num & 0x0000FF00) >> 8,
		($num & 0x000000FF)
	);
}

# returns the network mask for the bits specified (1..32)
sub ipnetmask {
	my $bits = shift;
	my $num = 0xFFFFFFFF;
	my $mask = ($num >> (32 - $bits)) << (32 - $bits);
	return int2ip($mask);
}

# returns the wildcard mask for the bits specified (1..32)
sub ipwildmask {
	my $num = ip2int( ipnetmask(shift) );
	$num = $num ^ 0xFFFFFFFF;
	return int2ip($num);
}

# returns the network IP of the CIDR block given
sub ipnetwork {
	my ($num, $bits) = @_;
	$num = ip2int($num) unless $num =~ /^\d+$/;
	return int2ip($num & ip2int(ipnetmask($bits)));
}

# returns the broadcast IP of the CIDR block given
sub ipbroadcast {
	my ($num, $bits) = @_;
	$num = ip2int($num) unless $num =~ /^\d+$/;
	my @ip = split(/\./, int2ip($num & ip2int(ipnetmask($bits))));
	my @wc = split(/\./, ipwildmask($bits));
	my $bc = "";
	for (my $i=0; $i < 4; $i++) { $ip[$i] += $wc[$i]; }
	return join(".",@ip);
}

# converts a large integer into KB,MB, etc totals (1024 = 1 K)
sub abbrnum {
	my ($num, $digits) = @_;
	my @size = (' B',' KB',' MB', ' GB', ' TB');
	my $i = 0;
	$digits = 0 if !defined $digits;

	return "0" . $size[0] unless $num;
	while (($num >= 1024) and ($i < 4)) {
		$num /= 1024;
		$i++;
	}
	return sprintf("%." . $digits . "f",$num) . $size[$i];
}

# returns a large number with commas
sub commify {
	my $num = reverse shift;			# reversing the string first makes things a LOT easier
	$num =~ s/(\d\d\d)(?=\d)(?!\d*\.)/$1,/g;	# insert the commas ...
	return scalar reverse $num;			# reverse it again to restore the actual number (with commas)
}  

# returns the number of days between the two dates, format: "YYYY-MM-DD"
# $char specifies the seperator used in the date, defaults to '-'
sub diffdays_ymd {
	my ($d1, $d2, $char) = @_;
	my ($date1, $date2, $diff, @ary);
	$char ||= '-';

	@ary = reverse split($char, $d1);
	$ary[1]--;
	$ary[2] -= 1900;
	$date1 = timelocal(0,0,12,@ary);

	@ary = reverse split($char, $d2);
	$ary[1]--;
	$ary[2] -= 1900;
	$date2 = timelocal(0,0,12,@ary);

	$diff = $date1 - $date2;
	return sprintf("%.0f", $diff / (60*60*24));
}

# converts a date of "YYYY-MM-DD" into a unix epoch timestamp
sub ymd2time {
	my ($date, $char) = @_;
	$char ||= '-';

	my @ary = reverse split($char, $date);
	$ary[1]--;
	$ary[2] -= 1900;
	return timelocal(0,0,12,@ary);
}

sub time2ymd {
	my ($time, $char) = @_;
	$char ||= '-';
	strftime("%Y-%m-%d", localtime($time));
}

{ 
	my @dim  = (31,28,31,30,31,30,31,31,30,31,30,31);		# static variables for dayssince1bc function ...
	my @mdim = (31,29,31,30,31,30,31,31,30,31,30,31);
	my $daysin4centuries	= 146097;				# static variables for datefrom1bc function ...
	my $daysin1century	= 36524;
	my $daysin4years	= 1461;
	my $daysin1year	= 365;

	# returns the number of days in the given month (1..12) or epoch timestamp, or undef for current epoch time
	sub daysinmonth {
		my ($year, $month) = @_;
		if (!defined $month) {	# we assume $year is an epoch timestamp, since there's no month
			($month, $year) = (localtime($year))[4,5];
			$year += 1900;
		} else {
			$month--;
		}
		return isleapyear($year) ? $mdim[$month] : $dim[$month];
	}

} # end of local date variables

# Returns true if the year given is a leap year or false otherwise. the year MUST be a 4 digit year '2003'
sub isleapyear {
	my ($year) = @_;
	return 0 unless $year % 4 == 0;
	return 1 unless $year % 100 == 0;
	return 0 unless $year % 400 == 0;
	return 1;
}

# Returns the day of the year (1 to 366)
sub dayofyear {
	my ($year, $month, $day) = @_;
	my @days = (0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334, 365);	# total days at the end of each month
	my $leapyear = 0;
	$leapyear = 1 if $month > 2 and isleapyear($year);
	return ($days[$month-1] + $day + $leapyear);
}

# Returns the date formated according to the format given (partially mimics PHPs date() function)
# one could always use the POSIX strftime() function too, which is much better than this.
my @weekdays = ('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday');
my @weekabbr = ('Sun','Mon','Tue','Wed','Thu','Fri','Sat');
my @months   = ('January','February','March','April','May','June','July','August','September','October','November','December');
my @monthabbr= ('Jan','Feb','Mar','Apr','May','June','July','Aug','Sept','Oct','Nov','Dec');
sub date {
	my $format = shift;
	my $now = shift || time();
	my ($sec,$min,$hour,$day,$mon,$year,$weekday,$yearday,$isdst) = localtime($now);
	my $ampm = '';
	$mon++;
	$year += 1900;
	$yearday++;
	my $year2k = sprintf("%02d", $year % 100);
	foreach my $val ($sec,$min,$hour,$day,$mon) { $val = '0'.$val if length($val) < 2; }
	my $tmptime = &getrealtime("$hour:00:00");
	my $hour12 = substr($tmptime, 0, 2);
	$ampm = substr($tmptime, 8, 2);

	$format =~ s/%a/lc $ampm/ge;			# am/pm
	$format =~ s/%A/uc $ampm/ge;			# AM/PM
	$format =~ s/%d/$day/ge;			# 01..31 day
	$format =~ s/%D/$weekabbr[$weekday]/ge;		# Sun..Sat 
	$format =~ s/%F/$months[$mon-1]/ge;		# Janurary..December
	$format =~ s/%h/$hour12/ge;			# 00..12 hour
	$format =~ s/%H/$hour/ge;			# 00..24 hour
	$format =~ s/%i/$min/ge;			# 00..59 minutes
	$format =~ s/%I/$isdst/ge;			# DST=0/1
	$format =~ s/%l/$weekdays[$weekday]/ge;		# Sunday..Saturday
	$format =~ s/%m/$mon/ge;			# 01..12 month
	$format =~ s/%M/$monthabbr[$mon-1]/ge;		# Jan..Dec
	$format =~ s/%r/gmtime($now)/ge;		# RFC 822 formatted date; i.e. "Thu, 21 Dec 2000 16:01:07" (no gmt diff: +0200)
	$format =~ s/%s/$sec/ge;			# 00..59 seconds
	$format =~ s/%w/$weekday/ge;			# 0..6 weekday number (0=sunday .. 6=saturday)
	$format =~ s/%Y/$year/ge;			# 2001 year
	$format =~ s/%y/$year2k/ge;			# 01 year
	$format =~ s/%z/$yearday/ge;			# 0 .. 365 day of the year

	return $format;
}

# Converts military time to standard time
sub getrealtime {
	my ($thetime) = @_;
	my ($h,$m,$s) = split(/:/,$thetime);
	my $ampm = "am";
	if ($h == 12) { $ampm = "pm"; }
		elsif ($h > 12) { $h = $h - 12; $ampm = "pm"; }
		elsif ($h == 0) { $h = 12; }
	$h = "0$h" if (length($h) < 2);
	return "$h:$m:$s" . $ampm;
}

# returns the seconds into total hours, minutes and seconds
sub compacttime {
  my ($seconds, $format) = @_;
  my ($d,$h,$m,$s) = ('00','00','00','00');
  my $str = $format || 'hh:mm:ss';
  $seconds ||= 0;
  my $old = $seconds;

  if ( ($str =~ /dd/) and ($seconds / (60*60*24)) >= 1)   { $d = sprintf("%d", $seconds / (60*60*24)); $seconds -= $d * (60*60*24)}
  if ( ($str =~ /hh/) and ($seconds / (60*60)) >= 1)      { $h = sprintf("%d", $seconds / (60*60));    $seconds -= $h * (60*60)}
  if ( ($str =~ /mm/) and ($seconds / 60) >= 1)           { $m = sprintf("%d", $seconds / 60);         $seconds -= $m * (60)}
  if ( ($str =~ /ss/) and ($seconds % 60) >= 1)           { $s = sprintf("%d", $seconds % 60);}
  $str =~ s/dd/sprintf("%02d",$d)/e;
  $str =~ s/hh/sprintf("%02d",$h)/e;
  $str =~ s/mm/sprintf("%02d",$m)/e;
  $str =~ s/ss/sprintf("%02d",$s)/e;

  return $str;
}

# A very simple version of an interpolating routine to do very simple variable substitution on a string.
# This allows for 2 levels of hash variables ONLY. ie: $key, or $key.var (but not $key.var.subvar) .. this is only meant to be 
# a SIMPLE interpolator :-) ... If a code ref is found in a $token, it will be called and it's return value used.
# This function was updated to use tokens like {$var.value} instead of $var.value
sub simple_interpolate {
	my ($str, $data, $fill) = @_;
	my ($var1,$var2, $rep, $rightpos, $leftpos, $varlen);
	$fill ||= 0;

	while ($str =~ /\{\$([a-z][a-z\d_]+)(?:\.([a-z][a-z\d_]+))?\}/gsi) {	# match $token or $key.token (but not $123token) 
		$var1 = lc $1;
		$var2 = lc($2 || '');
		$varlen = length($var1 . $var2) + 2;
		if (exists $data->{$var1}) {
			if ($var2 ne '') {
				$rep = exists $data->{$var1}{$var2} ? $data->{$var1}{$var2} : ($fill) ? "$var1.$var2" : '';
				$varlen++;					# must account for the extra '.' in the $token.var
			} else {
				$rep = $data->{$var1};
			}

			if (ref $rep eq 'CODE') {
				my $value = &$rep;
				$rep = $value;
			}
		} else {
			$rep = $fill ? $var1 : '';
		} 

		$rightpos = pos($str) - 1;
		$leftpos  = $rightpos - $varlen;
		substr($str, $leftpos, $rightpos-$leftpos+1, $rep);
	}
	return $str;
}

sub iswindows {
	return (lc substr($^O,0,-2) eq "mswin");
}

sub print_r { # mimic PHP.. sorta
	print Dumper(@_);
}

# expands a range of numbers in a list, ie: 1,5,10-20,50-100,123,140
sub expandlist {
	my ($str) = @_;
	$str =~ s/[^,\d-]//g;	# strip everything except numbers, dashes and commas
	$str =~ s/-{2,}/-/g;	# reduce duplicate dashes
	$str =~ s/,{2,}/,/g;	# reduce duplicate commas
	$str =~ s/,-|-,//g;	# remove combinations of ",-" or "-,"
	my @parts = split(/,/,$str);
	my @range = ();
	while (defined(my $part = shift @parts)) {
		my ($low, $high) = split(/-/, $part);
		if (defined $high) {
			$high = $low if $high eq '';
			if ($high > $low) {
				push(@range, $low..$high);
			} else {
				push(@range, $high..$low);
			}
		} else {
			push(@range, $low);
		}
	}

	my %uniq;
	@range = grep(!$uniq{$_}++, @range);
	return wantarray ? @range : [ @range ];
}

sub trim {
	my ($str) = @_;
	$str =~ s/^\s+//;
	$str =~ s/\s+$//;
	return $str;
}

{
 my %b = ();
 sub bench {
        my $a = $_[0];
        if (exists $b{$a}) {
                my $t = tv_interval($b{$a});
                printf("bench '$a': %0.7f seconds\n", $t);
                delete $b{$a};
		return $t;
        } else {
                $b{$a} = [ gettimeofday ];
        }
 }
}

1;
