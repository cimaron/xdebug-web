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

Inspector = (function($) {

	function Inspector() {
		
		this.state = {
			open : {}
		};

		this.uid = 0;
	}

Inspector.prototype = {	

	action_log : function(data) {

		var uid = this.uid++;
		$('#console-container').append('<div class="watch" id="console-' + uid + '" />');
		var watch = {};
		watch.name = '';
		watch.type = 'object';
		watch.className = 'object';
		watch.children = [data];

		this.updateWatch('console-' + uid, watch);
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

		state = !$(button).hasClass('watch-toggle-open');

		if (state) {
			$(button).addClass('watch-toggle-open');
			$(el).removeClass('inactive').addClass('active');
			
			this.state.open[$(el).data('path')] = true;
			
		} else {
			$(button).removeClass('watch-toggle-open');
			$(el).removeClass('active').addClass('inactive');			

			delete this.state.open[$(el).data('path')];
		}

		reload = $(el).find('.describe')[0];
		if (reload) {
			var ctx = $(reload).attr('rel');
			this.command('describe', ctx + ' ' + el.id);
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
			this.toggleChild(i, el, state);
		}.bind(this));
	},

	/**
	 *
	 */
	toggleChild : function(i, el, state) {

		if ($(el).hasClass('watch-reload')) {
			var reload = $(el).data('reload');
			debugger_ui.debugger.dbgPropertyGet(reload.fullname, reload.stack_depth).then(function(response) {
																								   
				var data = response.data;
				var node = data.children[0];
				
				node.setAttribute('fullname', reload.name);
				node.setAttribute('name', reload.name);
				node.setAttribute('stack_depth', reload.stack_depth);

				var row = $(el).closest('tr');
				debugger_ui.debugger.injectInspectPane(node, row, 'after');
				row.remove();				
			});
			return;
		}

		if (state) {
			$(el).css('display', 'table-row');	
		} else {
			$(el).css('display', 'none');	
		}

		var active = $(el).hasClass('active');

		if (active) {
			this.toggleChildren(el, state);
		}		
	},

	treeHtml : function(tree, depth, parentid, parent_open) {
		//var out = "";
		var uid = this.uid++;

		var indent = depth * 20  + 10;
		var open = this.state.open[tree.fullname];

		var tr = $('<tr>')
			.addClass('watch_row')
			.addClass('watch_row_' + uid)
			.attr('id', 'watch_row_' + uid)
			.addClass('inactive')
			.data('uid', uid)
			.data('depth', depth)
			.data('parentid', parentid)
			.data('path', tree.fullname)
			;

		if (tree.reload) {
			tr.addClass('watch-reload');
			tr.data('reload', tree.reload);
		}

		if (depth == 0) {
			tr.addClass('watch-top');			
		} else {
			tr.addClass('watch_row_' + parentid + '_child');
			if (!parent_open) {
				tr.css('display', 'none');
			}
		}

		var td = $('<td>')
			.appendTo(tr)
			.css('padding-left', indent)
			;

		var span = $('<span>')
			.appendTo(td)
			;

		if (tree.children.length) {
			
			var toggle = $('<span>')
				.addClass('watch-toggle')
				.attr('onclick', "debugger_ui.debugger.inspector.toggleWatch(this);")
				;
			
			if (open) {
				toggle.addClass('watch-toggle-open');
			}

			toggle.appendTo(span);
		}

		span = $('<span>')
			.appendTo(span)
			.addClass('watch-name')
			.html(depth == 0 ? tree.fullname : tree.name)
			;
		
		td = $('<td>')
			.appendTo(tr)
			.html(tree.display)
			;
		
		var list = [tr];

		for (var i = 0; i < tree.children.length; i++) {
			list = list.concat(this.treeHtml(tree.children[i], depth + 1, uid, open));
		}

		return list;
		//return out;
	},

	parseCData : function(node) {
		var text = node.textContent;
		text = text.trim();
		var cdata = atob(text);		
		return cdata;
	},

	makeString : function(str) {
		return jQuery('<div />').text(str).html();
	},

	makeTree : function(data) {

		var attrs = {}, attr;
		for (var i = 0; i < data.attributes.length; i++) {
			attr = data.attributes[i];
			attrs[attr.name] = attr.value;
		}

		var value = data.textContent;
		if (attrs.encoding && attrs.encoding == 'base64') {
			var value = this.parseCData(data);
		}

		var node = {
			source : data,
			display : data,
			short : value,
			name : this.makeString(attrs.name),
			fullname : attrs.fullname ? attrs.fullname : attrs.name,
			children : []
		};

		if (this.isAssociativeArray(data)) {
			//data.children.sort(this.sortAssociative);
		}

		for (var i = 0; i < data.children.length; i++) {

			var child = data.children[i];
			
			child.attributes.stack_depth = attrs.stack_depth;

			if (attrs.type == 'object' && child.getAttribute('name') == 'CLASSNAME') {
				data.children.splice(i, 1);
				i--;
				continue;
			}

			node.children[i] = this.makeTree(child);
			if (attrs.type == 'array') {
				node.children[i].name = '<span class="watch-key-array">' + node.children[i].name + '</span>';
			}
			
		}

		if (data.children.length == 0 && attrs.numchildren > 0) {
			//max_depth exceeded
			node.reload = {
				fullname : attrs.fullname,
				name : attrs.name,
				stack_depth : attrs.stack_depth ? attrs.stack_depth : 0
			};
		}

		if (data.children.length != 0 && attrs.numchildren > data.children.length) {
			//max_children exceeded

			var onclick = "Debugger.getMore('" + this.encodeAttr(node.fullname) + "', this);";				
			node.children.push({
				display : '<span class="watch-arraymore-load" rel="1" onclick="' + onclick + '">more&hellip;</span>',
				short : '',
				name : '',
				fullname : 'more',
				children : []
			});
		}


		//get html by type
		switch (attrs.type) {

			case 'null':
			case 'uninitialized':
				node.display = '<span class="watch-null">' + attrs.type + '</span>';
				node.short = node.display;
				break;

			case 'bool':
				node.display = '<span class="watch-boolean">' + (value == '0' ? 'false' : 'true') + '</span>';
				node.short = node.display;
				break;

			case 'int':
			case 'float':

				if (value == 'INF') {
					value = Infinity;
				}
				if (value == 'NAN') {
					value = NaN;
				}

				node.display = '<span class="watch-number">' + value + '</span>';
				node.short = node.display;
				break;

			case 'string':
				var value = this.makeString(value);
				var full = '<span class="watch-string">' + value + '</span>';
				var short_str;

				if (value.length > 80) {
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

			case 'breakpoint':
				node.name = attrs.breaktype;

			case 'file':
				var filename = attrs.filename;
				var line = attrs.lineno;
				var full = '<span class="watch-file">' + filename + ' (line ' + line + ')</span>';

				var onclick = "Debugger.getSource('" + filename.replace(/("')/, '\\$1') + "', " + line + ");";
				filename = filename.replace('file://' + Debugger.state.docroot, '');
				
				node.display = '<span class="watch-file" onclick="' + onclick + '">' + filename + ' (line ' + line + ')</span>';
				node.short = node.display;
				if (attrs.where) {
					node.name = attrs.where;
				}
				node.fullname = node.name;

				node.children.unshift({name : "", display : full, children : []});
				break;
/*

			case 'comment':
				var value = this.makeString(data.value);
				node.name = '<span class="watch-comment-name">' + node.name + '</span>';	
				node.display = '<span class="watch-comment">' + value + '</span>';
				node.short = node.display;
				break;
*/

			case 'resource':

				var parts = value.match(/id='([^']+)' type='([^']+)'/);			
				node.display = '<span class="watch-object">Resource ( ' + parts[2] + '</span>, <span class="watch-object">#' + parts[1] + ' )</span>';
				node.short = node.display;
				break;

			case 'array':

				var display = [];

				if (this.isAssociativeArray(data)) {
	
					for (var j = 0; j < node.children.length && j < 3; j++) {
						display.push('<span class="watch-short-key">' + node.children[j].name + '=</span>' + node.children[j].short);
					}
	
					if (j < data.total) {
						display.push('<span class="watch-arraymore">' + (attrs.numchildren - j) + ' more&hellip;</span>');
					}

					node.display = '<span class="watch-array">{</span> ' + display.join(", ") + ' <span class="watch-array">}</span>';

				} else {

					for (var j = 0; j < node.children.length && j < 3; j++) {
						display.push(node.children[j].short);
					}

					if (j < data.children.length) {
						display.push('<span class="watch-arraymore">' + (attrs.numchildren - j) + ' more&hellip;</span>');	
					}
					
					node.display = '<span class="watch-array">[</span> ' + display.join(", ") + ' <span class="watch-array">]</span>';
				}

				node.short = '<span class="watch-object">Array</span>';

				break;

			case 'object':

				if (attrs.facet) {
		
					if (attrs.facet == 'protected') {
						node.name = '<span class="watch-protected">' + node.name + '</span>';			
					}
		
					if (attrs.facet == 'private') {
						var parts;
						if (parts = attrs.name.match(/\*([^\*]+)\*(.+)/)) {
							attrs.definedClass = parts[1];
							attrs.name = parts[2];
							node.name = this.makeString(attrs.name + " (" + attrs.definedClass + ")");
						}
						node.name = '<span class="watch-private">' + node.name + '</span>';			
					}
				}

				var classname = attrs.classname;

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
/*

			case 'function':
					
				if (data.extension) {
					node.name = '<span class="watch-extension">' + node.name + '</span>';	
				}

				node.name = '<span class="watch-key-function">' + node.name + '</span>';
				if (!node.children.length) {
					node.display = '<span class="watch-function describe" rel="function ' + data.name + '">function()</span>';
					node.children = [{name : '<div class="loading"></div>', display : "", children : []}];
				} else {
					node.display = '<span class="watch-function">function()</span>';					
				}
				node.short = '<span class="watch-function">function()</span>';
				break;

			case 'class':
				node.name = '<span class="watch-key-class">' + node.name + '</span>';
				if (!node.children.length) {
					node.display = '<span class="watch-class describe" rel="class ' + data.name + '">Class</span>';
					node.children = [{name : '<div class="loading"></div>', display : "", children : []}];
				} else {
					node.display = '<span class="watch-class">Class</span>';
				}
				node.short = 'Class';
				break;
			default:
				node.display = data.value;
*/
			default:
				node.display = attrs.type;
				node.short = node.display;
				break;
		}

		return node;
	},

	/**
	 * Determine if an array is associative
	 */
	isAssociativeArray : function(data) {

		if (data.getAttribute('type') != 'array') {
			return;	
		}

		for (var i = 0; i < data.children.length; i++) {
			var n = data.children[i].getAttribute('name');
			if (!this.isNumeric(n)) {
				return true;
			}
		}
		
		return false;
	},

	isNumeric : function(n) {
		return (typeof(n) === 'number' || typeof(n) === 'string') && n !== '' && !isNaN(n);
	},

	sortAssociative : function(a, b) {
		return a.getAttribute('name').localeCompare(b.getAttribute('name'));
	},
	
	sortArray : function(a, b) {
		return ((a.getAttribute('name') == b.getAttribute('name')) ? 0 : (parseInt(a.getAttribute('name')) > parseInt(b.getAttribute('name')) ? 1 : -1));			
	},
	
	encodeAttr : function(str) {
		str = str.replace(/'/g, "\\'");
		str = str.replace(/"/g, "\\\"");
		return str;
	}

};
 
 return Inspector;
 
}(jQuery));

