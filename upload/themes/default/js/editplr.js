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

	$('#btn-delete').click(function(){
		return window.confirm(delete_message);
	});

	$('a.openmap').click(open_map);
	$('body').unload(function(){ if (window.GUnload) GUnload(); });

});

function remove_map() {
	if (window.GUnload) GUnload();
	$('#smallmap').hide();
}

function open_map() {
	var t = $(this);
	var ofs = t.offset();
	$('#smallmap').css({ top: ofs.top, left: ofs.left }).show();
	map = new GMap2(document.getElementById("smallmap"), { });

	// initialize map
	var lat = $('input[name=latitude]').val();
	var lng = $('input[name=longitude]').val();

	var c = mapconf.center.split(',');
	var ll = mapconf.center ? { lat: c[0], lng: c[1] } : { lat: 40.317232, lng: -95.339355 };
	if (!isNaN(parseInt(lat))) ll.lat = lat; 
	if (!isNaN(parseInt(lng))) ll.lng = lng;
	map.setCenter(new GLatLng(ll.lat,ll.lng), 2); //mapconf.zoom ? mapconf.zoom : 4);

	if (!mapconf.maptype) mapconf.maptype = 'G_SATELLITE_MAP';
	eval("map.setMapType(" + mapconf.maptype + ")");
	map.addControl(new GSmallMapControl);

	// standard icon base
	var stdIcon = new GIcon();
	stdIcon.image = themeurl + '/img/icons/' + mapconf.standard_icon;
	stdIcon.shadow = themeurl + '/img/icons/' + mapconf.standard_icon_shadow;
	stdIcon.iconSize = new GSize(32,32);
	stdIcon.shadowSize = new GSize(59,32);
	stdIcon.iconAnchor = new GPoint(16,32);
	stdIcon.infoWindowAnchor = new GPoint(16,16);

	var point = new GLatLng(ll.lat, ll.lng);
	var marker = new GMarker(point, {icon: stdIcon, draggable: true});
	map.addOverlay(marker);

	GEvent.addListener(marker, "dragend", function() {
		var l = this.getLatLng();
		$('input[name=latitude]').val(l.lat());
		$('input[name=longitude]').val(l.lng());
	});

	var smallmap = $('#smallmap');
	var maptimer = null;
	smallmap.mouseout(function(){
		if (maptimer) clearTimeout(maptimer);
		maptimer = setTimeout(remove_map, 1500);
	});
	smallmap.mouseover(function(){
		if (maptimer) { 
			clearTimeout(maptimer);
			maptimer = null
		}
	});

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

