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
#	$Id: Config.pm 493 2008-06-17 11:26:35Z lifo $
#
#       PS::Config is responsible for loading a single 'conftype' from the
#       database. PS::ConfigHandler is used to load and keep track of several
#       'conftype' configs using PS::Config objects
package PS::Config;

use strict;
#use warnings;
use base qw( PS::Debug );
use Data::Dumper;

use Carp;

our $VERSION = '1.00.' . (('$Rev: 493 $' =~ /(\d+)/)[0] || '000');
our $AUTOLOAD;

sub new {
	my $proto = shift;
	my $db = shift;
	my $class = ref($proto) || $proto;
	my $self = { debug => 0, class => $class, dbconf => {}, conf => {}, db => $db };
	bless($self, $class);

#	$self->debug($self->{class} . " initializing");

	return $self;
}

# loads a portion of config from the database into object memory
sub load {
	my $self = shift;
	my @conftypes = ref $_[0] ? @$_[0] : @_;
	my $total = 0;					# tally how many config variables were loaded
	my $db = $self->{db};				# ALIAS
	my $vars = join(',', map { $db->qi($_) } qw( section var value ));
	my $where = join(',', map { $db->quote($_) } @conftypes);
	my $IDX = 100;					# start high to avoid clashing with possible [sections] in stats.cfg
	my $cmd;
	my ($section, $var, $value);

	$cmd = "SELECT $vars FROM $db->{t_config} WHERE conftype ";
	$cmd .= (@conftypes == 1) ? "= $where " : "IN ($where) ";
	$cmd .= "AND var IS NOT NULL ";
	$cmd .= "ORDER BY id ";
#	$cmd .= "ORDER BY section,var,idx ";

	$db->query($cmd);
	if ($db->{sth}) {
		$db->{sth}->bind_columns(\($section, $var, $value));
		while ($db->{sth}->fetch) {
			next if !defined $var or $var eq '';			# ignore empty/blank variable names
			if (!defined $section or $section eq '' or $section eq 'global') {
				_assignvar($self->{conf}, $var, $value);
			} else {
				unless (exists $self->{conf}{$section}) {
					$self->{conf}{$section}{IDX} = $IDX++;
					$self->{conf}{$section}{SECTION} = $section;
				} 
				_assignvar($self->{conf}{$section}, $var, $value);
			}
			$total++;
		}
	} else {
		$self->errlog("Error reading config from database: " . $db->errstr, 'fatal');
	}

	# validate the loaded config if there's a matching validate method
	foreach my $conftype (@conftypes) {
		my $valfunc = "_validate_$conftype";
		if ($self->can($valfunc)) {
			$self->$valfunc;
		}
	}

	return $total;
}

# each type of config loaded can have a _validate_<conftype> method to verify loaded settings are properly set.
# the _validate_* method is called automatically after the config is loaded.
sub _validate_main {
	my $self = shift;
	my $c = $self->{conf};
	$self->debug('Validating "main" config...');

	# lower-case the following options
	foreach my $v (qw( uniqueid gametype modtype plr_primary_name plr_save_on registration startofweek )) {
		$c->{$v} = lc $c->{$v};
	}

	# make sure each variable is a positive number
	my $badnum = 0;
	foreach my $v (qw( minconnected maxdays allow_username_change ignore_bots clantag_detection baseskill plr_sessions_max )) {
		if (!defined $c->{$v}) {
			$c->{$v} = 0;
		} elsif ($c->{$v} !~ /^\d+$/) {
			$badnum++;
			$self->warn("Invalid '$v' configured ($c->{$v}). Must be a positive number.");
		}
	}
	$self->fatal("You must fix your config before the stats update can continue.") if $badnum;

	# verify the 'auto.update_*' settings
	foreach my $v (map { "update_$_" } qw( awards clans maxdays ranks players )) {
		if (!defined $c->{auto}{$v}) {
			$c->{auto}{$v} = 0;
		}
		$c->{auto}{$v} = lc $c->{auto}{$v};
		if ($c->{auto}{$v} and $c->{auto}{$v} !~ /^(all|hourly|daily|weekly|monthly)$/) {
			$self->warn("Invalid 'auto.$v' configured ($c->{auto}{$v}). Must be one of: all,hourly,daily,weekly,monthly");
			$c->{auto}{$v} = 0;
		}
	}

	# I dont know if i want this method to call db->update() and change the uniqueid from 'steamid' or not ....
	$c->{uniqueid} = 'worldid' if $c->{uniqueid} eq 'steamid';
	if ($c->{uniqueid} !~ /^(worldid|name|ipaddr)$/) {
		$self->fatal("Invalid 'worldid' configured ($c->{uniqueid}). Must be one of: 'worldid', 'name', 'ipaddr'.");
#		$c->{uniqueid} = 'worldid';
	}

	if ($c->{plr_save_on} !~ /^(disconnect|round)$/) {
#		$self->fatal("Invalid 'plr_save_on' configured ($c->{plr_save_on}). Must be one of: 'disconnect', 'round'.");
		$c->{plr_save_on} = 'disconnect';
	}

	if ($c->{plr_primary_name} !~ /^(most|first|last)$/) {
#		$self->fatal("Invalid 'plr_primary_name' configured ($c->{plr_primary_name}). Must be one of: 'most', 'first', 'last'.");
		$c->{plr_primary_name} = 'most';
	}

	if ($c->{startofweek} !~ /^(sunday|monday)$/) {
#		$self->fatal("Invalid 'startofweek' configured ($c->{startofweek}). Must be one of: 'sunday', 'monday'.");
		$c->{startofweek} = 'sunday';
	}

}


# sets the config option, and returns the previous value
# ignores command line options
sub set {
	my $self = shift;
	my $old;
	my $c = $self->{conf};				# base part for var
	my $num = scalar(@_) - 1;			# how many 'parts' make up the 'variable' (excludes the last arg)

	while (@_ > 1) {
		my $var = shift;
		foreach my $subvar (split(/\./, $var)) {
			$c->{$subvar} = {} if !defined($c->{$subvar});
			if (ref $c->{$subvar}) {
				$c = $c->{$subvar};
			} else {
				$old = $c->{$subvar};
				$c->{$subvar} = shift;
				return $old;
			}
		}
	}

	$old = $c;
	$c = shift;
	return $old;
}

# ignores command line options, use AUTOLOAD method for that functionality if needed
sub get {
	my $self = shift;
	my $section = shift;
	my $var = shift;
	my $conf = $self->{conf};

#	use Data::Dumper;
#	print Dumper($conf) unless defined $section;

	if (!defined $section and !defined $var) {	# undef
		return wantarray ? %$conf : $conf;	# return the entire config hash
	} elsif (defined $section and defined $var) {	# (section, var)
		return undef unless exists $conf->{$section} and exists $conf->{$section}{$var};
		return $conf->{$section}{$var};
#	} elsif ($section =~ /^([^\.]+)\.(.+)/) {	# "section.var"
	} elsif ($section =~ /^(.+)\.(.+)/) {	# "section.var"
		$section = $1;
		$var = $2;
		return undef unless exists $conf->{$section} and exists $conf->{$section}{$var};
		return $conf->{$section}{$var};
	} else {					# var ($section will actually be the $var)
		return undef unless exists $conf->{$section};
		return $conf->{$section};
	}
}

# saves the current config settings (ignoring all command line parameters) to the database
sub save {
	croak("Method 'save' not implemented yet.");
}

# saves the current config settings (ignoring all command line parameters) to a FILE
sub savefile {
	my $self = shift;
	my %arg = (
		'filename'	=> '',
		'config'	=> {},
		'header'	=> "# Config saved on " . scalar(localtime) . "\n\n",
		(scalar @_ == 1) ? ( 'filename' => shift ) : @_
	);
	my $doclose = 1;
	my $length = 0;
	my $c = $arg{config};
	my ($sub, $key, $value, @list, @sublist);

	if (ref \$arg{filename} eq 'GLOB') {
		*FILE = \$arg{filename};
		$doclose = 0;
	} else {
		return undef unless open(FILE, ">$arg{filename}");
	}

	print FILE $arg{header} if $arg{header};

	@list = sort grep { ref $c->{$_} ne 'HASH' } keys %$c;
	foreach (@list) {
		$length = length($_) if length($_) > $length;
	}
	foreach $key (@list) {
		print FILE $self->_writevar($key, $c->{$key}, '', $length);
	}

	# now we write all non-global variables ...
#	@list = sort { ($c->{$a}{IDX} || 0) <=> ($c->{$b}{IDX} || 0) } grep { ref $c->{$_} eq 'HASH' } keys %$c;
	@list = sort grep { ref $c->{$_} eq 'HASH' } keys %$c;
	foreach $key (@list) {
		print FILE "\n[" . (defined $c->{$key}{SECTION} ? $c->{$key}{SECTION} : $key) . "]\n";
		@sublist = sort keys %{$c->{$key}};
		$length = 0;
		foreach (@sublist) {                              # get longest length of variables
			$length = length($_) if length($_) > $length;
		}
		foreach $sub (@sublist) {
			next if uc $sub eq $sub;
			print FILE $self->_writevar($sub, $c->{$key}{$sub}, '  ', $length);
		}
	}

	close(FILE) if $doclose;
	return 1;
}

# internal func for savefile(), do not call directly
sub _writevar {
	my $self = shift;
	my ($key, $value, $prefix, $length) = @_;
	my $line = "";
	if (ref $value eq 'ARRAY') {
		$line .= $self->_writevar($key, $_, $prefix, $length) foreach @$value;     # write each array element seperately
	} else {
		$line = $length ? sprintf("%-${length}s ", $key) : "$key ";
		if ($value =~ /\n/) {					# if there are newlines, we treat the variable as a var >> END block
			$value .= "\n" unless $value =~ /\n$/;		# add newline if its not present
			$line .= ">> END\n" . $value . "END\n";
		} elsif ($value =~ /^\s+/ or $value =~ /\s+$/) {	# if there are leading/trailing spaces we surround it with quotes
			$line .= "= \"$value\"\n";
		} else {						# just print it out normally
			$line .= "= $value\n";
		}
	}
	return defined $prefix ? $prefix . $line : $line;
}

# loads config from a file (stats.cfg, etc).
# can be called as a class method or module method ($conf->loadfile() or PS::Config->loadfile()
sub loadfile {
  my $self = shift;
  my %args = (
	'filename'	=> '',
	'oldconf'	=> undef,
	'fatal'		=> 1,
	'warning'	=> 1,
	'commentstr'	=> '#',
	'idx'		=> 0,
	'section'	=> 'global',
	'sectionname'	=> 'SECTION',
	'idxname'	=> 'IDX',
	'ignorequotes'	=> 0,
	'preservecase'	=> 0,
	'noarrays'	=> 0,
	(scalar @_ == 1) ? ( 'filename' => shift ) : @_
  );
  my ($var, $val, $begin, $end, $begintotal, $tell);
  my %blockend = ( '{' => '}', '[' => ']' );
  my $mainconf = defined $args{oldconf} 
	? $args{oldconf}
	: ref $self ? $self->{conf} : {};
  my $confptr = $mainconf;
  my $was_fh = 0;
  $args{section} = lc $args{section};			# make sure section names are always lowercase
							# this allows us to start at an alternate section from 'global'


  if (ref \$args{filename} eq 'GLOB') {
    *FILE = \$args{filename};
    $was_fh = 1;
  } else {
    unless (open(FILE, "<$args{filename}")) {
      if ($args{fatal} or $args{warning}) {
        carp("Error opening config file: $args{filename}: $!");
        $args{fatal} ? exit : return wantarray ? () : {};
      } 
    }
  }
  $tell = tell FILE if $was_fh;		# save current file pos

  while (<FILE>) {
    s/^\s+//;                                   			        # remove whitespace from front
    s/\s+$//;                               					# remove whitespace from end
    next if $args{commentstr} ne 'none' and /^\Q$args{commentstr}/; 		# skip comments
    next if /^$/; 								# skip blank lines
    next if not /^\[?\s*\S+\s*(>|\]|=|\{|\[)/;					# skip 'invalid' lines

    if (/^\[\s*(.+)\s*\]/) {							# [SECTION] header
      $args{section} = lc $1;
      ## create section if needed and create reference to new hash section, taking care of 'global'
      if ($args{section} ne 'global') {
	# keep order of sections as read from file
        $mainconf->{ $args{section} }{ $args{idxname} } = ++$args{idx} unless exists $mainconf->{ $args{section} };
        $confptr = $mainconf->{ $args{section} };
        $confptr->{ $args{sectionname} } = $1 unless exists $confptr->{ $args{sectionname} };		# preserve the section header case
      } else {
        $confptr = $mainconf;						# reset confptr to 'global' level of hash
      }

    } elsif (/^\s*(\S+?)\s*=\s*(.*)/) {						# VAR = VALUE
      ($var, $val) = ($1,defined $2 ? $2 : '');
      $var = lc $var unless $args{preservecase};
      $val =~ s/\s*\Q$args{commentstr}\E.*// if $args{commentstr} ne 'none'; 	# remove comments from end
      if (($var eq '$comments') and ($val ne '')) {				# change the comment str if requested
        $args{commentstr} = $val;
        next;
      }
      $val =~ s/^"(.*)"$/$1/ unless $args{ignorequotes};			# remove double quotes if present

      if ($var =~ /^([\w\d]+)\.([\w\d]+)/) {					# dot notation to specify a different SECTION
        if (lc $1 ne 'global') {						# IGNORE 'global' sections
          _assignvar($mainconf->{$1}, $2, $val, $args{noarrays});		# NOTE: use %newconf and not $confptr !
        } else {
          _assignvar($mainconf, $2, $val, $args{noarrays});
        }
      } else {									# normal variable
        _assignvar($confptr, $var, $val, $args{noarrays});
      }

    } elsif (/^\s*(\S+?)\s*>+\s*([\.\w\d]+)/) {					# VAR >> END
      ($var, $val) = ($1,$2);
      my $token = $val;
      $var = lc $var unless $args{preservecase};
      $val = '';
      while (my $line = <FILE>) {
        if ($line =~ /^\s*\Q$token\E\s*$/i) {					# matched 'END' token
          last;
        } else {
          $val .= $line;
        }
      }

      if ($var =~ /^([\w\d]+)\.([\w\d]+)/) {					# dot notation to specify a different SECTION
        if ($1 ne 'global') {							# IGNORE 'global' sections
          _assignvar($mainconf->{$1}, $2, $val, $args{noarrays});		# NOTE: use %newconf and not $confptr !
        } else {
          _assignvar($mainconf, $2, $val, $args{noarrays});
        }
      } else {									# normal variable
        _assignvar($confptr, $var, $val, $args{noarrays});
      }

    } elsif (/^\s*(\S+?)\s*([{\[])\s*(.*)/) {					# -- VAR {[ VALUE (multi-line) ]} --
      ($var, $begin, $val) = ($1,$2,defined $3 ? $3 : '');
      $end = $blockend{$begin};							# get block ending character
      $var = lc $var unless $args{preservecase};
      my $block = '';

      $begintotal = 1;
      if ($val =~ /^(.*)(\Q$end\E\s*)/) {					# var { $1 } ($2 = $end; line doesn't have to exist)
        $block = $1;
        if (defined $2) {
          $val = $end;								# set '}' so the while loop below will not run.
          $begintotal = 0;
        } else {
          $block .= "\n";
        }
      }
      while ( (($val ne $end) or ($begintotal>0)) and !eof(FILE)) {		# This runs when an {} block has more than one line
        $val = getc(FILE);							# get next char
        $begintotal-- if ($val eq $end);					# must account for nested {} blocks
        $begintotal++ if ($val eq $begin);
        $block .= $val if ($val ne $end) or ($begintotal>0);
      }
      $block =~ s/^\s+//;							# trim white space from value
      $block =~ s/\s+$//;

      if ($begin.$end eq '{}') {						# CODE block { ... } needs to be run
        my $code = $block;
        my $this = eval $code;
        if (!$@) {
          $block = (defined $this) ? $this : '';
        } else {
          &logerror("Invalid code block '$var' specified in $args{filename} ($@)",1);
        }
      }

      if ($var =~ /^([\w\d]+)\.([\w\d]+)/) {					# dot notation to specify a different SECTION
        if ($1 ne 'global') {							# IGNORE 'global' sections
          _assignvar($mainconf->{$1}, $2, $block, $args{noarrays});		# NOTE: use %newconf and not $confptr !
        } else {
          _assignvar($mainconf, $2, $block, $args{noarrays});
        }
      } else {									# normal variable
        _assignvar($confptr, $var, $block, $args{noarrays});
      }
#      _assignvar($confptr, $var, $block, $args{noarrays});			# assign final value to variable

    } ## if..else..
  } ## while(FILE) ...

  # rewind to where we started if we supplied a file handle
  if ($was_fh) {
#    print "rewinding file to byte $tell!\n";
    seek(FILE,$tell,0);
  } else {
    close(FILE);
  }

  # convert all arrays in the config to scalar strings (if noarrays is specified)
  if ($args{noarrays}) {
    foreach my $k (keys %{$mainconf}) {
      if (ref $mainconf->{$k} eq 'HASH') {						# handle sub-hashes (there can be only 2 levels;
        foreach my $k2 (keys %{$mainconf->{$k}}) {					# so there is need for recursion)
          next unless ref $mainconf->{$k}{$k2} eq 'ARRAY';
          my $ary = $mainconf->{$k}{$k2};
          $mainconf->{$k}{$k2} = join("\n", @$ary);
        }
      } else {
        next unless ref $mainconf->{$k} eq 'ARRAY';
        my $ary = $mainconf->{$k};
        $mainconf->{$k} = join("\n", @$ary);
      }
    }
  }
  return wantarray ? %{$mainconf} : $mainconf;
}

# NOT A CLASS METHOD
# internal function for loadfile(). Assigns a value to the 'var'. Automatically converts var into an array if required
sub _assignvar {
	my ($conf, $var, $val, $noary) = @_;
	if (!$noary and exists $conf->{$var}) {
		if (ref $conf->{$var} ne 'ARRAY') {
			my $old = $conf->{$var};
			$conf->{$var} = [ $old ];		# convert scalar into an array with its original value
		}
		push(@{$conf->{$var}}, $val);			# add new value to the array
	} else {
		$conf->{$var} = $val;				# single value, so we keep it as a scalar
	}
	return 1;
}

# autloaded method to allow get/set'ing of config and command line parameters from {param} and {conf}
# If a variable doesn't exist it simply returns undef w/o creating the key in either hash.
# Variables found in {param} always override {conf}.
sub AUTOLOAD {
	my $self = ref($_[0]) =~ /::/ ? shift : undef;
	my $var = $AUTOLOAD;
	$var =~ s/.*:://;
	return if $var eq 'DESTROY';
#	print "AUTOLOAD: $AUTOLOAD(@_)\n";

	# no object? Then we're trying to call a normal function somewhere in this class file
	if (!defined $self) {
		my ($pkg,$filename,$line) = caller;
		die("Undefined subroutine $var called at $filename line $line.\n");
	}

#	# command line paramters override normal config settings
#	if (defined $self->{param} and $self->{param}->exists($var)) {
#		return $self->{param}->$var(@_);
#	}
	return scalar @_ ? $self->set($var, @_) : $self->get($var);
}

1;
