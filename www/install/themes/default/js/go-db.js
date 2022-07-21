var allow_next;
$(function(){
	$('#btn-next').click(do_next);
	$('#btn-test').click(do_test);
	$('#config-form input.field').change(function(){ allow_next = false; });
	$('#dbq a').click(dbq_click);
});

function dbq_click(e) {
	e.preventDefault();
	$('#dbq').next().slideDown(); 
	$('#dbq em').remove(); 
	return false 
}

function do_next(e) {
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
		$.post('go.php', params, function(data){
			$('#db-pending').slideUp();
			$('#db-results').html(data).slideDown('normal', function(){
				$('#dbq a').click(dbq_click);
				$('#go-ctrl input:not(:hidden)').attr('disabled','');
//				$('#btn-test').attr('disabled','');
//				if (allow_next) $('#btn-next').attr('disabled', '');
				if (go_next && allow_next) {
					$('#config-form').submit();
				}
			});
		});
	});
}
