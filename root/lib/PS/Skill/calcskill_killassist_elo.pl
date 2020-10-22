sub calcskill_killassist_elo {
	my ($self,$k,$v,$w) = @_;

	my $kskill = $k->skill || $self->{baseskill};
	my $vskill = $v->skill || $self->{baseskill};

	my $diff = $kskill - $vskill;			# difference in skill
	my $prob = 1 / ( 1 + 10 ** ($diff / 400) );	# find probability of kill
	my $kadj = $self->{_adj}->[-1] || 32;
	my $vadj = $self->{_adj}->[-1] || 32;
	my $kmins = int $k->totaltime / 60;
	my $vmins = int $v->totaltime / 60;
	my $idx = 0;
	foreach my $level (@{$self->{_adj_onlinetime}}) {
		if ($kmins >= $level) {
			$kadj = $self->{_adj}->[$idx];
#			print "level: " . ("\t" x $idx+1) . "$level\n";
			last;
		}
		$idx++;
	}
	$idx = 0;
	foreach my $level (@{$self->{_adj_onlinetime}}) {
		if ($vmins >= $level) {
			$vadj = $self->{_adj}->[$idx];
#			print "level: " . ("\t" x $idx+1) . "$level\n";
			last;
		}
		$idx++;
	}
	
	my $kbonus = $kadj * (1-$prob);

	# assistant gets 1/3 of the normal skill points given
	$kskill += $kbonus / 3;

	$k->skill($kskill);
}
