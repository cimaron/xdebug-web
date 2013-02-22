<?php

/**
 * Command class
 */
class Command {

	public $name = "";
	public $options = array();
	public $data = NULL;
	public $client = NULL;
	
	protected $host = NULL;
	protected $port = NULL;
	protected $sh = NULL;
	protected $error = array();

	/**
	 * Constructor
	 *
	 * @param   string   $name      Command name
	 * @param   array    $options   Command options
	 * @param   mixed    $data      Command data
	 * @param   string   $host      Server host
	 * @param   string   $port      Server port
	 */
	public function __construct($name, $options = array(), $data = NULL, $host = 'localhost', $port = 9001) {

		$this->name = $name;
		$this->options = $options;
		$this->data = $data;
		
		$this->host = $host;
		$this->port = $port;
		$this->client = gethostbyaddr($_SERVER['REMOTE_ADDR']);
	}

	/**
	 * String representation
	 */
	public function __toString() {

		$str = json_encode($this) . chr(0);
		
		return $str;
	}

	/**
	 * Get error
	 */
	public function getError() {
		
		if (empty($this->error)) {
			return false;
		}

		$response = array(
			'name' => 'error',
			'data' => $this->error,
		);

		return $response;
	}

	/**
	 * Open a connection if not already open
	 */
	public function open() {

		if ($this->sh === NULL) {

			$this->sh = @fsockopen($this->host, $this->port, $errno, $errstr, 120);

			if (!$this->sh) {
				$this->error = array(
					'code' => $errno,
					'message' => $errstr,
				);
				return false;
			}

			$this->error = array();
		}
		
		return true;
	}

	/**
	 * Close connection
	 */
	public function close() {

		if ($this->sh !== NULL) {
			fclose($this->sh);
		}

		$this->sh = NULL;
		$this->error = array();
	}

	/**
	 * Send command and return data
	 */
	public function send() {

		if (!$this->open()) {
			$error = json_encode($this->getError());
			return $error;
		}

		$send = (string)$this;
		fwrite($this->sh, $send, strlen($send));

		$buffer = "";
		while (!feof($this->sh)) {
			$buffer .= fgets($this->sh, 128);
		}

		$this->close();

		if (!$buffer) {

			$this->error = array(
				'code' => 500,
				'message' => "Connection lost, or unknown error",
			);

			$error = json_encode($this->getError());
			return $error;
		}

		return $buffer;
	}

}



/**
 * Build DBGp command
 */
function build_dbgp_command($command, $transaction_id, $args, $data) {

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




