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
<style type="text/css">

.ace-dreamweaver .ace_content {
	font-family: "Courier New", Courier, monospace;
}

.ace-dreamweaver .ace_line .ace_support.ace_php_tag {
	font-weight: bold;
}

.ace-dreamweaver .ace_line .ace_comment,
.ace-dreamweaver .ace_line .ace_comment.ace_doc,
.ace-dreamweaver .ace_line .ace_comment.ace_doc.ace_tag {
    color: #FF9900;
}

.ace-dreamweaver .ace_line .ace_string {
	color: #CC0000;
}

.ace-dreamweaver .ace_line .ace_support.ace_function {
	color: #0000FF;
}

.ace-dreamweaver .ace_line .ace_constant.ace_language {
	color: #552200;
}

.ace-dreamweaver .ace_line .ace_variable {
	color: #000000;
}

.ace-dreamweaver .ace_line .ace_keyword.ace_operator {
	color: #0000FF;
}

.ace-dreamweaver .ace_line .ace_constant.ace_numeric {
	color: #FF0000;
}

.ace-dreamweaver .ace_gutter {
	background-color: #0099CC;
	color: #FFFFFF;
}

.ace_gutter-cell.ace_breakpoint {
    border-radius: 20px 0 0 20px;
    box-shadow: 0px 0px 10px 10px #990000 inset;
	color: #FFFFFF;
}
</style>
<script>
(function($) {

	var editor;

	$().ready(function() {

		editor = ace.edit("src");
		editor.setTheme("ace/theme/dreamweaver");
		editor.getSession().setMode("ace/mode/php");
		
		editor.on("guttermousedown", function(e){
			var target = e.domEvent.target;
			if (target.className.indexOf("ace_gutter-cell") == -1) {
				return;
			}
			if (!editor.isFocused()) {
				return;
			}
			if (e.clientX > 25 + target.getBoundingClientRect().left) {
				return;
			}
	
			var row = e.getDocumentPosition().row;
	
			var breakpoints = e.editor.session.getBreakpoints();
	
			if (!breakpoints[row]) {
				e.editor.session.setBreakpoint(row);
				Debugger.setBreakpoint(Debugger.state.file, row + 1);
			} else {
				e.editor.session.clearBreakpoint(Debugger.state.file, row);
				Debugger.clearBreakpoint(Debugger.state.file, row + 1);
			}
			e.stop();
		});

		var curline = 0;

		debugger_ui.debugger.bind('onDebuggerFile', function(e) {
			$('#source-file').html($('<option>').text(e.file));
		});

		debugger_ui.debugger.bind('onDebuggerSource', function(e) {
			editor.setValue(e.source);
			editor.setReadOnly(true);
			editor.clearSelection();

			editor.gotoLine(curline, 0);
			editor.scrollToLine(curline, true);

			//debugger_ui.debugger.displayBreakpoints();
		});

		debugger_ui.debugger.bind('onDebuggerLineno', function(e) {
			editor.gotoLine(e.line, 0);
			editor.scrollToLine(e.line, true);
			curline = e.line;		
		});

		['onProxyStatus', 'onDebuggerStatus'].forEach(function(name, i) {
			debugger_ui.debugger.bind(name, function(e) {
			
				var el = (i == 0 ? $('#proxy-status') : $('#debugger-status'));
				['stopped', 'waiting', 'started'].forEach(function(status) {
					el.removeClass('indicator-' + status);
				});

				switch (e.status) {
					case 'connected':
					case 'starting':
					case 'connecting':
					case 'running':
						el.addClass('indicator-started');
						break;

					case 'break':
					case 'waiting':
						el.addClass('indicator-waiting');
						break;
					
					case 'stopping':
					case 'disconnected':
						el.addClass('indicator-stopped');
						break;						
					
					default:
						console.log(e.status);
				}
			});
		});
		
		debugger_ui.debugger.bind('onRawData', function() {
		});
		debugger_ui.debugger.bind('onSendProxyCommand', function() {
		});
		

		/*
		//doesn't give us info on what breakpoint changed
		editor.session.on('changeBreakpoint', function(e) {
		});
		*/

		$('#buttons').buttonset();
		resize();	
	});


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

	$('#source-file').on("keyup", function(e) {
		if (e.which == 13) {
			Debugger.getSource($(this).val());
		}
	});
	
	
}(jQuery));
</script>

<div id="source-pane">

	<div id="buttons-container">
		<select id="source-file" style="width:50%;"></select>

		<input type="checkbox" id="break-enabled" />
		<div id="buttons">
			<span id="proxy-status" class="indicator indicator-stopped"></span>
			<span id="debugger-status" class="indicator indicator-stopped"></span>
			<button id="resume"    onclick="debugger_ui.debugger.dbgRun();" title="Resume Execution"><img src="/assets/img/icons/control.png" alt="resume" /></button>
			<button id="stop"      onclick="debugger_ui.debugger.dbgStop();" title="Stop Execution"><img src="/assets/img/icons/cross-script.png" alt="stop" /></button>
			<button id="step_over" onclick="debugger_ui.debugger.dbgStepOver();" title="Step Over"><img src="/assets/img/icons/arrow-step-over.png" alt="step over" /></button>
			<button id="step_into" onclick="debugger_ui.debugger.dbgStepInto();" title="Step Into"><img src="/assets/img/icons/arrow-step.png" alt="step into" /></button>
			<button id="step_out" onclick="debugger_ui.debugger.dbgStepOut();" title="Step Out"><img src="/assets/img/icons/arrow-step-out.png" alt="step out"  /></button>
		</div>
	</div>

	<div id="source-container">
		<div id="src"></div>
	</div>

</div>

