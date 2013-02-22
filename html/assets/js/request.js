/*
Copyright (c) 2013 Cimaron Shanahan

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

(function($) {
		  
Request = {
	
	options : {
		baseUrl : '/servers/proxy/commands'
	},

	/**
	 * Current state
	 */
	state : {
		connected : false,
		listening : false,
		proxy : true,
	},

	/**
	 * Debugger events
	 */
	events : {
		onDebuggerConnect : null,
		onDebuggerDisconnect : null,
		onGetDebuggerPacket : null,
		onGetDebuggerResponse : null,
		onDebuggerProxyError : null,
		onDebuggerProxyStart : null
	},

	/**
	 * Initialize client status
	 */
	initialize : function() {
		this.reset();
		this.status();
	},

	/**
	 * Send request
	 */
	_ajax : function(script, args, success, complete) {
		$.ajax({
			url : this.options.baseUrl + "/" + script,
			dataType : 'json',
			data : args,
			success : success,
			complete : complete, 
		});
	},

	triggerEvent : function(name, data) {
		console.log(name, data);
		if (this.events[name]) {
			this.events[name](data);
		}
	},

	/**
	 * Send reset request
	 *
	 * @param   function   callback   Callback on complete
	 */
	reset : function(callback) {
		this._ajax("reset.php", null, callback);
	},

	/**
	 * Send listen request
	 */
	listen : function() {
		if (!this.state.listening) {
			console.log('listener starting');
			this._ajax("listen.php", null, $.proxy(this._listenResponse, this));
			this.state.listening = true;
		}
	},

	/**
	 * Handle listen response
	 */
	_listenResponse : function(resp, xhr) {

		this.state.listening = false;

		var data = resp.data;
		this._statusResponse(resp);

		if (data.packets && data.packets.length) {
			for (var i = 0; i < data.packets.length; i++) {
				this.triggerEvent('onGetDebuggerPacket', data.packets[i]);	
			}
		}

	},

	/**
	 * Send 'send' request
	 */	
	send : function(args) {
		this._ajax("send.php", args, $.proxy(this._sendResponse, this));
	},

	/**
	 * Handle send response
	 */
	_sendResponse : function(resp, xhr) {
		var data = resp.data;

		this._statusResponse(resp);

		if (data.connected) {
			this.triggerEvent('onGetDebuggerResponse', data);
		}
	},

	/**
	 * Send request to listener
	 */	
	status : function() {
		this._ajax("status.php", null, $.proxy(this._statusResponse, this));
	},

	/**
	 * Handle status response
	 */
	_statusResponse : function(resp, xhr) {

		if (!this._checkServer(resp)) {
			return;	
		}

		var data = resp.data;
		if (data.connected && !this.state.connected) {
			this.state.connected = true;
			this.triggerEvent('onDebuggerConnect', data);
		}

		if (!data.connected && this.state.connected) {
			this.state.connected = false;
			this.triggerEvent('onDebuggerDisconnect', data);
		}
	},

	_checkServer : function(resp) {

		if (resp.name == 'error') {

			if (this.state.connected) {
				this.triggerEvent('onDebuggerDisconnect');
				this.state.connected = false;
			}

			if (this.state.proxy) {
				this.triggerEvent('onDebuggerProxyError');
				this.state.proxy = false;
			}

			setTimeout($.proxy(this.status, this), 5000);

			return false;

		} else {

			if (!this.state.proxy) {
				this.triggerEvent('onDebuggerProxyStart');
				this.state.proxy = true;
			}

			setTimeout($.proxy(this.listen, this), 0);

			return true;
		}
	}
	
};
		  
$().ready($.proxy(Request.initialize, Request));

}(jQuery));

