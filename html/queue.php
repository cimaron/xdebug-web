<?php

class DbgQueue {
	public $data = array();
	public function __construct($data = NULL) {

		if (!$data) {
			$data = array();
		}

		if (is_array($data)) {
			$data = (object)array('queue' => $data);
		}

		foreach ($data->queue as $msg) {
			$this->data[] = new DbgMessage($msg->action, $msg->data);
		}
	}
}

class DbgMessage {
	public $action;
	public $data;
	public function __construct($action, $data = NULL) {
		$this->action = $action;
		$this->data = $data;
	}
}

class DbgQueueWriter {

	const MAX_TRIES = 100;

	protected $context = '';
	protected $path = '';
	protected $fh = NULL;
	protected $locked = false;
	
	public function getInstance($context) {
		static $instances = array();
		if (!isset($instances[$context])) {
			$instances[$context] = new DbgQueueWriter($context);
		}
		return $instances[$context];
	}
	
	public function __construct($context = '') {
		$this->context = $context;
		$this->path = dirname(__FILE__) . '/ipc/' . $context . '.txt';
		if (file_exists($this->path)) {
			$this->fh = fopen($this->path, "r+");
		}
	}

	public function read($empty = true) {

		if (!$this->fh) {
			return false;
		}

		if (!$this->lock()) {
			return false;
		}

		fseek($this->fh, 0);

		$str = '';
		while ($data = fread($this->fh, 65536)) {
			$str .= $data;
		}

		//get queue
		//$data = unserialize(gzuncompress($str));
		$data = unserialize($str);

		/*
		if ($data) {
			$data = new DbgQueue($data->queue);
		}
		*/

		//write empty queue
		if ($empty) {
			$this->write(new DbgQueue());
		}

		$this->unlock();

		return $data;
	}

	public function lock() {

		if ($this->locked) {
			return true;
		}

		$tries = 0;

		do {
			//repeat, sleep
			if ($locked === false) {
				usleep(250);
			}

			$this->locked = flock($this->fh, LOCK_EX);
		} while (!$this->locked && $tries < self::MAX_TRIES); 

		return $this->locked;
	}
	
	public function unlock() {
		if ($this->locked) {
		   	flock($this->fh, LOCK_UN);
		}
	}
	
	public function write($queue) {

		$locked = $this->locked;

		if (!$this->fh) {
			return false;
		}

		if (!$this->lock()) {
			return false;
		}

		rewind($this->fh);

		//$str = gzcompress(serialize($queue));
		$str = serialize($queue);

		ftruncate($this->fh, 0);
		$result = fwrite($this->fh, $str);

		if ($result) {
			fflush($this->fh);
		}

		if (!$locked) {
			$this->unlock();
		}

		return $result;
	}

	public function enqueue($action, $data = NULL) {
		$queue = $this->read(false);

		$msg = new DbgMessage($action, $data);
		$queue->data[] = $msg;
		$this->write($queue);
	}

}

