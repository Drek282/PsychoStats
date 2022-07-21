var allow_next;
var compiledir = null;
var nosave = null;
$(function(){
	$('#nosave').click(toggle_compiledir);
	$('#btn-test').click(do_test);
	$('#btn-next').click(do_next);
	compiledir = $('#compiledir').val();
	nosave = $('#nosave').attr('checked');
});

function do_next(e) {
	if (compiledir != $('#compiledir').val() || nosave != $('#nosave').attr('checked')) {
		nosave = $('#nosave').attr('checked');
		allow_next = false;
	}
	if (!allow_next) {
		do_test(e, true);
		e.preventDefault();
		return false;
	}
}

function do_test(e, go_next) {
	$('#go-ctrl input:not(:hidden)').attr('disabled','disabled');
	$('#db-results').slideUp();
	$('#db-pending').slideDown('normal', function(){
		var params = $('#config-form').serialize();
		params = params.replace('&s=' + $('#step').val(), '&s=' + step);
		params += '&a=1';
		compiledir = $('#compiledir').val();
		$.post('go.php', params, function(data){
			$('#db-pending').slideUp();
			$('#db-results').html(data).slideDown('normal', function(){
				$('#go-ctrl input:not(:hidden)').attr('disabled','');
//				$('#go-ctrl input:not(:hidden)').not('#btn-next').attr('disabled','');
//				if (allow_next) $('#btn-next').attr('disabled', '');
//				alert(allow_next);
				if (go_next && allow_next) {
					$('#config-form').submit();
				}
			});
		});
	});
}

function toggle_compiledir(e) {
	var c = $('#compiledir-row');
	var r = $('#reduce-warning');
	if (this.checked) {
		c.slideUp();
		r.show('normal');
	} else {
		c.slideDown();
		r.hide('normal');
	}
}
