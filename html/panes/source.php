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

	WindowLayout.options.center.childOptions.center.onresize = function() {
		resize();
		return true;
	};

	$().ready(function() {
		$('#buttons').buttonset();
		resize();
	});
	
}(jQuery));
</script>