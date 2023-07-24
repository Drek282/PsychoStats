// http://code.google.com/apis/maps/documentation/
var map;

async function init_google() {
	// the window will scroll if we don't disable the wheel while hovering over the map
	//$('#map').hover(disable_wheel, enable_wheel);

	// initialize map
	var ll = mapconf.center ? mapconf.center.split(',') : [ 40.317232,-95.339355 ]; 	// Default is US
	const { Map } = await google.maps.importLibrary("maps");
	const { MapTypeId } = await google.maps.importLibrary("maps");
	const { MapOptions } = await google.maps.importLibrary("maps");
	if (!mapconf.maptype) mapconf.maptype = SATELLITE;
	const maptypecall = eval("google.maps.MapTypeId." + mapconf.maptype);
	
	// disable mousewheel and gestures
	if (!mapconf.smoothzoom && !mapconf.mousewheel ) {
		var gesturehandling = 'none';
		var zoomcontrol = false;
	} else {
		var gesturehandling = 'auto';
		var zoomcontrol = true;
	}

	//const mapcontrolcall = (mapconf.ctrl_maptype == 1) ? true : false;
	map = new Map(
		document.getElementById("map"), {
			center: new google.maps.LatLng(Number(ll[0]),Number(ll[1])),
			zoom: mapconf.zoom ? mapconf.zoom : 4,
			mapTypeId: maptypecall,
			mapTypeControl: mapconf.ctrl_maptype,
  			gestureHandling: gesturehandling,
  			zoomControl: zoomcontrol,
			streetViewControl: false,
			styles: [
				{ featureType: "poi", stylers: [{ "visibility": "off" }] },
				{ featureType: "transit", stylers: [{ visibility: "off" }] },
			]
	});

	//if (mapconf.ctrl_overview) map.addControl(new GOverviewMapControl());
	//if (mapconf.ctrl_map) eval("map.addControl(new " + mapconf.ctrl_map + "())");

	// standard icon base
	var stdIcon = {};
	stdIcon.image = themeurl + '/img/icons/' + mapconf.standard_icon;
	stdIcon.iconSize = new google.maps.Size(32, 32);
	stdIcon.iconWindowAnchor = new google.maps.Point(0, 16);
	stdIcon.infoAnchor = new google.maps.Point(0, 0);

	// custom icon base
	var customIcon = {};
	customIcon.iconSize = new google.maps.Size(20, 20);
	customIcon.infoWindowAnchor = new google.maps.Point(0, 0);
	customIcon.iconAnchor = new google.maps.Point(10, 20);

	// start adding markers to the map
	var icon;
	var markers = {};
	$.get('overview.php', { ip: 100 }, function(xml) {
		// add each ip marker to the map
		$('marker', xml).each(function(i){
			var t = $(this);
			var lat = t.attr('lat');
			//window.alert(lat);
			var lng = t.attr('lng');
			var latlng = lat+','+lng;
			if (markers[latlng]) return;	// don't add the same marker more than once
			markers[latlng] = true;

			// auto center on the first marker, chances are most markers will be surrounding the same area
			if (i == $('marker', xml).slice(-1)) map.setCenter(new google.maps.LatLng(Number(lat), Number(lng)));

			if (mapconf.enable_custom_icons && t.attr('icon')) {
				customIcon.image = iconsurl + '/' + t.attr('icon');
				icon = { 
   					url: customIcon.image,
    				size: customIcon.iconSize, //adjust size of image placeholder  
    				origin: customIcon.infoWindowAnchor, //origin
     				anchor: customIcon.iconAnchor //anchor point
				}
			} else {
				icon = {
   					url: stdIcon.image,
    				size: stdIcon.iconSize, //adjust size of image placeholder  
    				origin: stdIcon.infoWindowAnchor, //origin
     				anchor: stdIcon.iconAnchor //anchor point
				};
			}

			const psinfo = makeInfo(t);	
  			const infowindow = new google.maps.InfoWindow({
    			content: psinfo,
    			ariaLabel: t.attr('name'),
  			});
			var marker = new google.maps.Marker({
				position: new google.maps.LatLng(Number(lat), Number(lng)),
				map,
				icon: icon,
				title:  t.attr('name'),
				optimized: true
			});

  			marker.addListener("click", () => {
    			infowindow.open({
      				anchor: marker,
      				map,
    			});
  			});
			// add the marker to the map
			marker.setMap(map);
		});
	});
}

function makeInfo(o) {
	var dom = $('#infowin').clone();
	var plrname = $('.name', dom);
	dom.removeAttr('id').addClass('gmapinfo');
	plrname.text(o.attr('name'));
	plrname.attr('href', plrname.attr('href').replace('id=x', 'id=' + encodeURIComponent( o.attr('plrid') ) ) );
	if (o.attr('icon')) {
		plrname.prepend("<img src='" + iconsurl + '/' + encodeURIComponent( o.attr('icon') ) + "' alt=''/> ");
	}

	$('.rank', dom).html(o.attr('rank') + ' <em>(Skill: ' + o.attr('skill') + ')</em>');
	$('.kills', dom).html(o.attr('kills') + ' <em>(Headshots: ' + o.attr('headshotkills') + ')</em>');
	$('.kpd', dom).html(o.attr('kpd'));
	$('.onlinetime', dom).html(o.attr('onlinetime'));
	$('.activity', dom).html(o.attr('activity') + '%');
	var bar = $('.pct-bar', dom);
	var pct = o.attr('activity');
	bar.attr('title', "Activity " + pct + "%");
	$('span', bar).css({ width: pct + '%', backgroundColor: '#' + $('#color-' + pct).text() });
	return dom.html();
}
