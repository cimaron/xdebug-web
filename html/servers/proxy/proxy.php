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

require_once dirname(__FILE__) . '/listener.php';
require_once dirname(__FILE__) . '/debugger.php';
require_once dirname(__FILE__) . '/ide.php';


/**
 * Acts as a proxy server for DBGp Debugger
 */
class DBGpProxy {

	public $debugger = NULL;
	public $ide = NULL;

	protected $listeners = array();

	/**
	 * Write log message
	 *
	 * @param   string   $message   Message
	 */
	public static function log($message = '') {
		static $fh;

		if (is_null($fh)) {
			$fh = fopen(dirname(__FILE__) . '/log.txt', "a");
			ftruncate($fh, 0);
		}

		if (strlen($message) > 100) {
			$message = substr($message, 0, 97) . '...';
		}
		$message = str_replace("\n", "\\n", $message);

		fwrite($fh, sprintf("[%s] - %s\n", date('Y-m-d H:i:s'), $message));

		echo $message . "\n";
	}

	/**
	 *
	 */
	public function __construct($host = 'localhost', $port = 9000) {
		$this->debugger = new DebuggerServer($this, $host, $port);
		$this->ide = new IdeServer($this);		
	}

	/**
	 * Run server
	 */
	public function run() {
		while (true) {
			$this->debugger->run_once();
			$this->ide->run_once();
		}
	}

	/**
	 * Add a new event listener
	 *
	 * @param   string               $name       Event name
	 * @param   mixed                $callback   String or array of callback function
	 * @param   SocketServerClient   $client     Socket client
	 * @param   array                $bound      Optional list of bound arguments appended to called arguments
	 */
	public function addEventListener($name, $callback, $client = NULL, $bound = array()) {

		$listener = new Listener($name, $callback, $client, $bound);

		$this->listeners[] = $listener;

		return $listener;
	}

	/**
	 * Remove event listener(s)
	 *
	 * @param   string               $name       Event name
	 * @param   mixed                $callback   String or array of callback function
	 * @param   SocketServerClient   $client     Socket client
	 */
	public function removeEventListener($name, $callback, $client = NULL) {

		foreach ($this->listeners as $i => $listener) {
			if ((!$name || $listener->name == $name) && (!$callback || $listener->callback == $callback) && (!$client || $listener->client == $client)) {
				unset($this->listeners[$i]);
			}
		}
	}

	/**
	 * Trigger event
	 *
	 * @param   string   $name   Event name
	 * @param   array    $args   Arguments
	 *
	 * @return  array
	 */
	public function triggerEvent($name, $args = array()) {

		//self::log("Triggering: $name");

		$results = array();
		foreach ($this->listeners as $i => $listener) {
			if ($listener->name == $name) {
				//self::log(sprintf("Found listener: %s(%s,%s)", $listener->callback[1], $listener->client ? $this->ide->clientString($listener->client) : "", json_encode($listener->bound)));
				$results[] = call_user_func_array($listener->callback, array_merge($args, $listener->bound));
			}
		}

		return $results;
	}

}





