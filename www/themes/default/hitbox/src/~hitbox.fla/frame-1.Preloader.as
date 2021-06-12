// PRELOADER 

var barWidth:Number = 200;
var barHeight:Number = 6;

this.createEmptyMovieClip("pBar_mc", 9999);
var bar:MovieClip = pBar_mc.createEmptyMovieClip("bar_mc", 10);
bar.beginFill(0x0000AA, 100);
bar.moveTo(0, 0);
bar.lineTo(barWidth, 0);
bar.lineTo(barWidth, barHeight);
bar.lineTo(0, barHeight);
bar.lineTo(0, 0);
bar.endFill();
bar._xscale = 0;

var stroke:MovieClip = pBar_mc.createEmptyMovieClip("stroke_mc", 20);
stroke.lineStyle(0, 0x0000FF);
stroke.moveTo(0, 0);
stroke.lineTo(barWidth, 0);
stroke.lineTo(barWidth, barHeight);
stroke.lineTo(0, barHeight);
stroke.lineTo(0, 0);

pBar_mc._x = (Stage.width - pBar_mc._width) / 2;
pBar_mc._y = (Stage.height - pBar_mc._height) / 2;

pBar_mc.onEnterFrame = function() {
	var loaded = getBytesLoaded();
	var total = getBytesTotal();
    var pctLoaded:Number = Math.floor(loaded / total * 100);
    if (!isNaN(pctLoaded)) {
        pBar_mc.bar_mc._xscale = pctLoaded;
        if (loaded >= total) {
			pBar_mc.clear();
            pBar_mc.onEnterFrame = undefined;
			pBar_mc.removeMovieClip();
			gotoAndPlay('hitbox');
			return;
        }
    }
};

