// jquery.xml2json.js
(function($){$.extend({xml2json:xml2json});function xml2json(xml,root){var o={};$(root==undefined?'response':root,xml).children().each(function(){o[this.tagName]=$(this).text()});return o}})(jQuery);

var delete_message = '';
$(document).ready(function() {
	// setup handlers for frame collapse/expand divs
	$('div.ps-column-frame, div.ps-table-frame').not('.no-ani').each(function(i) {
		var frame = this;
		$('div:eq(0)', this).click(function() {	// on the frame 'header' apply the onClick handler
			ps_header_handler(frame, null, 'slow');
		});
	});

	// global ajax status animations
	$('#ajax-status').ajaxStart(ajax_start);
	$('#ajax-status').ajaxStop(ajax_stop);
});

var ajax_stopped = true;
function ajax_start() {
	if (!ajax_stopped) return;
	ajax_stopped = false;
	$(this).fadeIn('fast');
}

function ajax_stop() {
	ajax_stopped = true;
	$(this).fadeOut('fast');
}

// frame animation handler
function ps_header_handler(frame, display, speed) {
	var f = $(frame);
	var content = f.children().eq(1);			// the table or content to collapse/expand
	var span = f.children(':first').children(':first').children(':first');	// /div/a/span: the span tag has the background-image
	var visible = (content.css('display') != null && content.css('display') != 'none');
	if (display == null) display = !visible;
	if (display == visible) return;				// nothing to do

	// update the icon in the header based on the new display state
	// the image filename must end in 'minus' or 'plus'.
	var img = span.css('backgroundImage');
	if (img.indexOf('minus.') != -1) {
		img = img.replace(/minus\./, 'plus.');
	} else if (img.indexOf('plus.') != -1) {
		img = img.replace(/plus\./, 'minus.');
	}
	span.css('backgroundImage', img);

	// toggle the display of the content
	if (speed) {
		display ? content.slideDown(speed) : content.slideUp(speed);
	} else {
		display ? content.show() : content.hide();
	}
}

// swaps two table TR rows
function move_row(e) {
	// don't do anything if the last request is still pending. 
	// repeated, fast clicks will cause the order to not actually update properly in the database.
	if (!ajax_stopped) return false;

	var a = $(this);

	// send AJAX request to update database
	var href = a.attr('href');
	var params = href.substring(href.indexOf('?')+1) + '&ajax=1';
	$.ajax({
		url: href.substr(0, href.indexOf('?')),
		data: params, 
		cache: false, 
		type: 'GET',
		success: function(data){
			if ($.trim(data) != 'success') {
				// force the browser to reload, because if the request errors it means
				// the user session timedout and is no longer logged in (most likely).
				window.location = window.location;
				return false;
			}

			var is_dn = a.hasClass('dn');
			var cur_row = a.parents('tr');				// get the TR of the current row
			var adj_row = is_dn ? cur_row.next() : cur_row.prev();	// get the row we're moving to
			var new_row = cur_row.clone(true);			// make a copy since we're removing the original
			cur_row.remove();

			// insert the cloned row into its new position
			if (is_dn) {
				new_row.insertAfter(adj_row);
			} else {
				new_row.insertBefore(adj_row);
			}

			// update the arrows of the rows that were switched
			new_row.next().length ? $('a.dn', new_row).show() : $('a.dn', new_row).hide();
			new_row.prev(':not(.hdr)').length ? $('a.up', new_row).show() : $('a.up', new_row).hide();
			adj_row.next().length ? $('a.dn', adj_row).show() : $('a.dn', adj_row).hide();
			adj_row.prev(':not(.hdr)').length ? $('a.up', adj_row).show() : $('a.up', adj_row).hide();

			// update the iteration column, if it exists
			var it = $('td.iter', new_row);
			if (it.length) {
				var it2 = $('td.iter', adj_row);
				var t = it.html();
				it.html(it2.html());
				it2.html(t);
			}

			// update the zebra stripe
			adj_row.toggleClass('even');
			new_row.toggleClass('even');
		}
	});

	return false;
}

var toggling = {};
var cross = 'cross';
var tick  = 'tick';
var toggle_var = 'enabled';
function click_toggle(e) {
	var a = $(this);
	var img = $('img', a);
	var src = img.attr('src');

	var href = a.attr('href');
	var params = href.substring(href.indexOf('?')+1) + '&ajax=1';

	e.preventDefault();
	if (toggling[href]) return false;
	toggling[href] = true;

	img.attr('src', themeurl + '/img/icons/ajax-16x16.gif');

	// send AJAX request to update database
	$.ajax({
		url: href.substr(0, href.indexOf('?')),
		data: params, 
		cache: false, 
		type: 'POST',
		success: function(xml){
			var o = $.xml2json(xml);
			if (parseInt(o.code) != 1) {
				// force the browser to reload, because if the request errors it means
				// the user session timedout and is no longer logged in (most likely).
				window.location = window.location;
				return false;
			}

			if (parseInt(o[toggle_var]) == 1) {
				img.attr('src', src.replace(cross,tick));
			} else {
				img.attr('src', src.replace(tick,cross));
			}
			toggling[href] = undefined;
		}
	});

	return false;
}
