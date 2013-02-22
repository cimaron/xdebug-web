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

require_once dirname(__FILE__) . '/response.php';


/**
 * Ide server class
 */
class IdeServer {

	public $server;
	public $clients = array();
	public $proxy;

	protected $connected = false;
	protected $async_packets = array();

	public function __construct($proxy) {

		$this->proxy = $proxy;

		$this->server = new SocketServer("localhost", 9001);
		$this->server->max_clients = 10;
		$this->server->hook("CONNECT", array($this, "connect"));
		$this->server->hook("DISCONNECT", array($this, "disconnect"));
		$this->server->hook("INPUT", array($this, "input"));
		//$this->server->hook("IDLE", array("DBGpProxy", "idle"));
		
		$this->proxy->addEventListener("onShutdown", array($this, 'onShutdown'));
		$this->proxy->addEventListener("onDebuggerConnect", array($this, 'onDebuggerConnect'));
		$this->proxy->addEventListener("onDebuggerDisconnect", array($this, 'onDebuggerDisconnect'));
		$this->proxy->addEventListener("onGetDebuggerPackets", array($this, 'onGetDebuggerPackets'));

		DBGpProxy::log("IDE proxy server listening on port 9000");
	}

	public function __destruct() {
		DBGpProxy::log("IDE proxy server going down");
	}

	/**
	 * New debugger client connection
	 *
	 * @param   object     $server   The server class
	 * @param   resource   $client   The connection socket
	 * @param   string     $input    Anything sent, if anything was sent
	 */
	public function connect(&$server, &$client, $input) {

		$client->buffer = "";

		$this->clients[$client->server_clients_index] = $client;

		DBGpProxy::log("IDE connected: " . $this->clientString($client));
		
		$this->input($server, $client, $input);

		$this->logClients();
		
		return true;
	}

	/**
	 * Client disconnected
	 *
	 * @param   object     $server   The server class
	 * @param   resource   $client   The connection socket
	 * @param   string     $input    Anything sent, if anything was sent
	 */
	public function disconnect(&$server, &$client, $input) {

		//remove all event listeners attacked to this client
		$this->proxy->removeEventListener(NULL, NULL, $client);

		unset($this->clients[$client->server_clients_index]);

		DBGpProxy::log("IDE disconnected: " . $this->clientString($client));
		
		$this->logClients();

		return true;
	}

	/**
	 * Check for server input
	 */
	public function run_once() {
		$this->server->loop_once();
	}

	public function logClients() {
		return;
		$info = array();
		foreach ($this->clients as $i => $cl) {
			$info[] = $this->clientString($cl);
		}
		DBGpProxy::log(count($this->clients) . " clients => " . implode(', ', $info));
	}

	/**
	 * Handle socket input
	 *
	 * @param   object     $server   The server class
	 * @param   resource   $client   The connection socket
	 * @param   string     $input    Anything sent, if anything was sent
	 */
	public function input(&$server, &$client, $input) {

		$client->buffer .= $input;

		while ($command_raw = $this->getCommand($client)) {

			$command = json_decode($command_raw);

			DBGpProxy::log($this->clientString($client) . " executed: " . $command_raw);

			$client->command = $command->name;

			switch ($command->name) {

				case 'echo':

					$this->sendResponse($client, 'echo', array('echo' => $command->data));
					break;

				case 'listen':

					//async packets are already queued and waiting, push to client
					if (count($this->async_packets)) {
						DBGpProxy::log("Processed queued packets");
						$this->sendResponse($client, 'response', array('connected' => true, 'packets' => $this->async_packets));
						$this->async_packets = array();
						break;
					}

					$this->proxy->addEventListener('onDebuggerConnect', array($this, 'onDebuggerConnect'), $client);
					$this->proxy->addEventListener('onDebuggerDisconnect', array($this, 'onDebuggerDisconnect'), $client);
					$this->proxy->addEventListener('onGetDebuggerPackets', array($this, 'onGetDebuggerPackets'), $client);
					break;
					
				case 'reset':

					//force close all other existing connections to prevent concurrency issues
					foreach ($this->clients as $cl) {
						if ($cl != $client) {
							$this->sendResponse($cl, 'error', array('message' => 'connection closed'));
						}
					}

					$this->buffer = "";
					$this->async_packets = array();

					$this->sendResponse($client, 'response', array('success' => true));

					break;

				case 'send':

					$dbgp_cmd = $command->data;
					$transaction_id = $command->options->transaction_id;
					$this->proxy->addEventListener('onGetDebuggerPackets', array($this, 'onGetDebuggerPackets'), $client, array($transaction_id));
					$this->proxy->addEventListener('onDebuggerDisconnect', array($this, 'onDebuggerDisconnect'), $client);
					$this->proxy->triggerEvent('onDebuggerCommand', array($dbgp_cmd));
					break;

				case 'status':

					$this->sendResponse($client, 'response', array('connected' => $this->connected));
					break;

				case 'shutdown':

					$this->sendResponse($client, 'response', array('success' => true));
					$this->proxy->triggerEvent("onShutdown");
					die();
					break;

				default:

					$this->send($client, 'error', array('message' => 'unknown command'));
			}

		}

		return true;
	}

	/**
	 * Send response to client and close connection
	 */
	protected function sendResponse($client, $name, $data) {
		$resp = new Response($name, $data);
		$resp->send($client);
		
		DBGpProxy::log("Sent response: " . (string)$resp);
		$this->server->disconnect($client->server_clients_index);
	}

	/**
	 * Get single command packet from buffer
	 *
	 * @param   ServerSocketClient   $client   Client object
	 *
	 * @return  string
	 */
	protected function getCommand($client) {

		//DBGpProxy::log($this->clientString($client) . " buffered: " . $client->buffer);

		$pos = strpos($client->buffer, chr(0));

		if ($pos === false) {
			return false;
		}

		$command = substr($client->buffer, 0, $pos);
		$client->buffer = substr($client->buffer, $pos + 1);

		return $command;
	}

	/**
	 * Shutdown event
	 */
	public function onShutdown() {

		foreach ($this->clients as $cl) {
			$this->sendResponse($cl, 'error', array('message' => 'connection closed'));
		}

		//DBGpProxy::log("Shutting down IDE server");
		socket_shutdown($this->server->master_socket, 1);
		usleep(500);
		socket_shutdown($this->server->master_socket, 0);
		socket_close($this->server->master_socket);
	}

	/**
	 * Debugger Connect Event
	 */
	public function onDebuggerConnect($client = NULL) {

		if ($client) {

			$this->sendResponse($client, 'response', array('connected' => true));
			return;

		} else {

			$this->connected = true;
			$this->buffer = "";
			$this->async_packets = array();
		}
	}

	/**
	 * Debugger Disconnect Event
	 */
	public function onDebuggerDisconnect($client = NULL) {

		if ($client) {

			$this->sendResponse($client, 'response', array('connected' => false));
			return;

		} else {

			$this->connected = false;
			$this->buffer = "";
			$this->async_packets = array();
		}
	}

	/**
	 * Get Debugger packet event
	 *
	 * This event can execute in one of three contexts:
	 * 1) Default server (always)
	 * 2) Client listening for async packets
	 * 3) Client listening for specific transaction result
	 *
	 * By default ordering, #1 will always execute first, so in #2, all async packets will already
	 * be queued in $this->async_packets, and there will none left in $packets
	 *
	 * @param   array                $packets          List of packets
	 * @param   SocketServerClient   $client           Socket client
	 * @param   int                  $transaction_id   Transaction ID to look for
	 */
	public function onGetDebuggerPackets(&$packets, $client = NULL, $transaction_id = NULL) {

		foreach ($packets as $i => $packet) {

			$node = $this->xml_to_object(simplexml_load_string($packet));

			//queue unmatched
			if (!isset($node->attributes['transaction_id'])) {
				DBGpProxy::log('Queued packet');
				$this->async_packets[] = $node;
				unset($packets[$i]);
				continue;
			}

			//response for transaction
			if ($transaction_id && $transaction_id == $node->attributes['transaction_id']) {
				$this->sendResponse($client, 'response', array('connected' => true, 'data' => $node));
				unset($packets[$i]);
			}
		}

		//async client listener
		if ($client && !$transaction_id) {
			DBGpProxy::log("Processed queued packets");
			$this->sendResponse($client, 'response', array('connected' => true, 'data' => $this->async_packets));
			$this->async_packets = array();
		}
		
	}

	/**
	 * Transform an xml node into an object
	 *
	 * @param   SimpleXml   $xml   XML Node
	 *
	 * @return  object
	 */
	protected function xml_to_object($xml) {
	
		$node = new stdClass;
		$node->name = $xml->getName();
		$node->attributes = array();
		$node->children = array();
	
		foreach ($xml->attributes() as $name => $value) {
			$node->attributes[$name] = (string)$value;
		}
	
		foreach ($xml->children() as $child) {
			$node->children[] = $this->xml_to_object($child);
		}

		foreach ($xml->children('http://xdebug.org/dbgp/xdebug') as $child) {
			$node->children[] = $this->xml_to_object($child);
		}
	
		if ($xml->count() == 0) {
			$node->data = (string)$xml;
			if (isset($node->attributes['encoding']) && $node->attributes['encoding'] == 'base64') {
				$node->data = base64_decode($node->data);
			}
			
		}
	
		return $node;
	}

	public function clientString($client) {
		return sprintf("Client(%s)%s", substr((string)$client->socket, 12), $client->command ? "[$client->command]" : "");
	}

}

