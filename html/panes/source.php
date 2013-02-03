<?php

?>
<script src="http://d1n0x3qji82z53.cloudfront.net/src-min-noconflict/ace.js" type="text/javascript" charset="utf-8"></script>
<script>
	jQuery().ready(function() {
		editor = ace.edit("src");
		editor.setTheme("ace/theme/dreamweaver");
		editor.getSession().setMode("ace/mode/php");
	});
</script>

<div id="source-pane">

	<div id="buttons-container">
		<div id="buttons">
			<!--<a class="button" href="#" onClick="Debugger.reload();" rel="tooltip" title="Reload Page"><i class="icon-refresh"></i></a>-->
			<button id="resume" onClick="Debugger.resume();" title="Resume Execution"><img src="/assets/img/icons/control.png" alt="resume" /></button>
			<button id="stop" onClick="Debugger.halt();" title="Stop Execution"><img src="/assets/img/icons/cross-script.png" alt="stop" /></button>
			<button id="step_over" disabled="disabled" title="Step Over"><img src="/assets/img/icons/arrow-step-over.png" alt="step over" /></button>
			<button id="step_into" disabled="disabled" title="Step Into"><img src="/assets/img/icons/arrow-step.png" alt="step into" /></button>
			<button id="step_out" disabled="disabled" title="Step Out"><img src="/assets/img/icons/arrow-step-out.png" alt="step out" /></button>
		</div>
	</div>

	<div id="source-container">
		<div id="src"></div>
	</div>

</div>

<script type="text/javascript">

(function($) {

	function resize() {
	
		var pane = $('#source-pane');
		var source = $('#source-container');

		source.height(0);

		var newheight =  pane.height() - (source.offset().top - pane.offset().top);
		source.height(newheight);

		editor.resize();	
	}

	$().ready(function() {

		WindowLayout.center.children.layout1.options.center.onresize = function() {
			resize();
			return true;
		};
	
		resize();
	});
	
}(jQuery));
</script>