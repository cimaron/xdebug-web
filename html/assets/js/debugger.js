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

$.entityEncode = function(str) {
	return $('<div />').text(str || "").html();
};



var tid = 1;
var Transaction = function(args, func, options) {
	this.id = tid++;
	this.args = args;
	this.args.tid = this.id;
	this.func = func;
	this.options = options;
};
Transaction.prototype.exec = function(context, res) {

	if (!this.func) {
		return null;
	}

	return this.func.call(context, res, this);
};


/**
 * Debugger Object
 */
Debugger = {

	version : '0.2',
	
	options : {
		elements : {
			state : 'run-state',
			source : 'source-container',
			file : 'source-file',
			context : 'inspect-local',
			stack : 'inspect-stack'
		}		
	},

	state : null,
	globalState : {
		filePrefix : '/var/www/www.tor.vm/files/code/cb.1_8/html/',
		breakpoints : {}
	},

	/**
	 * Write log info
	 *
	 * @param   string   action   Action or command
	 * @param   string   data     Extra data
	 */
	log : function(action, data) {
		console.log(action + ": ", data);
		$('#debug table').append('<tr><td class="key">' + action + '</td><td class="value">' + $.entityEncode(data) + '</td></tr>');
		$('#debug').scrollTop(1000);
	},

	/**
	 * Polling function to check status
	 *
	 * @static
	 */
	checker : function() {
		$.ajax({
			url : "status.php",
			dataType : 'json',
			complete : function(xhr, status) {
				//setTimeout(function() { Debugger.checker(); }, 5000);
			},
			success : function(data, xhr) {
				Debugger.handleResponse(data);
				Debugger.listener();
			}
		});
	},

	/**
	 * Polling function to listen for data
	 *
	 * @static
	 */
	listener : function() {
		$.ajax({
			url : "listen.php",
			dataType : 'json',
			complete : function(xhr, status) {
				setTimeout(function() { Debugger.listener(); }, 0);
			},
			success : function(data, xhr) {
				Debugger.handleResponse(data);
			}
		});
	},

	/**
	 * Send command to client
	 *
	 * @param   string   command    Command to execute
	 * @param   hash     args       Optional arguments
	 * @param   string   data       Optional data
	 * @param   mixed    callback   Transaction or Callback function
	 * @param   mixed    force      Force the command to execute, even if connection is not established
	 */
	command : function(command, args, data, callback, force) {

		if (!this.state.connected && !force) {
			this.log('not connected');
			return;
		}

		var args = {
			command : command,
			args : args,
			data : data
		};

		var trans = new Transaction(args, callback);

		this.state.transactions[trans.id] = trans;

		this.state.commandQueue.push(trans);

		if (!this.state.currentCommand) {
			this.nextCommand();
		}
	},

	/**
	 *
	 */
	nextCommand : function() {

		if (this.state.commandQueue.length == 0) {
			return;	
		}

		var trans = this.state.commandQueue.shift();
		this.state.currentCommand = trans;

		this.log('send', JSON.stringify(trans.args));

		var data = $.ajax({
			url : "command.php",
			type : 'post',
			data : trans.args
		});

		trans.sent = 1;
	},

	/**
	 * Handle response
	 *
	 * @param   object   data   Response object
	 */
	handleResponse : function(data) {
		//this.log('response', JSON.stringify(data));

		//no current connections
		if (!data || !data.connected) {
			if (this.state.connected) {
				this.log('disconnected', this.state.connected);
				this.reset();
			}
			return;
		}

		//new connection != old connection
		if (this.state.connected && data.connected != this.state.connected) {
			this.log('disconnected', this.state.connected);
			this.reset();
		}

		//new connection
		if (!this.state.connected) {
			var newconnection = true;

			this.log('connected', data.connected);
			this.state.connected = data.connected;
		}
		
		//if there's no queue, this means it's the initial status check
		if (!data.queue) {
			if (newconnection) {
				this.command('status', null, null, Debugger.handleStatus);
			}
			return;
		}

		var transactions = this.state.transactions;
		var queue = this.state.commandQueue;

		for (var i = 0; i < data.queue.length; i++) {
			var res = data.queue[i];

			//throw away anything before an init packet
			if (res.name == 'init') {
				this.handleInit(res);
				newconnection = false;
				continue;
			}

			var tid = res.attributes.transaction_id;
			if (transactions[tid]) {
				transactions[tid].received = true;
				transactions[tid].exec(this, res);
				delete transactions[tid];
			}
		}

		if (this.state.currentCommand && this.state.currentCommand.received) {
			//console.log('currentCommand fulfilled');
			this.state.currentCommand = null;
			//console.log(queue.length + ' items in queue');
			this.nextCommand();
			return;
		}

	},

	/**
	 * Reset all state
	 */
	reset : function() {		

		this.state = {
			connected : null,
			status : null,
			features : {},
			transactions : {},
			commandQueue : [],
			file : '',
			line : 0,
			breakpoints : null,
			source : ''
		};

		this.updateStatus('disconnected');
	},

	/**
	 * Handle debugger init packet
	 *
	 * @param   object   init   Init packet
	 */
	handleInit : function(res) {

		this.state.info = res.attributes;
		this.state.breakpoints = [];

		this.state.file = this.state.info.fileuri;
		this.updateFile(this.state.file);

		this.log('init', this.state.info);

		var features = ['language_supports_threads', 'language_name', 'language_version', 'encoding',
		                'protocol_version', 'supports_async', 'data_encoding', 'breakpoint_languages',
		                'breakpoint_types', 'multiple_sessions', 'max_children', 'max_data', 'max_depth'];

		for (var i = 0; i < features.length; i++) {
			this.command('feature_get', {n : features[i]}, null, Debugger.handleFeatureGet);
		}

		this.command('status', null, null, Debugger.handleStatus);
	},

	/**
	 * Handle debugger feature_get response packet
	 *
	 * @param   object   res     Response
	 * @param   object   trans   Transaction
	 */
	handleFeatureGet : function(res, trans) {
		this.log('feature_get', res.attributes.feature_name + "=" + res.data);
		this.state.features[res.attributes.feature_name] = res.data;
	},

	/**
	 * Handle debugger run response packet
	 *
	 * @param   object   res     Response
	 * @param   object   trans   Transaction
	 */
	handleRun : function(res, trans) {
		this.handleStatus(res, trans);
	},

	/**
	 * Handle debugger step_over response packet
	 *
	 * @param   object   res     Response
	 * @param   object   trans   Transaction
	 */
	handleStepOver : function(res, trans) {
		this.handleStatus(res, trans);
	},

	/**
	 * Handle debugger step_into response packet
	 *
	 * @param   object   res     Response
	 * @param   object   trans   Transaction
	 */
	handleStepInto : function(res, trans) {
		this.handleStatus(res, trans);
	},
	
	/**
	 * Handle debugger step_out response packet
	 *
	 * @param   object   res     Response
	 * @param   object   trans   Transaction
	 */
	handleStepOut : function(res, trans) {
		this.handleStatus(res, trans);
	},

	/**
	 * Handle debugger status response packet
	 *
	 * @param   object   res     Response
	 * @param   object   trans   Transaction
	 */
	handleStatus : function(res, trans) {
		this.log('status', res);
		
		var status = res.attributes.status;

		this.updateStatus(status);

		switch (status) {

			case 'starting':

				if (!this.state.file) {
					this.updateSource('Cannot query source until resume executed');
				} else {
					this.command('source', {f : this.state.file}, null, Debugger.handleSource);					
				}

				if (!this.state.breakpoints) {
					this.state.breakpoints = [];
					this.command('breakpoint_list', null, null, Debugger.handleBreakpointList);
				} else {
					this.updateBreakpoints();
				}

				break;

			case 'break':

				if (!this.state.breakpoints) {
					this.command('breakpoint_list', null, null, Debugger.handleBreakpointList);
				}

				if (res.children.length) {

					var breakinfo = res.children[0].attributes;

					//update line number
					if (this.state.line != breakinfo.lineno) {
						this.state.line = breakinfo.lineno;
					}

					//update file
					if (this.state.file != breakinfo.filename) {				
						this.state.file = breakinfo.filename;
						this.updateFile(this.state.file);
						this.command('source', {f : this.state.file}, null, Debugger.handleSource);					
					} else {
						this.updateLine(this.state.line);						
					}

				} else {
					this.state.file = '';
					this.updateSource('', 0);
					this.updateFile(this.state.file);
				}

				this.command('stack_get', null, null, Debugger.handleStackGet);
				this.command('context_get', null, null, Debugger.handleContextGet);
				break;

			case 'stopping':
				this.updateSource('');
				break;
		}
	},

	/**
	 * Handle debugger source response packet
	 *
	 * @param   object   res     Response
	 * @param   object   trans   Transaction
	 */
	handleSource : function(res, trans) {

		this.log('source', res.data);

		this.state.source = res.data;

		this.updateSource(this.state.source);
		this.updateLine(this.state.line);

		var breakpoints = this.state.breakpoints[this.state.file];
		if (breakpoints) {
			for (var i in breakpoints) {
				editor.session.setBreakpoint(breakpoints[i].line - 1);
			}
		}
	},

	/**
	 * Handle debugger breakpoint_set response packet
	 *
	 * @param   object   res     Response
	 * @param   object   trans   Transaction
	 */
	handleBreakpointSet : function(res, trans) {

		var breakpoint = this.state.breakpoints[trans.args.args.f][trans.args.args.n];

		breakpoint.id = res.attributes.id;

		this.log('breakpoint_set', breakpoint);
	},

	/**
	 * Handle debugger breakpoint_remove response packet
	 *
	 * @param   object   res     Response
	 * @param   object   trans   Transaction
	 */
	handleBreakpointRemove : function(res, trans) {
		this.log('breakpoint_remove', res);
	},

	/**
	 * Handle debugger breakpoint_list response packet
	 *
	 * @param   object   res     Response
	 * @param   object   trans   Transaction
	 */
	handleBreakpointList : function(res, trans) {
		this.log('breakpoint_list', res);
		
		this.state.breakpoints = [];

		for (var i = 0; i < res.children.length; i++) {
			var node = res.children[i];

			var breakpoint = {
				type : node.attributes.type,
				file : node.attributes.filename,
				line : node.attributes.lineno
			};

			if (!this.state.breakpoints[breakpoint.file]) {
				this.state.breakpoints[breakpoint.file] = {};	
			}

			this.state.breakpoints[breakpoint.file][breakpoint.line] = breakpoint;
		}

		this.updateBreakpoints();
	},

	/**
	 * Handle debugger context_get response packet
	 *
	 * @param   object   res     Response
	 * @param   object   trans   Transaction
	 */	
	handleContextGet : function(res, trans) {
		this.log('context_get', res);

		//populate context pane
		this.updateContext(null);
		for (var i = 0; i < res.children.length; i++) {
			this.updateContext(res.children[i]);			
		}
	},

	/**
	 * Handle debugger stack_get response packet
	 *
	 * @param   object   res     Response
	 * @param   object   trans   Transaction
	 */	
	handleStackGet : function(res, trans) {
		this.log('stack_get', res);

		var stack = res.children;

		//need to get current file info
		if (this.state.file == '') {
			var current = stack[0].attributes;
			this.state.file = current.filename;
			this.state.line = current.lineno;
			this.updateFile(this.state.file);
			this.command('source', {f : this.state.file}, null, Debugger.handleSource);
		}

		//populate stack pane
		this.updateStack(null);
		for (var i = 0; i < stack.length; i++) {
			this.updateStack(stack[i]);			
		}
	},









	/**
	 * Update status display
	 *
	 * @param   string   status   Status text
	 */
	updateStatus : function(status) {
		$('#' + this.options.elements.state).text(status);		
	},

	updateFile : function(file) {
		filepath = file.replace('file://', '');
		if (filepath.indexOf(this.globalState.filePrefix) == 0) {
			filepath = filepath.substr(this.globalState.filePrefix.length);
		}
	
		$('#' + this.options.elements.file).val(filepath);
	},

	/**
	 * Update source code in editor
	 *
	 * @param   string   source   Source code
	 */
	updateSource : function(source) {
		editor.setValue(source);
		editor.setReadOnly(true);
		editor.clearSelection();
	},

	/**
	 * Update line number in editor
	 *
	 * @param   int   line   Line number	 
	 */
	updateLine : function(line) {
		editor.gotoLine(line, 0);
		editor.scrollToLine(line, true);		
	},

	/**
	 * Update breakpoints displayed
	 */
	updateBreakpoints : function() {

		editor.session.clearBreakpoints();

		var file = this.state.file;
		if (this.state.breakpoints[file]) {

			var breakpoints = this.state.breakpoints[file];
			for (var i in breakpoints) {
				editor.session.setBreakpoint(breakpoints[i].line - 1);	
			}
		}

	},

	/**
	 * Update context pane
	 *
	 * @param   object   property   Property object
	 */
	updateContext : function(property) {
		
		if (!property) {
			var table = $('<table width="100%" />');
			$('#' + this.options.elements.context).html(table);
			return;
		}

		//get html as tr for property

		var tree = this.inspector.makeTree(property);
		var html = this.inspector.treeHtml(tree, 0);
		
		$('#' + this.options.elements.context + ' table').append(html);
	
		console.log(property);
	},

	/**
	 * Update stack pane
	 *
	 * @param   object   frame   Stack frame object
	 */
	updateContext : function(frame) {

		if (!frame) {
			var table = $('<table width="100%" />');
			$('#' + this.options.elements.stack).html(table);
			return;
		}

		//get html as tr for property

		var tree = this.inspector.makeTree(frame);
		var html = this.inspector.treeHtml(tree, 0);
		
		$('#' + this.options.elements.context + ' table').append(html);
	
		console.log(property);
	},



	getSource : function() {

		/*
		editor.setValue(res.data);
		editor.setReadOnly(true);
		editor.clearSelection();
		*/

		var file = $('#' + this.options.elements.file).val();

		if (file.indexOf('/') == 0) {
			file = 'file://' + file;	
		} else {
			file = 'file://' + this.globalState.filePrefix + file;	
		}

		this.state.file = file;
		this.updateFile(this.state.file);

		this.command('source', {f : file}, null, Debugger.handleSource);
	},

	setBreakpoint : function(line) {

		if (!this.state.breakpoints[this.state.file]) {
			this.state.breakpoints[this.state.file] = {};	
		}

		var breakpoint = {
			type : 'line',
			file : this.state.file,
			line : line
		};

		this.state.breakpoints[breakpoint.file][breakpoint.line] = breakpoint;

		this.command('breakpoint_set', {t : breakpoint.type, f : breakpoint.file, n : breakpoint.line}, null, Debugger.handleBreakpointSet);
	},

	clearBreakpoint : function(line) {

		if (!this.state.breakpoints[this.state.file]) {
			return;
		}

		var breakpoint = this.state.breakpoints[this.state.file][line];
		
		delete this.state.breakpoints[this.state.file][line];

		if (breakpoint) {
			this.command('breakpoint_remove', {d : breakpoint.id}, null, Debugger.handleBreakpointRemove);
		}
		
	},






	/**
	 * Update watch variables action
	 */
	action_updateWatch : function(data) {
		this.updateWatch('inspect-watch', data);
	},

	/**
	 * Update global variables action
	 */
	action_updateGlobal : function(data) {
		this.updateWatch('inspect-global', data);
	},

	/**
	 * Update defined variables action
	 */
	action_updateDefined : function(data) {
		this.updateWatch('inspect-defines', data);
	},


};


}(jQuery));

jQuery().ready(function() {
	Debugger.reset();
	Debugger.checker();
});

