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
	<script src="assets/js/jquery.cookie.js"></script>
	<script>
		//Initialize this here so our subpanes can modify the configuration before loading
		WindowLayout = {};
		WindowLayout.options = {
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
			west : {
				size : '15%'
			},
			center : {
				paneSelector : ".outer-center",
				childOptions : {
					center : {
						paneSelector : ".middle-center"
					},
					south : {
						paneSelector : ".middle-south",
						initClosed : true,
						childOptions : {
							center : {
								paneSelector : ".inner-center",
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
		};

		$(document).ready(function () {
			WindowLayout.layout = $('body').layout(WindowLayout.options);
		});
	</script>

	<script src="assets/js/debugger.js"></script>
	<script src="assets/js/request.js"></script>
	<script src="assets/js/inspector.js"></script>
	
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

<div class="ui-layout-west">
	<? include 'panes/files.php'; ?>
</div>

<div class="outer-center">

	<div class="middle-center">
		<? include 'panes/source.php'; ?>
	</div>

	<div class="middle-south">
		<div class="inner-center">
			<? include 'panes/console.php'; ?>
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

