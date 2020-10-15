$(document).ready(function(){
	$('#protocol').change(change_proto).keyup(change_proto);
	$('#blank').click(change_blank);
	$('#ls-table a.up, #ls-table a.dn').click(move_row);
	$('table .toggle').click(click_toggle).attr('title','Click to enable or disable');

	change_proto();
	change_blank();
});

function change_blank(e) {
	var div = $('#ls-password');
	var blank = $('#blank')[0];
	if (!blank) return;

	if (blank.checked) {
		div.hide();
	} else {
		div.show();
	}
}

function change_proto(e) {
	var proto = $('#protocol')[0];
	if (!proto) return;
	var value = proto.options[ proto.selectedIndex ].value;

	$('div[@id^=ls-]', this.form).show();
	if (proto.selectedIndex < 1 || value == '' || value == 'file') {
		$('#ls-stream,#ls-stream-opt,#ls-host,#ls-port,#ls-passive,#ls-username,#ls-blank,#ls-password').hide();
	} else if (value == 'ftp' || value == 'sftp') {
		if (value == 'sftp') $('#ls-passive').hide();
		$('#ls-stream,#ls-stream-opt,#ls-recursive').hide();
	} else if (value == 'stream') {
		$('#ls-path,#ls-passive,#ls-username,#ls-blank,#ls-password,#ls-recursive,#ls-skiplast,#ls-skiplastline').hide();
	}
}

function confirm_del(e) {
	return window.confirm("Are you sure you want to delete this log source?");
}
