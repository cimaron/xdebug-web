<?php

include 'dbgp.php';

$action = $_REQUEST['action'];
$data = $_REQUEST['data'];

$dbgp = new DBGp(DBGp::CTX_IDE);
$dbgp->sendCommand($action . ' ' . base64_encode($data));
