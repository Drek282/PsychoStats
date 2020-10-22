$(document).ready(function(){
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

	// autocomplete handlers
	$('#member').keydown(member_keydown);
	$('#member-list').dblclick(member_add);

	// handler: add member to list
	$('#add-btn').click(member_add);

	// handler: remove member from list
	$('#member-table a[@id^=mem]').click(member_remove);

});

var ac_minchars = 2; 		// minimum characters required before an autocomplete request is made
var ac_delay = 1000;		// ms delay before submitting an autocomplete request
var last_keydown = null;	// last key pressed
var has_focus = false;
var ac_post = null;
function member_keydown(e) {
	last_keydown = e.keyCode;
	var list = $('#member-list');
	switch(e.keyCode) {
		case 34: // page-down
		case 40: // down
			e.preventDefault();
			if (list[0].selectedIndex+1 < list[0].options.length) list[0].selectedIndex++;
			break;
		case 33: // page-up
		case 38: // up
			e.preventDefault();
			if (list[0].selectedIndex > 0) list[0].selectedIndex--;
			break;
		case 13: // enter/return
			list.focus();
			break;
		case 16: // shift
		case 37: // left
		case 39: // right
			break;
		case  9: // tab
			e.preventDefault();
		default: // anything else .... 
			if (this.value.length+1 >= ac_minchars) {
				clearTimeout(ac_post);
				ac_post = setTimeout(member_post, ac_delay);
			}
//			alert(e.keyCode);
			break;
	}
}

function member_add(){
	var selected = $('#member-list option:selected');
	if (!selected.length) return false;
	var params = $('#member-form').serialize() + '&ajax=1';
	$.post('editclan.php', params, function(data){
		if ($.trim(data) != 'error') {
			$('#member-table tbody').append(data);
			$('#member-table a[@id^=mem]').click(member_remove);
			member_zebra();
			selected.remove();
			if ($('#member-list')[0].options.length == 0) $('#member-row').slideUp('fast');
			$('#member').focus();
		}
	});
}

function member_post() {
	if ($('#member').val().length == 0) return false;
	$.post('editclan.php', { memberlist: 1, value: $('#member').val() }, function(data){
		list = $('#member-list');
		list.html(data);
		if (list[0].options.length == 0) {
			list[0].options[0] = new Option('No matches found ...', '');
		}
		list[0].selectedIndex = -1;
		$('#member-row:not(:visible)').slideDown('fast');
		$('#member').focus();
	});
}

function member_remove(){
	var id = this.id.substr(4);
	$.post('editclan.php', { id: clanid, del: id, ajax: 1 }, function(data){
		if ($.trim(data) == 'success') {
			$('#mem-' + id).parent().parent().remove();
			member_zebra();
			$('#member').focus();
		}
	});
	return false;
}

function member_zebra() {
	$('#member-table tr').slice(1).filter(':odd').addClass('even');
	$('#member-table tr').slice(1).filter(':even').removeClass('even');
}

var icons_loading = null; // true when the AJAX request is pending a response
var icons_loaded = null;  // holds the loaded icon data
function toggle_gallery() {
	if (icons_loading) return;
	icons_loading = true;
	var gallery = $('#icon-gallery');
	gallery.slideToggle('fast');
	if (!icons_loaded) {
		$.get('ajax/iconlist.php', { t: 'img' }, function(data){
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

