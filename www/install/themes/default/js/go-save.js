var save_attempted = false;
$(function(){
	// using css width: 100% on the textarea breaks in IE so I'll just do it here instead
	var c = $('#config-text');
	if ($('#manual').is(':visible')) c.width( c.parent().width() - 5 );
	$(window).resize(function(){ if ($('#manual').is(':visible')) c.width( c.parent().width() - 5 ) });
	$('#toggle-manual').click(toggle_manual);
	$('#btn-save').click(do_save);
	$('.config-frame input').keydown(keydown);
});

function keydown(e){
	if (e.keyCode == 13) {
		do_save(e);
		e.preventDefault();
		return false;
	}
}

function do_save(e) {
	e.preventDefault();
	$('#ftp-result').slideUp();
	$('#go-ctrl input:not(:hidden)').attr('disabled','disabled');
	$('#db-pending').slideDown('normal', function(){
		var params = $('#config-form').serialize();
		params = params.replace('&s=' + $('#step').val(), '&s=' + step);
		params += '&a=1';
		$.post('go.php', params, function(data){
			$('#db-pending').slideUp();
			$('body').append(data);
			$('#btn-back, #btn-save').attr('disabled','');
//			if (allow_next) 
			$('#btn-next').attr('disabled', '');
		});
	});
}

function toggle_manual(e) {
	var t = $(this);
	var m = $('#manual');
	if (m.is(':visible')) {
		m.slideUp();
	} else {
		m.slideDown();
	}
	e.preventDefault();
	return false;
}
