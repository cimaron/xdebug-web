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


<div class="row-fluid" id="tab-debug">

	
	<div class="span7">
	
		<div class="navbar">
			<div class="navbar-inner">
		 
				<div class="btn-group" id="debug-buttons">
					<a class="btn disabled" href="#" onClick="Debugger.reload();" rel="tooltip" title="Reload Page"><i class="icon-refresh"></i></a>
					<a class="btn" href="#" onClick="Debugger.resume();" rel="tooltip" title="Resume Execution"><i class="icon-play"></i></a>
					<a class="btn" href="#" onClick="Debugger.halt();" rel="tooltip" title="Stop Execution"><i class="icon-stop"></i></a>
					<a class="btn disabled" href="#" rel="tooltip" title="Step Into"><i class="icon-arrow-down"></i></a>
					<a class="btn disabled" href="#" rel="tooltip" title="Step Over"><i class="icon-arrow-right"></i></a>
					<a class="btn disabled" href="#" rel="tooltip" title="Step Out"><i class="icon-arrow-up"></i></a>
				</div>
	
				<form class="navbar-form pull-right">
					<select><option>test</option></select>
				</form>
			
			</div>
		</div>
	
		<div id="src_container">
			<div id="src"></div>
		</div>
	
	
	</div>

<!-- ====================================== -->
	
	<div class="span5" id="info_container">
	
		<ul class="nav nav-tabs">
			<li class="active">
				<a href="#local" data-toggle="tab">Local</a>
			</li>
			<li>
				<a href="#watch" data-toggle="tab">Watch</a>
			</li>
			<li>
				<a href="#global" data-toggle="tab">Global</a>
			</li>
			<li>
				<a href="#defines" data-toggle="tab">Defines</a>
			</li>
			<li>
				<a href="#trace" data-toggle="tab">Trace</a>
			</li>
		</ul>
	
		<div id="watch_panes" class="tab-content">
			<div class="tab-pane active watch" id="local"></div>
			<div class="tab-pane watch" id="watch">
				<div class="row-fluid">
					<input type="text" id="addwatch" value="" class="span11" onchange="Debugger.addWatch(this);" placeholder="Enter expression..." />
				</div>
				<div id="watch-container"></div>
			</div>
			<div class="tab-pane watch" id="global"></div>
			<div class="tab-pane watch" id="defines"></div>
			<div class="tab-pane watch" id="trace"></div>
		</div>

	</div>

</div>

<script type="text/javascript">

(function($) {

	function resize(e) {
		$('#src_container').height(0);
		$('#watch_panes').height(0);

		var newheight =  $(document).height() - $('#src_container').offset().top;
		$('#src_container').height(newheight);	

		var newheight =  $(document).height() - $('#watch_panes').offset().top;
		$('#watch_panes').height(newheight);	
	}

	$().ready(resize);
	$().ready(function() {
		//Reenable when bug with tooltip and btn-group is fixed in bootstrap
		//$('#debug-buttons .btn').tooltip({placement:'bottom'});
	});
    $('#tab_debug').on('shown', resize)

	Debugger.addWatch = function(el) {
		var val = $(el).val();
		$(el).val('');
		Debugger.action('exec', val);
	}

}(jQuery));
</script>
