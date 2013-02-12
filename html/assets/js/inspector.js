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
		  
Debugger.inspector = {	

	uid : 0,

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
		} else {
			$(button).removeClass('watch-toggle-open');
			$(el).removeClass('active').addClass('inactive');			
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
			out += '<span class="watch-toggle" onclick="Debugger.inspector.toggleWatch(this);"></span>';
		}

		out += '<span><span class="watch-name">' + (depth == 0 ? tree.fullname : tree.name) + '</span></span></td>';
		out += '<td>' + tree.display + '</td>';
		
		if (depth == 0) {
			//out += '<td class="watch-close"><span>&times;</span></td>';	
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
		node.short = data.value;
		node.name = this.makeString(data.attributes.name);
		node.fullname = data.attributes.fullname;

		//
		node.children = [];
		for (var i in data.children) {
			node.children[i] = this.makeTree(data.children[i]);
			if (data.attributes.type == 'array') {
				node.children[i].name = '<span class="watch-key-array">' + node.children[i].name + '</span>';
			}
		}

		if (data.attributes.facet) {

			if (data.attributes.facet == 'protected') {
				node.name = '<span class="watch-protected">' + node.name + '</span>';			
			}

			if (data.attributes.facet == 'private') {
				node.name = '<span class="watch-private">' + node.name + '</span>';			
			}
			
			/*
			if (data.extension) {
				node.name = '<span class="watch-extension">' + node.name + '</span>';	
			}
			*/
		}

		//get html by type
		switch (data.attributes.type) {
			case 'uninitialized':
				node.display = '<span class="watch-null">uninitialized</span>';
				node.short = node.display;
				break;

			case 'null':
				node.display = '<span class="watch-null">null</span>';
				node.short = node.display;
				break;

			case 'bool':
				node.display = '<span class="watch-boolean">' + (data.data ? 'true' : 'false') + '</span>';
				node.short = node.display;
				break;

			case 'int':
			case 'float':

			var value = data.data;

				if (data.data == 'INF') {
					value = Infinity;
				}
				if (data.data == 'NAN') {
					value = NaN;
				}

				node.display = '<span class="watch-number">' + value + '</span>';
				node.short = node.display;
				break;

			case 'string':
				var value = this.makeString(data.data);
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
/*
			case 'file':
				var full = '<span class="watch-file">' + data.value.file + ' (line ' + data.value.line + ')</span>';

				var onclick = "Debugger.command('source', '" + data.value.file.replace(/("')/, '\\$1') + " " + data.value.line + "')";
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

				break;
*/
			case 'array':

				var display = [];

				if (this.isAssociativeArray(data)) {
	
					for (var j = 0; j < node.children.length && j < 3; j++) {
						display.push('<span class="watch-short-key">' + node.children[j].name + '=</span>' + node.children[j].short);
					}
	
					if (j < data.total) {
						display.push('<span class="watch-arraymore">' + (data.attributes.numchildren - j) + ' more&hellip;</span>');	
					}
	
					node.display = '<span class="watch-array">{</span> ' + display.join(", ") + ' <span class="watch-array">}</span>';

				} else {

					for (var j = 0; j < node.children.length && j < 3; j++) {
						display.push(node.children[j].short);					
					}
	
					if (j < data.total) {
						display.push('<span class="watch-arraymore">' + (data.attributes.numchildren - j) + ' more&hellip;</span>');	
					}
	
					node.display = '<span class="watch-array">[</span> ' + display.join(", ") + ' <span class="watch-array">]</span>';
				}

				node.short = '<span class="watch-object">Array</span>';

				break;

			case 'object':

				var classname = data.attributes.classname;

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
				node.display = data.attributes.type;
				node.short = node.display;
				break;
		}

		return node;
	},

	/**
	 * Determine if an array is associative
	 */
	isAssociativeArray : function(data) {

		for (var i = 0; i < data.children.length; i++) {
			var n = data.children[i].attributes.name;
			if (typeof n != 'number' || parseInt(n) != n) {
				return true;
			}
		}
		
		return false;
	}

};
		  
}(jQuery));

