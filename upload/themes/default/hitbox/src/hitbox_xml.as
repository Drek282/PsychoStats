var conf_xml_file:String 	= _root.confxml ? _root.confxml : "xml/config.xml";
var data_xml_file:String 	= _root.weaponxml ? _root.weaponxml + '?xml=w&id=' + (isNaN(_root.id) ? 0 : _root.id) : "xml/weapons.xml";
var hitbox:Object 			= new Object();
var weapons:Array 			= new Array();
var conf_xml:XML 			= new XML();
var data_xml:XML 			= new XML();
var weaponTotals:Object 	= new Object();
var weaponsLoaded:Boolean 	= false;
var configLoaded:Boolean 	= false;
var	shotloc = [ 'chest', 'head', 'leftarm', 'rightarm', 'leftleg', 'rightleg', 'stomach' ];

// hitbox defaults, if no config is loaded.
hitbox._bullets = 10;
hitbox._title = "%s Hitbox";

conf_xml.ignoreWhite = true;
conf_xml.onLoad = function(success){
	if (!success){ return; }
	var root = this.firstChild;
	var nodes = root.childNodes;
	for (i=0; i<nodes.length; i++) {
		root['_' + nodes[i].localName ] = nodes[i].childNodes[0].nodeValue;
	}
	if (isNaN(hitbox._bullets) or hitbox._bullets < 1) hitbox._bullets = 10;
//	trace("XML config loaded");
	configLoaded = true;
}
conf_xml.load(conf_xml_file);

data_xml.ignoreWhite = true;
data_xml.onLoad = function(success){
	if (!success){ return; }
	var root = this.firstChild;
	var nodes = root.childNodes;
	for (i=0; i<nodes.length; i++) {
		var n = nodes[i];
		weapons[i] = new Object();
		weapons[i].w_uniqueid = n.nodeName;

		var vars = n.childNodes;
		for (j=0; j<vars.length; j++) {
//			trace(vars[j].nodeName);
			weapons[i]["w_" + vars[j].nodeName] = vars[j].firstChild.nodeValue;
		}
		// if no name is defined then default to the uniqueid
		if (!weapons[i].w_name) weapons[i].w_name = n.nodeName;
	}

	// sort weapons alphabetically
	weapons.sortOn('w_name', Array.CASEINSENSITIVE);

	// calculate some totals
	vars = [ 'w_damage', 'w_ffkills', 'w_kills', 'w_headshotkills', 'w_hits', 'w_shots' ];
	for (var i=0; i<vars.length; i++) {
		weaponTotals[vars[i]] = weapons.sum(vars[i]);
	}

	// calculate percentages for each shot hit locations
	for (i=0; i<weapons.length; i++) {
		for (j=0; j<shotloc.length; j++) {
			loc = 'w_shot_' + shotloc[j];
			weapons[i][loc + 'pct'] = Math.round(weapons[i][loc] / weapons[i].w_hits * 100);
			weapons[i]['w_shot_pct_max'] = Math.max(weapons[i][loc + 'pct'], isNaN(weapons[i]['w_shot_pct_max']) ? 0 : weapons[i]['w_shot_pct_max']);
		}
//		trace(weapons[i].w_name + ": " + weapons[i].w_shot_headpct + "% headshot hits");
	}

	// populate the weaponlist scrolling pane
/*
	var pane:MovieClip;
	pane = scrollPane.content;
	for (var i:Number = 0; i < weapons.length; i++) {
		var mc:MovieClip = pane.attachMovie("weaponItem", "weap"+i, pane.getNextHighestDepth(), {_y: 40*i+5, _x: 1});
		mc.loadMovie('http://liche.net/3.1/img/weapons/halflife/cstrike/' + weapons[i].w_uniqueid + '.gif');
		scrollPane.invalidate();
	}
/**/

/**/
	// add the weapons to the comboBox
	for (i=0; i<weapons.length; i++) {
		weaponlist.addItem(weapons[i].w_name, weapons[i]);
	}
/**/

//	trace("Weapon data loaded");
	weaponsLoaded = true;
	loadWeapon(weapons[0]);
}
data_xml.load(data_xml_file);

var weaponlistListener:Object = new Object();
weaponlistListener.change = function(ev:Object):Void {
	loadWeapon(ev.target.selectedItem.data);
}
weaponlist.addEventListener("change", weaponlistListener);

function str_replace(searchfor, replacement, holder) {
	temparray = holder.split(searchfor);
	holder = temparray.join(replacement);
	return (holder);
}

// used to dynamically change BG color
MovieClip.prototype.drawRectangle = function(width, height, col) {
	this.beginFill(col,100);
	this.lineTo(width,0);
	this.lineTo(width,height);
	this.lineTo(0,height);
	this.lineTo(0,0);
	this.endFill();
}

Array.prototype.sum = function(key:String):Number {
	var total = 0;
	for (var i=0; i < this.length; i++) {
		total += parseInt(this[i][key]);
	}
	return total;
}