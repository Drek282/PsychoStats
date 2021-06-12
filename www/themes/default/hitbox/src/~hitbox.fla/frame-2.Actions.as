#include "hitbox_xml.as"
// #include "hitbox_target.as"

import mx.transitions.Tween;

stop();

// displays all the stats for the weapon.
function loadWeapon(w:Object) {
	if (w == undefined) w = weapons[0];
	if (w == undefined) return;
	var acc:Number = Math.round(w.w_accuracy);
	if (isNaN(acc)) acc = 0; 
	acc = Math.min(acc,100);

	var rooturl:String = _root.imgpath ? _root.imgpath : ''; //'http://liche.net/3.1/img/weapons/halflife/cstrike';
	weapon_mc.loadMovie(rooturl + '/' + w.w_uniqueid + '.gif');

	for (var i=0; i<shotloc.length; i++) {
		var pct = isNaN(w['w_shot_' + shotloc[i] + 'pct']) ? 0 : w['w_shot_' + shotloc[i] + 'pct'];
		var col = new Color(hitbox_mc[shotloc[i] + '_mc']);
		var shade = pct / w['w_shot_pct_max'] * 100;
		col.setTransform({ra: 100, ba: 100, ga: 100, bb: -shade/100*255});
		hitbox_mc[shotloc[i] + '_pct'].text = pct + '%';
	}

	target.w_accuracy.text = acc + '%';
}

// returns a bullet coord based on the accuracy given
function shoot(accuracy:Number, radius:Number):Object {
	var obj:Object = new Object();
	accuracy = 100-accuracy;
	shot = random(accuracy) / 100;
	radians = random(360) * (Math.PI/180);
	obj.x = (shot*radius) * Math.cos(radians);
	obj.y = (shot*radius) * Math.sin(radians);
	return obj;
}

function doTargetUpdate() {
	if (!weaponsLoaded) return;
	this.totalholes++;
	if (this.delay and !(this.totalholes % this.delay == 0)) return;
	var acc = weaponlist.selectedItem.data.w_accuracy;
	var pos = shoot(acc, this._width/2);
	var b:MovieClip = this.attachMovie('bullet_hole','hole'+this.totalholes, this.getNextHighestDepth());
	b._rotation = random(360);
	b.num = this.totalholes;
	b._x = pos.x;
	b._y = pos.y;
	this.holes.push(b);

	if (this.holes.length > hitbox._bullets) {
		b = this.holes.shift();
		b.removeMovieClip();
	}
}
target.holes = new Array();
target.totalholes = 0;
target.delay = Math.round(12/3);
target.onEnterFrame = doTargetUpdate;
