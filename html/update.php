<?php

header('Content-Type: application/json');

include 'queue.php';

$in = DbgQueueWriter::getInstance('in');

$queue = $in->read();

ob_start('ob_gzhandler');
echo @json_encode($queue->data);
