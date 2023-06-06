// http://code.google.com/apis/maps/documentation/
var map;
$(function(){
	$('body').unload(function(){ if (window.GUnload) GUnload(); });

	// adjust the height of the map to fit
	var m = $('#map');
	if (m.length) {
		m.height(m.parent().height() - 2);
	}

	if (GBrowserIsCompatible()) init_google();
});

function init_google() {
	map = new GMap2(document.getElementById("map"), { });

	// initialize map
//	var lat = $('#latitude').text();
//	var lng = $('#longitude').text();

	map.setCenter(new GLatLng(plr_lat,plr_lng), 2);

	if (!mapconf.maptype) mapconf.maptype = 'G_SATELLITE_MAP';
	eval("map.setMapType(" + mapconf.maptype + ")");
//	map.addControl(new GSmallMapControl);

	// standard icon base
	var stdIcon = new GIcon();
	stdIcon.image = themeurl + '/img/icons/' + mapconf.standard_icon;
	stdIcon.shadow = themeurl + '/img/icons/' + mapconf.standard_icon_shadow;
	stdIcon.iconSize = new GSize(32,32);
	stdIcon.shadowSize = new GSize(59,32);
	stdIcon.iconAnchor = new GPoint(16,32);
	stdIcon.infoWindowAnchor = new GPoint(16,16);

	var point = new GLatLng(plr_lat, plr_lng);
	var marker = new GMarker(point, {icon: stdIcon});
	map.addOverlay(marker);
}
