<?php
define('VERSION', '0.2');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  	
	<meta charset="utf-8">
	<title>PHP Debugger <? echo VERSION; ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="">
	<meta name="author" content="">

	<script src="assets/js/jquery-1.8.2-uncompressed.js"></script>
	<script src="assets/js/bootstrap.js"></script>
	<script src="assets/js/debugger.js"></script>
	
	<!-- Le styles -->
	<link href="assets/css/bootstrap-uncompressed.css" rel="stylesheet">
	<link href="assets/css/debugger.css" rel="stylesheet">

	<!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
	<!--[if lt IE 9]>
	<script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->	
</head>

<body>

<div class="container-fluid">

	<h4 style="position: absolute; right: 20px;">
		PHP Debugger <? echo VERSION; ?>
	</h4>

    <ul class="nav nav-tabs">
		<li class="active">
			<a id="tab_iframe" href="#iframe" data-toggle="tab">Window</a>
		</li>
		<li>
			<a id="tab_debug" href="#debug" data-toggle="tab">Debug</a>
		</li>
		<li>
			<a id="tab_console" href="#console" data-toggle="tab">Console</a>
		</li>		
    </ul>

	<div class="tab-content">
		<div class="tab-pane active" id="iframe"><? include 'tabs/iframe.php'; ?></div>
		<div class="tab-pane" id="debug"><? include 'tabs/debug.php'; ?></div>
		<div class="tab-pane" id="console"><? include 'tabs/console.php'; ?></div>
	</div>

</div>

</body>
</html>

