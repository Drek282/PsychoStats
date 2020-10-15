#!/usr/bin/perl
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
#	$Id: pslang.pl 467 2008-05-28 14:31:48Z lifo $
#

BEGIN { # FindBin isn't going to work on systems that run the stats.pl as SETUID
	use strict;
	use warnings;

	use FindBin; 
	use lib $FindBin::Bin;
	use lib $FindBin::Bin . "/lib";
}

our $VERSION = '1.00.' . (('$Rev: 467 $' =~ /(\d+)/)[0] || '000');

use strict;
use warnings;

use Cwd;
use File::Spec::Functions;
use File::Path;
use Getopt::Long;
use Data::Dumper;
use IPC::Open2;
use util qw( print_r );
use serialize;

our $args = {};
our $baselang = {};
our $newlang = {};
our $map = {};
our @phpfiles = ();
our @htmlfiles = ();

die usage() unless GetOptions(
	'<>'				=> \&GetOptions_callback,
	'dir=s'				=> \$args->{dir},		# default: '.'
	'theme=s'			=> \$args->{theme},		# default: 'default'
	'baselanguage=s'		=> \$args->{baselang},		# default: 'en_US'
	'newlanguage|language=s'	=> \$args->{language},	
	'nolang'			=> \$args->{nolang},
	'output=s'			=> \$args->{output},
	'php=s'				=> \$args->{php},		# default: '/usr/bin/php'
	'help'				=> \$args->{help},
);

die usage() if $args->{help};

$args->{dir} 		= '.' 			unless defined $args->{dir};
$args->{baselang} 	= 'en_US' 		unless defined $args->{baselang};
$args->{language}	= 'en_US'		unless defined $args->{language};
$args->{theme}		= 'default'		unless defined $args->{theme};
$args->{php}		= '/usr/bin/php'	unless defined $args->{php};
$args->{output}		= '-'			unless defined $args->{output};

my $themedir = catfile($args->{dir}, 'themes', $args->{theme},'');
warn "Base directory:  $args->{dir}\n";
warn "Theme directory: $themedir\n";
warn "Base language:   $args->{baselang}\n";
warn "\n";

# the theme directory has to exist, this implies that the base directory exists.
if (!-d $themedir) {
	die "Theme directory does not exist!\nUse -dir to specify the base directory where " . 
	    "the PsychoStats PHP front-end is located.\n";
}

# first we want to read in our base language file, if it exists
if (!$args->{nolang}) {
	my $file = catfile($themedir, 'language', $args->{baselang} . '.php');
	if (-f $file and -x $args->{php}) {
		# load the language file and serialize its language map
		my $class = "PsychoLanguage_$args->{theme}_$args->{baselang}";
		my $code = "<?php\n" . 
			"define('PSYCHOSTATS_PAGE', true);\n" . 
			"include_once('$args->{dir}/includes/class_lang.php');\n" . 
			"\@include_once('$themedir/language/$args->{baselang}.php');\n" . 
			"\$l = new $class();\n" . 
			"\$l->serialize()\n" . 
			"?>\n";
		open2(*READ, *WRITE, $args->{php});
		print WRITE $code;
		close(WRITE);
		my $text = join('', grep { !/^$/ } <READ>);
		close(READ);

		$text =~ s/^\s+//;
		$text =~ s/\s+$//;

		if (substr($text, 0, 2) eq 'a:') {
			$baselang = unserialize($text);
			if (ref $baselang ne 'HASH') {
				die "Error unserializing PHP data:\n" . Dumper($text);
			}
			warn sprintf("%d language phrases loaded from class $class.\n", scalar keys %$baselang);
		} else {
			warn "PHP returned an invalid result: $text\n";
		}
	} else {
		if (-x $args->{php}) {
			warn "Error loading base language from $file: $!\n";
		} else {
			warn "Non-executable PHP specified: $args->{php}\n";
		}
	}
}

# load a list of all PHP files to scan for strings
@phpfiles = (
	glob( catfile($args->{dir}, '*.php') ),
	glob( catfile($args->{dir}, 'ajax', '*.php') ),
	glob( catfile($args->{dir}, 'includes', '*.php') ),
	glob( catfile($args->{dir}, 'includes', '*', '*.php') ),
	glob( catfile($args->{dir}, 'includes', 'PS', '*.php') ),
	glob( catfile($args->{dir}, 'includes', 'PS', '*', '*.php') ),
	glob( catfile($args->{dir}, 'install', '*.php') ),
	glob( catfile($args->{dir}, 'plugins', '*.php') ),
);

# load a list of all theme HTML files to scan for strings
@htmlfiles = (
	glob(catfile($themedir, '*.html')), 
	glob(catfile($themedir, '*', '*.html')), 
);

# gather string translations from all PHP files
foreach my $file (@phpfiles) {
	my $source = slurpfile($file);
	while ($source =~ /->trans\((["'])(.+?)\1/g) {
		$map->{$2} = $2;
	}
}

# gather string translations from all HTML files
foreach my $file (@htmlfiles) {
	my $source = slurpfile($file);
	# match: <#a small phrase#> or <!--<#TOKEN#>-->multi-line phrase<!---->
	while ($source =~ /(<!--)?<#(.+?)#>(-->\s*(.+?)\s*<!---->)?/msg) {
		if ($4) {
			my $key = $2;
			my $text = $4;
			$text =~ s/^\s+//;
			$text =~ s/\s+$//;
			$map->{$key} = $text;
		} else {
			$map->{$2} = $2;
		}
	}
}

warn sprintf("%d translation mappings loaded.\n", scalar keys %$map);

my $code = join('', <DATA>);
my $tokens = {
	'theme'		=> $args->{theme},
	'language'	=> $args->{language},
	'baselang'	=> $args->{baselang},
	'class'		=> 'PsychoLanguage_' . $args->{theme} . '_' . $args->{language},
	'parent_class'	=> 'PsychoLanguage' . ($args->{baselang} and $args->{baselang} ne $args->{language} ? "_$args->{theme}_$args->{baselang}" : "" ),
	'map'		=> '',
	'methods'	=> '',
};
my $mapstr = '';
my $methodstr = '';

foreach my $key (sort {uc $a cmp uc $b} keys %$map) {
	my $text = $map->{$key};
	# If the string is multi-line or its more than ~200 characters (and key is a valid function name) use a method to translate it
	if (($text =~ /\n/ or length($text) > 200) and $key =~ /^[A-Z_]+[A-Z_0-9]+$/) {
		$methodstr .= trans_method($key, $text);
	} else {		# string is single-line
		$mapstr .= sprintf("\t'%s' =>\n\t\t'%s',\n", addslashes($key), $key eq $text ? '' : addslashes($text));
	}
}

$tokens->{map} = $mapstr;
$tokens->{methods} = $methodstr;
$code = interpolate($code, $tokens);

# change output to a file, if it currently points to a directory
if ($args->{output} ne '-' and -d $args->{output}) {
	$args->{output} = catfile($args->{output}, $args->{language} . ".php");
}

# output the code and we're done.
open(OUT, ">$args->{output}") or die("Error opening output file: $!");
print OUT $code;
close(OUT);
warn "Language code for $args->{language} written to $args->{output}\n" unless $args->{output} eq '-';


sub trans_method {
	my ($key, $text) = @_;
	my $code = "function $key() {\n\t\$text  = '';\n";
	my @lines = split(/\n/, $text);
	foreach my $line (@lines) {
		$line =~ s/^\s+//;		# remove leading whitespace
		$code .= "\t\$text .= '" . addslashes($line) . "' . \"\\n\";\n";
	}
	$code .= "\treturn \$text;\n}\n\n";
	return $code;
}

sub interpolate {
	my ($str, $data) = @_;
	my ($var1,$var2, $rep, $rightpos, $leftpos, $varlen);
	while ($str =~ /\{([a-z][a-z\d_]+)(?:\.([a-z][a-z\d_]+))?\}/gsi) {
		$var1 = lc $1;
		$var2 = lc($2 || '');
		$varlen = length($var1 . $var2);
		if (exists $data->{$var1}) {
			if ($var2 ne '') {
				$rep = exists $data->{$var1}{$var2} ? $data->{$var1}{$var2} : "{$var1.$var2}";
				$varlen++;					# must account for the extra '.' in the $token.var
			} else {
				$rep = $data->{$var1};
			}
		} else {
			$rep = '';
		} 

		$rightpos = pos($str) - 1;
		$leftpos  = $rightpos - $varlen - 1;
		substr($str, $leftpos, $rightpos-$leftpos+1, $rep);
	}
	return $str;
}

sub addslashes {
	my $text = shift;
	my $q = shift || "'";
	# Make sure to do the backslash first!
	$text =~ s/\\/\\\\/g;
	$text =~ s/'/\\'/g if $q eq "'";
	$text =~ s/"/\\"/g if $q eq '"';
	$text =~ s/\0/\\0/g;
	return $text;
}

sub slurpfile {
	my ($file) = @_;
	open(F, "<$file") || die "Error opening file $file: $!";
	my @lines = <F>;
	close(F);
	return wantarray ? @lines : join('', @lines);
}

sub usage {
	my $me = 'pslang.pl';
	warn $_[0] if scalar @_;
	warn "USAGE:\n";
	warn " Note: Certain features are not actually working yet.\n";
	warn " -dir <path>	Base directory where the PsychoStats PHP front-end is located\n";
	warn " -theme <theme> Theme to scan (default: default)\n";
	warn " -nolang	Do not load lanugage strings already defined in the base language\n";
	warn " -baselanguage	Specify original language (default: en_US)\n";
	warn " -language	Specify new language (default: en_US)\n";
	warn "\nExample:\n\t$me -dir /path/to/psychstats/ -theme default -o fr_FR.php\n";
	warn "\n\tThe en_UK.php file will contain english phrases to translate into french.\n";
	return "\n";
}

sub GetOptions_callback {
	$args->{args} = [] unless ref $args->{args} eq 'ARRAY';
	push(@{$args->{args}}, shift);
}

__DATA__
<?php
/*
	{language}.php
	$Id: pslang.pl 467 2008-05-28 14:31:48Z lifo $

	Language mapping for '{language}' auto-generated from pslang.pl.

	To start a new language set, copy this file to a new name using the locale or a simple name 
	representing the language (ie: chinese) as its name. A locale string is normally a 2 character
	code for the language, ie: en for english, fr for french, etc... Followed by an underscore (_) 
	then a 2 character country code for the language, ie: US, DE, PT, etc...
	For example, for french you might use "fr_FR", or for spanish use "es_US".

	Use the pslang.pl script included with PsychoStats to auto-generate a new language file like this.
*/
if (!defined("PSYCHOSTATS_PAGE")) die("Unauthorized access to " . basename(__FILE__));

// If the language translation extends another translation set then you should include
// that class file once here. This is useful for updating a translation set w/o having to define 
// every single language map if some translations are no different from the extended language.
//include_once($this->language_dir('{baselang}') . '/{language}.php');

class {class} extends {parent_class} {

function {class}() {
	$this->{parent_class}();
	// You can set a locale if you want (which will affect certain system calls)
	// however, setting a locale is not 100% portable between systems and setlocale is not
	// thread-safe. Setting the locale on a multi-threaded server (ie: apache2 using mm_worker model) 
	// will affect other threads that are running at the same time.
//	setlocale(LC_ALL, '{language}.UTF-8');

	// Every english phrase that can be translated is located here.
	// Becareful to properly escape strings so quotes are displayed properly.
	// Most strings are simple phrases or words. For more complex or larger translations, see the methods below.
	$this->map = array(
{map}
	) + $this->map;
}

// if a translation keyword maps to a method below then the matching method should return the translated string.
// This is most useful for those large blocks of text in the theme. 
{methods}

}

?>
