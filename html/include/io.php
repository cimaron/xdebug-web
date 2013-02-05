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


class IOWriter {

	const MAX_TRIES = 100;

	protected $context = '';
	protected $path = '';
	protected $fh = NULL;
	protected $locked = false;

	public function getInstance($context) {
		static $instances = array();
		if (!isset($instances[$context])) {
			$instances[$context] = new IOWriter($context);
		}
		return $instances[$context];
	}
	
	public function __construct($context = '') {
		$this->context = $context;
		$this->path = dirname(__FILE__) . '/../ipc/' . $context . '.txt';
		if (file_exists($this->path)) {
			$this->fh = fopen($this->path, "r+");
		}
	}

	public function read() {

		if (!$this->fh) {
			return false;
		}

		if (!$this->lock()) {
			return false;
		}

		fseek($this->fh, 0);

		$buffer = '';
		while ($data = fread($this->fh, 65536)) {
			$buffer .= $data;
		}

		ftruncate($this->fh, 0);

		$this->unlock();

		return $buffer;
	}


	public function write($str) {

		$locked = $this->locked;

		if (!$this->fh) {
			return false;
		}

		if (!$this->lock()) {
			return false;
		}

		fseek($this->fh, 0, SEEK_END);

		$result = fwrite($this->fh, $str);

		if ($result) {
			fflush($this->fh);
		}

		if (!$locked) {
			$this->unlock();
		}

		return $result;
	}

	/**
	 * Lock file for writing
	 */
	public function lock() {

		if ($this->locked) {
			return true;
		}

		$tries = 0;

		do {
			//repeat, sleep
			if ($this->locked === false) {
				usleep(250);
			}

			$this->locked = flock($this->fh, LOCK_EX);
		} while (!$this->locked && $tries < self::MAX_TRIES); 

		return $this->locked;
	}

	/**
	 * Lock file for reading
	 */
	public function unlock() {
		if ($this->locked) {
		   	flock($this->fh, LOCK_UN);
		}
	}

}

