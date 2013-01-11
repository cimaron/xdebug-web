
<div class="navbar">
	<div class="navbar-inner">
		<div class="btn-group">
			<a class="btn" href="#" onClick="Debugger.clear_console();">clear</a>
		</div>
	</div>
</div>

<div id="console-container">
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

	$().ready(resize);
    $('#tab_console').on('shown', resize)
    $('#tab_console').on('shown', shown)

	Debugger.clear_console = function() {
		$('#console-container').html('');
	}

}(jQuery));
</script>
