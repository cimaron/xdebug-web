

(function($) {

Debugger = {
	
	version : '0.2',
	
	uid : 0,
	
	options : {
		srcId : 'src',
	},

	connected : {},

	/**
	 * Send action to client
	 */
	action : function(action, data) {
		console.log('sent', action, data);
		$.ajax({
			url : "action.php",
			type : 'post',
			data : {
				action : action,
				data : data
			}
		});
	},

	/**
	 * Send resume action to client
	 */
	resume : function() {
		this.action('resume');
	},

	/**
	 * Send halt action to client
	 */
	halt : function() {
		this.action('halt');
	},

	/**
	 * Polling function for status updates
	 *
	 * @static
	 */
	updater : function() {
		$.ajax({
			url : "update.php",
			dataType : 'json',
			complete : function(xhr, status) {
				setTimeout(Debugger.updater, 0);
			},
			success : function(data, xhr) {
				Debugger.update(data);
			}

		});
	},

	/**
	 * Process status updates
	 */
	update : function(data) {
		for (var i = 0; i < data.length; i++) {
			var msg = data[i];
			console.log('got', msg);
			if (msg.init) {
				this.handleInit(msg.init);	
			} else if (msg.close) {
				this.handleClose(msg);	
			}

			if (typeof this['action_' + msg.action] == 'function') {
				this['action_' + msg.action](msg.data);	
			}
		}
	},

	/**
	 * Handle debugger init packet
	 *
	 * @param   object   init   Init packet
	 */
	handleInit : function(init) {
		this.connected[init.thread] = init;
		init.features = [];
	},

	/**
	 * Handle debugger close packet
	 *
	 * @param   object   close   Close packet
	 */
	handleClose : function(close) {
		delete this.connected[close.thread];
	},

	/**
	 * Update local variables action
	 */
	action_updateLocal : function(data) {
		this.updateWatch('local', data);
	},

	/**
	 * Update watch variables action
	 */
	action_updateWatch : function(data) {
		this.updateWatch('watch-container', data);
	},

	/**
	 * Update global variables action
	 */
	action_updateGlobal : function(data) {
		this.updateWatch('global', data);
	},

	/**
	 * Update defined variables action
	 */
	action_updateDefined : function(data) {
		this.updateWatch('defines', data);
	},

	/**
	 * Update trace variables action
	 */
	action_updateTrace : function(data) {
		this.updateWatch('trace', data);
	},

	action_selectPane : function(pane) {
		$('#tab_' + pane).tab('show');
	},

	/**
	 * Update source pane
	 */
	action_updateSource : function(src) {
		editor.setValue(src.text);
		editor.setReadOnly(true);
		editor.clearSelection();
		editor.gotoLine(src.line, 0);
		editor.scrollToLine(src.line, true);
	},

	action_log : function(data) {

		var uid = this.uid++;
		$('#console-container').append('<div class="watch" id="console-' + uid + '" />');
		var watch = {};
		watch.name = '';
		watch.type = 'object';
		watch.className = 'object';
		watch.children = [data];

		this.updateWatch('console-' + uid, watch);

		if (!$($('#tab_console')[0].parentNode).hasClass('active')) {
			var count = 0;
			var text = $('#tab_console').text();
			var res;
			if (res = text.match(/Console \(([0-9]+)\)/)) {
				count = parseInt(res[1]);
			}
			count++;
			$('#tab_console').text('Console (' + count + ')');
		}

		
	},
	
	action_describe : function(data) {
		var row = this.replaceWatch(data[1], data[0]);
		var button = $(row).find('.watch-toggle');
		this.toggleWatch(button);
	},
	
	updateWatch : function(id, data, append) {

		var table = $('<table width="100%" />');
		if (append) {
			$('#' + id).append(table);
		} else {
			$('#' + id).html(table);
		}

		var tree = this.makeTree(data);
		for (var i = 0; i < tree.children.length; i++) {
			table.append(this.treeHtml(tree.children[i], 0));
		}
	},

	replaceWatch : function(id, data) {
		
		var table = $('<table width="100%" />');

		var tree = this.makeTree(data);
		table.append(this.treeHtml(tree, 0));
		
		var rows = $(table).find('tr.watch_row');

		$('.' + id + '_child').remove();
		var me = $('.' + id);
		me.after(rows);
		me.remove();

		return rows[0];
	},

	/**
	 *
	 */
	toggleWatch : function(button) {
		var state, el, reload;

		el = $(button).closest('tr.watch_row')[0];

		state = $(button).html() == '+';

		if (state) {
			$(button).html('-');
			$(el).removeClass('inactive').addClass('active');
		} else {
			$(button).html('+');
			$(el).removeClass('active').addClass('inactive');			
		}

		reload = $(el).find('.describe')[0];
		if (reload) {
			var ctx = $(reload).attr('rel');
			this.action('describe', ctx + ' ' + el.id);
		}

		this.toggleChildren(el, state);
	},

	/**
	 *
	 */
	toggleChildren : function(el, state) {

		var child_class = el.id + '_child';
		var children = $('.' + child_class);

		children.each(function(i, el) {
			
			if (state) {
				$(el).css('display', 'table-row');	
			} else {
				$(el).css('display', 'none');	
			}

			var active = $(el).hasClass('active');

			if (active) {
				Debugger.toggleChildren(el, state);
			}
		});
	},

	treeHtml : function(tree, depth, parentid) {
		var out = "";
		var uid = this.uid++;

		var indent = depth * 20  + 10;

		if (depth == 0) {
			out += '<tr class="watch_row watch_row_' + uid + ' watch-top inactive" id="watch_row_' + uid + '">';
		} else {
			out += '<tr class="watch_row watch_row_' + uid + ' watch_row_' + parentid + '_child inactive" id="watch_row_' + uid + '" style="display:none">';
		}
		
		out += '<td style="padding-left:' + indent + 'px;"><span>';
		if (tree.children.length) {
			out += '<span class="watch-toggle" onclick="Debugger.toggleWatch(this);">+</span>';
		}
		out += '<span><span class="watch-name">' + tree.name + '</span></span></td>';
		out += '<td>' + tree.display + '</td>';
		
		if (depth == 0) {
			out += '<td class="watch-close"><span>&times;</span></td>';	
		}
		
		out += '</tr>';

		for (var i = 0; i < tree.children.length; i++) {
			out += this.treeHtml(tree.children[i], depth + 1, uid);
		}

		return out;
	},

	makeString : function(str) {
		return jQuery('<div />').text(str).html();
	},
	
	makeTree : function(data) {

		var node = {};

		//value display
		node.display = data;
		node.short = data;
		node.name = data.name;

		//
		node.children = [];
		for (var i in data.children) {
			node.children[i] = this.makeTree(data.children[i]);
			if (data.type == 'array') {
				node.children[i].name = '<span class="watch-key-array">' + node.children[i].name + '</span>';
			}
		}

		if (data.access == 'protected') {
			node.name = '<span class="watch-protected">' + node.name + '</span>';			
		}

		if (data.access == 'private') {
			node.name = '<span class="watch-private">' + node.name + '</span>';			
		}
		
		if (data.extension) {
			node.name = '<span class="watch-extension">' + node.name + '</span>';	
		}

		//get html by type
		switch (data.type) {

			case 'NULL':
				node.display = '<span class="watch-null">null</span>';
				node.short = node.display;
				break;

			case 'boolean':
				node.display = '<span class="watch-boolean">' + data.value + '</span>';
				node.short = node.display;
				break;

			case 'integer':
			case 'double':
				var value = data.value;
				if (data.value == 'Infinity') {
					value = Infinity;
				}
				if (data.value == 'NaN') {
					value = NaN;	
				}
	
				node.display = '<span class="watch-number">' + value + '</span>';
				node.short = node.display;
				break;

			case 'string':
				var value = this.makeString(data.value);
				var full = '<span class="watch-string">' + value + '</span>';
				var short_str;

				if (value.length > 63) {
					node.children = [{name : "", display : full, children : []}];
					short_str = value.substr(0, 30) + ' &hellip; ' + value.substr(value.length - 30);
					short_str = short_str.replace(/\s/, '&nbsp;');
					node.display = '<span class="watch-string watch-string-short">' + short_str + '</span>';
				} else {
					node.display = full;
				}

				if (value.length > 12) {
					short_str = value.substr(0, 8) + ' &hellip; ';
					short_str = short_str.replace(/\s/, '&nbsp;');
					node.short = '<span class="watch-string watch-string-short">' + short_str + '</span>';
				} else {
					node.short = full;
				}

				break;

			case 'file':
				var full = '<span class="watch-file">' + data.value.file + ' (line ' + data.value.line + ')</span>';

				var onclick = "Debugger.action('source', '" + data.value.file.replace(/("')/, '\\$1') + " " + data.value.line + "')";
				node.display = '<span class="watch-file" onclick="' + onclick + '">' + data.value.name + ' (line ' + data.value.line + ')</span>';
				node.short = node.display;
				node.children.unshift({name : "", display : full, children : []});
				break;

			case 'comment':
				var value = this.makeString(data.value);
				node.name = '<span class="watch-comment-name">' + node.name + '</span>';	
				node.display = '<span class="watch-comment">' + value + '</span>';
				node.short = node.display;
				break;

			case 'resource':
				node.display = '<span class="watch-object">Resource ( ' + data.res_type + '</span>, <span class="watch-object">' + data.value + ' )</span>';
				node.short = node.display;
				break;

			case 'hash':

				var display = [];
				for (var j = 0; j < node.children.length && j < 3; j++) {
					display.push('<span class="watch-short-key">' + node.children[j].name + '=</span>' + node.children[j].short);
				}

				if (j < data.total) {
					display.push('<span class="watch-arraymore">' + (data.total - j) + ' more&hellip;</span>');	
				}

				node.display = '<span class="watch-array">{</span> ' + display.join(", ") + ' <span class="watch-array">}</span>';
				node.short = '<span class="watch-object">Array</span>';

				break;

			case 'array':

				var display = [];
				for (var j = 0; j < node.children.length && j < 3; j++) {
					display.push(node.children[j].short);					
				}

				if (j < data.total) {
					display.push('<span class="watch-arraymore">' + (data.total - j) + ' more&hellip;</span>');	
				}

				node.display = '<span class="watch-array">[</span> ' + display.join(", ") + ' <span class="watch-array">]</span>';
				node.short = '<span class="watch-object">Array</span>';

				break;

			case 'object':

				var classname = data.classname;

				var display = [];
				for (var j = 0; j < node.children.length && j < 3; j++) {
					display.push('<span class="watch-short-key">' + node.children[j].name + '=</span>' + node.children[j].short);
				}

				if (j < node.children.length) {
					display.push('<span class="watch-arraymore">more&hellip;</span>');	
				}

				node.display = '<span class="watch-object">' + classname + ' {</span> ' + display.join(", ") + ' <span class="watch-object">}</span>';
				node.short = '<span class="watch-object">{&hellip;}</span>';
				break;

			case 'function':
				node.name = '<span class="watch-key-function">' + node.name + '</span>';
				if (!node.children.length) {
					node.display = '<span class="watch-function describe" rel="function ' + data.name + '">function()</span>';
					node.children = [{name : "loading...", display : "", children : []}];
				} else {
					node.display = '<span class="watch-function">function()</span>';					
				}
				node.short = '<span class="watch-function">function()</span>';
				break;

			case 'class':
				node.name = '<span class="watch-key-class">' + node.name + '</span>';
				if (!node.children.length) {
					node.display = '<span class="watch-class describe" rel="class ' + data.name + '">Class</span>';
					node.children = [{name : "loading...", display : "", children : []}];
				} else {
					node.display = '<span class="watch-class">Class</span>';
				}
				node.short = 'Class';
				break;

			default:
				node.display = data.value;
		}

		return node;
	}

};


}(jQuery));

jQuery().ready(Debugger.updater);