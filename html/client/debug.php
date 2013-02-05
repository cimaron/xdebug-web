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


require_once dirname(__FILE__) . '/dbgp.php';
require_once dirname(__FILE__) . '/inspector.php';
require_once dirname(__FILE__) . '/../include/dbgp.php';


/**
 * PHP Debugger
 */
class PHPDebugger {

	protected $dbgp = NULL;
	protected $inspector = NULL;

	protected $watch = array();
	protected $defines = array();
	protected $stack = array();
	protected $local = array();
	protected $globals = array();

	protected $dirty = true;

	protected $config = array(
		'timeout' => 3600,   //one hour
		'active' => true,
		'shortcut' => 'DEBUG',
		'domain' => 'http://debugger.cimaron.vm',
	);

	/**
	 * Get instance of debugger
	 *
	 * @param   array   $config   Configuration overrides
	 *
	 * @return  PHPDebugger
	 */
	public static function getInstance($config = array()) {
		static $instance;
		if (!isset($instance)) {
			$instance = new PHPDebugger($config);
		}
		return $instance;
	}

	/**
	 * Constructor
	 *
	 * @param   array   $config   Configuration overrides	 
	 */
	public function __construct($config = array()) {
	
		$this->config = array_merge($this->config, $config);
		$this->dbgp = new DBGp(DBGp::CTX_DEBUGGER);

		$this->inspector = new PHPDebuggerInspector($config);

		//throw away all stale commands
		$this->dbgp->getCommands();

		$this->dbgp->sendData(DBGpPacket::init());

		set_error_handler(array($this, 'error'), E_ALL & ~E_NOTICE);
	}

	/**
	 * Destructor
	 */
	public function __destruct() {
		$this->dbgp->sendData(DBGpPacket::close());
	}

	public function setConfig($key, $value) {
		$this->config[$key] = $value;
	}

	public function getConfig($key) {
		return $this->config[$key];
	}

	public function error($errno, $errstr, $errfile, $errline, $errcontext) {

		static $errcount = 0, $constants = array();

		//generate list of error types
		if ($errcount == 0) {
			foreach (get_defined_constants() as $key => $val) {
				if (preg_match('#^E_#', $key)) {
					$constants[$val] = $key;
				}
			}
		}

		//impose some limits for runaway processes
		if ($errcount++ < 100) {
			$err = new stdClass;

			isset($constants[$errno]) ? $err->errno = $constants[$errno] : NULL;
			$errstr ? $err->errstr = $errstr : NULL;
			$errfile ? $err->errfile = $errfile : NULL;
			$errline ? $err->errline = $errline : NULL;
			//$errcontext ? $err->errcontext = $errcontext : NULL;

			$log = new PHPDebuggerNode($err->errno, 'file');

			$file = array(
				'file' => $err->errfile,
				'line' => $err->errline,
				'name' => basename($err->errfile),
			);
			$log->value = $file;

			$log->children[] = new PHPDebuggerNode('message', 'string', $err->errstr);

			$this->dbgp->sendData(DBGpPacket::response('log', $log));
		}
	}

	/**
	 * Sends passed arguments to log
	 */
	public function log() {
		foreach (func_get_args() as $data) {
			$inspect = $this->inspector->inspect($data);
			$this->dbgp->sendData(DBGpPacket::response('log', $inspect));
		}
	}

	/**
	 * Send watch variables
	 */
	protected function _sendVars($action, $vars, $prefix = '', $prepare = true) {

		//note that we can't include $GLOBALS because it returns a recursive dependency error
		$super = array('_REQUEST', '_GET', '_POST', '_COOKIE', '_FILES', '_ENV', '_SERVER', '_SESSION');

		$newvars = array();
		foreach ($vars as $key => $val) {
			//note that checking that key = 'GLOBALS' is not 100% reliable. ideally, we'd compare against the actual object, but see comment above
			/*if ($key == 'GLOBALS' || (in_array($key, $super) && $GLOBALS[$key] === $val)) {
				$key = '<b>' . $prefix . $key . '</b>';
			} else {
				$key = $prefix . $key;
			}*/
			$key = $prefix . $key;
			$newvars[$key] = $val;
		}
		$vars = $newvars;
	
		if ($prepare) {
			$tree = $this->inspector->inspect($vars);
		} else {
			$tree = $vars;
		}

		$this->dbgp->sendData(DBGpPacket::response($action, $tree));
	}

	/**
	 * Build defines/class/functions info
	 *
	 * @return   object
	 */
	protected function buildDefines() {

		//define root nodes
		$defined = new PHPDebuggerNode('', 'hash');
		$system = new PHPDebuggerNode('PHP', 'hash');
		$extensions = array();

		//get constants
		$consts = get_defined_constants(true);
		foreach ($consts as $key => $list) {

			$list = $this->inspector->inspect($list);

			if ($key == 'user') {
				$defined->children += $list->children;
			} elseif ($key == 'Core') {
				$system->children += $list->children;
			} else {
				if (!isset($extensions[$key])) {
					$extensions[$key] = $list->children;
				}
			}
		}

		//get functions
		$funcs = get_defined_functions();
		foreach ($funcs as $type => $list) {
			foreach ($list as $func) {

				$node = $this->inspector->describeFunction($func, NULL, false);
				$ext = isset($node->extension) ? $node->extension : NULL;

				if ($type == 'user') {
					$defined->children[] = $node;
				} elseif ($ext) {
					if (!isset($extensions[$ext])) {
						$extensions[$ext] = array();
					}
					$extensions[$key][] = $node;
				} else {
					$system->children[] = $node;
				}
			}
		}

		//get classes
		$classes = get_declared_classes();
		foreach ($classes as $class) {

			$node = $this->inspector->describeClass($class, false);
			$ext = isset($node->extension) ? $node->extension : NULL;

			if (isset($node->user) && $node->user) {
				$defined->children[] = $node;
			} elseif ($ext && $ext != 'Core') {
				if (!isset($extensions[$ext])) {
					$extensions[$ext] = array();
				}
				$extensions[$key][] = $node;
			} else {
				$system->children[] = $node;
			}
		}

		$sort = array($this, '_nodeSort');

		//sort and append extensions
		uksort($extensions, 'strcasecmp');
		foreach ($extensions as $i => $extension) {
			usort($extension, $sort);
			$extnode = new PHPDebuggerNode($i, 'hash');
			$extnode->children = $extension;
			$defined->children[] = $extnode;
		}

		//sort and append system
		usort($system->children, $sort);
		$defined->children[] = $system;

		//sort defined
		usort($defined->children, $sort);

		return $defined;
	}

	public function _nodeSort($a, $b) {
		return strcasecmp($a->name, $b->name);
	}

	/**
	 * Parse stack from trace
	 */
	public function getStack(&$trace) {

		$stack = array();

		$node = new PHPDebuggerNode('', 'hash');

		foreach ($trace as $i => $tr) {

			$name = $i;
			if (isset($tr['function'])) {
				$name = $tr['function'] . '()';
			}

			if (isset($tr['class'])) {
				$name = $tr['class'] . $tr['type'] . $name;
			}

			if (isset($tr['file'])) {
				$frame = new PHPDebuggerNode($name, 'file');
				$frame->value = array(
					'file' => $tr['file'],
					'line' => $tr['line'],
					'name' => basename($tr['file']),
				);

				$node->children[] = $frame;
			}

			if (isset($tr['object'])) {
				$frame->children[] = $this->inspector->inspect($tr['object'], '$this');
			}

			if (isset($tr['args'])) {
				foreach ($tr['args'] as $k => $v) {
					$frame->children[] = $this->inspector->inspect($v, $k);
				}
			}
		}

		return $node;
	}

	/**
	 * Execute breakpoint
	 *
	 * @param   object   $THIS    Current object scope
	 * @param   array    $vars    Current scope local variables
	 * @param   array    $trace   Current scope backtrace
	 * @param   bool     $new     New breakpoint, or false if continuing same breakpoint
	 */
	public function breakpoint($THIS, $vars = array(), $trace = array(), $new = true) {

		if (!$this->config['active']) {
			return;
		}

		if ($new) {
			$this->dirty = true;
			$this->defines = $this->buildDefines();

			//prepare local
			ksort($vars);
			if ($THIS) {
				$vars = array('this' => $THIS) + $vars;
			} else {
				unset($vars['GLOBALS']);
			}
			$this->local = $vars;
			$this->send('local');

			//prepare stack
			$this->stack = $this->getStack($trace);

			$src = $trace[0]['file'];
			$line = $trace[0]['line'];

			if (file_exists($src)) {
				$text = file_get_contents($src);
			} else {
				$text = "Could not load $src";
			}
			
			$this->dbgp->sendData(DBGpPacket::response('updateSource', array('text' => utf8_encode($text), 'line' => $line)));
		//	exit;
		}

		return $this->pause();
	}

	/**
	 * Parse a command
	 *
	 * @param   string   $command   Command to parse
	 *
	 * @return  object
	 */
	protected function parseCommand($command) {
		$parts = preg_split('#\s#', trim($command));

		$parsed = new stdClass;
		$parsed->command = $parts[0];
		$parsed->data = array();

		for ($i = 1; $i < count($parts); $i++) {
			if ($parts[$i][0] == '-') {
				$key = substr($parts[$i], 1);
				$parsed->$key = $parts[$i + 1];
				$i++;
			} else {
				$parsed->data[] = base64_decode($parts[$i]);
			}
		}

		return $parsed;
	}

	/**
	 * Execute paused wait loop
	 */
	protected function pause() {

		//one hour break limit
		$start = time();	
		while (time() - $start < $this->config['timeout']) {
			set_time_limit(60);
			$queue = $this->dbgp->getCommands();

			foreach ($queue as $msg) {

				$command = $this->parseCommand($msg);

				switch ($command->command) {

					case 'halt':
						die("Terminated by debugger");

					case 'reload':
						/*echo '<script type="text/javascript">location.reload();</script>';*/
						break;

					case 'exec':
						return array($command->data[0]);
						break;

					case 'resume':
						return array();

					case 'get':
						$this->send($command->data[0]);
						break;

					case 'describe':
						list($ctx, $name, $return) = explode(' ', $command->data[0]);

						if ($ctx == 'class') {
							$node = $this->inspector->describeClass($name);
						}
						
						if ($ctx == 'function') {
							$node = $this->inspector->describeFunction($name);
						}

						$this->dbgp->sendData(DBGpPacket::response('describe', array($node, $return)));
						break;

					case 'source':
						list($src, $line) = explode(' ', $command->data[0]);
						if (file_exists($src)) {
							$text = file_get_contents($src);
						}
						if ($text === false) {
							$text = "Could not load $src";
						}
						$this->dbgp->sendData(DBGpPacket::response('updateSource', array('text' => utf8_encode($text), 'line' => $line)));
						break;
				}
			}

			usleep(100);
		}

		die("Debugger timed out");				
	}

	public function send($type) {

		switch ($type) {
			case 'local':
				$this->_sendVars('updateLocal', $this->local, '$');
				break;

			case 'watch':
				$this->_sendVars('updateWatch', $this->watch);
				break;

			case 'global':
				$this->_sendVars('updateGlobal', $GLOBALS, '$');
				break;

			case 'defines':
				$this->_sendVars('updateDefined', $this->defines, '', false);
				break;

			case 'stack':
				$this->_sendVars('updateStack', $this->stack, '', false);
				break;
		}
	}

	/**
	 * Send to watch
	 */
	public function addWatch($name, $val) {
		$this->watch[$name] = $val;
		$this->dirty = true;
		$this->send('watch');
	}

	public function diff($vars, $exclude) {
		$newvars = array();
		foreach ($vars as $key => $value) {
			if (!in_array($key, $exclude)) {
				$newvars[$key] = $value;
			}
		}
		return $newvars;
	}
	
	
}

eval('
function ' . PHPDebugger::getInstance()->getConfig('shortcut') . '() {
	return PHPDebugger::getInstance();
}
');

define('BREAKPOINT', '
	do {
		$__debug = PHPDebugger::getInstance();
		$__eval = $__debug->breakpoint(isset($this) ? $this : NULL,
			$__debug->diff(get_defined_vars(), array("__debug", "__eval", "__stmt", "__e")),
			debug_backtrace(),
			!isset($__eval));
		foreach ($__eval as $__stmt) {
			$__e = eval("try { return (" . $__stmt . "); } catch (Exception \$__e) { return \$__e; }");
			$__debug->addWatch($__stmt, $__e);
		}
	} while (count($__eval));
	unset($__debug, $__eval, $__stmt, $__e);
');

PHPDebugger::getInstance();
