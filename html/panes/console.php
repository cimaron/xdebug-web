
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
		$('#console-container').height(0);
		var newheight =  $(document).height() - $('#console-container').offset().top;
		$('#console-container').height(newheight);	
	}

	function shown() {
		$('#tab_console').text('Console');
	}

	$().ready(function() {
		resize();
		$('#console-pane .button').button();
	});
	$(window).resize(resize);	
    $('#tab_console').on('shown', resize)

    $('#tab_console').on('shown', shown)

	Debugger.clear_console = function() {
		$('#console-container').html('');
	}

}(jQuery));
</script>
