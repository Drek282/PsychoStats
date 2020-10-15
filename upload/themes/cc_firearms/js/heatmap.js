/**
 *	PsychoStats Heatmap Implementation
 *	$Id$
 */
// jquery.dimensions.js
(function($){$.dimensions={version:'@VERSION'};$.each(['Height','Width'],function(i,name){$.fn['inner'+name]=function(){if(!this[0])return;var torl=name=='Height'?'Top':'Left',borr=name=='Height'?'Bottom':'Right';return this.is(':visible')?this[0]['client'+name]:num(this,name.toLowerCase())+num(this,'padding'+torl)+num(this,'padding'+borr)};$.fn['outer'+name]=function(options){if(!this[0])return;var torl=name=='Height'?'Top':'Left',borr=name=='Height'?'Bottom':'Right';options=$.extend({margin:false},options||{});var val=this.is(':visible')?this[0]['offset'+name]:num(this,name.toLowerCase())+num(this,'border'+torl+'Width')+num(this,'border'+borr+'Width')+num(this,'padding'+torl)+num(this,'padding'+borr);return val+(options.margin?(num(this,'margin'+torl)+num(this,'margin'+borr)):0)}});$.each(['Left','Top'],function(i,name){$.fn['scroll'+name]=function(val){if(!this[0])return;return val!=undefined?this.each(function(){this==window||this==document?window.scrollTo(name=='Left'?val:$(window)['scrollLeft'](),name=='Top'?val:$(window)['scrollTop']()):this['scroll'+name]=val}):this[0]==window||this[0]==document?self[(name=='Left'?'pageXOffset':'pageYOffset')]||$.boxModel&&document.documentElement['scroll'+name]||document.body['scroll'+name]:this[0]['scroll'+name]}});$.fn.extend({position:function(){var left=0,top=0,elem=this[0],offset,parentOffset,offsetParent,results;if(elem){offsetParent=this.offsetParent();offset=this.offset();parentOffset=offsetParent.offset();offset.top-=num(elem,'marginTop');offset.left-=num(elem,'marginLeft');parentOffset.top+=num(offsetParent,'borderTopWidth');parentOffset.left+=num(offsetParent,'borderLeftWidth');results={top:offset.top-parentOffset.top,left:offset.left-parentOffset.left}}return results},offsetParent:function(){var offsetParent=this[0].offsetParent;while(offsetParent&&(!/^body|html$/i.test(offsetParent.tagName)&&$.css(offsetParent,'position')=='static'))offsetParent=offsetParent.offsetParent;return $(offsetParent)}});function num(el,prop){return parseInt($.curCSS(el.jquery?el[0]:el,prop,true))||0}})(jQuery);
// ui.mouse.js
(function($){$.ui=$.ui||{};$.extend($.ui,{plugin:{add:function(module,option,set){var proto=$.ui[module].prototype;for(var i in set){proto.plugins[i]=proto.plugins[i]||[];proto.plugins[i].push([option,set[i]])}},call:function(instance,name,arguments){var set=instance.plugins[name];if(!set)return;for(var i=0;i<set.length;i++){if(instance.options[set[i][0]])set[i][1].apply(instance.element,arguments)}}},cssCache:{},css:function(name){if($.ui.cssCache[name])return $.ui.cssCache[name];var tmp=$('<div class="ui-resizable-gen">').addClass(name).css({position:'absolute',top:'-5000px',left:'-5000px',display:'block'}).appendTo('body');$.ui.cssCache[name]=!!(((/^[1-9]/).test(tmp.css('height'))||(/^[1-9]/).test(tmp.css('width'))||!(/none/).test(tmp.css('backgroundImage'))||!(/transparent|rgba\(0, 0, 0, 0\)/).test(tmp.css('backgroundColor'))));try{$('body').get(0).removeChild(tmp.get(0))}catch(e){}return $.ui.cssCache[name]},disableSelection:function(e){if(!e)return;e.unselectable="on";e.onselectstart=function(){return false};if(e.style)e.style.MozUserSelect="none"},enableSelection:function(e){if(!e)return;e.unselectable="off";e.onselectstart=function(){return true};if(e.style)e.style.MozUserSelect=""}});$.fn.extend({mouseInteraction:function(o){return this.each(function(){new $.ui.mouseInteraction(this,o)})},removeMouseInteraction:function(o){return this.each(function(){if($.data(this,"ui-mouse"))$.data(this,"ui-mouse").destroy()})}});$.ui.mouseInteraction=function(element,options){var self=this;this.element=element;$.data(this.element,"ui-mouse",this);this.options=$.extend({},options);$(element).bind('mousedown.draggable',function(){return self.click.apply(self,arguments)});if($.browser.msie)$(element).attr('unselectable','on')};$.extend($.ui.mouseInteraction.prototype,{destroy:function(){$(this.element).unbind('mousedown.draggable')},trigger:function(){return this.click.apply(this,arguments)},click:function(e){if(e.which!=1||$.inArray(e.target.nodeName.toLowerCase(),this.options.dragPrevention)!=-1||(this.options.condition&&!this.options.condition.apply(this.options.executor||this,[e,this.element])))return true;var self=this;var initialize=function(){self._MP={left:e.pageX,top:e.pageY};$(document).bind('mouseup.draggable',function(){return self.stop.apply(self,arguments)});$(document).bind('mousemove.draggable',function(){return self.drag.apply(self,arguments)})};if(this.options.delay){if(this.timer)clearInterval(this.timer);this.timer=setTimeout(initialize,this.options.delay)}else{initialize()}return false},stop:function(e){var o=this.options;if(!this.initialized)return $(document).unbind('mouseup.draggable').unbind('mousemove.draggable');if(this.options.stop)this.options.stop.call(this.options.executor||this,e,this.element);$(document).unbind('mouseup.draggable').unbind('mousemove.draggable');this.initialized=false;return false},drag:function(e){var o=this.options;if($.browser.msie&&!e.button)return this.stop.apply(this,[e]);if(!this.initialized&&(Math.abs(this._MP.left-e.pageX)>=o.distance||Math.abs(this._MP.top-e.pageY)>=o.distance)){if(this.options.start)this.options.start.call(this.options.executor||this,e,this.element);this.initialized=true}else{if(!this.initialized)return false}if(o.drag)o.drag.call(this.options.executor||this,e,this.element);return false}})})(jQuery);
// ui.slider.js
(function($){$.fn.extend({slider:function(options){var args=Array.prototype.slice.call(arguments,1);if(options=="value")return $.data(this[0],"ui-slider").value(arguments[1]);return this.each(function(){if(typeof options=="string"){var slider=$.data(this,"ui-slider");slider[options].apply(slider,args)}else if(!$.data(this,"ui-slider"))new $.ui.slider(this,options)})}});$.ui.slider=function(element,options){var self=this;this.element=$(element);$.data(element,"ui-slider",this);this.element.addClass("ui-slider");this.options=$.extend({},options);var o=this.options;$.extend(o,{axis:o.axis||(element.offsetWidth<element.offsetHeight?'vertical':'horizontal'),maxValue:!isNaN(parseInt(o.maxValue,10))?parseInt(o.maxValue,10):100,minValue:parseInt(o.minValue,10)||0,startValue:parseInt(o.startValue,10)||'none'});o.realMaxValue=o.maxValue-o.minValue;o.stepping=parseInt(o.stepping,10)||(o.steps?o.realMaxValue/o.steps:0);$(element).bind("setData.slider",function(event,key,value){self.options[key]=value}).bind("getData.slider",function(event,key){return self.options[key]});this.handle=o.handle?$(o.handle,element):$('> *',element);$(this.handle).mouseInteraction({executor:this,delay:o.delay,distance:o.distance||0,dragPrevention:o.prevention?o.prevention.toLowerCase().split(','):['input','textarea','button','select','option'],start:this.start,stop:this.stop,drag:this.drag,condition:function(e,handle){if(!this.disabled){if(this.currentHandle)this.blur(this.currentHandle);this.focus(handle,1);return!this.disabled}}}).wrap('<a href="javascript:void(0)"></a>').parent().bind('focus',function(e){self.focus(this.firstChild)}).bind('blur',function(e){self.blur(this.firstChild)}).bind('keydown',function(e){if(/(37|39)/.test(e.keyCode))self.moveTo((e.keyCode==37?'-':'+')+'='+(self.options.stepping?self.options.stepping:(self.options.realMaxValue/self.size)*5),this.firstChild)});if(o.helper=='original'&&(this.element.css('position')=='static'||this.element.css('position')==''))this.element.css('position','relative');if(o.axis=='horizontal'){this.size=this.element.outerWidth();this.properties=['left','width']}else{this.size=this.element.outerHeight();this.properties=['top','height']}this.element.bind('click',function(e){self.click.apply(self,[e])});if(!isNaN(o.startValue))this.moveTo(o.startValue,0);if(this.handle.length==1)this.previousHandle=this.handle;if(this.handle.length==2&&o.range)this.createRange()};$.extend($.ui.slider.prototype,{plugins:{},createRange:function(){this.rangeElement=$('<div></div>').addClass('ui-slider-range').css({position:'absolute'}).css(this.properties[0],parseInt($(this.handle[0]).css(this.properties[0]),10)+this.handleSize(0)/2).css(this.properties[1],parseInt($(this.handle[1]).css(this.properties[0]),10)-parseInt($(this.handle[0]).css(this.properties[0]),10)).appendTo(this.element)},updateRange:function(){this.rangeElement.css(this.properties[0],parseInt($(this.handle[0]).css(this.properties[0]),10)+this.handleSize(0)/2);this.rangeElement.css(this.properties[1],parseInt($(this.handle[1]).css(this.properties[0]),10)-parseInt($(this.handle[0]).css(this.properties[0]),10))},getRange:function(){return this.rangeElement?this.convertValue(parseInt(this.rangeElement.css(this.properties[1]),10)):null},ui:function(e){return{instance:this,options:this.options,handle:this.currentHandle,value:this.value(),range:this.getRange()}},propagate:function(n,e){$.ui.plugin.call(this,n,[e,this.ui()]);this.element.triggerHandler(n=="slide"?n:"slide"+n,[e,this.ui()],this.options[n])},destroy:function(){this.element.removeClass("ui-slider ui-slider-disabled").removeData("ul-slider").unbind(".slider");this.handles.removeMouseInteraction()},enable:function(){this.element.removeClass("ui-slider-disabled");this.disabled=false},disable:function(){this.element.addClass("ui-slider-disabled");this.disabled=true},focus:function(handle,hard){this.currentHandle=$(handle).addClass('ui-slider-handle-active');if(hard)this.currentHandle.parent()[0].focus()},blur:function(handle){$(handle).removeClass('ui-slider-handle-active');if(this.currentHandle&&this.currentHandle[0]==handle){this.previousHandle=this.currentHandle;this.currentHandle=null}},value:function(handle){if(this.handle.length==1)this.currentHandle=this.handle;return((parseInt($(handle!=undefined?this.handle[handle]||handle:this.currentHandle).css(this.properties[0]),10)/(this.size-this.handleSize()))*this.options.realMaxValue)+this.options.minValue},convertValue:function(value){return(value/(this.size-this.handleSize()))*this.options.realMaxValue},translateValue:function(value){return((value-this.options.minValue)/this.options.realMaxValue)*(this.size-this.handleSize())},handleSize:function(handle){return $(handle!=undefined?this.handle[handle]:this.currentHandle)['outer'+this.properties[1].substr(0,1).toUpperCase()+this.properties[1].substr(1)]()},click:function(e){var pointer=[e.pageX,e.pageY];var clickedHandle=false;this.handle.each(function(){if(this==e.target)clickedHandle=true});if(clickedHandle||this.disabled||!(this.currentHandle||this.previousHandle))return;if(this.previousHandle)this.focus(this.previousHandle,1);this.offset=this.element.offset();this.moveTo(this.convertValue(e[this.properties[0]=='top'?'pageY':'pageX']-this.offset[this.properties[0]]-this.handleSize()/2))},start:function(e,handle){var o=this.options;this.offset=this.element.offset();this.handleOffset=this.currentHandle.offset();this.clickOffset={top:e.pageY-this.handleOffset.top,left:e.pageX-this.handleOffset.left};this.firstValue=this.value();this.propagate('start',e);return false},stop:function(e){this.propagate('stop',e);if(this.firstValue!=this.value())this.propagate('change',e);return false},drag:function(e,handle){var o=this.options;var position={top:e.pageY-this.offset.top-this.clickOffset.top,left:e.pageX-this.offset.left-this.clickOffset.left};var modifier=position[this.properties[0]];if(modifier>=this.size-this.handleSize())modifier=this.size-this.handleSize();if(modifier<=0)modifier=0;if(o.stepping){var value=this.convertValue(modifier);value=Math.round(value/o.stepping)*o.stepping;modifier=this.translateValue(value)}if(this.rangeElement){if(this.currentHandle[0]==this.handle[0]&&modifier>=this.translateValue(this.value(1)))modifier=this.translateValue(this.value(1));if(this.currentHandle[0]==this.handle[1]&&modifier<=this.translateValue(this.value(0)))modifier=this.translateValue(this.value(0))}this.currentHandle.css(this.properties[0],modifier);if(this.rangeElement)this.updateRange();this.propagate('slide',e);return false},moveTo:function(value,handle){var o=this.options;if(handle==undefined&&!this.currentHandle&&this.handle.length!=1)return false;if(handle==undefined&&!this.currentHandle)handle=0;if(handle!=undefined)this.currentHandle=this.previousHandle=$(this.handle[handle]||handle);if(value.constructor==String)value=/\-\=/.test(value)?this.value()-parseInt(value.replace('-=',''),10):this.value()+parseInt(value.replace('+=',''),10);if(o.stepping)value=Math.round(value/o.stepping)*o.stepping;value=this.translateValue(value);if(value>=this.size-this.handleSize())value=this.size-this.handleSize();if(value<=0)value=0;if(this.rangeElement){if(this.currentHandle[0]==this.handle[0]&&value>=this.translateValue(this.value(1)))value=this.translateValue(this.value(1));if(this.currentHandle[0]==this.handle[1]&&value<=this.translateValue(this.value(0)))value=this.translateValue(this.value(0))}this.currentHandle.css(this.properties[0],value);if(this.rangeElement)this.updateRange();this.propagate('start',null);this.propagate('stop',null);this.propagate('change',null)}})})(jQuery);

var loop_speed 	= 100;		// how fast to move the slider
var loop_inc	= 0.15;		// how much to move the slider

var slider_hour = 0;
var heatmaps 	= [];
var loop_timer 	= null;
var looping 	= false;		// true, if the slider is automatically moving
var mouse_moving_slider = false;	// true, if the mouse is on the slider and pressed down
var heatmap_overlay = null;		// jquery pointer to heatmap overlay image
var pending_heatmaps = 0;
var loaded_heatmaps = 0;
var slider;				// slider object
$(function(){
	slider = $('div.heatmap-slider');
	// setup slider control
	slider.slider({
		minValue: 0,
		maxValue: 23,
//		stepping: 1,
//		steps: 24 * 100,
		slide: move_slider,
		change: move_slider
	});

	// add handlers for slider interactivity
	$('div.ui-slider-handle, div.heatmap-slider')
		.mousedown(function(){ mouse_moving_slider = true })
		.mouseup(function(){ mouse_moving_slider = false })
		.dblclick(toggle_slider_loop)

	// adjust the image size if the window changes
	$(window).resize(resize_all);

	$('div.heatmap .ontop').css('opacity', 0.50);

	// handler for <select> element to change the heatmap type
	$('select[@name=heatid]').change(function(){
		if (this.options[this.selectedIndex].value) {
			this.form.submit();
		}
	});

	// small tweak for IE (if I ever get the alpha maps working for it
	if ($.browser.msie) {
		$('div.heatmap div.hour').css('right', '20px');
	}

//	start_slider_loop();
});

// preloads all heatmap images and starts the animation loop once they're loaded
function init_heatmap(imgs, overlay) {
	slider.slider('disable');
	if (overlay) {
		var ovr = new Image();
		ovr.onload = function(){
			var img = $("<img class='overlay' src='" + this.src + "' />");
//			img.css('display', 'none');		// don't do this, or IE6 doesn't resize the image properly
			$('div.heatmap').append(img);		// insert into DOM, so the w/h will be valid
			img.attr('_width', img.width());	// keep track of the original dims
			img.attr('_height', img.height());
			resize_image(img);
			img.css('display', '');
			heatmap_overlay = img;

			// load the remaining heatmap images after the main heatmap overlay is loaded
			if (imgs.length) {
				pending_heatmaps = imgs.length;
				for (var i=0; i < imgs.length; i++) {
					heatmaps[i] = new Image();
					heatmaps[i].onload = heatmap_loaded;
					heatmaps[i].src = 'heatimg.php?id=' + imgs[i];
				}
			}
		};
		ovr.src = overlay;
	}
}

// each heatmap pre-loaded is processed here. Once all are loaded the slider is enabled.
function heatmap_loaded() {
	loaded_heatmaps++;
	update_heatmap_progress();
	// once all images are preloaded add them to the DOM in proper order
	if (loaded_heatmaps >= pending_heatmaps) {
		for (var i=heatmaps.length-1; i >= 0; i--) {
			var img = $("<img class='heat' src='" + heatmaps[i].src + "' />");
			img.css('display', 'none');
			$('div.heatmap').prepend(img);
			img.width(heatmap_overlay.width()).height(heatmap_overlay.height());
			heatmaps[i] = img;	// change heatmap image pointer to the DOM element
		}
		if (loaded_heatmaps > 1) {
			slider.slider('enable');
			$('div.heatmap .hour').show();
		} else {
			$('div.heatmap .hour').hide();
		}
		slider.slider('moveTo', 0);
//		start_slider_loop();
	}
}

// displays a progress bar on top of the heatmap overlay while heatmap images are downloaded
function update_heatmap_progress() {
	var div = $('div.heatmap-progress');
	var pct = Math.round(loaded_heatmaps / pending_heatmaps * 100);
	if (pct >= 100) {
		div.hide();
	} else {
		if (!div.is(':visible')) {
			var h = $('div.heatmap');
			var o = h.offset();
			div.css({
				top: (o.top + h.height()/2 - div.height()/2) + 'px',
				left: (o.left + h.width()/2 - div.width()/2) + 'px'
			});
			div.show();
		}
	}
	$('.pct-bar', div).attr('title', pct + '%');
	$('.pct-bar span', div).css('width', pct + '%');
}

// resizes all heatmap images to the proper dimensions
function resize_all(e) {
	resize_image(heatmap_overlay);
	$('div.heatmap .heat').each(function(){
		var img = $(this);
		img	
			.width(heatmap_overlay.width())
			.height(heatmap_overlay.height())
			.css({ left: heatmap_overlay.css('left'), top: heatmap_overlay.css('top') })
	});
}

// resizes the image proportionally to fit within its parent element.
function resize_image(img,maxwidth,maxheight) {
	var w = parseInt(img.attr('_width'));
	var h = parseInt(img.attr('_height'));
	if (!maxwidth) maxwidth = img.parent().width() - 10;
	if (!maxheight) maxheight = 800;
	if (w <= maxwidth && h <= maxheight) {
		img.width(w);
		img.height(h);
	} else {
		if (w > maxwidth) {
			img.width(maxwidth);
			img.height(Math.round(maxwidth * h / w));
		} else if (h > maxheight) {
			img.height(maxheight);
			img.width(Math.round(maxheight * w / h));
		}
	}
	// For FF: Resize the parent to the same height, otherwise when we shrink down, the height of the parent
	// will remain at it's original pre-shrunk size until the window is resized again. annoying.
	img.parent().height(img.height());
}

// makes the slider move automatically
function start_slider_loop(e) {
	if (looping) return;
	looping = true;
	loop_timer = setTimeout(auto_move_slider, loop_speed);
}

function stop_slider_loop(e) {
	if (!looping) return;
	clearTimeout(loop_timer);
	looping = false;
	loop_timer = null;
	// stop the current fade animation if its active
}

function toggle_slider_loop(e) {
	if (!$('div.heatmap-slider').hasClass('ui-slider-disabled')) {
		if (looping) {
			stop_slider_loop();
		} else {
			start_slider_loop();
		}
	}
	// don't bubble event, or dblclick will trigger twice
	e.stopPropagation();
}

// moves the slider ~1 tick.
function auto_move_slider() {
	if (!mouse_moving_slider) {	// do nothing if the user is holding the mouse down
		if (slider.slider('value') < 23) {
			slider.slider('moveTo', Math.min(slider.slider('value') + loop_inc, 23));
		} else {
			slider.slider('moveTo', 0);
		}
	}
	loop_timer = setTimeout(auto_move_slider, loop_speed);
}

// every time the slider moves or changes this is called
// this actually displays the heatmaps by blending 2 images together on top of the overlay
var last_low = 0;
var last_high = 0;
function move_slider(e, ui) {
//	var hour = Math.round(ui.value);
//	if (hour == slider_hour) return;
//	slider_hour = hour;
	// update the image with a new hourly image

	var low = Math.floor(ui.value);						// index of heatmap to use
	var high = Math.ceil(ui.value);
	var low_alpha = Math.round(100 - (ui.value - low) * 100) / 100;		// alpha for the heatmap
	var high_alpha = Math.round(100 - (high - ui.value) * 100) / 100;
//	debug('hours: ' + low + '..' + high + ' || alpha: ' + low_alpha + ', ' + high_alpha);

	var low_img = heatmaps[low];
	var high_img = heatmaps[high];
	var o = heatmap_overlay.offset();

	// must use 'display: ""' instead of the show() function because it will set the display to 'block' and cause the 
	// heatmap to position to the left instead of on top of the overlay in Mozilla.
	low_img.css({ display: '', left: heatmap_overlay.css('left'), top: heatmap_overlay.css('top') });
	high_img.css({ display: '', left: heatmap_overlay.css('left'), top: heatmap_overlay.css('top') });
	if (!$.browser.msie) {
		low_img.css('opacity', low_alpha);
		high_img.css('opacity', high_alpha);
	}

	if (loaded_heatmaps > 1) {
		$('div.heatmap div.hour span.hour').html(String(low).length < 2 ? '0' + low : low);
	}

	if (last_low != low && last_low != high) {
		heatmaps[last_low].hide();
	}
	if (last_high != high && last_high != low) {
		heatmaps[last_high].hide();
	}

	last_low = low;
	last_high = high;
}

function debug(str, append) {
	if (!append) $('#debug').html('');
	$('#debug').append(str);
}

