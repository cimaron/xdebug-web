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

/**
 * Transform an xml node into an object
 *
 * @param   SimpleXml   $xml   XML Node
 *
 * @return  object
 */
function xml_to_object($xml) {

	$node = new stdClass;
	$node->name = $xml->getName();
	$node->attributes = array();
	$node->children = array();

	foreach ($xml->attributes() as $name => $value) {
		$node->attributes[$name] = (string)$value;
	}

	foreach ($xml->children() as $child) {
		$node->children[] = xml_to_object($child);
	}

	foreach ($xml->children('http://xdebug.org/dbgp/xdebug') as $child) {
		$node->children[] = xml_to_object($child);
	}

	if ($xml->count() == 0) {
		$node->data = (string)$xml;
		if ($node->attributes['encoding'] == 'base64') {
			$node->data = base64_decode($node->data);
		}
		
	}

	return $node;
}

/**
 * Read connection data queue
 *
 * @return  array
 */
function get_queue($connection) {	

	$dbgp = new DBGp(DBGp::CTX_IDE, $connection);

	$start = time();

	$timeout = (int)(isset($_REQUEST['timeout']) ? (int)$_REQUEST['timeout'] : 1);
	$timeout = max(0, min($timeout, 30));

	$queue = array();
	while (empty($queue)) {

		$queue = $dbgp->getData();
		
		if (get_connection() != $connection) {
			return false;
		}

		if (empty($queue)) {
			usleep(100);
		}

		if (time() - $start > $timeout || connection_aborted()) {
			break;
		}
	}

	foreach ($queue as $i => $item) {
		$item = str_replace('&#0;', '??', $item);
		$xml = simplexml_load_string($item);
		$queue[$i] = xml_to_object($xml);
	}

	return $queue;
}

/**
 * Get active connection
 */
function get_connection() {

	$control = IOWriter::getInstance('status');

	//Get a list of connection/disconnection activity to find latest active connection id
	$connections = $control->read(false);
	$connections = explode("\n", trim($connections));

	$status = '';
	foreach ($connections as $con) {
		list($status, $id) = explode(' ', $con);
		if ($status == 'disconnect') {
			IOWriter::getInstance($id . '.in')->destroy();
			IOWriter::getInstance($id . '.out')->destroy();
		}
	}

	//we don't need to save the connections info any more if the last one was a disconnect
	if ($status == 'disconnect') {
		$control->write("");
	}

	if ($status != 'connect') {
		return false;
	}

	return $id;
}

/**
 * Build command
 */
function get_command($command, $transaction_id, $args, $data) {
	
	$command = preg_replace('#[^a-z_]#', '', $command);
	$transaction_id = (int)$transaction_id;
	$args = $args ? (array)$args : array();
	$data = (string)$data;

	$args['i'] = $transaction_id;
	foreach ($args as $key => $value) {
		$command .= " -$key $value";
	}

	if ($data) {
		$command .= ' -- ' . base64_encode($data);
	}
	
	return $command;
}



