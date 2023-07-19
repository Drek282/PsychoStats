/*
	simple JS to hide the 'success' box on any page that uses message popups after a request is completed.
*/
$(document).ready(function() {
	if ($('div.success').length) {
		setTimeout("fade_out('success')", 4000);
	}
});

function fade_out(cl) {
	var div = $('div.' + cl);
	if (!div.length) return;
//	div.SlideOutUp(5000);
	div.slideUp('fast');
//	div.hide(4000);
}
