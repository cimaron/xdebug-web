<?php




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
		$this->path = dirname(__FILE__) . '/ipc/' . $context . '.txt';
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

