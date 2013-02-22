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
			watch : 'inspect-watch',
			global : 'inspect-global',
			defines : 'inspect-defines',
			stack : 'inspect-stack',
			breakpoints : 'inspect-breakpoints',
			breaken : 'break-enabled',
			loading : 'loading-indicator'
		},
		max_depth : 2,
		sortDefines : 'type'
	},

	state : null,

	/**
	 * Write log info
	 *
	 * @param   string   action   Action or command
	 * @param   string   data     Extra data
	 */
	log : function(action, data) {
		console.log(action + ": ", data);
		while ($('#debug table tr').length > 20) {
			$('#debug table tr:first-child').remove();
		}
		$('#debug table').append('<tr><td class="key">' + action + '</td><td class="value">' + $.entityEncode(data) + '</td></tr>');
		$('#debug').scrollTop(1000);
	},

	/**
	 * Reset all state
	 */
	reset : function() {		

		this.state = {
			
			connected : null,
			status : null,

			stack_depth : 0,

			features : {
				max_depth : 2,
				max_children : 32
			},

			transactions : {},
			currentCommand : false,
			commandQueue : [],
			
			file : '',
			docroot : '',
			
			source : '',
			line : 0,

			breakpoints : ($.cookie('debugger.breakpoints') ? JSON.parse($.cookie('debugger.breakpoints')) : null),

			contextDirty : true,
			watchDirty : true,
			definesDirty : true,
			stackDirty : true,
			breakpointsDirty : true,
		};

		this.displayStatus('disconnected');
		this.displaySource('', 0);
	},

	/**
	 * Handle Debugger Proxy Start Event
	 */
	onDebuggerProxyStart : function(data) {
		this.displayStatus('disconnected');
	},

	/**
	 * Handle Debugger Proxy Error Event
	 */
	onDebuggerProxyError : function(data) {
		this.displayStatus('restart proxy');
	},

	/**
	 * Handle Debugger Connect Event
	 */
	onDebuggerConnect : function(data) {
		this.reset();
		this.state.connected = true;
		this.log('connected', data.connected);
		this.displayStatus('connected');
		this.command('status', null, null, Debugger.handleStatus);
		$('#' + this.options.elements.loading).addClass('loading');
	},

	/**
	 * Handle Debugger Connect Event
	 */
	onDebuggerDisconnect : function(data) {
		if (this.state.connected) {
			this.reset();
		}
		this.log('disconnected', data.connected);
		this.displayStatus('disconnected');
		$('#' + this.options.elements.loading).removeClass('loading');
	},

	/**
	 * Handle Debugger Async Packet Event
	 */
	onGetDebuggerPacket : function(data) {
		//this.log('packet', JSON.stringify(data));
		if (data.name == 'init') {
			this.handleInit(data);
		}
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
	command : function(command, args, data, callback, force, options) {

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
		trans.options = options;

		this.state.transactions[trans.id] = trans;

		this.state.commandQueue.push(trans);

		if (!this.state.currentCommand) {
			this.nextCommand();
		} else {
			console.log('Queued', trans);	
		}
	},

	/**
	 *
	 */
	nextCommand : function() {

		if (this.state.commandQueue.length == 0) {
			$('#' + this.options.elements.loading).removeClass('loading');
			return;	
		}

		var trans = this.state.commandQueue.shift();
		this.state.currentCommand = trans;

		this.log('send', JSON.stringify(trans.args));

		Request.send(trans.args);

		$('#' + this.options.elements.loading).addClass('loading');
	},

	/**
	 * Handle Debugger Response Packet Event
	 */
	onGetDebuggerResponse : function(data) {
		this.log('response', JSON.stringify(data));

		var transactions = this.state.transactions;
		var queue = this.state.commandQueue;
		var current = this.state.currentCommand;
		var res = data.data;

		/*
		if (res.children.length && res.children[0].name == 'error') {
			this.log('error', res.children[0].children[0].data);	
			
			//we can only assume it was the last transaction
			var tid = current.id;	
		} else {
			var tid = res.attributes.transaction_id;
		}
		*/
		var tid = res.attributes.transaction_id;

		if (tid != current.id || !transactions[tid]) {
			alert('Invalid transaction');
			this.reset();
			return;
		}

		transactions[tid].received = true;
		transactions[tid].exec(this, res);
		delete transactions[tid];

		//console.log('currentCommand fulfilled');
		this.state.currentCommand = null;
		//console.log(queue.length + ' items in queue');
		this.nextCommand();
	},


	/**
	 * Handle debugger init packet
	 *
	 * @param   object   init   Init packet
	 */
	handleInit : function(res) {

		this.state.info = res.attributes;

		if (!$('#' + this.options.elements.breaken)[0].checked) {
			this.command('run', null, null, Debugger.handleStatus);
			return;
		}

		this.state.file = this.state.info.fileuri;
		this.command('source', {f : this.state.file}, null, Debugger.handleSource);					
		this.displayFile(this.state.file);

		this.log('init', this.state.info);

		var features = ['language_supports_threads', 'language_name', 'language_version', 'encoding',
		                'protocol_version', 'supports_async', 'data_encoding', 'breakpoint_languages',
		                'breakpoint_types', 'multiple_sessions', 'max_children', 'max_data', 'max_depth'];

		for (var i = 0; i < features.length; i++) {
			this.command('feature_get', {n : features[i]}, null, Debugger.handleFeatureGet);
		}

		this.command('feature_set', {n : 'max_depth', v : this.options.max_depth});
		//this.command('feature_set', {n : 'max_children', v : 512});

		for (var i in this.state.breakpoints) {
			var breakpoint = this.state.breakpoints[i];
			this.command('breakpoint_set', {t : breakpoint.type, f : breakpoint.file, n : breakpoint.line}, null, Debugger.handleBreakpointSet);			
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

		this.displayStatus(status);

		if (this.state.breakpoints === null) {
			this.state.breakpoints = {};
			this.command('breakpoint_list', null, null, Debugger.handleBreakpointList);
		}

		switch (status) {

			case 'starting':

				break;

			case 'break':

				this.state.contextDirty = true;
				this.state.watchDirty = true;
				this.state.globalDirty = true;
				this.state.definesDirty = true;
				this.state.stackDirty = true;
				this.state.breakpointsDirty = true;

				if (!this.state.docroot) {
					this.command('eval', null, 'realpath($_SERVER["DOCUMENT_ROOT"])', Debugger.handleDocRoot);
				}

				if (!this.state.file) {
					this.command('eval', null, 'realpath($_SERVER["SCRIPT_FILENAME"])', Debugger.handleScriptFilename);				
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
						this.displayFile(this.state.file);
						this.command('source', {f : this.state.file}, null, Debugger.handleSource);					
					} else {
						this.updateLine(this.state.line);						
					}

				} else {
					this.state.file = '';
					this.displaySource('', 0);
					this.displayFile(this.state.file);
				}

				this.command('stack_depth', null, null, Debugger.handleStackDepth);
				this.activeTab(this.activeTab());

			break;

			case 'stopping':
				this.displaySource('', 0);
				this.command('run');
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

		this.displaySource(this.state.source);
		this.updateLine(this.state.line);

		var breakpoints = this.state.breakpoints;
		for (var i in breakpoints) {
			if (breakpoints[i].file == this.state.file) {
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
		this.log('breakpoint_set', res);
		this.command('breakpoint_list', null, null, Debugger.handleBreakpointList);		
	},

	/**
	 * Handle debugger breakpoint_remove response packet
	 *
	 * @param   object   res     Response
	 * @param   object   trans   Transaction
	 */
	handleBreakpointRemove : function(res, trans) {
		this.log('breakpoint_remove', res);
		this.command('breakpoint_list', null, null, Debugger.handleBreakpointList);
	},

	/**
	 * Handle debugger breakpoint_list response packet
	 *
	 * @param   object   res     Response
	 * @param   object   trans   Transaction
	 */
	handleBreakpointList : function(res, trans) {
		this.log('breakpoint_list', res);
		
		for (var i = 0; i < res.children.length; i++) {
			var node = res.children[i];
			var uid = node.attributes.filename + '|' + node.attributes.lineno;

			var breakpoint = {
				uid : uid,
				id : node.attributes.id,
				type : node.attributes.type,
				file : node.attributes.filename,
				line : node.attributes.lineno
			};

			this.state.breakpoints[uid] = breakpoint;
		}
		$.cookie('debugger.breakpoints', JSON.stringify(this.state.breakpoints));

		this.updateInspectPane(null, this.options.elements.breakpoints);
		for (var i = 0; i < res.children.length; i++) {
			res.children[i].attributes.breaktype = res.children[i].attributes.type;
			res.children[i].attributes.type = 'breakpoint';
			this.updateInspectPane(res.children[i], this.options.elements.breakpoints);
		}

		this.displayBreakpoints();
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
		this.updateInspectPane(null, this.options.elements.context);

		res.children.sort(function(a, b) { return ((a.attributes.name == b.attributes.name) ? 0 : ((a.attributes.name > b.attributes.name) ? 1 : -1)); });

		for (var i = 0; i < res.children.length; i++) {
			this.updateInspectPane(res.children[i], this.options.elements.context);
		}

		this.updateInspectPane(null, this.options.elements.watch);
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
			this.displayFile(this.state.file);
			this.command('source', {f : this.state.file}, null, Debugger.handleSource);
		}

		//populate stack pane
		this.updateInspectPane(null, this.options.elements.stack);
		for (var i = 0; i < stack.length; i++) {
			this.updateInspectPane(stack[i], this.options.elements.stack);			
		}
	},

	/**
	 * Handle debugger stack_depth response packet
	 *
	 * @param   object   res     Response
	 * @param   object   trans   Transaction
	 */
	handleStackDepth : function(res, trans) {
		this.log('stack_depth', "depth =" + res.attributes.depth);
		this.state.stack_depth = parseInt(res.attributes.depth);
	},

	/**
	 * Handle debugger eval response packet
	 *
	 * @param   object   res     Response
	 * @param   object   trans   Transaction
	 */	
	handleEval : function(res, trans) {
		this.log('eval', res);

		for (var i = 0; i < res.children.length; i++) {
			if (trans.options.el == this.options.elements.watch) {
				var name = trans.options.name
				res.children[i].attributes.fullname = name;
				res.children[i].attributes.name = name;
				this.updateInspectPane(res.children[i], this.options.elements.watch);
			}
		}
	},

	/**
	 * Handle defines eval response packet
	 *
	 * @param   object   res     Response
	 * @param   object   trans   Transaction
	 */
	handleDefines : function(res, trans) {	
		this.log('eval(defines)', res);

		//populate context pane
		this.updateInspectPane(null, this.options.elements.defines);

		var list = res.children[0];
		for (var i = 0; i < list.children.length; i++) {
			this.updateInspectPane(list.children[i], this.options.elements.defines);
		}
	},

	/**
	 * Handle global eval response packet
	 *
	 * @param   object   res     Response
	 * @param   object   trans   Transaction
	 */
	handleGlobal : function(res, trans) {	
		this.log('eval(global)', res);

		//populate context pane
		this.updateInspectPane(null, this.options.elements.global);

		var list = res.children[0];
		for (var i = 0; i < list.children.length; i++) {
			list.children[i].attributes.fullname = "$" + list.children[i].attributes.name;
			list.children[i].attributes.stack_depth = trans.options.stack_depth;
			this.updateInspectPane(list.children[i], this.options.elements.global);
		}
	},

	/**
	 * Handle eval to get document root path
	 * 
	 * @param   object   res     Response
	 * @param   object   trans   Transaction	 
	 */
	handleDocRoot : function(res, transaction) {
		this.log('eval(document_root)', res);
		this.state.docroot = res.children[0].data + '/';

		this.updateFilepane(this.state.docroot);
	},

	/**
	 * Handle eval to get script filename
	 * 
	 * @param   object   res     Response
	 * @param   object   trans   Transaction	 
	 */
	handleScriptFilename : function(res, transaction) {
		this.log('eval(script_filename)', res);
		this.state.file = 'file://' + res.children[0].data;
		this.command('source', {f : this.state.file}, null, Debugger.handleSource);
		this.displayFile(this.state.file);
	},

	/**
	 * Handle property_get for next page
	 * 
	 * @param   object   res     Response
	 * @param   object   trans   Transaction	 
	 */
	handleMore : function(res, transaction) {

		var options = transaction.options;
		var data = res.children[0];
		var numchildren = parseInt(data.attributes.numchildren);
		var pagesize = parseInt(data.attributes.pagesize);
		var page = parseInt(data.attributes.page);

		var row = $(options.element).closest('tr');
		for (var i = 0; i < data.children.length; i++) {
			this.injectInspectPane(data.children[i], row, 'before');
		}

		var parent = $('#watch_row_' + row.data('parentid'));
		//this.inspector.toggleChildren(parent[0], true);

		if (pagesize * (page + 1) >= numchildren) {
			row.remove();
		} else {
			$(options.element).attr('rel', page + 1);
		}
	},

	/**
	 * Handle property_get for reload
	 * 
	 * @param   object   res     Response
	 * @param   object   trans   Transaction	 
	 */
	handleReload : function(res, trans) {

		var options = trans.options;
		var data = res.children[0];

		data.attributes.fullname = options.name;
		data.attributes.name = options.name;
		data.attributes.stack_depth = options.stack_depth;

		var row = $(options.element).closest('tr');
		this.injectInspectPane(data, row, 'after');
		row.remove();
	},

	/**
	 * Parse file string
	 */
	parseFile : function(file) {
		
		var parsed = {};

		if (file.indexOf('file://') == 0) {
			file = file.substr(7);
		}
		
		if (file.indexOf(this.state.docroot) == 0) {
			file = file.substr(this.state.docroot.length);
		}
		
		parsed.rel = file;
		parsed.abs = this.state.docroot + file;
		parsed.uri = 'file://' + parsed.abs;

		return parsed;
	},

	/**
	 * Update status display
	 *
	 * @param   string   status   Status text
	 */
	displayStatus : function(status) {
		$('#' + this.options.elements.state).text(status).removeClass().addClass('status-' + status);
	},

	displayFile : function(file) {
		file = this.parseFile(file);		
		$('#' + this.options.elements.file).val(file.rel);
		//$('#files-pane').jstree("search", fil.rel);
	},

	/**
	 * Update source code in editor
	 *
	 * @param   string   source   Source code
	 */
	displaySource : function(source) {
		if (window.editor) {
			editor.setValue(source);
			editor.setReadOnly(true);
			editor.clearSelection();
			this.displayBreakpoints();
		}
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
	displayBreakpoints : function() {

		editor.session.clearBreakpoints();

		var breakpoints = this.state.breakpoints;
		for (var i in breakpoints) {
			if (breakpoints[i].file == this.state.file) {
				editor.session.setBreakpoint(breakpoints[i].line - 1);	
			}
		}
	},

	/**
	 * Add data to inspect pane
	 *
	 * @param   object   data      Data object
	 * @param   string   element   Element id to insert into
	 */
	updateInspectPane : function(data, element) {
		
		if (!data) {
			var table = $('<table width="100%" />');
			$('#' + element).html(table);
			return;			
		}

		var tree = this.inspector.makeTree(data);
		var html = this.inspector.treeHtml(tree, 0, 0);

		$('#' + element + ' table').append(html);
	},

	/**
	 * Inject data into inspect pane
	 *
	 * @param   object    data       Data object
	 * @param   element   element    Element to insert before
	 * @param   string    position   'before' or 'after'
	 */
	injectInspectPane : function(data, element, position) {

		var tree = this.inspector.makeTree(data);
		var html = this.inspector.treeHtml(tree, element.data('depth'), element.data('parentid'));

		if (position == 'before') {
			$(element).before(html);
		} else {
			$(element).after(html);	
		}

		this.inspector.toggleChild(0, html[0], true);
	},

	/**
	 * Get source code
	 *
	 * @param   string   file   Filename
	 * @param   int      line   Line number
	 */
	getSource : function(file, line) {

		file = this.parseFile(file);

		this.state.file = file.uri;
		this.displayFile(this.state.file);

		if (line) {
			this.state.line = line;
		}

		this.command('source', {f : file.uri}, null, Debugger.handleSource);
	},

	/**
	 * Add a breakpoint
	 *
	 * @param   string   file   Filename
	 * @param   int      line   Line number
	 */
	setBreakpoint : function(file, line) {

		file = this.parseFile(file).uri;
		
		var uid = file + "|" + line;

		var breakpoint = {
			uid : uid,
			type : 'line',
			file : file,
			line : line
		};

		this.state.breakpoints[uid] = breakpoint;

		this.command('breakpoint_set', {t : breakpoint.type, f : breakpoint.file, n : breakpoint.line}, null, Debugger.handleBreakpointSet);
		
		this.displayBreakpoints();
	},

	/**
	 * Remove a breakpoint
	 *
	 * @param   string   file   Filename
	 * @param   int      line   Line number
	 * @param   id       id     Breakpoint ID (optional)
	 */
	clearBreakpoint : function(file, line, id) {

		if (!id) {

			file = this.parseFile(file).uri;

			var breakpoints = this.state.breakpoints;
			for (var i in breakpoints) {
				if (breakpoints[i].file == file && breakpoints[i].line == line) {
					id = breakpoints[i].id;
					delete breakpoints[i];
				}
			}
		}

		if (id) {
			this.command('breakpoint_remove', {d : id}, null, Debugger.handleBreakpointRemove);
		}

		this.displayBreakpoints();
	},

	/**
	 * Add watch expression
	 */
	addWatch : function(expr) {
		this.command('eval', null, expr, Debugger.handleEval, false, {el : this.options.elements.watch, expr : expr, name : expr});
	},

	/**
	 * Get or set active tab
	 */
	activeTab : function(tab) {

		if (tab) {

			if (tab == 'local' && this.state.contextDirty) {
				this.command('context_get', null, null, Debugger.handleContextGet);
				this.state.contextDirty = false;
			}

			if (tab == 'defines' && this.state.definesDirty) {
				this.command('eval', null, "array('constants' => get_defined_constants(true), 'classes' => get_declared_classes(), 'functions' => get_defined_functions());", Debugger.handleDefines);				
				this.state.definesDirty = false;
			}

			if (tab == 'global' && this.state.globalDirty) {
				this.command('feature_set', {n : 'max_children', v : 1000});
				this.command('feature_set', {n : 'max_depth', v : this.state.features.max_depth + 1});
				this.command('property_get', {n : '$GLOBALS', d : this.state.stack_depth - 1}, null, Debugger.handleGlobal, false, {stack_depth : this.state.stack_depth - 1});
				this.command('feature_set', {n : 'max_children', v : this.state.features.max_children});
				this.command('feature_set', {n : 'max_depth', v : this.state.features.max_depth});
				this.state.globalDirty = false;
			}

			if (tab == 'stack' && this.state.stackDirty) {
				this.command('stack_get', null, null, Debugger.handleStackGet);
				this.state.stackDirty = false;
			}

			if (tab == 'stack' && this.state.breakpointsDirty) {
				this.command('breakpoint_list', null, null, Debugger.handleBreakpointList);
				this.state.stackDirty = false;
			}

		} else {
			var href = $('#inspect-pane .ui-tabs-active a').attr('href');
			tab = href.match(/#inspect-([a-z]+)/)[1];
			return tab;
		}
	},

	getMore : function(name, element) {
		var page = parseInt($(element).attr('rel'));
		this.command('property_get', {n : name, p : page}, null, Debugger.handleMore, false, {element : element, page : page, fullname : name});	
	},

	reload : function(fullname, name, stack_depth, element) {
		this.command('property_get', {n : fullname, d : stack_depth}, null, Debugger.handleReload, false, {element : element, fullname : fullname, name : name, stack_depth : stack_depth});	
	}

};


}(jQuery));

jQuery().ready(function() {

	Debugger.reset();
	//$.cookie('debugger.breakpoints', []);
	Request.events.onDebuggerConnect = $.proxy(Debugger.onDebuggerConnect, Debugger);
	Request.events.onDebuggerDisconnect = $.proxy(Debugger.onDebuggerDisconnect, Debugger);
	Request.events.onGetDebuggerPacket = $.proxy(Debugger.onGetDebuggerPacket, Debugger);
	Request.events.onGetDebuggerResponse = $.proxy(Debugger.onGetDebuggerResponse, Debugger);
	Request.events.onDebuggerProxyStart = $.proxy(Debugger.onDebuggerProxyStart, Debugger);
	Request.events.onDebuggerProxyError = $.proxy(Debugger.onDebuggerProxyError, Debugger);

	Debugger.displayStatus('disconnected');
});

