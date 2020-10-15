# this helps medics in TF2 get more skill since they assist more than they kill.
sub calcskill_killassist_default {
	my ($self,$k,$v,$w) = @_;
	my ($kbonus, $vbonus);

	my $kskill = $k->skill || $self->{baseskill};
	my $vskill = $v->skill || $self->{baseskill};

	# don't allow player skill to go negative ...
	$kskill = 1 if $kskill < 1;
	$vskill = 1 if $vskill < 1;

	if ($kskill > $vskill) {
		# killer is better than the victim
		$kbonus = ((($kskill + $vskill)**2 / $kskill**2));
	} else {
		# the victim is better than the killer
		$kbonus = ((($vskill + $kskill)**2 / $vskill**2 * $vskill / $kskill));
	}
	# the assistant gets 1/3 of the skill points normally given.
	$kbonus /= 3;
	$kbonus = $kskill if $kbonus > $kskill;

	$kskill += $kbonus;

	$k->skill($kskill);
}
