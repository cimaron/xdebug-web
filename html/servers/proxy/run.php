<?php

include dirname(__FILE__) . '/../../include/SocketServer.php';
include dirname(__FILE__) . '/proxy.php';

$server = new SocketServer("192.168.1.101", 9000);

$server->max_clients = 2; // Allow no more than 2 people to connect at a time (one debugger, one control)
$server->hook("CONNECT", array("DBGpProxy", "connect"));
$server->hook("DISCONNECT", array("DBGpProxy", "disconnect"));
$server->hook("IDLE", array("DBGpProxy", "idle"));

$server->infinite_loop(); // Run Server Code Until Process is terminated.

