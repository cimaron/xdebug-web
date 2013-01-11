<?php

include 'queue.php';

$action = $_REQUEST['action'];
$data = $_REQUEST['data'];

$out = DbgQueueWriter::getInstance('out');

$out->enqueue($action, $data);
