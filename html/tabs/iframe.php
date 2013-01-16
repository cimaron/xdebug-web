

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

	$().ready(resize);
	$(window).resize(resize);
	$('#tab_iframe').on('shown', resize)

	Debugger.reloadWindow = function(form) {
		$('#windowPane').attr('src', $('#windowPane').attr('src'));
	};

	Debugger.loadWindow = function(form) {
		$.ajax({
			url : "home.php",
			data : {action:"add",url:form.url.value}
		});
		$('#windowPane').attr('src', form.url.value);
	};
	
	$().ready(function() {
		$('#windowPane').on('load', function() {
			$('#window-form').find('input[name="url"]').val($('#windowPane')[0].contentWindow.location.href);
		});
	});
}(jQuery));
</script>
