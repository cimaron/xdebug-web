<?php

include 'include/dbgp.php';

$command = preg_replace('#[^a-z_]#', '', $_REQUEST['command']);
$data = $_REQUEST['data'];
$tid = (int)$_REQUEST['tid'];

$dbgp = new DBGp(DBGp::CTX_IDE);
$dbgp->sendCommand("$command -i $tid " . base64_encode($data));
