sub calcskill_kill_elo {
	my ($self,$k,$v,$w) = @_;

	my $kskill = $k->skill || $self->{baseskill};
	my $vskill = $v->skill || $self->{baseskill};

	my $diff = $kskill - $vskill;			# difference in skill
	my $prob = 1 / ( 1 + 10 ** ($diff / 400) );	# find probability of kill
	my $kadj = $self->{_adj}->[-1] || 32;           # killer's K-value
	my $vadj = $self->{_adj}->[-1] || 32;           # victim's K-value
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
	
	my $kbonus = $kadj * $prob; # Killer gains as many points as victim
	my $vbonus = $vadj * $prob; # loses (ie. zero-sum, unless K-values differ)

	my $weight = $w->weight;
	if (defined $weight and $weight != 0.0 and $weight != 1.0) {
		$kbonus *= $weight;
		$vbonus *= $weight;
	}

	$kskill += $kbonus;
	$vskill -= $vbonus;

	$k->skill($kskill);
	$v->skill($vskill);
}

sub calcskill_kill_elo_init {
	my ($self) = @_;

	# initialize the adjustment levels for the ELO calculations
	$self->{_adj_onlinetime} = [];
	$self->{_adj} = [];
	foreach my $key (sort grep { /^kill_onlinetime_\d+$/ } keys %{$self->{skillcalc}}) {
		my $num = ($key =~ /(\d+)$/)[0];
		my $adjkey = "kill_adj_$num";
		next unless exists $self->{skillcalc}{$adjkey};			# only allow matching adjustments
		push(@{$self->{_adj}}, $self->{skillcalc}{$adjkey});
		push(@{$self->{_adj_onlinetime}}, $self->{skillcalc}{$key});
	}
}
