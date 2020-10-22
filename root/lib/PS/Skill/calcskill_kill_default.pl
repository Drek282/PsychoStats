# Alternative kill skill calculation originally written by Ghost.
# Implemented here by Stormtrooper on Jan 7th, 2008.
# Made the 'default' skill calculations in PS3.1 on Feb 29th, 2008.
#
sub calcskill_kill_default {
	my ($self,$k,$v,$w) = @_;
	my ($kbonus, $vbonus);

	my $kskill = $k->skill || $self->{baseskill};
	my $vskill = $v->skill || $self->{baseskill};

	# don't allow player skill to go negative ...
	$kskill = 1 if $kskill < 1;
	$vskill = 1 if $vskill < 1;

	if ($kskill > $vskill) {
		# killer is better than the victim
		$kbonus = ($kskill + $vskill)**2 / $kskill**2;
		$vbonus = $kbonus * $vskill / ($vskill + $kskill);
	} else {
		# the victim is better than the killer
		$kbonus = ($vskill + $kskill)**2 / $vskill**2 * $vskill / $kskill;
		$vbonus = $kbonus * ($vskill + $self->{baseskill}) / ($vskill + $kskill);
	}

	# do not allow the victim to lose more than X points
	$vbonus = 10 if $vbonus > 10;

	$vbonus = $vskill if $vbonus > $vskill;
	$kbonus = $kskill if $kbonus > $kskill;

	# apply weapon weight to skill bonuses
	my $weight = $w->weight;
	if ($weight) {
		$kbonus *= $weight;
		$vbonus *= $weight;
	}

	$kskill += $kbonus;
	$vskill -= $vbonus;

	$k->skill($kskill);
	$v->skill($vskill);
}
