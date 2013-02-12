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

include dirname(__FILE__) . '/io.php';

class DBGp {

	const CTX_DEBUGGER = 1;
	const CTX_IDE      = 2;

	protected $reader = NULL;
	protected $writer = NULL;
	protected $control = NULL;

	protected $format_data = NULL;
	protected $format_cmd = NULL;

	public function __construct($context, $connection) {

		$this->format_data = new DBGpDataPacket();
		$this->format_cmd = new DBGpCmdPacket();

		if ($context == self::CTX_DEBUGGER) {
			$this->reader = IOWriter::getInstance($connection . '.in');
			$this->writer = IOWriter::getInstance($connection . '.out');			
		} elseif ($context == self::CTX_IDE) {
			$this->reader = IOWriter::getInstance($connection . '.out');
			$this->writer = IOWriter::getInstance($connection . '.in');			
		}

		$this->control = IOWriter::getInstance('status');
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
				$prepared[] = $packet;
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

		foreach (explode(chr(0), $data) as $res) {
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
			$processed .= $cmd . chr(0);
		}

		return $processed;
	}
}
