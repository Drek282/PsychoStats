var allow_next;
$(function(){
	$('#btn-next').click(do_next);
	$('#btn-skip').click(do_skip);
	$('#btn-init').click(do_init);
	$('#config-form input.field').change(function(){ allow_next = false; });
	$('#dropdb').click(click_dropdb);
	$('input.gametype').click(click_gametype);
});

function click_gametype(e) {
	var me = $(e.target);
	var modtypes = me.parents('p:eq(0)').next('ul');
	if (!modtypes.length) {
		return;
	}
	if (me[0].checked) {
		modtypes.slideDown();
	} else {
		modtypes.slideUp();
	}
	return;
}

function click_dropdb() {
	var row = $('#overwrite-row');
	var cb = $('#overwrite');
	if (this.checked) {
		row.slideUp();
		cb.attr('disabled', 'disabled');
	} else {
		row.slideDown();
		cb.attr('disabled', '');
	}
}

function do_skip(e) {
	if (!window.confirm("Are you sure you want to skip this step?\nIf your DB is not properly initialized your PsychoStats website will not work.")) {
		e.preventDefault();
		return false;
	} else {
		$('#config-form').submit();
	}
}

function do_next(e) {
	if (!allow_next) {
		do_init(e, true);
		e.preventDefault();
		return false;
	}
}

function do_init(e, go_next) {
	e.preventDefault();
	if ($('#dropdb').attr('checked')) {
		var proceed = window.confirm('All current data in the database will be lost!\nAre you sure you want to continue?');
		if (!proceed) return false;
	}
	$('#go-ctrl input:not(:hidden)').attr('disabled','disabled');
	$('#db-results').slideUp();
	$('#db-pending').slideDown('normal', function(){
		var params = $('#config-form').serialize();
		params = params.replace('&s=' + $('#step').val(), '&s=' + step);
		params += '&a=1';
		$.post('go.php', params, function(data){
			$('#db-pending, #when-ready').slideUp();
			$('#db-results').html(data).slideDown('normal', function(){
				$('#btn-back, #btn-init, #btn-skip').attr('disabled','');
				if (allow_next) $('#btn-next').attr('disabled', '');
				if (go_next && allow_next) {
//				if (allow_next) {
					$('#config-form').submit();
				}
			});
		});
	});
}
