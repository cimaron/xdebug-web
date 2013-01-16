

(function($) {

Debugger = {
	
	version : '0.2',
	
	uid : 0,
	
	options : {
		srcId : 'src',
	},
	
	/**
	 * Send action to client
	 */
	action : function(action, data) {
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
				setTimeout(Debugger.updater, 2000);
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
			if (typeof this['action_' + msg.action] == 'function') {
				this['action_' + msg.action](msg.data);	
			}
		}
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
		//if (typeof data == 'object') {
			var uid = this.uid++;
			$('#console-container').append('<div class="watch" id="console-' + uid + '" />');
			this.updateWatch('console-' + uid, {object:data});
		//} else {
		//	$('#console-container').append('<div>' + data + '</div>');	
		//}

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
	
	updateWatch : function(id, data, append) {

		var table = $('<table width="100%" />');
		if (append) {
			$('#' + id).append(table);
		} else {
			$('#' + id).html(table);
		}

		var tree = this.makeTree(data);
		for (var i = 0; i < tree.length; i++) {
			table.append(this.treeHtml(tree[i], 0));
		}
	},

	/**
	 *
	 */
	toggleWatch : function(el) {

		var my_class, state, button;

		my_class = el.id;
		el = $(el).closest('tr.watch_row')[0];
		button = $(el).find('.watch-toggle');

		state = button.html() == '+';

		if (state) {
			button.html('-');
			$(el).removeClass('inactive').addClass('active');
		} else {
			button.html('+');
			$(el).removeClass('active').addClass('inactive');			
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

			if (!state || $(el).hasClass('active')) {
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
		var tree = [], display, short, children, name;

		for (var i in data) {
			var value = data[i];

			display = value;
			short = value;
			children = [];

			//Determine type
			var type = typeof value;
			if (type == 'object' && value === null) {
				type = 'null';
			}
			if (type == 'object' && value instanceof Array) {
				type = 'array';	
			}
			if (type == 'object' && value.__PHP_Type) {
				type = value.__PHP_Type;
				delete value.__PHP_Type;
			}

			name = i;

			var matched;
			if (data instanceof Object && (matched = i.match(/([px])(.+):(.+)/))) {
				if (matched[1] == 'p') {
					name = '<span class="watch-protected">' + matched[3] + '</span>';				
				} else {
					name = '<span class="watch-private">' + matched[3] + '</span>';						
				}
				i = matched[3];
			}

			//get html by type
			switch (type) {

				case 'null':
					display = '<span class="watch-null">null</span>';
					short = display;
					break;

				case 'boolean':
					display = '<span class="watch-boolean">' + value + '</span>';
					short = display;
					break;

				case 'number':
					display = '<span class="watch-number">' + value + '</span>';
					short = display;
					break;

				case 'string':
					value = this.makeString(value);
					var full = '<span class="watch-string">"' + value + '"</span>';
					var short_str;

					if (value.length > 63) {
						short_str = value.substr(0, 30) + ' &hellip; ' + value.substr(value.length - 30);
						short_str = short_str.replace(/\s/, '&nbsp;');
						display = '<span class="watch-string watch-string-short">"' + short_str + '"</span>';
						children = [{name:"", display : full, children : []}];
					} else {
						display = full;
					}

					if (value.length > 12) {
						short_str = value.substr(0, 4) + ' &hellip; ' + value.substr(value.length - 4);
						short_str = short_str.replace(/\s/, '&nbsp;');
						short = '<span class="watch-string watch-string-short">"' + short_str + '"</span>';
					} else {
						short = full;
					}

					break;
				
				case 'array':

					children = this.makeTree(value);
					display = [];
					for (var j = 0; j < children.length && j < 3; j++) {
						display.push(children[j].short);
					}
					if (j < children.length) {
						display.push('<span class="watch-arraymore">' + (children.length - j) + ' more&hellip;</span>');	
					}
					display = '<span class="watch-array">[</span> ' + display.join(", ") + ' <span class="watch-array">]</span>';
					short = '<span class="watch-object">Array</span>';
					break;
				
				case 'object':
					var classname = 'object';
					if (value.__PHP_Incomplete_Class_Name) {
						classname = value.__PHP_Incomplete_Class_Name;
						delete value.__PHP_Incomplete_Class_Name;
					}

					children = this.makeTree(value);
					display = [];
					for (var j = 0; j < children.length && j < 3; j++) {
						display.push('<span class="watch-short-key">' + children[j].key + '=</span>' + children[j].short);
					}
					if (j < children.length) {
						display.push('<span class="watch-arraymore">more&hellip;</span>');	
					}
					display = '<span class="watch-object">' + classname + ' {</span> ' + display.join(", ") + ' <span class="watch-object">}</span>';
					short = '<span class="watch-object">{&hellip;}</span>';
					break;

				case 'function':
					name = '<span class="watch-key-function">' + i + '</span>';
					display = '<span class="watch-function">function()</span>';
					short = 'function';
					children = this.makeTree(value);
					break;

				case 'class':
					name = '<span class="watch-key-class">' + i + '</span>';
					display = '<span class="watch-class">Class</span>';
					short = 'Class';
					children = this.makeTree(value);
					break;

				default:
					display = value;
			}

			if (data instanceof Array) {
				name = '<span class="watch-key-array">' + i + '</span>';				
			}
			
			tree.push({
				key : i,
				name : name,
				display : display,
				children : children,
				short : short
			});
		}

		return tree;
	}

};


}(jQuery));

jQuery().ready(Debugger.updater);