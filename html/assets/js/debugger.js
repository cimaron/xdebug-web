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

Debugger = (function($) {

	function DebuggerInstance() {

		this.connected = false;

		this.fileuri = '';
		this.language = '';
		this.protocol_version = '';
		this.appid = '';
		this.idekey = '';

		this.status = null;
		this.stack_depth = 0;

		this.features = {
			max_depth : 2,
			max_children : 32
		};


		this.source = '';
		this.line = 0;

		this.breakpoints = ($.cookie('debugger.breakpoints') ? JSON.parse($.cookie('debugger.breakpoints')) : []);
		
	};


	$.entityEncode = function(str) {
		return $('<div />').text(str || "").html();
	};

	var tid = 1;
	var Command = function(args, options) {
		this.id = tid++;
		this.args = args;
		this.args.tid = this.id;
		this.options = options;
	};

	function Debugger() {
		
		Events.initialize.apply(this);
		
		this.instances = [];
		this.requests = {};

		var This = this;
		setTimeout(function() {
			This.connect();
		}, 0);

		this.inspector = new Inspector();
		//$.cookie('debugger.breakpoints', []);
	}

Debugger.version = '0.2';

/**
 * Debugger Object
 */
Debugger.prototype = {

	/**
	 * Write log info
	 *
	 * @param   string   action   Action or command
	 * @param   string   data     Extra data
	 */
	log : function(action, data) {
		
		if (data instanceof Element) {
			var string = "<" + data.tagName;
			for (var i = 0; i < data.attributes.length; i++) {
				var attrib = data.attributes[i];
				string += " " + attrib.name + "=" + attrib.value;
			}
			string += ">";
			string += data.innerHTML;
			string += "</" + data.tagName + ">";
			data = string;
		} else {
			data = JSON.stringify(data);
		}
		
		try {
			this.trigger('onLog', [action, $.entityEncode(data)]);		
		} catch (e) {
			this.trigger('onLog', [action, 'Unable to convert data to json: ']);
		}
	},

	connect : function() {

		var host = '192.168.1.101';

		this.socket = new Connection({
			host : host,
		});

		this.socket.bind('onConnect', this.onConnect.bind(this));
		this.socket.bind('onDisconnect', this.onDisconnect.bind(this));
		this.socket.bind('onData', this.onData.bind(this));
		this.socket.bind('onError', this.onError.bind(this));

		this.log('proxy:connecting to ', host);

		this.trigger('onProxyStatus', [{status : 'connecting'}]);
	},

	/**
	 * Handle Proxy Connect Event
	 */
	onConnect : function() {

		this.log('proxy:connected');

		this.connected = true;

		this.trigger('onProxyStatus', [{status : 'waiting'}]);

		this.proxySetName('cimaron').then(function(response) {
			this.log('proxy:setName', response.data);
			this.trigger('onProxyStart');
		}.bind(this));
	},

	/**
	 * Handle Proxy Disconnect Event
	 */
	onDisconnect : function(data) {

		if (this.connected) {

			this.log('proxy:disconnected');

			this.connected = false;

			this.trigger('onProxyDisconnect');
		}

		//Reject outstanding requests
		for (var i in this.requests) {
			if (this.requests[i].reject) {
				this.requests[i].reject(new Error('Connection lost'));	
			}
		}

		for (var i in this.instances) {
			this.handleDebuggerDisconnected(i);
		}

		setTimeout(this.connect.bind(this), 3000);
	},

	/**
	 * Handle Proxy Data Event
	 */
	onData : function(response) {

		var data = response.data;
		this.log('proxy:rawdata', data);

		switch (response.type) {
			
			case 'connect':
				this.handleDebuggerConnected(response.appid, data);
				break;

			case 'disconnect':
				this.handleDebuggerDisconnected(response.data);
				break;

			case 'debugger':

				data = this.parseXml(data);
				this.log('debugger:data', data);

				//pass-thru

			default:

				var trans = response.request.trans;
				if (this.requests[trans]) {
	
					var packet = this.requests[trans];
					if (packet.resolve) {
						packet.resolve({
							instance : this.instances[response.appid],
							data : data,
							extra : packet.extra
						});
					}
					delete this.requests[trans];
				}
	
				break;
		}

		this.trigger('onRawData', [{response : response}]);
	},

	/**
	 * Handle Proxy Error Event
	 */
	onError : function(response) {
		this.trigger('onProxyError');
	},

	/**
	 * Send command to proxy
	 *
	 * @param   string   command   Command
	 * @param   hash     args      Command arguments (optional)
	 * @param   mixed    success   Success callback (optional)
	 * @param   mixed    failure   Failure callback (optional)
	 * @param   mixed    trans     Transaction (optional)
	 *
	 * @return  promise
	 */
	sendProxyCommand : function(command, args, success, failure, trans, extra) {

		if (!this.connected) {
			return false;	
		}

		if (!trans) {
			trans = tid++;
		}

		var packet = {
			method : command,
			data : args,
			trans : trans,
			extra : extra
		};

		this.socket.send(packet);

		this.log('proxy:send', packet);

		var promise = new Promise(function(resolve, reject) {
			packet.resolve = resolve;
			packet.reject = reject;
		});

		this.requests[trans] = packet;

		if (success || failure) {
			promise.then(success, failure);
		}

		this.trigger('onSendProxyCommand', [{packet : packet}]);

		return promise;
	},

	/**
	 * Send command to debugger
	 *
	 * @param   string   command   Command
	 * @param   hash     args      Command arguments (optional)
	 * @param   mixed    data      Command data (optional)
	 * @param   mixed    success   Success callback (optional)
	 * @param   mixed    failure   Failure callback (optional)
	 *
	 * @return  promise
	 */
	sendDebuggerCommand : function(command, args, data, success, failure) {

		var trans = tid++
		args = args || {};
		args.i = trans;
		
		command = this.makeDebugCommand(command, args, data)

		this.log('debugger:send', command);

		var promise = this.sendProxyCommand('debug', command, success, failure, trans, {args : args, data : data});

		this.trigger('onDebuggerStatus', [{instance : null, status : 'running'}]);

		return promise;
	},

	/**
	 * Encode a command to send to debugger
	 */
	makeDebugCommand : function(command, args, data) {
		
		var parts = [];
		parts.push(command);

		if (args) {
			for (var i in args) {
				parts.push('-' + i);
				
				var arg = args[i];

				if (typeof arg == 'string' && (arg.indexOf('"') != -1 || arg.indexOf(' ') != -1)) {
					if (arg.indexOf('"') != -1) {
						arg = arg.replace('"', '\"');
					}
					arg = '"' + arg + '"';
				}

				parts.push(arg);
			}
		}
		
		if (data) {
			parts.push('--' + btoa(data));
		}

		return parts.join(' ');
	},

	/**
	 * Parse XML string into tree
	 */
	parseXml : function(xml) {
		var parser = new DOMParser();
		node = parser.parseFromString(xml, "text/xml");
		return node.childNodes[0];
	},
	
	parseCData : function(node) {
		var text = node.textContent;
		text = text.trim();
		var cdata = atob(text);		
		return cdata;
	},

	/**
	 * Handle debugger connected packet
	 *
	 * @param   object   init   Init packet
	 */
	handleDebuggerConnected : function(appid, packet) {

		this.log('debugger:connected', packet);

		var init = this.parseXml(packet);

		var instance = new DebuggerInstance();

		instance.connected = true;
		instance.fileuri = init.getAttribute('fileuri');
		instance.language = init.getAttribute('language');
		instance.protocol_version = init.getAttribute('protocol_version');
		instance.appid = init.getAttribute('appid');
		instance.idekey = init.getAttribute('idekey');
		
		this.instances[instance.appid] = instance;

		/*
		if (!$('#' + this.options.elements.breaken)[0].checked) {
			this.command('run', null, null, Debugger.handleStatus);
			return;
		}
		*/

		//Get current state

		this.trigger('onDebuggerStatus', [{instance : instance, status : 'connected'}]);

		this.dbgFeatureSet('max_children', 50);

		this.dbgStatus();
		this.dbgSource(instance.fileuri);
		
		return;

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
	 * Handle debugger connected packet
	 *
	 * @param   object   init   Init packet
	 */
	handleDebuggerDisconnected : function(appid) {

		this.log('debugger:disconnected', appid);

		var instance = this.instances[appid];
		delete this.instances[appid];


		this.trigger('onDebuggerStatus', [{instance : instance, status : 'disconnected'}]);
		this.trigger('onProxyStatus', [{status : 'waiting'}]);
	},

	/**
	 * Send proxy setName command
	 *
	 * @param   string   name      Name to set
	 * @param   mixed    success   Success callback (optional)
	 * @param   mixed    failure   Failure callback (optional)
	 *
	 * @return  promise
	 */
	proxySetName : function(name, success, failure) {
		return this.sendProxyCommand('setName', name)
			.then(success, failure)
			;
	},

	/**
	 * Send debugger context_get command
	 *
	 * @param   int     stack_depth   stack depth (optional)
	 * @param   mixed   context_id    context ID (optional)
	 * @param   mixed   success       Success callback (optional)
	 * @param   mixed   failure       Failure callback (optional)
	 *
	 * @return  promise
	 */
	dbgContextGet : function(stack_depth, context_id, success, failure) {
		
		var args = {};
		
		if (stack_depth) {
			args.d = stack_depth;
		}
		
		if (context_id) {
			args.c = context_id;	
		}

		return this.sendDebuggerCommand('context_get', args)
			.then(success, failure)
			;
	},

	/**
	 * Send debugger run command
	 *
	 * @param   mixed    success   Success callback (optional)
	 * @param   mixed    failure   Failure callback (optional)
	 *
	 * @return  promise
	 */
	dbgEval : function(cmd, success, failure) {
		return this.sendDebuggerCommand('eval', null, cmd)
			.then(success, failure)
			;
	},

	/**
	 * Send debugger feature_set command
	 *
	 * @param   string   name      Name
	 * @param   string   value     Value
	 * @param   mixed    success   Success callback (optional)
	 * @param   mixed    failure   Failure callback (optional)
	 *
	 * @return  promise
	 */
	dbgFeatureSet : function(name, value, success, failure) {
		return this.sendDebuggerCommand('feature_set', {n : name, v : value})
			.then(this.handleFeatureSet.bind(this))
			.then(success, failure)
			;
	},

	/**
	 * Send debugger property_get command
	 *
	 * @param   mixed    success   Success callback (optional)
	 * @param   mixed    failure   Failure callback (optional)
	 *
	 * @return  promise
	 */
	dbgPropertyGet : function(fullname, stack_depth, success, failure) {
		return this.sendDebuggerCommand('property_get', {
				n : fullname,
				d : stack_depth
			})
			.then(success, failure)
			;
	},

	/**
	 * Send debugger run command
	 *
	 * @param   mixed    success   Success callback (optional)
	 * @param   mixed    failure   Failure callback (optional)
	 *
	 * @return  promise
	 */
	dbgRun : function(success, failure) {
		return this.sendDebuggerCommand('run')
			.then(this.handleStatus.bind(this))
			.then(success, failure)
			;
	},

	/**
	 * Send debugger status command
	 *
	 * @param   string   file      File to query
	 * @param   mixed    success   Success callback (optional)
	 * @param   mixed    failure   Failure callback (optional)
	 *
	 * @return  promise
	 */
	dbgSource : function(file, success, failure) {
		return this.sendDebuggerCommand('source', {f : file})
			.then(this.handleSource.bind(this))
			.then(success, failure)
			;
	},

	/**
	 * Send debugger status command
	 *
	 * @param   mixed    success   Success callback (optional)
	 * @param   mixed    failure   Failure callback (optional)
	 *
	 * @return  promise
	 */
	dbgStatus : function(success, failure) {
		return this.sendDebuggerCommand('status', null)
			.then(this.handleStatus.bind(this))
			.then(success, failure)
			;
	},

	/**
	 * Send debugger step_into command
	 *
	 * @param   mixed    success   Success callback (optional)
	 * @param   mixed    failure   Failure callback (optional)
	 *
	 * @return  promise
	 */
	dbgStepInto : function(success, failure) {
		return this.sendDebuggerCommand('step_into')
			.then(this.handleStatus.bind(this))
			.then(success, failure)
			;
	},

	/**
	 * Send debugger step_out command
	 *
	 * @param   mixed    success   Success callback (optional)
	 * @param   mixed    failure   Failure callback (optional)
	 *
	 * @return  promise
	 */
	dbgStepOut : function(success, failure) {
		return this.sendDebuggerCommand('step_out')
			.then(this.handleStatus.bind(this))
			.then(success, failure)
			;
	},

	/**
	 * Send debugger step_over command
	 *
	 * @param   mixed    success   Success callback (optional)
	 * @param   mixed    failure   Failure callback (optional)
	 *
	 * @return  promise
	 */
	dbgStepOver : function(success, failure) {
		return this.sendDebuggerCommand('step_over')
			.then(this.handleStatus.bind(this))
			.then(success, failure)
			;
	},

	/**
	 * Handle debugger context_get response packet
	 *
	 * @param   object   response   Object with debugger instance and response data
	 */	
	handleContextGet : function(response) {

		var instance = response.instance;
		var data = response.data;

		this.log('debugger:context_get', data);

		//populate context pane
		var pane = $('#inspect-local')
		this.updateInspectPane(null, pane);

		//collect nodes
		var nodes = [];
		for (var i = 0; i < data.children.length; i++) {
			nodes.push(data.children[i]);	
		}

		nodes.sort(function(a, b) {
			a = a.getAttribute('name');
			b = b.getAttribute('name');
			return (a > b ? 1 : -1);
		});

		for (var i = 0; i < nodes.length; i++) {
			this.updateInspectPane(nodes[i], pane);
		}

		/*
		this.updateInspectPane(null, this.options.elements.watch);
		*/
		
		return response;
	},

	/**
	 * Handle debugger feature_set response packet
	 *
	 * @param   object   response   Object with debugger instance and response data
	 */	
	handleFeatureSet : function(response) {

		var instance = response.instance;
		var data = response.data;

		var name = response.extra.args.n;
		var value = response.extra.args.v;

		instance.features[name] = value;

		this.log('debugger:feature_set', name + ':' + value);

		this.trigger('onFeatureSet', [{
			instance : instance,
			name : name,
			value : value
		}]);

		return response;
	},

	/**
	 * Handle the source response
	 *
	 * @param   object   response   Object with debugger instance and response data
	 *
	 * @return  hash
	 */
	handleSource : function(response) {

		var instance = response.instance;
		var data = response.data;

		var source = this.parseCData(data);

		this.log('debugger:source', source);

		/*
		var breakpoints = this.state.breakpoints;
		for (var i in breakpoints) {
			if (breakpoints[i].file == this.state.file) {
				editor.session.setBreakpoint(breakpoints[i].line - 1);
			}
		}
		*/
		
		this.trigger('onDebuggerSource', [{
			instance : instance,
			source : source
		}]);

		return response;
	},

	/**
	 * Handle the status response
	 *
	 * @param   object   response   Object with debugger instance and response data
	 *
	 * @return  hash
	 */
	handleStatus : function(response) {

		var instance = response.instance;
		var data = response.data;

		var status = data.getAttribute('status');

		this.log('debugger:status', status);

		/*
		if (instance.breakpoints === null) {
			instance.breakpoints = {};
			this.dbgBreakpointList();
		}
		*/
		
		switch (status) {

			case 'starting':

				this.trigger('onDebuggerFile', [{
					instance : instance,
					file : instance.fileuri
				}]);

				this.trigger('onDebuggerLineno', [{
					instance : instance,
					line : instance.line
				}]);

				break;

			case 'break':
			
				var message = data.children[0];
				var filename = message.getAttribute('filename');
				var line = message.getAttribute('lineno');
				
				if (instance.fileuri != filename) {
					
					instance.fileuri = filename;
					instance.line = line;

					this.trigger('onDebuggerFile', [{
						instance : instance,
						file : instance.fileuri
					}]);

					this.trigger('onDebuggerLineno', [{
						instance : instance,
						line : instance.line
					}]);
					
					this.dbgSource(instance.fileuri);

				} else  if (line != instance.line) {
					
					instance.line = line;
					
					this.trigger('onDebuggerLineno', [{
						instance : instance,
						line : instance.line
					}]);
				}

				this.dbgContextGet().then(this.handleContextGet.bind(this));

				/*
				this.state.contextDirty = true;
				this.state.watchDirty = true;
				this.state.globalDirty = true;
				this.state.definesDirty = true;
				this.state.stackDirty = true;
				this.state.breakpointsDirty = true;
				if (!this.state.docroot) {
					* /
					this.dbgEval('realpath($_SERVER["DOCUMENT_ROOT"])').then(function(data) {
						debugger;
						this.log('eval(document_root)', data);
						//this.state.docroot = res.children[0].data + '/';
						//this.updateFilepane(this.state.docroot);
					}.bind(this));
					/ *
				}

				if (!this.state.file) {
					this.command('eval', null, 'realpath($_SERVER["SCRIPT_FILENAME"])', Debugger.handleScriptFilename);				
				}

				this.command('stack_depth', null, null, Debugger.handleStackDepth);
				this.activeTab(this.activeTab());
				*/
				break;

			case 'stopping':
				
				this.trigger('onDebuggerSource', [{
					instance : instance,
					source : ''
				}]);

				this.trigger('onDebuggerLineno', [{
					instance : instance,
					line : 0
				}]);

				this.dbgRun();
				break;
		}

		this.trigger('onDebuggerStatus', [{
			instance : instance,
			status : status
		}]);

		return response;		
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


	displayFile : function(file) {
		file = this.parseFile(file);		
		$('#' + this.options.elements.file).val(file.rel);
		//$('#files-pane').jstree("search", fil.rel);
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
			element.html(table);
			return;			
		}

		var tree = this.inspector.makeTree(data);
		var html = this.inspector.treeHtml(tree, 0, 0);

		element.children('table').append(html);
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

};

	$.extend(Debugger.prototype, Events.prototype);

	return Debugger;

}(jQuery));

