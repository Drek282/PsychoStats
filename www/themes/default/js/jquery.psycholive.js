/**
 *	This file is part of PsychoStats.
 *
 *	Written by Jason Morriss
 *	Copyright 2008 Jason Morriss
 *
 *	PsychoStats is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	PsychoStats is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with PsychoStats.  If not, see <http://www.gnu.org/licenses/>.
 *
 *	$Id: jquery.psycholive.js 566 2008-10-14 15:09:38Z lifo $
 *
 *	PsychoStats (PsychoLive) jQuery plugin. For use with PsychoStats.
 */
(function($) {
	var debug_level = 0;	// global debugging level
	$.fn.psycholive = function(options) {
		var opt = $.extend({}, $.fn.psycholive.defaults, options);
		var obj = [];
		debug_level = opt.debug || 0;

		if (opt.url) {
			if (!opt.setup) opt.setup = opt.url;
			if (!opt.queue) opt.queue = opt.url;
		}
		
		// normally it won't be useful to initialize more than one
		// psycholive instance at a time. But maybe someone will think
		// of something in the future to make this useful.
		this.each(function() {
			obj.push(new PsychoLive(this, opt));
		});

		// return the single object if only one instance was started
		if (obj.length) {
			return obj.length == 1 ? obj[0] : obj;
		}
		
		return false;
	};

	// playback status constants
	var STATUS = { // const
		STOP: 'stop',	// frame playback is stopped
		PLAY: 'play',	// frame playback is running
		IDLE: 'idle'	// waiting for sync or other events to occur
	};
	
	// Z-index stacking order for various in-game objects
	var ZINDEX = { // const
		MSG:	65000,	// generic messages
		ROUND:	1000,	// 'Round Starting' message
		SCORE:	600,	// scoreboard
		DMG:	400,	// player damage text
		AVATAR:	300,	// player avatar
		ICON:	295,	// avatar icon
		HEALTH: 110,	// avatar health
		ENTITY:	200,	// any item on the canvas (ie: bomb, flag)
		TICKER:	695,	// timer tickers attached to an entity (bomb)
		LOG: 	150,	// combat log
		BODY:	100	// a dead body on the canvas
	};
	
	// PsychoLive constructor.
	// @param DOMElement ele  DOM element to wrap psycholive into
	// @param Object     opt  Options/settings to change behavior
	function PsychoLive(ele, opt) {
		return this.init(ele, opt);
	};
	$.fn.psycholive.PsychoLive = PsychoLive;
	// PsychoLive methods can be overridden using
	// $.fn.psycholive.PsychoLive.prototype.method_name
	PsychoLive.prototype = $.fn.psycholive.PsychoLive.prototype = {
		canvas_initialized: false,	// can't do any animations until true
		
		init: function(ele, opt){
			;;; debug4('psycholive.init()');
			this.ele = $(ele);
			this.opt = opt;
			// default to current location script path
			if (!this.opt.setup) this.opt.setup = location.pathname;
			if (!this.opt.queue) this.opt.queue = location.pathname;

			;;; debug4('new PsychoLive(#' + this.ele.attr('id') + ')',5);
			this.reset();
			this.canvas = new Canvas(this.ele, this.opt);
			this.events = new Events();
			if (opt.enable_pause) {
				this.enable_pause();
			}
			
			if (this.opt.enable_scoreboard) {
				this.enable_scoreboard();
			}

			// start her up!!
			if (this.opt.auto_start) {
				this.play(this.opt.game_id);
			}
			return this;
		},

		enable_pause: function(){
			var $this = this;
			this.canvas.enable_pause(
				function(){ $this.pause() },
				function(){ $this.unpause() }
			);
			return this;
		},

		disable_pause: function(){
			this.canvas.disable_pause();
			return this;
		},

		// Reset the environment to a blank canvas and no events.
		reset: function(){
			;;; debug4('psycholive.reset()');
			this.status = STATUS.STOP;
			this.prev_game = {}; //$.extend({}, this.game);	// copy of previous game
			this.game = {};
			this.queue = [];
			this.entities = {};
			this.players = {};
			this.keysdown = {};
			this.queue_pending = false;
			this.queue_locked = false;
			this.timestamp = 0;		// current event timestamp
			this.idle = 0;			// total seconds idle
			this.round = 0;
			if (this.canvas) this.canvas.reset();
			return this;
		},

		// Sets up a game for playback. This makes an AJAX call to the
		// server to get the game parameters for playback, if needed.
		setup: function(game_id, auto_advance, auto_start) {
			var $this = this;
			if (!game_id) game_id = 0;
			if (auto_advance == undefined) auto_advance = this.opt.auto_advance;
			if (auto_start == undefined) auto_start = this.opt.auto_start;
			;;; debug4('psycholive.setup('+game_id+','+auto_advance+','+auto_start+')');
			if (this.status != STATUS.STOP) {
				this.stop();
			}
			this.reset();
			
			var data = { game: game_id, req: 'setup', advance: auto_advance ? -1 : 0 };
			if ($.isFunction(this.opt.setup) || typeof this.opt.setup == 'object') {
				// allow the caller to return the game info
				var game;
				if ($.isFunction(this.opt.setup)) {
					game = this.sanitize(this.opt.setup.call(this, data));
				} else {
					game = this.sanitize(this.opt.setup);
				}
				if (!game || typeof game != 'object' || game.error) {
					if (game && game.error.constructor == String) {
						this.canvas.message('Error loading game:<br />' + game.error,'MC');
					} else {
						this.canvas.message('Error loading game','MC');
					}
				} else {
					this.status = STATUS.IDLE;
					if ($.isFunction(this.opt.setup_callback)) {
						this.opt.setup_callback.call(this, game);
					}
					if (this.init_game(game)) {
						this.canvas.init_background(function(canvas,img){$this.canvas_callback(canvas,img)});
						if (auto_start) this.play();
					}
				}
			} else if (typeof this.opt.setup == 'string') {
				// send an AJAX request to fetch the game info
				;;; debug4('getJSON(request, ' + game_id + ')');
				$.ajax({
					type: 'GET',
					url: this.opt.setup,
					data: data,
					cache: false,
					dataType: 'json',
					success: function(game){
						;;; debug4('getJSON(success, ' + game.game_id + ')');
						$this.status = STATUS.IDLE;
						if ($.isFunction($this.opt.setup_callback)) {
							$this.opt.setup_callback.call($this, game);
						}
						if ($this.init_game(game)) {
							$this.canvas.init_background(function(canvas,img){$this.canvas_callback(canvas,img)});
							if (auto_start) $this.play();
						}
					},
					error: function(xhr, status, exception) {
						;;; debug4('getJSON(error, ' + status + ')');
						$this.canvas.message('Error loading game:<br />' + status,'MC');
					}
				});
			}
			return this;
		},

		// callback function for the canvas.init_background call.
		// this is called when the background is fully initialized and
		// inserted into the DOM. Once this is called, we can safely
		// add players and entities to the map.
		canvas_callback: function(canvas, img) {
			this.canvas_initialized = true;
			this.init_players();
			this.init_items();
			this.init_scoreboard();
			this.init_combatlog();
		},

		// performs some pre-playback initialization of a game record.
		init_game: function(game) {
			;;; debug4('psycholive.init_game()');
			if (this.game) {
				// copy of previous game
				this.prev_game = $.extend({}, this.game);
			}
			this.game = this.sanitize(game);
			this.next_offset = 0;
			
			if (this.canvas.msg_init) {
				this.canvas.clear_messages(this.canvas.msg_init);
				delete this.canvas.msg_init;
			}
			
			if (!this.game.code) {
				// no games were found, so we'll check again in a few seconds
				this.canvas.reset();
				this.msg_nogame = this.canvas.message(game.message || 'No games found!', 'MC');
				this.queue_locked = true;
				this.one_timer('recheck_queue', this.opt.recheck_interval);
				return false;
			} else if (this.prev_game.game_id && this.prev_game.game_id == this.game.game_id) {
				var $this = this;
				// the game returned is the same as the one we
				// just played back.
				this.stop();
				this.canvas.reset();
				this.msg_nogame = this.canvas.message('No new games available.<br/><a class="refresh" href="javascript:void(0)">Click here to refresh</a>', 'MC');
				$(this.msg_nogame).css({textAlign: 'center'});
				$('a', this.msg_nogame).click(function(e){
					$this.setup(0); //, $this.opt.auto_advance);
					e.preventDefault();
					return false;
				});
				;;; debug('NO NEW GAMES AVAILABLE - FULL STOP!');
				// don't try and recheck from this point...
				return false;
			}

			this.canvas.init_game(game);
			this.next_offset = this.game.next_offset || 0;
			return true;
		},

		// must be called after the game and canvas are initialized
		init_players: function() {
			// initialize any players currently connected
			if (this.game.players && this.game.players.length) {
				for (var p=0; p < this.game.players.length; p++) {
					this.players[this.game.players[p].ent_id] =
						new Player(this.game.players[p], this);
				}
			}
			return this;
		},

		// must be called after the game and canvas are initialized
		init_items: function() {
			// initialize any items on the ground
			if (this.game.items) {
				with (this.game) for (var name in items) {
					var item = new Entity(name, this.opt, this.canvas.dom);
					this.canvas.drop(name, item, items[name].xyz);
					if (name == 'bomb' && items[name].value) {
						if (items[name].timestamp) {
							var seconds = items[name].value - (this.game.newest_timestamp - items[name].timestamp);
							item.start_timer(seconds);
						}
					}
				}
			}
		},

		// Start playback. If no session is currently being played
		// then a new session will start up first.
		play: function(game_id){
			if (!game_id && this.game.game_id) {
				game_id = this.game.game_id;
			}
			;;; debug4('psycholive.play('+game_id+')');
			if (this.status == STATUS.STOP) {
				// we need to setup a new game first...
				return this.setup(game_id); //, true);
			} else if (this.status != STATUS.IDLE) {
				// stop current game and restart...
				this.stop();
				this.reset();
			}
			this.start(true);
			return this;
		},

		// start playback and queue management
		start: function(queue_once){
			if (this.msg_intermission) {
				this.canvas.clear_messages(this.msg_intermission);
				delete this.msg_intermission;
			}
			
			if (this.status == STATUS.PLAY) return this;
			this.status = STATUS.PLAY;
			if (!this.queue_locked) {
				this.start_timer('manage_queue', this.opt.queue_interval);
				if (queue_once) this.manage_queue();	// fire once manually...
			}
			this.start_timer('frame', this.opt.frame_interval);
			return this;
		},

		// Stop playback completely. All frame and queue timers.
		stop: function(){
			if (this.status == STATUS.STOP) return this;
			this.status = STATUS.STOP;
			if (!this.queue_locked) this.stop_timer('manage_queue');
			this.stop_timer('frame');
			return this;
		},
		
		// Pause frame playback and display 'paused' message.
		pause: function(){
			if (this.status != STATUS.PLAY) return this;
			this.stop_timer('frame');
			if (!this.queue_locked) this.stop_timer('manage_queue');
			// stop all player animations
			//for (var p in this.players) {
			//	this.players[p].stop();
			//}
			this.msg_pause = this.canvas.message('Game Paused', 'TL', true);
			return this;
		},

		// unpause playback (after pause() is called)
		unpause: function(){
			if (this.status != STATUS.PLAY) return this;
			this.frame();	// fire once manually
			this.start_timer('frame', this.opt.frame_interval);
			if (!this.queue_locked) this.start_timer('manage_queue', this.opt.queue_interval);
			if (this.msg_pause) this.canvas.clear_messages(this.msg_pause);
			return this;
		},

		// update the current playback frame. This is the animation
		// workhorse where everything moves around.
		frame: function(){
			;;; debug5('psycholive.frame()');

			// initialize the current timestamp if its zero.
			// we initialize to the first event found.
			if (!this.timestamp && this.queue.length) {
				this.timestamp = parseInt(this.queue[0].event_time);
			}
			
			var queued = this.queue.length;
			
			// process current events for the current timestamp
			while (this.queue.length && this.timestamp == parseInt(this.queue[0].event_time)) {
				var q, i = 1;
				
				// group similar events into a single queue to
				// reduce the number of times we have to call an
				// event handler.
				while(i < this.queue.length
				      && this.queue[0].event_type == this.queue[i].event_type
				      && this.queue[0].event_time == this.queue[i].event_time
				) {
					i++;
				}
				q = this.queue.splice(0, i);

				// call event handler to process events
				event_handler = q[0].event_type;
				if ($.isFunction(this.events[q[0].event_type])) {
					this.events[q[0].event_type].apply(this, [ q ]);
				} else {
					;;; debug("'" + q[0].event_type + "' event handler does not exist!");
				}
			}
			
			// update any item tickers
			if (this.canvas.items) with (this.canvas) {
				for (var i in items) {
					items[i].tick();
				}
			}

			// update the scoreboard (does nothing if not visible)
			this.update_scoreboard();

			//;;; debug('seconds behind: ' + Math.round(new Date().getTime()/1000 - this.timestamp));
			// Increment the timestamp for the next iteration only
			// if there is an event currently waiting. Do not
			// increment it if the queue is empty since it's
			// possible to overrun the queue when watching a
			// real-time game.
			if (this.queue.length) {
				this.timestamp++;
				this.idle = 0;
			} else {
				if (!this.idle) this.idle = new Date().getTime()/1000;
			}

			// the queue has ended... go to intermission...
			if (this.game.end_time && this.next_offset >= this.game.max_idx) {
				;;; debug('END OF QUEUE -- Going to intermission!');
				this.intermission();
			} else if (this.idle && new Date().getTime()/1000 - this.idle > this.opt.max_queue_idle) {
				;;; debug('QUEUE IS IDLE -- Going to intermission!');
				this.intermission();
			}

			return this;
		},

		// display intermission message.
		intermission: function() {
			this.stop();		// stop playback
			this.msg_intermission = this.canvas.message('Intermission', 'B-5C', true);
			if (!this.keysdown[32]) this.toggle_scoreboard(true);
			this.one_timer('reset_intermission', this.opt.intermission_interval);
		},

		// reset intermission and attempt to get the next game.
		reset_intermission: function() {
			if (this.msg_intermission) {
				this.canvas.clear_messages(this.msg_intermission);
				delete this.msg_intermission;
			}
			// try to setup for the next available game
			if (this.opt.continuous) {
				this.setup(0); //, this.opt.auto_advance);
			}
		},

		// manage the queue by fetching new events.
		manage_queue: function(){
			;;; debug5('psycholive.manage_queue(' + this.seconds_queued() + ' seconds)');

			// throttle the queue if we have too much ...
			if (this.queue_locked
			    || this.queue_pending
			    || this.seconds_queued() > this.opt.max_seconds_to_queue)
			{
				return this;
			}
			var $this = this;
			var params = {
				game: this.game.game_id,
				req: 'update', 
				s: this.opt.seconds_per_interval,
				o: this.next_offset
			};
			this.queue_pending = true;
			if ($.isFunction(this.opt.queue)) {
				// call user function for a batch of events...
				// its assumed the user func will call this.process_queue()
				this.process_queue(this.opt.queue.call(this, params));
			} else {
				// send GET request for a batch of events...
				$.getJSON(this.opt.queue, params, function(data){ $this.process_queue(data) });
			}

			return this;
		},

		// takes an event queue response and processes it...
		process_queue: function(data) {
			;;; debug5('psycholive.process_queue()');
			data.game = this.sanitize(data.game, true);
			if (!data.code) {
				//this.queue_pending = false;
				this.canvas.message(data.message || 'Fetching queue failed', 'M-20C');
				return this;
			} else if (data.game.game_id != this.game.game_id) {
				this.canvas.message('Mismatched game returned! Restarting', 'MC');
				this.stop();
				this.prev_game = {};
				this.setup(data.game.game_id); //, true);
				return this;
			} else if (data.next_offset < this.next_offset) {
				this.canvas.message('Invalid offset returned! Restarting', 'MC');
				this.stop();
				this.prev_game = {};
				this.setup(data.game.game_id); //, true);
			}
			// advance the offset 
			this.next_offset = data.next_offset || 0;
			
			;;; debug5(data.events.length + ' events returned.');
			// add any new events to the end of the queue
			if (data.events && data.events.constructor == Array) {
				this.queue = this.queue.concat(data.events);
			}

			// extend the current game object with the new information
			// from the response.
			$.extend(this.game, data.game);
			
			// reset the pending flag so we can get more events...			
			this.queue_pending = false;
			return this;
		},

		// called when no games were found and we want to recheck...
		recheck_queue: function() {
			;;; debug4('psycholive.recheck_queue()');
			this.stop_timer('recheck_queue');
			this.queue_locked = false;
			this.stop();		// just make sure its stopped...
			this.setup(0); //, true);	// game_id 0 so we'll check for newest
		},

		// returns the total seconds currently queued
		seconds_queued: function() {
			if (!this.queue || !this.queue.length) return 0;
			return parseInt(this.queue[this.queue.length-1].event_time) -
				parseInt(this.queue[0].event_time) + 1;
		},

		start_timer: function(func, interval){
			if ($.isFunction(this[func])) {
				;;; debug('psycholive.start_timer('+func+', '+interval+')');
				$.timer.add(this, interval, func, this[func], 0, false);
			}
			return this;
		},
		
		stop_timer: function(func) {
			;;; debug('psycholive.stop_timer('+func+')');
			$.timer.remove(this, func, this[func]);	
			return this;
		},
		
		one_timer: function(func, interval) {
			return $.timer.add(this, interval, func, this[func], 1);
		},
		
		// sanitizes the game object (convert strings to ints, etc)
		sanitize: function(obj, nodefaults) {
			var params;
			if (typeof obj != 'object') return obj;
			if (!nodefaults) {
				if (!obj.gametype) obj.gametype = 'halflife';
				if (!obj.modtype) obj.modtype = 'gungame';
				if (!obj.map) obj.map = 'unknown';
			}
			params = [ 'code', 'start_time', 'end_time', 'max_idx',
				'next_offset', 'game_id', 'newest_timestamp', 
				'total_events', 'server_ip', 'server_port'
				];
			for (var i in params) {
				obj[params[i]] = parseInt(obj[params[i]]);
			}
			// if no overlay was returned we fake a record so
			// playback can at least show something... 
			if (!nodefaults) {
				if (!obj.overlay) {
					obj.overlay = {
						fake: true,
						image_url: this.opt.decals_url + this.opt.decals.no_overlay,
						gametype: obj.gametype,
						modtype: obj.modtype,
						map: obj.map,
						minx: -8000,
						miny: -8000,
						maxx: 8000,
						maxy: 8000,
						flipv: 1,
						fliph: 0,
						width: this.opt.width,
						height: this.opt.height
					};
				} else {
					params = [ 'height', 'width', 'minx', 'miny', 'maxx',
						'maxy', 'fliph', 'flipv', 'rotate' ];
					for (var i in params) {
						obj.overlay[params[i]] = parseInt(obj.overlay[params[i]]);
					}
				}
				if (!obj.players || !obj.players.length) obj.players = [];
				if (!obj.entities || !obj.entities.length) obj.entities = [];
			}
			return obj;
		},

		enable_scoreboard: function() {
			var $this = this;
			if (this.scoreboard_enabled) return this;
			this.scoreboard_enabled = true;
			$(document)
				.bind('keypress.psycholive', function(e){ return $this.events.KEY_PRESS.call($this,e) })
				.bind('keyup.psycholive', function(e){ return $this.events.KEY_UP.call($this,e) });
			return this;
		},
		
		disable_scoreboard: function() {
			if (!this.scoreboard_enabled) return this;
			delete this.scoreboard_enabled;
			$(document)
				.unbind('keypress.psycholive')
				.unbind('keyup.psycholive');
			return this;
		},

		// initialize the scoreboard, must be called after the
		// background is fully initialized, otherwise we won't know the
		// dimensions to use to create it.
		// this is initialized from canvas_callback().
		init_scoreboard: function() {
			if (!this.opt.enable_scoreboard) {
				return this;
			}
			;;; debug('psycholive.init_scoreboard()');
			
			if (this.scoreboard) {
				// reset the existing scoreboard and remove children
				this.scoreboard.hide().empty();
				// reappend the scoreboard to the current DOM
				if (!this.scoreboard.parent().length) {
					this.scoreboard.appendTo(this.canvas.dom);
				}
			} else {
				// create dom for new scoreboard
				this.scoreboard = $('<div class="scoreboard"></div>')
					.css({
						display: 'none',
						position: 'absolute',
						overflow: 'auto',
						opacity: 0.75,
						zIndex: ZINDEX.SCORE
					})
					.appendTo(this.canvas.dom);
			}

			var teams = team_names(this.game.gametype, this.game.modtype);

			// rebuild the scoreboard DOM
			// header, 2 columns or rows of teams and a footer
			$('<div class="scoreboard-hdr">Scoreboard (hold space)</div>').appendTo(this.scoreboard);

			// I hate using tables for layout, but in this case it's
			// the easiest way to deal with it.
			if (Math.round(this.canvas.dom.width() * 0.80) >= 325) {
				$('<div class="scoreboard-table"><table><tbody>' +
				  '<tr class="scoreboard-teamhdr">' +
				  '<th class="' + teams[0].toLowerCase() + '">' + teams[0] + '</th>' +
				  '<th class="' + teams[1].toLowerCase() + '">' + teams[1] + '</th>' +
				  '</tr>' +
				  '<tr class="scoreboard-teams">' +
				  '<td class="team ' + teams[0].toLowerCase() + '"><ul></ul></td>' +
				  '<td class="team ' + teams[1].toLowerCase() + '"><ul></ul></td>' +
				  '</tr>' +
				  //'<tr><td class="scoreboard-ftr" colspan="2">Hold space to see scoreboard</td></tr>' + 
				  '</tbody></table></div>').appendTo(this.scoreboard);
			} else {	// width is too narrow to do 2 columns
				$('<div class="scoreboard-table"><table><tbody>' +
				  '<tr class="scoreboard-teamhdr"><th class="' + teams[0].toLowerCase() + '">' + teams[0] + '</th></tr>' +
				  '<tr class="scoreboard-teams"><td class="team ' + teams[0].toLowerCase() + '"><ul></ul></td></tr>' +
				  '<tr class="scoreboard-teamhdr"><th class="' + teams[1].toLowerCase() + '">' + teams[1] + '</th></tr>' +
				  '<tr class="scoreboard-teams"><td class="team ' + teams[1].toLowerCase() + '"><ul></ul></td></tr>' +
				  //'<tr><td class="scoreboard-ftr">Hold space to see scoreboard</td></tr>' + 
				  '</tbody></table></div>').appendTo(this.scoreboard);
			}
			// footer
			//$('<div class="scoreboard-ftr">Hold space to see scoreboard</div>').appendTo(this.scoreboard);
			
			return this;
		},
		
		// updates the scoreboard only if its visible
		update_scoreboard: function(force_update) {
			if (!this.opt.enable_scoreboard || !this.scoreboard) {
				return this;
			}

			// shortcut to the DOM of the scoreboard
			var sb = this.scoreboard;
			
			// if the scoreboard is not visible then do nothing.
			if (!sb.is(':visible') && !force_update) {
				return this;
			}

			;;; debug5('psycholive.update_scoreboard()');

			if ($.isFunction(this.opt.scoreboard_callback)) {
				if (!this.opt.scoreboard_callback.apply(sb, [ this, 'update', sb, force_update ])) {
					// allow the user function to stop
					// processing by returning false
					return this;
				}
			}

			// get each team separately and sort
			var teams = {};
			for (var p in this.players) {
				var t = this.players[p].team_name();
				if (teams[t] == undefined) teams[t] = [];
				teams[t].push(this.players[p]);
			}
			var table = $('table', sb);
			var names = team_names(this.game.gametype, this.game.modtype);
			for (var t in names) {
				t = names[t];
				if (!teams[t]) {
					continue;
				}
				// sort by kills DESC, deaths ASC, onlinetime DESC
				teams[t] = teams[t].sort(function(a,b){
					var c = b.plr.kills - a.plr.kills;
					if (c == 0) { // kills are the same, sort by deaths
						return a.plr.deaths - b.plr.deaths;
						//var d = a.plr.deaths - b.plr.deaths;
						//if (d == 0) { // deaths are the same, sort by onlinetime
						//	return b.onlinetime() - a.onlinetime();
						//} else {
						//	return d;
						//}
					} else {
						return c;
					}
				});
			}
			
			// remove all players from the board
			$('td.team ul li', table).remove();

			// update all players on the board
			for (var i in [0,1]) {
				var team = $('td.' + names[i].toLowerCase() + ' ul', table);
				var html = '';
				for (var p in teams[names[i]]) {
					p = teams[names[i]][p];
					html += '<li id="plr' + this.game.game_id + '-' + p.plr.ent_id + '">' +
						'<span class="name">' + p.name() + '</span>' +
						'<span class="onlinetime">' + seconds_to_time(p.onlinetime()) + '</span>' +
						'<span class="kills">' + p.plr.kills + '</span>' +
						'<span class="deaths">' + p.plr.deaths + '</span>' +
						'</li>';
				}
				if (html != '') team.append(html);
			}

			var h = Math.round(this.canvas.dom.height() * 0.80);
			sb.height( sb.height() >= h ? h : 'auto' );
			
			return this;
		},
		
		// toggles the scoreboard (hide/show)
		toggle_scoreboard: function(toggle, speed) {
			if (!this.opt.enable_scoreboard || !this.scoreboard) {
				return this;
			}

			var sb = this.scoreboard;
			
			if (toggle == undefined) toggle = sb.is(':visible');
			if (speed == undefined) speed = 'normal';
			
			// call user function, if defined
			if ($.isFunction(this.opt.scoreboard_callback)) {
				if (!this.opt.scoreboard_callback.apply(sb, [ this, 'toggle', sb, toggle, speed ])) {
					// allow the user function to stop
					// processing by returning false
					return this;
				}
			}
			
			// toggle the scorebard
			if (toggle) {
				// auto adjust the w/h of the scoreboard to a
				// percentage of the overlay image so it always
				// fits within it. 
				
				var cw = this.canvas.dom.width();
				var ch = this.canvas.dom.height();
				var w = Math.round(cw * 0.80);		// 80%
				var h = Math.round(ch * 0.80);		// 80%
				sb.width(w).css({
					left: Math.round(cw/2 - w/2),
					top:  Math.round(ch/2 - h/2)
				});
				
				// update the scoreboard before its shown
				this.update_scoreboard(true);

				// make sure the current height of the SB is
				// within the overlay boundaries.
				sb.height( sb.height() >= h ? h : 'auto' );
				sb.stop().css({opacity: 0.75}).hide().fadeIn(speed);
			} else {
				sb.stop().css({opacity: 0.75}).show().fadeOut(speed);
			}
			
			return this;
		},
		
		init_combatlog: function() {
			if (!this.opt.enable_combatlog) {
				return this;
			}
			;;; debug('psycholive.init_combatlog()');
			
			if (this.combatmsg) {
				this.combatmsg.hide().empty();
				if (!this.combatmsg.parent().length) {
					this.combatmsg.appendTo(this.canvas.dom);
				}
			} else {
				this.combatmsg = $('<div class="combatlog"><ul></ul></div>')
					.css({
						position: 'absolute',
						opacity: 1.0,
						//height: this.opt.combatlog_size + 'em',
						//width: this.opt.combatlog_width + 'px',
						left: '1px',
						top:  '1px'
					})
					.appendTo(this.canvas.dom);
				position_element(this.combatmsg, this.canvas.dom, this.opt.combatlog_pos);
			}
			return this;
		},

		// clear the combat log
		clear_combatlog: function() {
			if (!this.combatmsg) {
				return this;
			}

			var div = this.combatmsg;

			// call user function, if defined
			if ($.isFunction(this.opt.combatlog_callback)) {
				if (!this.opt.combatlog_callback.apply(sb, [ this, 'clear', div ])) {
					// allow the user function to stop
					// processing by returning false
					return this;
				}
			}

			this.combatmsg.fadeOut('normal',
				function(){ div.hide(); $('ul li', div).remove(); }
			);
			
			return this;
		},

		// write a message to the combat log and maintain its overall size
		combatlog: function(msg) {
			if (!this.combatmsg) {
				return this;
			}

			var div = this.combatmsg;

			// call user function, if defined
			if ($.isFunction(this.opt.combatlog_callback)) {
				if (!this.opt.combatlog_callback.apply(sb, [ this, 'update', div, msg ])) {
					// allow the user function to stop
					// processing by returning false
					return this;
				}
			}

			if (!div.is(':visible')) {
				div.fadeIn();
			}
			
			// remove the last message if we have too many
			if ($('ul li', div).length >= this.opt.combatlog_size) {
				$('ul li:last', div).remove();
			}

			// write the message to the combat log
			$('ul', div).prepend('<li>' + msg + '</li>');
			var li = $('li:first', div);
			setTimeout(function(){
				if (li.length) {
					li.fadeOut('slow', function(){
						if (li.length) li.remove();
						//if ($('ul li', div).length == 0) div.fadeOut();
					});
				}
			}, 3000);
			
			return this;
		}
		
	};

	// PsychoLive Player constructor.
	function Player(plr, pslive) {		// new Player()
		return this.init(plr, pslive);
	};
	$.fn.psycholive.Player = Player;
	Player.prototype = $.fn.psycholive.Player.prototype = {
		init: function(plr, pslive) {
			this.plr = {
				ent_id:		parseInt(plr.ent_id),
				start_time:	parseInt(plr.start_time || plr.event_time),
				health: 	isFinite(plr.health) ? Math.max(parseInt(plr.health), 100) : 100,
				type: 		plr.ent_type,
				name: 		plr.ent_name,
				//team: 	parseInt(plr.team),
				//alive:	plr.alive ? true : false,
				//spawned: 	plr.spawned ? true : false,
				kills:		plr.kills ? parseInt(plr.kills) : 0,
				deaths:		plr.deaths ? parseInt(plr.deaths) : 0,
				suicides: 	plr.suicides ? parseInt(plr.suicides) : 0,
				xyz: 		[0,0,0],
				pov:		0
			};
			this.pslive = pslive;
			this.game = pslive.game;
			this.canvas = pslive.canvas;
			this.opt = pslive.opt;
			this.avatar = {};
			this.items = {};

			// create the overall wrapper for the player's avatar
			this.avatar.dom = $('<div class="pslive-avatar"></div>')
				.attr('title', this.name())
				.css({
					padding: 0,
					margin: 0,
					border: 'none',
					display: 'block',
					position: 'absolute',
					overflow: 'visible',
					top: -this.opt.avatar.height + 'px',
					left: -this.opt.avatar.width  + 'px',
					width: this.opt.avatar.width,
					height: this.opt.avatar.height,
					zIndex: ZINDEX.AVATAR
				})
				.appendTo(this.canvas.dom);
			
			// create the avatar icon
			var tokens = this.plr;
			tokens.team = this.team_name().toLowerCase();
			this.avatar.icon = $('<div class="icon"></div>')
				.css({
					padding: 0,
					margin: 0,
					border: 'none',
					display: 'block',
					position: 'absolute',
					overflow: 'hidden',
					background: 'url(' + this.opt.decals_url
						+ parse_token_str(this.opt.avatar.icon, tokens)
						+ ') no-repeat 0 50%',
					top: Math.round(this.opt.avatar.height/2 - this.opt.avatar.icon_height/2) + 'px',
					left: Math.round(this.opt.avatar.width/2 - this.opt.avatar.icon_width/2)  + 'px',
					width: this.opt.avatar.icon_width,
					height: this.opt.avatar.icon_height,
					zIndex: ZINDEX.ICON
				})
				.appendTo(this.avatar.dom);
			
			// create the avatar health bar
			if (this.opt.avatar.health) {
				this.avatar.health = $('<div class="health"></div>') //<div class="pct"></div></div>')
					.css($.extend({}, this.opt.avatar.health_styles, {
						padding: 0,
						margin: 0,
						display: 'block',
						position: 'absolute',
						overflow: 'hidden',
						width: this.opt.avatar.health_width + 'px',
						height: this.opt.avatar.health_height + 'px',
						zIndex: ZINDEX.HEALTH
					}))
					.appendTo(this.avatar.dom);
				position_element(
					this.avatar.health,
					this.avatar.dom,
					this.opt.avatar.health_pos
				);
			}
			
			// set the plrs team, so their decal will update
			this.team(plr.team, true);
			this.alive(plr.alive);

			// if the players spawn flag is set they lets add them
			// to the canvas, dead or alive.
			this.spawn(plr.xyz);
			if (this.spawned()) {
				if (!this.alive()) this.died();
				if (plr.items) {
					for (var i in plr.items) {
						if (plr.items[i]) this.pickup(i);
					}
				}
			}

			if (this.opt.pngfix) this.avatar.dom.pngFix();

			return this;
		},

		// returns the total time this player has been connected
		onlinetime: function() {
			return this.pslive.timestamp - this.plr.start_time;
		},

		name: function(name) {
			if (name == undefined) {
				return this.plr.name;
			}
			this.plr.name = name;
			return this;
		},
		
		// get/set the player's spawn flag
		spawned: function(spawn) {
			if (spawn != undefined) {
				this.plr.spawned = spawn;
				return this;
			}
			return this.plr.spawned;
		},
		
		// spawn the plr at a spot on the map instantly.
		// this will actually display the avatar on the map.
		spawn: function(xyz) {
			xyz = xyz ? xyz.split(' ') : this.plr.xyz;
			//if (xyz[0] == this.plr.xyz[0] && xyz[1] == this.plr.xyz[1]) {
			//	// don't spawn the player on the same spot twice
			//	return this;
			//}
			this.plr.xyz = xyz;
			var xy = this.canvas.translate(this.avatar.dom, this.plr.xyz);
			this.avatar.dom.css({
				left: xy[0] + 'px',
				top:  xy[1] + 'px',
				display: 'none',
				opacity: 1.0
			}).fadeIn('fast');
			this.plr.spawned = true;
			if (this.body) {
				this.body.remove();
				delete this.body;
			}
			return this;
		},
		
		// move the player from its current location to somewhere else
		// using a smooth animation and POV angle direction
		move: function(xyz, pov) {
			// ignore dead players. we don't need to see spectators
			if (!this.alive()) {
				return this;
			}
			if (!this.spawned()) {
				// don't move until we've properly spawned...
				// this avoids some potential jitter at the
				// start of rounds.
				;;; debug(this.name() + ' was not spawned before moving!');
				this.spawn(xyz);
				//return this;
			}
			xyz = xyz.split(' ');
			if (this.plr.xyz[0] != xyz[0] || this.plr.xyz[1] != xyz[1]) {
				this.plr.xyz = xyz;
				var xy = this.canvas.translate(this.avatar.dom, this.plr.xyz);
				this.avatar.dom.animate(
					{ left:xy[0]+'px',top:xy[1]+'px' },
					this.opt.frame_interval, // + (this.opt.frame_interval * 0.01),
					'linear'
				);
			}

			// change the players POV (sliding CSS technique)
			// Note: this does not work with IE5/6 with fixpng
			// enabled (only the first sub-image will ever show)
			if (this.opt.avatar.icon_steps > 0) {
				pov = Math.floor(pov / this.opt.avatar.icon_steps) * this.opt.avatar.icon_steps;
				if (pov < 0) pov += 360;
				if (pov != this.plr.pov) {
					var x = -Math.abs(pov) / this.opt.avatar.icon_steps * this.opt.avatar.icon_width;
					this.avatar.icon.css('backgroundPosition', x + 'px 50%');
					this.plr.pov = pov;
				}
			}
			return this;
		},
		
		// player attacked or healed someone else (or themself)
		attacked: function(p2, weapon, dmg) {
			var top, left, neg = (dmg < 0), val = Math.abs(dmg);
			p2.health(dmg);

			if (!this.opt.show_dmg || dmg == 0) {
				return this;
			}

			// the 2 <span>'s create text that has a background
			// shadow for easier reading.
			var div = $('<span class="pslive-dmg">' + val + '<span>' + val + '</span></span>')
				.css({position: 'absolute',zIndex: ZINDEX.DMG,display: 'none'})
				.appendTo(p2.avatar.dom);
			if (!neg) div.addClass('heal');

			// start the dmg text centered on the avatar icon
			top  = Math.round(
				parseInt(p2.avatar.icon.css('top'))
				+ p2.avatar.icon.height()/2
				- div.height()/2
			);
			left = Math.round(
				parseInt(p2.avatar.icon.css('left'))
				+ p2.avatar.icon.width()/2
				- div.width()/2
			);

			// alternate the dmg animation left and right...
			if (p2.dmg_go_left == undefined) p2.dmg_go_left = (Math.random() > 0.5);
			p2.dmg_go_left = !p2.dmg_go_left;

			// animate the dmg text up and out.
			// if its a 'heal' then the dmg goes down and out.
			div	.css({top: top + 'px', left: left + 'px', display: ''})
				.animate({
						left: (p2.dmg_go_left ? '-' : '+') + '=10px',
						top: (neg ? '-' : '+') + '=10px',
						opacity: 'hide'
					},
					{
						duration: this.opt.frame_interval,
						easing: 'swing',
						queue: false,
						complete: function(){div.remove(); delete div;}
					});
			return this;
		},
		
		killed: function(p2, weapon, hs){
			if (p2.alive()) {
				var msg;
				if (this.plr.ent_id == p2.plr.ent_id) {
					this.plr.suicides++;
					msg = '<b>' + this.name() + '</b> committed suicide with <b>' + weapon + '</b>';
				} else {
					this.plr.kills++;
					p2.plr.deaths++;
					msg = '<b>' + this.name() + '</b> killed <b>' + p2.name() + '</b> with <b>' + weapon + '</b>';
				}
				if (hs) msg += ' (hs)';
				this.pslive.combatlog(msg);
				p2.died();
			};
			return this;
		},

		died: function() {
			this.alive(false);
			if (this.body && this.body.length) {
				this.body.remove();
			}
			this.avatar.dom.stop();		// stop movement animation
			this.body = this.avatar.icon
				.clone()
				.addClass('pslive-body')
				.css({
					left: parseInt(this.avatar.dom.css('left')) + parseInt(this.avatar.icon.css('left')),
					top:  parseInt(this.avatar.dom.css('top')) + parseInt(this.avatar.icon.css('top')),
					opacity: 0.0,
					position: 'absolute'
				})
				.insertAfter(this.avatar.dom)
				.fadeTo(this.opt.frame_interval, 0.25);
			this.avatar.dom.fadeOut('fast');
		},

		// player picked up something (bomb, flag, etc...)
		pickup: function(name) {
			// don't duplicate the name
			if (this.items[name]) {
				this.items[name].cleanup();
				delete this.items[name];
			}

			// remove the item from the map
			this.canvas.clear(name);

			// create a new item for the player avatar
			this.items[name] = new Entity(name, this.opt, this.avatar.dom);

			// position the item depending on what it is
			var pos = '';
			switch (name) {
				case 'flag':
					// top left from the center
					pos = 'M-4C-4';
					break;
				case 'bomb':
					// bottom right from the center
					pos = 'BR';
					break;
				default:
					// left middle
					pos = 'LM';
					break;
			}
			this.items[name].pos(pos);
			return this;
		},

		// player dropped something (bomb, flag, etc...)
		drop: function(name, xyz) {
			if (!this.items[name]) {
				return this;
			}

			// add it to the canvas
			var item = this.canvas.drop(
				name,
				this.items[name].clone(),	// clone it
				xyz ? xyz : this.plr.xyz
			);

			// remove it from the player avatar
			this.items[name].cleanup();
			delete this.items[name];
			return item;
		},

		// the player changed teams ('team' is already an integer)
		team: function(team, force) {
			if (team === undefined) {
				return this.plr.team;
			}
			team = parseInt(team);
			var old = this.plr.team;
			this.plr.team = team;
			if (force || old != team) {
				var tokens = this.plr;
				tokens.team = this.team_name().toLowerCase();
				// update the player's avatar to match the team
				this.avatar.icon.css('backgroundImage',
					'url(' + this.opt.decals_url
					+ parse_token_str(this.opt.avatar.icon, tokens)
					+ ')'
				);
			}
			return this;
		},

		// returns the team name of the player or the team ID specified.
		team_name: function(team) {
			var name = 'UNKNOWN';
			if (team == undefined) {
				team = this.plr.team;
			}
			var t = parseInt(team);
			if (isNaN(t) && team) {
				// team is already a string, so return it
				return team.toUpperCase();
			}
			if (this.game.gametype == 'halflife') {
				if (t == 1) {
					name = 'SPECTATOR';
				} else if (this.game.modtype == 'cstrike') {
					switch (t) {
						case 2: name = 'TERRORIST'; break;
						case 3: name = 'CT'; break;
					}
				}
			}
			return name;
		},
		
		// returns the type of player (plr or bot)
		type: function() {
			// If ent_type is a number then convert it to a proper
			// entity string (PLR_CONNECT returns a number but
			// everything else requires a string)
			if (this.plr.type.constructor != String) {
				// this self-updates the variable so the
				// assignment is only done once
				switch (parseInt(this.plr.type)) {
					case 2:  this.plr.type = 'plr'; break;
					case 3:  this.plr.type = 'bot'; break;
					default: this.plr.type = 'unknown';
				}
			}
			return this.plr.type;
		},
		
		// changes the player's health by the dmg given.
		// if 'absolute' is true then the players health is set to dmg.
		health: function(dmg, absolute) {
			if (absolute) {
				this.plr.health = dmg;
			} else {
				this.plr.health += dmg;
			}
			if (this.avatar.health) {
				var len;
				if (this.opt.avatar.health_width > this.opt.avatar.health_height) {
					len = Math.ceil(this.plr.health / 100 * this.opt.avatar.health_width);
					this.avatar.health.css('width',  len > 0 ? len : 0);
				} else {
					len = Math.ceil(this.plr.health / 100 * this.opt.avatar.health_height);
					this.avatar.health.css('height', len > 0 ? len : 0);
				}
			}
			return this;
		},

		alive: function(alive) {
			if (alive == undefined) {
				return this.plr.alive;
			}
			if (alive) {
				this.avatar.dom.css({display: '', opacity: 1.0});
				this.health(100, true);
				if (this.body) {
					this.body.remove();
					delete this.body;
				}
			}
			this.plr.alive = alive;
			return this;
		},
		
		stop: function() {
			// stop entire queue, not just the current effect
			//this.avatar.dom.stop();
			//this.avatar.dom.queue("fx", []);
		},
		
		cleanup: function(all) {
			for (var i in this.items) {
				this.items[i].cleanup();
				delete this.items[i];
			}
			if (all) {
				if (this.body) {
					this.body.remove();
					delete this.body;
				}
			}
			return this;
		},
		
		disconnect: function() {
			if (this.dom) {
				this.dom.stop();
			}
			this.cleanup(true);
		}
	};

	// PsychoLive Entity constructor.
	// Entities are non-player items on a map, like a bomb, flag, turret
	// or other structures.
	// @param String name Defines the name of the entity: 'bomb', 'turret', ...
	// @param Object opt  Options/Settings hash.
	// @param Object ele  optional dom element to attach entity to.
	function Entity(name, opt, ele) { // new Entity()
		return this.init(name, opt, ele);
	};
	Entity.count = 0;	// static var
	$.fn.psycholive.Entity = Entity;
	Entity.prototype = $.fn.psycholive.Entity.prototype = {
		init: function(name, opt, ele) {
			this.id = 'pslive-entity-' + (++Entity.count);
			this.name = name.toLowerCase();
			this.opt = opt;
			this.attached = undefined;
			this.dom = $('<div id="' + this.id + '" class="entity entity-' + this.name + '">' +
				'<img src="' + this.opt.decals_url + this.opt.decals['entity_' + name] + ' />' +
				'</div>')
				.css({
					position: 'absolute',
					overflow: 'visible',
					//height: '16px',
					//width: '16px',
					top: 0,
					left: 0,
					zIndex: ZINDEX.ENTITY
				});
			if (ele) {
				this.attach(ele);
			}
			return this;
		},
		
		// returns a cloned item, w/o any attachment
		clone: function() {
			var ent = new Entity(this.name, this.opt);
			// get an exact clone of the dom?
			//ent.dom.remove();
			//ent.dom = this.dom.clone();
			return ent;
		},
		
		// Attach the entity to another element. If it's already
		// attached to something else, it'll auto-remove itself from
		// that element first.
		attach: function(ele) {
			if (this.attached) {
				this.detach();
			}
			this.attached = ele;
			this.dom.appendTo(ele);
			return this;
		},
		
		// detach from its parent element
		detach: function() {
			if (this.attached){ 
				$('#' + this.id).remove();
				this.attached = undefined;
			}
			return this;
		},
		
		// change the position of the entity
		pos: function(x,y) {
			if (!this.dom || !this.attached) return this;
			if (x.constructor == Array) {
				y = x[1];
				x = x[0];
			} else if (!isFinite(x)) {
				// x is a position string, ie: 'MC'
				var xy = position_element(this.dom, this.attached, x, true);
				x = xy[0];
				y = xy[1];
			}
			this.dom.css({left: x + 'px', top: y + 'px'});
			return this;
		},
		
		// shortcut for dom css
		css: function(a,b) {
			this.dom.css(a,b);
			return this;
		},
		
		// adds a timer box to the entity. mainly used for bombs.
		// this is not a real timer, the main frame loop will update this.
		start_timer: function(seconds){
			if (!this.ticker) {
				var s = seconds_to_time(seconds);
				this.ticker = $('<div class="pslive-entity-timer">' + s + '<span>' + s + '</span></div>')
					.css({
						left: '0px',
						top: $('img', this.dom).height() + 'px',
						//opacity: 0.8,
						position: 'absolute',
						zIndex: ZINDEX.TICKER
					});
				if (this.opt.decals.timer) {
					this.ticker.css({
						background: 'url(' + this.opt.decals_url + this.opt.decals.timer + ') no-repeat'
					});
				}
				this.ticker.appendTo(this.dom);
				this.ticker_seconds = seconds;
				this.ticker_paused = false;
			}
			return this;
		},
		
		// removes the timer from the entity
		stop_timer: function(){
			if (this.ticker) {
				this.ticker.remove();
				delete this.ticker;
				delete this.ticker_seconds;
				delete this.ticker_paused;
			}
			return this;
		},
		
		pause_timer: function(paused) {
			this.ticker_paused = paused;	
		},
		
		// Advance the timer tick one second
		tick: function(seconds) {
			if (this.ticker && !this.ticker_paused && this.ticker_seconds > 0) {
				this.ticker_seconds -= Math.max(0, seconds != undefined ? seconds : 1);
				var s = seconds_to_time(this.ticker_seconds);
				this.ticker.html(s + '<span>' + s + '</span>');
			}
		},
		
		// animate the item and bounce it.
		// only works if jquery.ui.effects is loaded
		start_bounce: function(times) {
			if (!this.dom.effect) return this;
			if (times == undefined) times = 0;
			this.bouncing = true;
			$.timer.add(this, this.opt.frame_interval, 'bounce', this._bounce, times, false);
			
			return this;
		},
		
		stop_bounce: function() {
			$.timer.remove(this, 'bounce', this._bounce);
			this.bouncing = false;
		},
		
		// PRIVATE function that performs the bounce animation
		_bounce: function() {
			$('img', this.dom).effect('bounce', { times: 1, distance: 3 }, this.opt.frame_interval/4);
		},
		
		// stop any animations and cleanup memory
		cleanup: function() {
			if (this.bouncing) this.stop_bounce();
			if (this.timer) this.remove_timer();
			if (this.dom) {
				this.dom.remove();
				delete this.dom;
			}
			return this;
		}
	};

	// PsychoLive Canvas constructor.
	// The canvas makes up the entire display area of where a PsychoLive
	// playback is shown.
	// @param object dom  jQuery DOM object of where to put the canvas
	// @param object opt  option hash
	function Canvas(dom, opt) { // new Canvas()
		return this.init(dom, opt);
	};
	$.fn.psycholive.Canvas = Canvas;
	Canvas.prototype = $.fn.psycholive.Canvas.prototype = {
		opt: 		{},		// local options
		game:		undefined,	// game object
		dom: 		undefined,	// parent DOM element (bg)
		background:	undefined,	// background image (overlay)
		width: 		100,		// current width
		height: 	100,		// current height
		paused: 	false,		// true if paused... duh
		
		init: function(dom, opt) {
			;;; debug4('canvas.init()');
			this.opt = opt;
			this.dom = dom;
			this.dimensions(opt.width, opt.height);
			this.reset();
			return this;
		},

		init_game: function(game) {
			;;; debug4('canvas.init_game()');
			this.game = game;
		},

		reset: function() {
			;;; debug4('canvas.reset()');
			this.items = {};
			this.init_dom();
			return this;
		},

		// allows the PsychoLive object to pass in handlers for
		// un/pausing the game...
		enable_pause: function(pause, unpause) {
			var $this = this;
			this.dom.click(
				function(){
					if ($this.paused) {
						$this.dom.attr('title', 'Click to pause');
						$this.paused = false;
						unpause();
					} else {
						$this.dom.attr('title', 'Click to unpause');
						$this.paused = true;
						pause();
					}
				}
			);
			this.dom.attr('title', 'Click to pause');
			return this;
		},
		
		// remove the pause toggle handlers
		disable_pause: function() {
			this.paused = false;
			this.dom.unbind('click'); //, this.pause).unbind('click', this.unpause);
			return this;
		},

		// initialize the DOM canvas background
		init_dom: function(dom) {
			;;; debug4('canvas.init_dom()');
			// assign our DOM element if given...
			if (dom) this.dom = dom;
			// clear everything from the DOM, normalize CSS and set
			// our dimensions.
			var $this = this;
			this.dom
				.empty()
				.width(this.width)
				.height(this.height)
				.css({
					position: 'relative',
					overflow: 'hidden',
					padding: 0
				});
			// display an 'initializing...' message
			this.msg_init = this.message('PsychoLive<br />Initializing...', 'MC');
			this.paused = false;
			return this;
		},

		init_background: function(callback) {
			var $this = this;
			var src = this.game.overlay.image_url;
			;;; debug4('canvas.init_background(' + src + ')');
			var background = new Image();
			background.onload = onload;
			background.onabort = onerror;
			background.onerror = onerror;
			background.src = src ? src : this.opt.decals.no_overlay;
			
			// local handlers for loading the image 
			function onload(){
				;;; debug('Overlay loaded: ' + this.src);
				// remove any overlay already in place.
				$('.pslive-overlay', $this.dom)
					.fadeOut('normal', function(){ $(this).remove(); });

				// slap the new overlay onto our background
				$this.background = $('<img class="pslive-overlay" src="' + this.src + '" />')
					.hide()
					.data('width', this.width)	// save original size
					.data('height', this.height)	// ...
					.prependTo($this.dom);
				;;; debug4('Overlay dimensions: ' + $this.background.width() + 'x' + $this.background.height());
				$this.resize_background();
				$this.background.fadeIn('normal');
				$this.background.loaded = true;
				if ($.isFunction(callback)) {
					callback($this, this);
				}
			}
			function onerror(){
				;;; debug('Error loading overlay: ' + this.src);
			}
			return this;
		},
		
		// resizes the overlay background to match the current
		// DOM size based on the scale setting.
		resize_background: function() {
			if (this.background.length) {
				this.resize_img(this.background);
				this.calc_cell_size();
				if (this.opt.fixed) {
					// reset dimensions incase they changed
					this.dom.width(this.opt.width);
					this.dom.height(this.opt.height);
				} else {
					// wrap our dimensions around the img
					this.dom.width(this.background.width());
					this.dom.height(this.background.height());
				}
				this.reposition_messages();
			}
			return this;
		},

		// Calculate grid cell size for overlay. This is used to
		// translate an entity xyz coord to an on-screen coord on the
		// map overlay.
		calc_cell_size: function() {
			this.cellWidth  = Math.abs(this.game.overlay.minx - this.game.overlay.maxx) / this.background.width();
			this.cellHeight = Math.abs(this.game.overlay.miny - this.game.overlay.maxy) / this.background.height();
			;;; debug('CELL SIZE = ' + Math.round(this.cellWidth) + 'x' + Math.round(this.cellHeight));
			return this;
		},
		
		// resizes the image to the maximum width and height based on
		// the scale settings.
		resize_img: function(img) {
			var iw = img.data('width');
			var ih = img.data('height');
			if (this.opt.scale == 'auto') {
				// scale the image proportionaly
				if (false && iw <= this.opt.width && ih <= this.opt.height) {
					// if the dimensions are smaller, keep
					// original size.
					img.width(iw);
					img.height(ih);
				} else {
					var sw = this.opt.width / iw;
					var sh = this.opt.height / ih;
					if (sh < sw) {
						img.height(this.opt.height);
						img.width(Math.round(iw * sh));
					} else {
						img.height(Math.round(ih * sw));
						img.width(this.opt.width);
					}
				}
			} else if (this.opt.scale == 'original') {
				// keep the original image dimensions
				img.width(iw);
				img.height(ih);
			} else if (this.opt.scale == 'max') {
				// resize the image to the maximum dimensions
				// note: this messes up the proportions but
				// is useful for situations where pslive is
				// embedded in a small block on a page and the
				// size of that block should never change.
				img.width(this.opt.width);
				img.height(this.opt.height);
			}
		},

		// Set the maximum dimensions for the canvas display.
		dimensions: function(width, height){
			var w = this.width;
			var h = this.height;
			this.width = width;
			this.height = height;
			if (w != width || h != height) {
				// resize the canvas to the new dimensions
			}
		},

		// display a message on top of the canvas DOM somewhere...
		// usually only a single message is displayed, if noclear is
		// true then any current messages will remain.
		message: function(str, pos, noclear) {
			if (!this.dom) return this;
			if (!noclear) this.clear_messages();
			var p = $('<div class="pslive-message">' + str + '</div>');
			p.css({ position: 'absolute', display: 'none', zIndex: ZINDEX.MSG, opacity: 0.8 });
			p.appendTo(this.dom);
			position_element(p, this.dom, pos);
			
			if (!this.messages) this.messages = [];
			this.messages.push({ element: p, pos: pos });
			return p.fadeIn();
		},
		
		// removes all messages from the DOM. if msg is specified
		// only the matching message is removed.
		clear_messages: function(msg) {
			if (msg) {
				for (var i=0; i<this.messages.length; i++) {
					if (this.messages[i] == msg) {
						delete this.messages[i];
					}
				}
				msg.fadeOut('normal', function(){msg.remove()});
			} else {
				this.messages = [];
				$('.pslive-message', this.ele).remove();
			}
		},

		// repositions all messages within the current DOM dimensions
		reposition_messages: function() {
			if (!this.messages) return this;
			for (var i=0; i<this.messages.length; i++) {
				position_element(this.messages[i].element, this.dom, this.messages[i].pos);
			}
			return this;
		},

		// translates the in-game co-ordinates to the overlay image.
		translate: function(ele, x, y) {
			var xx = 0, yy = 0;
			if (y == undefined) {
				if (x.constructor == Array) {
					y = x[1];
					x = x[0];
				} else if (x.constructor == String) {
					var xy = x.split(' ');
					x = xy[0];
					y = xy[1];
				}
			}
			xx = Math.round(Math.abs(this.game.overlay.minx - parseInt(x)) / this.cellWidth);
			yy = Math.round(Math.abs(this.game.overlay.miny - parseInt(y)) / this.cellHeight * -1 + this.background.height());
			if (ele) {
				xx -= Math.round(ele.width()/2);
				yy -= Math.round(ele.height()/2);
			}
			return [ xx, yy ];
		},

		// drop an item onto the canvas at the specified location
		// returns a reference to the dropped item
		drop: function(name, item, xyz) {
			var xy = this.translate(item.dom, xyz);
			if (this.items[name]) {
				this.clear(name);
			}
			this.items[name] = item.attach(this.dom).pos(xy);
			
			switch (name) {
				case 'bomb':
					this.items[name].start_bounce();
					break;
			}
			
			return this.items[name];
		},

		// clear an item, or all items from the canvas
		clear: function(name) {
			if (name) {
				if (this.items[name]) {
					this.items[name].cleanup();
					delete this.items[name];
				}
			} else {
				for (var i in this.items) {
					this.items[i].cleanup();
					delete this.items[i];
				}
			}
			return this;
		}
	};

	// Game event handler constructor
	// In event handlers 'this' points to the 'PsychoLive' object
	function Events() {
		return this.init();
	}
	$.fn.psycholive.Events = Events;
	Events.prototype = $.fn.psycholive.Events.prototype = {
		init: function() {
			return this;
		},
		PLR_CONNECT: function(events) {
			// this is triggered when players ENTER the game, not
			// when they connect since the connect events are mostly
			// useless for our purposes.
			for (var e in events) {
				var ent = events[e];
				// a json object should be included with all connect events
				if (ent.json) ent = $.extend(ent, eval('(' + ent.json + ')'));
				this.players[ ent.ent_id ] = new Player(ent, this);
			}
		},
		PLR_DISCONNECT: function(events) {
			for (var e in events) {
				this.players[ events[e].ent_id ].disconnect(events[e].value);
				delete this.players[ events[e].ent_id ];
			}
		},
		PLR_SPAWN: function(events) {
			for (var e in events) {
				this.players[ events[e].ent_id ].spawn(events[e].xyz);
			}
		},
		PLR_MOVE: function(events) {
			for (var e in events) {
				this.players[ events[e].ent_id ].move(events[e].xyz, events[e].value);
			}
		},
		PLR_NAME: function(events) {
			for (var e in events) {
				this.players[ events[e].ent_id ].name(events[e].value);
			}
		},
		PLR_TEAM: function(events) {
			for (var e in events) {
				this.players[ events[e].ent_id ].team(events[e].value);
			}
		},
		PLR_HURT: function(events) {
			for (var e in events) {
				var p  = this.players[ events[e].ent_id  ];
				var p2 = this.players[ events[e].ent_id2 ];
				p.attacked(p2, events[e].weapon, -events[e].value);
			}
		},
		PLR_KILL: function(events) {
			for (var e in events) {
				var p  = this.players[ events[e].ent_id  ];
				var p2 = this.players[ events[e].ent_id2 ];
				// if this.value is 1 then its a headshot
				p.killed(p2, events[e].weapon, parseInt(events[e].value) ? true : false);
			}
		},
		PLR_BOMB_PICKUP: function(events) {
			for (var e in events) {
				this.players[ events[e].ent_id ].pickup('bomb');
			}
			this.canvas.clear('bomb');
		},
		PLR_BOMB_DROPPED: function(events) {
			for (var e in events) {
				this.players[ events[e].ent_id ].drop('bomb');
			}
		},
		PLR_BOMB_PLANTED: function(events) {
			for (var e in events) {
				this.players[ events[e].ent_id ]
					.drop('bomb', events[e].xyz)
					.start_timer(events[e].value);
			}
		},
		PLR_BOMB_DEFUSED: function(events) {
			this.canvas.clear('bomb');
		},
		PLR_BOMB_EXPLODED: function(events) {
			// shake the map, if the effect() function is available
			if (this.canvas.dom.effect) {
				this.canvas.dom.effect('shake', { times: 2, distance: 4 }, 100);
			}
			this.canvas.clear('bomb');
		},
		ROUND_START: function(events) {
			var $this = this; // needed for callback below...
			this.round++;
			// mark all players 'alive'
			for (var p in this.players) {
				this.players[p].alive(true);
				if (!this.players[p].spawned()) {
					this.players[p].spawn();
				}
			}

			// clear the map of any dropped items and timers
			this.canvas.clear();

			// show a "Round Started!" message for a few seconds...
			var div = $('<div class="round-start">Round Starting!</div>')
				.css({ position: 'absolute', opacity: 0, zIndex: ZINDEX.ROUND })
				.appendTo(this.canvas.dom);
			position_element(div, this.canvas.dom, 'MC');
			div	.fadeIn(this.opt.frame_interval)
				.animate({ opacity: 1 }, this.opt.frame_interval * 4)	// fake delay; do nothing
				.fadeOut(this.opt.frame_interval, function(){div.remove(); delete div});
			// hide the scoreboard, if space isn't being held
			if (!this.keysdown[32] && this.round > 1) {
				this.toggle_scoreboard(false);
			}
			if (this.msg_round_end) {
				this.msg_round_end.fadeOut('fast', function(){$this.canvas.clear_messages($this.msg_round_end); delete $this.msg_round_end;});
				delete this.msg_round_end;
			}
			;;; debug('THE ROUND HAS STARTED!');
		},
		ROUND_END: function(events) {
			// remove all items from each player
			for (var p in this.players) {
				this.players[p].cleanup().spawned(false);
			}
			this.clear_combatlog();
			// show scoreboard, if space isn't being held
			if (!this.keysdown[32]) this.toggle_scoreboard(true);
			this.msg_round_end = this.canvas.message('End of Round', 'BL', true);
			;;; debug('THE ROUND HAS ENDED!!!');
		},
		KEY_PRESS: function(e) {
			var key = e.keyCode ? e.keyCode : e.which;
			// key is being held down, so ignore the event
			if (this.keysdown[key]) {
				e.stopPropagation();
				e.preventDefault();
				return false;
			}
			switch(key) {
				// space is the only cross-browser key I can
				// get to work properly for input...
				case 32:			// SPACE
					this.keysdown[key] = true;
					this.toggle_scoreboard(true);
					break;
			}
			if (this.keysdown[key]) {
				//this.verbose(key + ' = keypress(k:' + e.keyCode + ' c:' + e.charCode + ' w:' + e.which + ')');
				e.stopPropagation();
				e.preventDefault();
				return false;
			}
			return true;
		},
		KEY_UP: function(e) {
			var key = e.keyCode ? e.keyCode : e.which;
			if (this.keysdown[key]) {
				//this.verbose(key + ' = keyup   (k:' + e.keyCode + ' c:' + e.charCode + ' w:' + e.which + ')');
				delete this.keysdown[key];
				e.stopPropagation();

				switch(key) {
					case 32:			// SPACE
						stop = true;
						this.toggle_scoreboard(false);
						break;
				}
			}
		}
	};

	// Timer code from http://jquery.offput.ca/every/
	// Included here so only 1 file has to be loaded for PsychoLive
	//$.fn.extend({
	//	everyTime: function(interval, label, fn, times, belay) {
	//		return this.each(function() {
	//			$.timer.add(this, interval, label, fn, times, belay);
	//		});
	//	},
	//	oneTime: function(interval, label, fn) {
	//		return this.each(function() {
	//			$.timer.add(this, interval, label, fn, 1);
	//		});
	//	},
	//	stopTime: function(label, fn) {
	//		return this.each(function() {
	//			$.timer.remove(this, label, fn);
	//		});
	//	}
	//});
	$.extend({
		timer: {
			guid: 1,
			global: {},
			regex: /^([0-9]+)\s*(.*s)?$/,
			powers: {
				// Yeah this is major overkill...
				'ms': 1,
				'cs': 10,
				'ds': 100,
				's': 1000,
				'das': 10000,
				'hs': 100000,
				'ks': 1000000
			},
			timeParse: function(value) {
				if (value == undefined || value == null)
					return null;
				var result = this.regex.exec($.trim(value.toString()));
				if (result[2]) {
					var num = parseInt(result[1], 10);
					var mult = this.powers[result[2]] || 1;
					return num * mult;
				} else {
					return value;
				}
			},
			add: function(element, interval, label, fn, times, belay) {
				var counter = 0;
				
				if (jQuery.isFunction(label)) {
					if (!times) 
						times = fn;
					fn = label;
					label = interval;
				}
				
				interval = jQuery.timer.timeParse(interval);
	
				if (typeof interval != 'number' || isNaN(interval) || interval <= 0)
					return;
	
				if (times && times.constructor != Number) {
					belay = !!times;
					times = 0;
				}
				
				times = times || 0;
				belay = belay || false;
				
				if (!element.$timers) 
					element.$timers = {};
				
				if (!element.$timers[label])
					element.$timers[label] = {};
				
				fn.$timerID = fn.$timerID || this.guid++;
				
				var handler = function() {
					if (belay && this.inProgress) 
						return;
					this.inProgress = true;
					if ((++counter > times && times !== 0) || fn.call(element, counter) === false)
						jQuery.timer.remove(element, label, fn);
					this.inProgress = false;
				};
				
				handler.$timerID = fn.$timerID;
				
				if (!element.$timers[label][fn.$timerID]) 
					element.$timers[label][fn.$timerID] = window.setInterval(handler,interval);
				
				if ( !this.global[label] )
					this.global[label] = [];
				this.global[label].push( element );
				
			},
			remove: function(element, label, fn) {
				var timers = element.$timers, ret;
				
				if ( timers ) {
					
					if (!label) {
						for ( label in timers )
							this.remove(element, label, fn);
					} else if ( timers[label] ) {
						if ( fn ) {
							if ( fn.$timerID ) {
								window.clearInterval(timers[label][fn.$timerID]);
								delete timers[label][fn.$timerID];
							}
						} else {
							for ( var f in timers[label] ) {
								window.clearInterval(timers[label][f]);
								delete timers[label][f];
							}
						}
						
						for ( ret in timers[label] ) break;
						if ( !ret ) {
							ret = null;
							delete timers[label];
						}
					}
					
					for ( ret in timers ) break;
					if ( !ret ) 
						element.$timers = null;
				}
			}
		}
	});
	
	if ($.browser.msie) {
		$(window).one("unload", function() {
			var global = $.timer.global;
			for ( var label in global ) {
				var els = global[label], i = els.length;
				while ( --i ) $.timer.remove(els[i], label);
			}
		});
	}
	// ^^ END OF TIMER CODE ^^

	// convert integer seconds into a time string of "00:00"
	function seconds_to_time(seconds) {
		var minutes = 0;
		seconds = Math.floor(seconds);
		if (seconds > 59) {
			minutes = Math.floor(seconds / 60);
			seconds = seconds % 60;
		}
		minutes = new String(minutes);
		seconds = new String(seconds);
		if (minutes.length == 1) minutes = '0' + minutes;
		if (seconds.length == 1) seconds = '0' + seconds;
		return minutes + ':' + seconds;
	};

	// Positions an element within another dom element (usually the canvas)
	// based on the size of the element and 2 character string which
	// specifies its position:
	// T=top,M=middle,B=bottom,L=left,C=center,R=right.
	// Each character can have an offset applied to it using +X or -X, for
	// example: T+20C (Top + 20 pixels and Center)
	// if xy_only is true than the values are returned instead of moving the
	// element directly.
	function position_element(ele, dom, str, xy_only){
		if (!str) str = 'MC';
		var left = 0;
		var top  = 0;
		var pos = str.toUpperCase().split('');
		var lastpos = '';
		for (var i=0; i<pos.length; i++) {
			var ofs = 0;
			if (pos[i].match(/[-+]/)) {
				var sign = pos[i++];
				var pct = false;
				var num = '';
				// read the number and optional % sign
				// technically this will parse incorrect numbers
				// like: +1%0 == 10% but I don't care...
				while (isFinite(pos[i]) || pos[i] == '%') {
					if (pos[i] == '%') {
						pct = true;
					} else {
						num += pos[i];
					}
					i++;
				}
				ofs = parseInt(sign + num);
				if (lastpos.match(/[TMB]/)) {	// TOP
					if (pct) {
						ofs = dom.height() * ofs * 0.01;
					}
					top += ofs;
				} else {			// LEFT
					if (pct) {
						ofs = dom.width() * ofs * 0.01;
					}
					left += ofs;
				}
			}
			lastpos = pos[i];
			switch (pos[i]) {
				case 'T': top  += 1; break;
				case 'M': top  += dom.height()/2 - ele.outerHeight(true)/2; break;
				case 'B': top  += dom.height() - ele.outerHeight(true) - 1; break;
				case 'L': left += 1; break;
				case 'C': left += dom.width()/2 - ele.outerWidth(true)/2; break;
				case 'R': left += dom.width() - ele.outerWidth(true) - 1; break;
			}
		}
		if (!xy_only) {
			ele.css({ left: Math.round(left) + 'px', top: Math.round(top) + 'px' });
			//return ele;
		}
		return [ left, top ];
	};
	
	// returns a string with %tokens% replaced with actual values from
	// the tokens object.
	function parse_token_str(str, tokens) {
		if (!tokens) return str;
		var left,right,t,v;
		left = str.indexOf('%');
		while (left != -1) {		// find first occurance of '%'
			right = str.indexOf('%', left+1);	// find second
			if (right == -1) break;
			t = str.substring(left+1, right);	// token name
			v = tokens[t] != undefined ? tokens[t] : '';
			str = str.substring(0, left) + v + str.substring(right+1);
			left = str.indexOf('%', left + v.length);
		}
		return str;
	};

	// returns an array of the teams for the current game
	function team_names(gametype, modtype) {
		if (gametype == 'halflife') {
			switch (modtype) {
				case 'gungame': return [ 'TERRORIST', 'CT' ];
			}
		}
		return [ 'TEAM1', 'TEAM2'] ;
	};
	
	// simple debug messages
	function debug(msg, level) {
		if (!level) level = 1;
		if (level > debug_level) return;
		$('#debug').prepend('<li>' + msg + '</li>').children(':gt(99)').remove();
		//if (window.console && window.console.log) {
		//	window.console.log(msg);
		//} else {
		//	$('#debug ul').prepend('<li>' + msg + '</li>');
		//}
	};
	function debug2(msg) { debug(msg, 2) };
	function debug3(msg) { debug(msg, 3) };
	function debug4(msg) { debug(msg, 4) };
	function debug5(msg) { debug(msg, 5) };
	// allow global access
	$.fn.psycholive.debug = debug;
	$.fn.psycholive.debug2 = debug2;
	$.fn.psycholive.debug3 = debug3;
	$.fn.psycholive.debug4 = debug4;
	$.fn.psycholive.debug5 = debug5;

	// Global defaults for PsychoLive.
	$.fn.psycholive.defaults = {
		debug: 			0,		// Debug level: debug info is logged using console.log (firebug)

		fixed:			false,		// if true, the w/h will be strictly enforced,
							// if false, the w/h will wrap around the scaled img
		scale:			'auto',		// how to scale overlays (auto, original, max)
		width: 			500,		// width of overlay canvas
		height:			500,		// height of overlay canvas

		auto_start: 		true,		// if true, playback will start automatically when instantiated
		auto_advance: 		true,		// if true, playback will start from the most recent events instead of the first
		url: 			undefined, 	// if defined setup and queue will default to this
		setup: 			undefined,	// url,func,object to provide game parameters
		queue:			undefined,	// url,func to receive queue updates
		setup_callback:		undefined,	// callback after a game setup is complete
		queue_callback: 	undefined,	// callback after each queue update

		enable_pause: 		true,		// if true, clicking on the DOM will toggle pause.
		enable_scoreboard:	true,		// if true, allow scoreboard to be shown
		enable_combatlog:	true, 		// if true, show combat log
		scoreboard_callback: 	undefined,	// a user function to call for each update to the scoreboard

		frame_interval: 	1000,		// playback speed (<1000 is only useful for precorded games)
		queue_interval: 	1000,		// update interval for event queue
		continuous:		true,		// if true: when a game ends the next available game will be played

		seconds_per_interval: 	15,		// how many seconds to queue at a time
		max_seconds_to_queue:	30,		// maximum seconds to queue (actual size will vary by +seconds_per_interval)

		intermission_interval:	30 * 1000,	// how many miliseconds to show intermission
		max_queue_idle:		60,		// how many seconds to wait for a non-empty queue
		max_queue_attempts: 	5,		// max number of queue timeouts allowed before halting
		recheck_interval: 	60 * 1000,	// recheck interval when no games are found

		combatlog_size:		5,		// how many messages show in the combat log
		combatlog_pos: 		'T+18L+3',	// location of the combat log div on the overlay
		combatlog_callback:	undefined,

		avatar: {
			width:		30,		// width of entire avatar dom
			height:		30,		// height...
			
			health: 	true,		// show player health?
			health_width:	16,		// length of player health bar
			health_height:	2,		// height...
			health_pos:	'B-4C',		// 2 char position string
			health_styles:	{
				opacity: 0.4,
				backgroundColor: '#00FF00'
			},
			
			icon:		'avatar-%team%-pov-23x23.png',
			icon_width: 	23,		// width of icon
			icon_height: 	23,		// height...
			icon_steps:	10		// number of steps (total = 359/steps)
		},
		show_dmg:	 	true,
		show_ghosts: 		true,		// if true, player ghosts are left behind when a player dies

		game_timer:		'TR',		// game timer position, blank for none. 2 characters:
							// T=top,B=bottom,M=middle, L=left,R=right,C=center

		pngfix: 		true,		// fix PNG img's for IE5/6?
							// note: this breaks plr POV

		decals_url:		'',		// base URL for decal images
		decals: 		{		// decal image definitions
			entity_bomb: 	'item-bomb.gif',
			timer: 		'timer.gif',
			no_overlay: 	'no_overlay.gif'
		}
	};
})(jQuery);

// jquery.pngFix.js
// http://jquery.andreaseberhard.de/
(function($){jQuery.fn.pngFix=function(j){j=jQuery.extend({blankgif:'blank.gif'},j);var k=(navigator.appName=="Microsoft Internet Explorer"&&parseInt(navigator.appVersion)==4&&navigator.appVersion.indexOf("MSIE 5.5")!=-1);var l=(navigator.appName=="Microsoft Internet Explorer"&&parseInt(navigator.appVersion)==4&&navigator.appVersion.indexOf("MSIE 6.0")!=-1);if(jQuery.browser.msie&&(k||l)){jQuery(this).find("img[@src$=.png]").each(function(){jQuery(this).attr('width',jQuery(this).width());jQuery(this).attr('height',jQuery(this).height());var a='';var b='';var c=(jQuery(this).attr('id'))?'id="'+jQuery(this).attr('id')+'" ':'';var d=(jQuery(this).attr('class'))?'class="'+jQuery(this).attr('class')+'" ':'';var e=(jQuery(this).attr('title'))?'title="'+jQuery(this).attr('title')+'" ':'';var f=(jQuery(this).attr('alt'))?'alt="'+jQuery(this).attr('alt')+'" ':'';var g=(jQuery(this).attr('align'))?'float:'+jQuery(this).attr('align')+';':'';var h=(jQuery(this).parent().attr('href'))?'cursor:hand;':'';if(this.style.border){a+='border:'+this.style.border+';';this.style.border=''}if(this.style.padding){a+='padding:'+this.style.padding+';';this.style.padding=''}if(this.style.margin){a+='margin:'+this.style.margin+';';this.style.margin=''}var i=(this.style.cssText);b+='<span '+c+d+e+f;b+='style="position:relative;white-space:pre-line;display:inline-block;background:transparent;'+g+h;b+='width:'+jQuery(this).width()+'px;'+'height:'+jQuery(this).height()+'px;';b+='filter:progid:DXImageTransform.Microsoft.AlphaImageLoader'+'(src=\''+jQuery(this).attr('src')+'\', sizingMethod=\'scale\');';b+=i+'"></span>';if(a!=''){b='<span style="position:relative;display:inline-block;'+a+h+'width:'+jQuery(this).width()+'px;'+'height:'+jQuery(this).height()+'px;'+'">'+b+'</span>'}jQuery(this).hide();jQuery(this).after(b)});jQuery(this).find("*").each(function(){var a=jQuery(this).css('background-image');if(a.indexOf(".png")!=-1){var b=a.split('url("')[1].split('")')[0];jQuery(this).css('background-image','none');jQuery(this).get(0).runtimeStyle.filter="progid:DXImageTransform.Microsoft.AlphaImageLoader(src='"+b+"',sizingMethod='crop')"}});jQuery(this).find("input[@src$=.png]").each(function(){var a=jQuery(this).attr('src');jQuery(this).get(0).runtimeStyle.filter='progid:DXImageTransform.Microsoft.AlphaImageLoader'+'(src=\''+a+'\', sizingMethod=\'scale\');';jQuery(this).attr('src',j.blankgif)})}return jQuery}})(jQuery);
	
