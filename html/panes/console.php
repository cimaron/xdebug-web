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
		<button id="console-button-clear" onClick="debugger_ui.trigger('onClearConsole');"><img src="/assets/img/icons/cross-script.png" alt="clear" /></button>

		<input type="checkbox" name="console-checkbox-persist" id="console-checkbox-persist" />
		<label for="console-checkbox-persist" />Persist
	</div>

	<div id="console-container">
		<table class="debug"></table>
	</div>
</div>

<script type="text/javascript">

(function($) {

	var log;

	function resize() {
		var pane = $('#console-pane');
		pane.height('100%');
		var con = $('#console-container');

		con.height(0);

		var newheight =  pane.height() - (con.offset().top - pane.offset().top);
		con.height(newheight);
	}

	function onLog(key, value) {

		value += '';
		var chunks = [], pos = 0, len = value.length;
		while (pos < len) {
			chunks.push(value.slice(pos, pos += 80));
		}
		
		args = "<tr><td class=\"key\">" + key + "</td><td class=\"value\">" + chunks.join("<br />") + "</td></tr>";
		
		var table = log.find('table');
		
		table.append(args);
		var l = table.find('tr').length

		table.find('tr:lt(' + (l - 20) + ')').remove();		

		log.scrollTop(log.scrollTop());
	}
	
	WindowLayout.options.center.childOptions.south.childOptions.center.onresize = function() {
		resize();
		return true;
	};


	$().ready(function() {
	
		log = $('#console-container');
	
		$('#console-pane .button').button();
		$('#console-checkbox-persist').button();
		resize();

		debugger_ui.bind('onLog', onLog);
		debugger_ui.debugger.bind('onLog', onLog);

		debugger_ui.bind('onClearConsole', function() {
			log.html('');	
		});

	});

}(jQuery));
</script>
