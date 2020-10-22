var total_to_delete = 0;
var delete_warning = 5;
$(document).ready(function(){
	// players.php: toggle all checkboxes on the page
	$('#delete-all').click(function(){
		total = $('input[@name^=del]').attr('checked', this.checked).length;
		total_to_delete = this.checked ? total : 0;
		warn = $('#delete-warning');
		(total_to_delete > delete_warning) ? warn.show() : warn.hide();
	});
	$('input[@name^=del]').click(function(){
		total_to_delete += this.checked ? 1 : -1;
		warn = $('#delete-warning');
		(total_to_delete > delete_warning) ? warn.show() : warn.hide();
	});
	$('#delete-btn').click(function(){
		if (total_to_delete == 0) return false;
		return window.confirm(delete_message);
	});

	// initialize warning incase browser is refreshed
	total_to_delete = $('input[@name^=del]:checked').length;
	warn = $('#delete-warning');
	(total_to_delete > delete_warning) ? warn.show() : warn.hide();

	// players_edit.php: display flag img
	$('#cc').keyup(function(event){
		if (this.value.length != 2) {
			$('#flag-img')[0].src = $('#blank-icon')[0].src;
		} else {
			var url = flags_url + '/' + this.value.toLowerCase() + '.png';
			// if the img exists then we set the img source to the url.
			// this prevents IE from causing a broken img appearing
			// when an unknown CC is entered.
			$.get(url, function(){
				$('#flag-img')[0].src = url;
			});
		}
	});
});

var icons_loading = null; // true when the AJAX request is pending a response
var icons_loaded = null;  // holds the loaded icon data
function toggle_gallery() {
	if (icons_loading) return;
	icons_loading = true;
	var gallery = $('#icon-gallery');
	gallery.slideToggle('fast');
	if (!icons_loaded) {
		$.get('../ajax/iconlist.php', { t: 'img' }, function(data){	// iconlist.php is a non-admin file
			icons_loaded = data;
			gallery.html(click_icon_message + "<br/>" + data);
			$('img[@id^=icon]', gallery).click(change_icon);
			icons_loading = false;
		});
	} else {
		icons_loading = false;
	}
}

function change_icon(event, blank) {
	if (!blank) {
		$('#icon-img')[0].src = this.src;
		$('#icon-input').val(decodeURI(this.alt));
	} else {
		$('#icon-img')[0].src = $('#blank-icon')[0].src;
		$('#icon-input').val('');
	}
	$('#icon-gallery').slideToggle('fast');
}



/*
var flags_loading = null; // true when the AJAX request is pending a response
var flags_loaded = null;  // holds the loaded flag data
function toggle_flags() {
	if (flags_loading) return;
	flags_loading = true;
	var gallery = $('#flag-gallery');
	gallery.slideToggle('fast');
	if (!flags_loaded) {
		$.get('../ajax/flaglist.php', { t: 'img' }, function(data){	// flaglist.php is a non-admin file
			flags_loaded = data;
			gallery.html(click_flag_message + "<br/>" + data);
			$('img', gallery).click(change_flag);
			flags_loading = false;
		});
	} else {
		flags_loading = false;
	}
}

function change_flag(event, blank) {
	if (!blank) {
		$('#flag-img')[0].src = this.src;
		$('#flag-input').val(decodeURI(this.alt));
	} else {
		$('#flag-img')[0].src = $('#blank-icon')[0].src;
		$('#flag-input').val('');
	}
	$('#flag-gallery').slideToggle('fast');
}
*/
