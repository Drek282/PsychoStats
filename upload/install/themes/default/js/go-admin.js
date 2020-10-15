var allow_next;
$(function(){
	$('#btn-next').click(do_next);
	$('#btn-create').click(do_create);
	$('.config-frame input').change(function(){ $(this).next().slideUp() });
	$('#admin-list a').click(click_admin);
	$('.config-frame input').keydown(keydown);
});

function keydown(e){
	if (e.keyCode == 13) {
		do_create(e);
		e.preventDefault();
		return false;
	}
}

function do_next(e) {
	if (!allow_next) {
		e.preventDefault();
		return false;
	}
}

function click_admin(e) {
	var a = $(this);
	var admin = a.text();
	if (!window.confirm("Are you sure you want to delete this user?\n\n\t" + admin + "\n\nThis can not be undone!")) {
		e.preventDefault();
		return false;
	}
	$('#go-ctrl input:not(:hidden)').attr('disabled','disabled');
	$('#db-results').slideUp();
	$('#db-pending p span').html("Deleting");
	$('#db-pending').slideDown('normal', function(){
		params = { s: step, a: 1, del: admin, install: $('#install-key').val() };
		$.post('go.php', params, function(data){
			$('#db-pending').slideUp();
			$('#db-results').html(data).slideDown('normal', function(){
				$('#btn-back, #btn-create').attr('disabled','');
				if (allow_next) $('#btn-next').attr('disabled', '');
			});
		});
	});
}

function do_create(e) {
	e.preventDefault();
	$('#go-ctrl input:not(:hidden)').attr('disabled','disabled');
	$('#db-results').slideUp();
	$('#db-pending p span').html("Creating");
	$('#db-pending').slideDown('normal', function(){
		var params = $('#config-form').serialize();
		params = params.replace('&s=' + $('#step').val(), '&s=' + step);
		params += '&a=1';
		$.post('go.php', params, function(data){
			$('#db-pending').slideUp();
			$('#db-results').html(data).slideDown('normal', function(){
				$('#btn-back, #btn-create').attr('disabled','');
				if (allow_next) $('#btn-next').attr('disabled', '');
			});
		});
	});
}
