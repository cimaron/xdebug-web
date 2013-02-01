<?php

header('Content-Type: application/json');

include 'include/dbgp.php';


$dbgp = new DBGp(DBGp::CTX_IDE);

$max_sleep = 4;
$start = time();
$current = 0;

$queue = $dbgp->getData();
while (empty($queue) && $current < $max_sleep) {
	usleep(100);
	$queue = $dbgp->getData();
	$current = time() - $start;
}

foreach ($queue as $i => $item) {	
	$queue[$i] = json_decode($item);
}

ob_start('ob_gzhandler');
echo json_encode($queue);

