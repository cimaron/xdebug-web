

<div class="navbar">
	<div class="navbar-inner">
    <form id="window-form" class="navbar-form pull-left" onsubmit="Debugger.loadWindow(this); return false;">
    	<input type="text" class="span6" name="url" placeholder="URL">
    	<button type="submit" class="btn"><i class="icon-refresh"></i></button>
    </form>
	</div>
</div>

<iframe id="windowPane" style="width: 100%; border: none;" src="home.php"></iframe>

<script type="text/javascript">

(function($) {

	function resize(e) {
		$('#windowPane').height(0);
		var newheight =  $(document).height() - $('#windowPane').offset().top;
		$('#windowPane').height(newheight);	
	}

	$(window).resize(resize);

	$('#tab_iframe').on('shown', resize)

	$().ready(function() {
		resize();
	});
}(jQuery));
</script>
