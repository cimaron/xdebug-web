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
 * Debugger server class
 */
class DebuggerServer {

	protected $server;
	public $client;
	public $proxy;

	protected $buffer = "";

	/**
	 * Constructor
	 *
	 * @param   DBGpProxy   $proxy   Proxy server
	 */
	public function __construct($proxy, $host = 'localhost', $port = 9000) {

		$this->proxy = $proxy;

		$this->server = new SocketServer($host, $port);

		$this->server->max_clients = 1;
		$this->server->hook("CONNECT", array($this, "connect"));
		$this->server->hook("DISCONNECT", array($this, "disconnect"));
		//$this->server->hook("IDLE", array($this, "idle"));

		$this->proxy->addEventListener("onShutdown", array($this, 'onShutdown'));
		$this->proxy->addEventListener("onDebuggerCommand", array($this, 'onDebuggerCommand'));
		
		DBGpProxy::log("Debugger proxy server listening on port 9000");
	}
	
	public function __destruct() {
		DBGpProxy::log("Debugger proxy server going down");
	}

	/**
	 * New debugger client connection
	 *
	 * @param   object     $server   The server class
	 * @param   resource   $client   The connection socket
	 * @param   string     $input    Anything sent, if anything was sent
	 */
	public function connect(&$server, &$client, $input) {

		$server->hook("INPUT", array($this, "input"));

		$this->buffer = $input;
		$this->client = $client;

		DBGpProxy::log("Debugger connected " . $client->lookup_hostname());

		$this->proxy->triggerEvent('onDebuggerConnect');
	}

	/**
	 * Client disconnected
	 *
	 * @param   object     $server   The server class
	 * @param   resource   $client   The connection socket
	 * @param   string     $input    Anything sent, if anything was sent
	 */
	public function disconnect(&$server, &$client, $input) {

		$server->unhook("INPUT", array($this, "input"));

		$this->client = NULL;
		$this->buffer = "";

		DBGpProxy::log("Debugger disconnected " . $client->lookup_hostname());
		
		$this->proxy->triggerEvent('onDebuggerDisconnect');
	}

	/**
	 * Check for server input
	 */
	public function run_once() {
		$this->server->loop_once();
	}

	/**
	 * Handle socket input
	 *
	 * @param   object     $server   The server class
	 * @param   resource   $client   The connection socket
	 * @param   string     $input    Anything sent, if anything was sent
	 */
	public function input(&$server, &$client, $input) {

		$this->buffer .= $input;

		$packets = array();
		while ($packet = $this->getPacketData()) {
			$packets[] = $packet;
			DBGpProxy::log("Received packet: " . $packet);
		}

		$this->proxy->triggerEvent('onGetDebuggerPackets', array(&$packets));
	}

	/**
	 * Get single xml packet data from buffer
	 *
	 * @return  string
	 */
	protected function getPacketData() {

		$first = strpos($this->buffer, chr(0));

		if ($first === false) {
			return '';
		}

		$second = strpos($this->buffer, chr(0), $first + 1);

		if ($second === false) {
			return '';
		}

		//$data_length = substr($input, 0, strpos($this->buffer, chr(0)));
		//$packet_length = strlen($data_length) + 1 + (int)$data_length + 1;

		//echo "data_length: $data_length\n";
		//echo "packet_length: $packet_length\n";

		$packet_length = $second + 1;
		$packet = substr($this->buffer, 0, $packet_length);
		$this->buffer = substr($this->buffer, $packet_length + 1);

		$data = substr($packet, $first + 1, $second - $first - 1);
		$data = str_replace('&#0;', '??', $data);

		return $data;
	}

	/**
	 * Format xml for display
	 *
	 * @param   string   $xml   XML
	 *
	 * @return  string
	 */
	protected function formatXml($xml) {

		$dom = new DOMDocument;
		$dom->preserveWhiteSpace = FALSE;
		$dom->formatOutput = TRUE;

		$dom->loadXML($xml);
		$xml = $dom->saveXml();
		$xml = str_replace('  ', '    ', $xml);

		return $xml;
	}



	/**
	 * Shutdown event
	 */
	public function onShutdown() {
		DBGpProxy::log("Shutting down debugger server");
		socket_shutdown($this->server->master_socket, 1);
		usleep(500);
		socket_shutdown($this->server->master_socket, 0);
		socket_close($this->server->master_socket);
	}

	/**
	 * Send command event
	 */ 
	public function onDebuggerCommand($command) {

		DBGpProxy::log("Sending command: " . $command);

		$command .= chr(0);

		@socket_write($this->client->socket, $command, strlen($command));
	}

}

