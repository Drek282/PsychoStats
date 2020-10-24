// JavaScript that drives the 'live server view' page
var server_refresh = 30000;
function servers_page_on_load() {
	$.ajaxSetup({
		timeout: 10000
	});

	online('-');
	kills('-');
	stopTimer();
	
	for (var i=0; i < servers.length; i++) {
		query(servers[i].id, true);
	}

	startTimer();
	setTimeout('servers_page_on_load()', server_refresh);
}

function toggle_menu(e) {
	var me = $(this);
	var serverid = this.id.split('-').pop();
	var prev = me.parent().siblings('.selected');
	var page = this.id.split('-')[0];
	if (!prev.length) return false;		// the selected menu link was clicked
	var prev_page = $('a', prev)[0].id.split('-')[0];
	me.parent().siblings().removeClass('selected');
	me.parent().addClass('selected');
	$('#' + prev_page + '-' + serverid).hide();
	$('#' + page + '-' + serverid).show();
	return false;
}

function startTimer() {
	$('#timer-bar').css('width', '1px').animate({
		width: '100%'
	}, {
		duration: server_refresh,
		complete: function() { startTimer() }
	});
}

function stopTimer() {
	$('#timer-bar').stop();
}

// send a query and display the result in the proper DIV wrapper 
// we also need to update the stats on the left menu
function query(id, show_load) {
	if (show_load) {
//		$('#hdr-' + id).html($('#q-hdr-' + id).html());
////		$('#menu-' + id).html($('#q-menu-' + id).html());
		$('#hdr-' + id + ' .ajax').show();
	}
	$('#timeout-' + id).hide();

	var tab = '';
	var prev = $('#menu-' + id + ' ul li.selected');
	tab = prev.length ? prev.children()[0].id.split('-')[0] : '';
	$.post('query.php', { s: id, t: tab}, function(data) {
		var srv = $('#server-wrapper-' + id);
		srv.html(data);
		$('.menu ul li a', srv).click(toggle_menu);
		online();
		kills();
		$('#hdr-' + id + ' .ajax').hide();
	});
}

function online(value) {
	if (value == null) {
		value = 0;
		for (i in info.online) {
			value += info.online[i];
		}
	} else {
		info.online = [];
	}
	$('#total-online').html(value);
}

function kills(value) {
	if (value == null) {
		value = 0;
		for (i in info.kills) {
			value += info.kills[i];
		}
	} else {
		info.kills = [];
	}
	$('#total-kills').html(value);
}
