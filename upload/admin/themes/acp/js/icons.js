$(document).ready(function(){
	if ($('#result').html().length > 1) $('#result').fadeIn('slow');
	$('.icon').click(click_icon);
//	$('.icon-uploaded').Pulsate(1000/3, 3); // not working with Interface 1.0 and jQuery 1.2.1
/** use this in place of Pulsate */
//	$('.icon-uploaded').fadeIn().fadeOut().fadeIn();
	$('.icon-uploaded')	// If I use fadeIn/Out jQuery forces the display to 'block' afterwards.
		// fade in and out several times to get the users attention
		.animate({ opacity: 0 })
		.animate({ opacity: 1 })
		.animate({ opacity: 0 })
		.animate({ opacity: 1 })
		.animate({ opacity: 0 })
		.animate({ opacity: 1 })
		.animate({ opacity: 0 })
		.animate({ opacity: 1 })
		.animate({ opacity: 0 })
		.animate({ opacity: 1 });
/**/
});

var result_in = false;
function click_icon(e) {
	var img = e.target;
	if (!img.src) return false;
	var file = decodeURI(img.src.split('/').pop());
	var confirm = $('#confirm');
	if (confirm.length && confirm[0].checked) {
		if (!window.confirm("Do you want to delete the '" + file + "' icon permanently?")) return false;
	}
	$.post('icons.php', { 'delete': file, 'ajax': 1 }, function(data) {
		if (data == 'success') {
			$('#result').addClass('good').removeClass('bad').html("Icon '" + file + "' was deleted successfully.");
			$(img).fadeOut('slow');
		} else {
			$('#result').addClass('bad').removeClass('good').html("Unable to delete icon '" + file + "'<br/>\n" + data.substr(0,255));
		}
		if (!result_in) {
			$('#result').fadeIn('slow');
			result_in = true;
		}

	});
	return false;	// don't let the link trigger
}
