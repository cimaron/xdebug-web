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

	<div id="loading-indicator" class="loading"></div>
	
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
		<div id="inspect-local" class="watch"></div>
		<div id="inspect-watch-container">
			<div id="inspect-watch" class="watch"></div>
			<input type="text" id="addwatch" value="" class="span11" placeholder="Enter expression..." />
		</div>
		<div id="inspect-global" class="watch"></div>
		<div id="inspect-defines" class="watch"></div>
		<div id="inspect-stack" class="watch"></div>
		<div id="inspect-breakpoints" class="watch"></div>
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

	$().ready(function() {

		$('.tabs').tabs({
			activate : function(e, tabs) {
				var watch = tabs.newTab.find('a').attr('href');
				watch = watch.match(/#inspect-([a-z]+)/)[1];
				Debugger.activeTab(watch);
			}
		});

		resize();
		
		$('#addwatch').on("keyup", function(e) {
			if (e.which == 13) {
				Debugger.addWatch($(this).val());
				$(this).val('');
			}
		});

	});

	debugger_ui.debugger.bind('onProxyStart', function() {
		this.displayStatus('waiting for connections');
		$('#loading-indicator').removeClass('loading');
	});

	debugger_ui.debugger.bind('onProxyDisconnect', function() {				
		this.displayStatus('not listening');
		$('#loading-indicator').removeClass('loading');
	});



}(jQuery));
</script>
