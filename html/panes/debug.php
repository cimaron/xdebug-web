<?php
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

?>

<div id="inspect-pane" class="tabs">

	<ul>
		<li rel="local">
			<a href="#inspect-local"><img src="<? echo $basedir; ?>/assets/img/icons/application-tree.png" alt="list" /> Local</a>
		</li>
		<li rel="watch">
			<a href="#inspect-watch-container"><img src="<? echo $basedir; ?>/assets/img/icons/eye.png" alt="eye" /> Watch</a>
		</li>
		<li rel="global">
			<a href="#inspect-global"><img src="<? echo $basedir; ?>/assets/img/icons/script-globe.png" alt="script globe" /> Global</a>
		</li>
		<li rel="defines">
			<a href="#inspect-defines"><img src="<? echo $basedir; ?>/assets/img/icons/application-list.png" alt="list" /> Defines</a>
		</li>
		<li rel="stack">
			<a href="#inspect-stack"><img src="<? echo $basedir; ?>/assets/img/icons/applications-stack.png" alt="app stack" /> Stack</a>
		</li>
		<li rel="breakpoints">
			<a href="#inspect-breakpoints"><img src="<? echo $basedir; ?>/assets/img/icons/breakpoint.png" alt="breakpoints" /> Breakpoints</a>
		</li>
	</ul>

	<div id="inspect-panes">
		<div id="inspect-local" class="watch inspect-pane"></div>
		<div id="inspect-watch-container">
			<div id="inspect-watch" class="watch inspect-pane"></div>
			<input type="text" id="addwatch" value="" class="span11" placeholder="Enter expression..." />
		</div>
		<div id="inspect-global" class="watch inspect-pane"></div>
		<div id="inspect-defines" class="watch inspect-pane"></div>
		<div id="inspect-stack" class="watch inspect-pane"></div>
		<div id="inspect-breakpoints" class="watch inspect-pane"></div>
	</div>

</div>


<script type="text/javascript">

(function($) {

	function resize() {
		var panes = $('#inspect-panes');
		var container = $('#inspect-pane');

		panes.height(0);

		var newheight =  container.height() - (panes.offset().top - container.offset().top);
		panes.height(newheight);
	}

	WindowLayout.options.east.onresize = function() {
		resize();
		return true;
	};


	/**
	 * Pane Class
	 */
	function Pane(el, getData) {

		this.open = false;

		this.el = el;
		this.loaded = false;
		this.getData = getData || function() {};

		this.reset();
	}

	Pane.prototype.reset = function() {
		this.loaded = false;
		this.el.html('');
	};
	
	Pane.prototype.reload = function() {
		this.loaded = false;
		this.getData();
	};

	Pane.prototype.load = function() {
		
		if (this.loaded) {
			return false;
		}
		
		this.getData();
	};

	/**
	 * Panes Class
	 */
	function Panes() {
		this.current = null;
		this.panes = [];
	}

	Panes.prototype.add = function(pane) {
		this.panes.push(pane);
	};

	Panes.prototype.reset = function() {
		for (var i = 0; i < this.panes.length; i++) {
			this.panes[i].reset();
		}
	};

	Panes.prototype.setCurrent = function(id) {

		for (var i = 0; i < this.panes.length; i++) {

			if ('#' + this.panes[i].el.attr('id') == id) {

				this.current = this.panes[i];

				this.current.open = true;
				this.current.load();

				break;
			}
		}
	};





	$().ready(function() {
	
		var current_pane = null;
		var panes = new Panes();
		var dbg = debugger_ui.debugger;
		
		// Inspect Pane
		var pane_inspect = new Pane($('#inspect-local'), function() {

			dbg.dbgContextGet()
				.then(dbg.handleContextGet.bind(dbg))
				.then(function() {
					this.loaded = true;
				}.bind(this))
				;
		});
		panes.add(pane_inspect);

		// Inspect Pane
		var pane_stack = new Pane($('#inspect-stack'), function() {

			/*
			this.command('breakpoint_list', null, null, Debugger.handleBreakpointList);
			*/

			dbg.dbgStackGet()
				.then(dbg.handleStackGet.bind(dbg))
				.then(function() {
					this.loaded = true;
				}.bind(this))
				;
		});
		panes.add(pane_stack);


		panes.current = pane_inspect;

		$('.tabs').tabs({
			activate : function(e, tabs) {
				var watch = tabs.newTab.find('a').attr('href');
				panes.setCurrent(watch);
			}
		});

		resize();
		
		/*
		$('#addwatch').on("keyup", function(e) {
			if (e.which == 13) {
				Debugger.addWatch($(this).val());
				$(this).val('');
			}
		});
		*/

		dbg.bind('onDebuggerStatus', function(e) {
			if (e.status == 'connected' || e.status == 'disconnected') {
				dbg.inspector.state.open = {};
				panes.reset();				
			}
			if (e.status == 'break') {
				panes.current.reload();
			}
		});
	

	function activeTab(tab) {
		
		return;
		if (tab) {

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

		} else {
			var href = $('#inspect-pane .ui-tabs-active a').attr('href');
			tab = href.match(/#inspect-([a-z]+)/)[1];
			return tab;
		}
	}

	});

}(jQuery));
</script>
