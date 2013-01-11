<?php
define('VERSION', '0.1');

session_start();
$_SESSION['list'] = (array)$_SESSION['list'];

if ($_REQUEST['action'] == 'add') {
	$_SESSION['list'][] = $_REQUEST['url'];
	$_SESSION['list'] = array_unique($_SESSION['list']);
	exit;
}

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
	
	<!-- Le styles -->
	<link href="assets/css/bootstrap-uncompressed.css" rel="stylesheet">
	<!--<link href="assets/css/home.css" rel="stylesheet">-->

	<!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
	<!--[if lt IE 9]>
	<script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->	
	
	
</head>

<body>

	<div class="container-fluid">
		<div class="row-fluid">
	
			<div class="span9">
				<h3>Welcome to PHPDebugger v<? echo VERSION; ?></h3>
		
				<div class="well" style="max-width: 400px;">
					<ul class="nav nav-list" id="history">
						<li class="nav-header">Recent pages</li>
						<? for ($i = 0; $i < 10 && $i < count($_SESSION['list']); $i++) { ?>
							<li><a href="<? echo $_SESSION['list'][$i]; ?>"><? echo $_SESSION['list'][$i]; ?></a>
						<? } ?>
					</ul>
				</div>
			</div>
	
		</div>

		<footer>
			&copy;<? echo date('Y'); ?>, Cimaron Shanahan. All Rights Reserved.
		</footer>
	</div>

</body>
</html>
