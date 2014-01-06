
DebuggerUi = (function($) {

	function DebuggerUi(options) {

		this.options = $.merge({
			elements : {
				state : 'run-state',
				source : 'source-container',
				file : 'source-file',
				context : 'inspect-local',
				watch : 'inspect-watch',
				global : 'inspect-global',
				defines : 'inspect-defines',
				stack : 'inspect-stack',
				breakpoints : 'inspect-breakpoints',
				breaken : 'break-enabled',
				loading : 'loading-indicator'
			}
	   }, options || {});


		Events.initialize.apply(this);
		
		this.debugger = new Debugger();
		this.inspector = new Inspector();
		
		var This = this;

		this.debugger.bind('onProxyDisconnect', $.proxy(this.onProxyDisconnect, this));

		//this.debugger.bind('onProxyStart', $.proxy(this.onProxyStart, this));
		//this.debugger.bind('onProxyError', $.proxy(this.onProxyError, this));

	}


	/**
	 * Update status display
	 *
	 * @param   string   status   Status text
	 */
	DebuggerUi.prototype.displayStatus = function(status) {
		$('#' + this.options.elements.state).text(status).removeClass().addClass('status-' + status);
	};

	DebuggerUi.prototype.onProxyStart = function() {
		this.displayStatus('listening');
	};

	DebuggerUi.prototype.onProxyError = function() {
		//this.displayStatus('restart proxy');
	};



	$.extend(DebuggerUi.prototype, Events.prototype);

	return DebuggerUi;

}(jQuery));

