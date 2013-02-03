<?php
include dirname(__FILE__) . '/include/config.php';
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
	<script src="assets/js/jquery-ui-1.10.0.custom.js"></script>
	<script src="assets/js/jquery.layout.js"></script>
	<script>
		$(document).ready(function () {
			WindowLayout = $('body').layout({
				north : {
					size: 40,
					slidable : false,
					fxName : "none",
					spacing_open : 1,
					resizable : false,
					togglerLength_open : 0,
					togglerLength_closed : -1
				},
				east : {
					size : '40%'
				},
				center : {
					paneSelector : ".outer-center",
					childOptions : {
						center : {
							paneSelector : ".middle-center"
						},
						south : {
							paneSelector : ".middle-south",
							childOptions : {
								center : {
									paneSelector : ".inner-center"
								},
								east : {
									paneSelector : ".inner-east",
									initClosed : true
								}
							},
							size : 200
						}
					}
				}
			});
		});
	</script>

	<script src="assets/js/debugger.js"></script>
	
	<!-- Le styles -->
	<link href="assets/css/jquery-ui-1.10.0.custom.css" rel="stylesheet">
	<link href="assets/css/layout-default.css" rel="stylesheet">
	<link href="assets/css/debugger.css" rel="stylesheet">
</head>

<body>

<div class="ui-layout-north">
	<h4 style="margin: 4px;">
		PHP Debugger <? echo VERSION; ?>
	</h4>
</div>

<div class="outer-center">

	<div class="middle-center">
		<? include 'panes/source.php'; ?>
	</div>

	<div class="middle-south">
		<div class="inner-center">
			<div id="console">
				<? include 'panes/console.php'; ?>
			</div>
		</div>
		<div class="inner-east" id="debug">
			<table></table>
		</div>
	</div>

</div>

<div class="ui-layout-east" style="width:400px;">
	<? include 'panes/debug.php'; ?>
</div>


</body>
</html>

