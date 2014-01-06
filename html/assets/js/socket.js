
/**
 * WebSocket Connection Class
 */
Connection = (function() {

	function defaults() {
		return {
			options : {
				host : 'localhost',
				port : '1984',
				path : '/',
			},
			socket : null,
			open : false
		};
	}

	function Connection(options) {

		Events.apply(this);

		this.options = $.extend(defaults().options, options);

		for (var i in this.options) {
			if (i.match(/^on/)) {
				this.bind(i, this.options[i]);
			}
		}

		try {
			this.socket = new WebSocket('ws://' + this.options.host + ':' + this.options.port + this.options.path);
			this.socket.onopen = this.onSocketOpen.bind(this);
			this.socket.onclose = this.onSocketClose.bind(this);
			this.socket.onerror = this.onSocketError.bind(this);
			this.socket.onmessage = this.onSocketMessage.bind(this);
		} catch (e) {
		}
	}

	Connection.prototype.send = function(data) {
		if (this.open) {
			//console.log('websocket: sending ' + JSON.stringify(data));	
			this.socket.send(JSON.stringify(data));
		} else {
			throw new Error('Socket not open');	
		}
	};

	Connection.prototype.onSocketOpen = function() {
		this.open = true;
		this.trigger('onConnect');
	};

	Connection.prototype.onSocketClose = function(e) {
		this.open = false;
		this.trigger('onDisconnect', e);
	};

	Connection.prototype.onSocketError = function(e) {
		this.trigger('onError', e);
	};

	Connection.prototype.onSocketMessage = function(e) {
		console.log('websocket: got ' + e.data);
		this.trigger('onData', [JSON.parse(e.data)]);
	};	

	$.extend(Connection.prototype, Events.prototype);

	return Connection;
	
}());

