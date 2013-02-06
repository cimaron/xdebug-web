<?
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
<div id="console-pane">

	<div id="toolbar" class="ui-widget-header ui-corner-all">
		<button id="beginning" onClick="Debugger.clear_console();"><img src="/assets/img/icons/cross-script.png" alt="clear" /></button>
	</div>
	
	<div id="console-container">
	</div>
</div>

<script type="text/javascript">

(function($) {

	function resize() {
		var pane = $('#console-pane');
		pane.height('100%');
		var con = $('#console-container');

		con.height(0);

		var newheight =  pane.height() - (con.offset().top - pane.offset().top);
		console.log(pane.height(), con.offset().top, pane.offset().top);
		con.height(newheight);
	}

	WindowLayout.options.center.childOptions.south.childOptions.center.onresize = function() {
		resize();
		return true;
	};

	$().ready(function() {
		$('#console-pane .button').button();
		resize();
	});

	Debugger.clear_console = function() {
		$('#console-container').html('');
	}

}(jQuery));
</script>
