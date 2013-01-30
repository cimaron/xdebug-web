<?php

include dirname(__FILE__) . '/io.php';

class DBGp {

	const CTX_DEBUGGER = 0x1;
	const CTX_IDE = 0x2;

	protected $reader = NULL;
	protected $writer = NULL;

	protected $format_data = NULL;
	protected $format_cmd = NULL;


	public function __construct($context) {

		$this->format_data = new DBGpDataPacket();
		$this->format_cmd = new DBGpCmdPacket();
			
		if ($context == self::CTX_DEBUGGER) {
			$this->reader = IOWriter::getInstance('in');
			$this->writer = IOWriter::getInstance('out');			
		} elseif ($context == self::CTX_IDE) {
			$this->reader = IOWriter::getInstance('out');
			$this->writer = IOWriter::getInstance('in');			
		}
	}

	public function sendCommand($command) {
		$commands = (array)$command;
		$formatted = $this->format_cmd->prepare($commands);
		$this->writer->write($formatted);
	}

	public function getCommands() {
		$commands = $this->reader->read();
		$processed = $this->format_cmd->process($commands);
		return $processed;
	}

	public function sendData($data) {
		$data = (array)$data;
		$formatted = $this->format_data->prepare($data);
		$this->writer->write($formatted);
	}
	
	public function getData() {
		$data = $this->reader->read();
		$processed = $this->format_data->process($data);
		return $processed;	
	}
}



class DBGpDataPacket {

	/**
	 * @param   string   $data   Encoded list of data
	 *
	 * @return  array
	 */
	public function process($data) {
		$null = chr(0);
		$len = 0;
		$packet = '';

		$prepared = array();
		while (strlen($data)) {

			$n1 = strpos($data, $null);
			$n2 = strpos($data, $null, $n1 + 1);
			$len = (int)substr($data, 0, $n1);

			if ($n2 - $n1 - 1 == $len) {
				$packet = substr($data, $n1 + 1, $len);
				$prepared[] = base64_decode($packet);
			}

			$data = substr($data, $n2 + 1);
		}

		return $prepared;
	}

	/**
	 * @param   array   $data   List of commands
	 *
	 * @return  string
	 */
	public function prepare($data) {
		$null = chr(0);
		$processed = '';
		foreach ($data as $packet) {
			$packet = base64_encode($packet);
			$processed .= (string)strlen($packet);
			$processed .= $null;
			$processed .= $packet;
			$processed .= $null;
		}
		return $processed;
	}
}

class DBGpCmdPacket {

	/**
	 * @param   string   $data   Encoded list of commands
	 *
	 * @return  array
	 */
	public function process($data) {
		$prepared = array();

		foreach (explode("\n", $data) as $res) {
			if ($res) {
				$prepared[] = $res;
			}
		}
		return $prepared;
	}

	/**
	 * @param   array   $data   List of commands
	 *
	 * @return  string
	 */
	public function prepare($data) {
		$processed = '';

		//foreach unecessary, except to leave space for extra processing per-item if needed
		foreach ($data as $cmd) {
			$processed .= $cmd . "\n";
		}

		return $processed;
	}
}
