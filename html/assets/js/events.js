
Events = (function() {

	function Events() {
		this._events = {};
	}

	Events.initialize = Events;

	/**
	 * Add event callback
	 *
	 * @param  string     name   Event name
	 * @param  function   func   Callback
	 */
	Events.prototype.bind = function(name, func) {
		if (typeof func == 'function') {
			this._events[name] = this._events[name] || [];
			this._events[name].push(func);
		}
	};

	/**
	 * Remove event callback
	 *
	 * @param  string     name   Event name
	 * @param  function   func   Callback
	 */
	Events.prototype.unbind = function(name, func) {
		if (this._events[name]) {
			var i = this._events[name].indexOf(func);
			if (i != -1) {
				this._events[name].splice(i, 1);	
			}
		}
	};

	/**
	 * Trigger event callback
	 *
	 * @param  string     name   Event name
	 *
	 * @return  array
	 */
	Events.prototype.trigger = function(name, args) {
		var events = this._events[name];
		if (events) {
			for (var i = 0; i < events.length; i++) {
				events[i].apply(window, args);
			}
		}
	};

	return Events;

}());

