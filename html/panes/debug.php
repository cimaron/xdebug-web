<?php

?>

<div id="inspect-pane" class="tabs">

	<ul>
		<li rel="local">
			<a href="#inspect-local"><img src="assets/img/icons/application-tree.png" alt="list" /> Local</span></a>
		</li>
		<li rel="watch">
			<a href="#inspect-watch-container"><img src="assets/img/icons/eye.png" alt="eye" /> Watch</a>
		</li>
		<li rel="global">
			<a href="#inspect-global"><img src="assets/img/icons/script-globe.png" alt="script globe" /> Global</a>
		</li>
		<li rel="defines">
			<a href="#inspect-defines"><img src="assets/img/icons/application-list.png" alt="list" /> Defines</a>
		</li>
		<li rel="stack">
			<a href="#inspect-stack"><img src="assets/img/icons/applications-stack.png" alt="app stack" /> Stack</a>
		</li>
	</ul>

	<div id="inspect-panes">
		<div id="inspect-local" class="watch"></div>
		<div id="inspect-watch-container">
			<input type="text" id="addwatch" value="" class="span11" onchange="Debugger.addWatch(this);" placeholder="Enter expression..." />
			<div id="inspect-watch" class="watch"></div>
		</div>
		<div id="inspect-global" class="watch"></div>
		<div id="inspect-defines" class="watch"></div>
		<div id="inspect-stack" class="watch"></div>
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

	$().ready(function() {

		$('.tabs').tabs({
			activate : function(e, tabs) {
				var watch = tabs.newTab.attr('rel');
				$('#inspect-' + watch).html('<div class="loading"></div>');
				Debugger.action('get', watch);
			}
		});

		WindowLayout.options.east.onresize = function() {
			resize();
			return true;
		};
	
		resize();
	});

	Debugger.addWatch = function(el) {
		var val = $(el).val();
		$(el).val('');
		Debugger.action('exec', val);
	}

}(jQuery));
</script>
