# "example" skill calculation. 
# This is an example of how to create a skill calculation formula.
# Follow the comments below for details.
#
# NOTE: 
#	calcskill_kill_NAME function is called for EVERY KILL EVENT.
#	calcskill_kill_NAME_init function is called once before any logs are processed.

# The name of the subroutine below should be the same as the filename w/o the ".pl" extension.
# The filename must always have the format of "calcskill_kill_BASENAME" where BASENAME is
# the unique name of the calculation, which is what is used as the configuration option.
sub calcskill_kill_example {
	# $self is a reference to the current PS::Game object.
	# $killer and $victim are references to PS::Player objects.
	# $weapon is a reference to a PS::Weapon object that was used to kill the victim.
	my ($self,$killer,$victim,$weapon) = @_;

	# always make sure the skill values default to the base value
	# if they have no skill to begin with.
	my $kskill = $killer->skill || $self->{baseskill};
	my $vskill = $victim->skill || $self->{baseskill};

	# do some calculations and determine the new values of skill
	# this example assigns 0 bonus to each player.
	my $vbonus = 0;
	my $kbonus = 0;

	# add the bonus to the killer
	$kskill += $kbonus;

	# subtract the bonus from the victim
	$vskill -= $vbonus;

	# apply the new absolute skill values to the players
	$killer->skill($kskill);
	$victim->skill($vskill);

	# that's it!, all done. Do not return anything from this function.
}

# optional INIT function. You do not need to include an init function 
# if you do not need to initialize any variables before logs are processed.
# The name of the init function is calcskill_kill_BASENAME_init
# where BASENAME is the same as the function above.
sub calcskill_kill_example_init {
	# $self is a reference to the current PS::Game object.
	my ($self) = @_;
}

# Any other code in this file is run from within the PS::Game context
# (eg: $self will reference the PS::Game object). However, it's not
# recommended to include any code outside of the init function. Any extra
# functions defined here are automatically a PS::Game method.
