$(document).ready(function() {
	// setup tab handler
	$('.ps-tabs ul > li').click(tab_click);

	// setup menu handler
	$('#ps-admin-menu a').click(menu_click);

	// setup delete handlers for config variables
	$('#ps-conf-form a.del').click(var_delete_click);

	// setup help handlers for config help animation blocks
	$('#ps-conf-form label.help').click(help_click);
//	$('#ps-conf-form label.help').mouseover(label_over).mouseout(label_out);

});

var help_timeouts = new Object();
var help_hovering = new Object();
var help_open = new Object();
function help_click_delayed(e) {
//	console.debug('help_click_delayed');
	var label = $(this);
	var id = label.attr('id').split('-')[1];
	if (help_timeouts[id]) return;

	help_timeouts[id] = setTimeout('help_click(undefined,"helplabel-' + id + '")', 500);
}

function help_click_cancel(e, click) {
//	console.debug('help_click_cancel(' + e + ',' + click + ',' + hover + ')');
	var label = click ? click : $(this);
	var id = label.attr('id').split('-')[1];

	if (help_timeouts[id]) {
		clearTimeout(help_timeouts[id]);
		help_timeouts[id] = undefined;
	}

	return;
}

function help_click(e, click) {
//	console.debug('help_click(' + e + ',' + click + ',' + hover + ')');
	var label = click ? click : $(this);

	help_click_cancel(e, label);

	var id = label.attr('id').split('-')[1];
	var help = $('#help-' + id);
	var row = $('#row-' + id);
	help.animate({
		height: 'toggle'//, opacity: 'toggle' // opacity is causing IE6 to be funky with the text
	}, 'slow');
	if (help_open[id]) {
		help_open[id] = undefined;
		row.animate({
			backgroundColor: '#F0F8FF'	// aliceblue
		}, 'slow');
	} else {
		help_open[id] = true;
		// set the initial background to the form background so we don't
		// get flash of white at the beginning of the animation.
		row.css('backgroundColor', $('#ps-conf-form').css('backgroundColor'));
		row.animate({
//			backgroundColor: '#FFFFE0'	// lightyellow
			backgroundColor: '#B0C4DE'
		}, 'slow');
	}
}

function var_delete_click(e) {
	return window.confirm("Really delete this variable? This can not be undone!");
}

function menu_click(e) {
	var menu = $(this);
	var id = menu.attr('id');
	if (!id) return true;	// no id, return true; allow the link to work
	var tab = $('#tab-' + id.split('-')[1]);
	return tab ? tab_click(e,tab) : false;
}

function tab_click(e,t) {
	var tab = t ? t : $(this);
	var id = tab.attr('id') ? tab.attr('id').split('-')[1] : null;
	var cl = tab.attr('class');
	// ignore the currently selected tab
	if (cl && cl.search(/\bsel\b/) != -1) return false;

	// hide all form divs and show the new one
	$('div[@id^=div-]').not('#div-' + id).hide();
	$('#div-' + id).show();

	// update the tab class for the previous and current tab
	$('.ps-tabs ul > li').removeClass('sel');
	$('#tab-' + id).addClass('sel');

	// update the admin menu
	$('#ps-admin-menu dd, #ps-admin-menu dt').removeClass('sel');
	$('#menu-' + id).addClass('sel');

	// update the hidden ct,s fields
//	$('#form ')

	tab.blur();
	return false;
}
