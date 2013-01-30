<?php

header('Content-Type: application/json');

include 'dbgp.php';

$dbgp = new DBGp(DBGp::CTX_IDE);

$queue = $dbgp->getData();
foreach ($queue as $i => $item) {
	$split = strpos($item, ' ');
	
	$queue[$i] = array(
		'action' => substr($item, 0, $split),
		'data' => json_decode(substr($item, $split + 1))
	);	
}

ob_start('ob_gzhandler');
echo json_encode($queue);

