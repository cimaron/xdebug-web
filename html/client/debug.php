<?php

require_once dirname(__FILE__) . '/../queue.php';

/**
 * PHP Debugger
 */
class PHPDebugger {

	const COMMAND_HALT = 'halt';
	const COMMAND_RESUME = 'resume';
	const COMMAND_RELOAD = 'reload';
	const COMMAND_EXEC = 'exec';
	const COMMAND_GET = 'get';

	protected $in = NULL;
	protected $out = NULL;

	protected $watch = array();
	protected $defines = array();
	
	protected $dirty = array(
		'local' => true,
		'watch' => false,
		'global' => true,
		'trace' => true,
	);

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
	
		header('Access-Control-Allow-Origin: ' . $this->config['domain']);
	
		$this->in = DbgQueueWriter::getInstance('in');
		$this->out = DbgQueueWriter::getInstance('out');
	}

	public function __destruct() {
		$this->in->enqueue('selectPane', 'iframe');
	}

	public function setConfig($key, $value) {
		$this->config[$key] = $value;
	}
	
	public function getConfig($key) {
		return $this->config[$key];
	}

	/**
	 * Sends passed arguments to log
	 */
	public function log() {
		foreach (func_get_args() as $data) {
			$this->in->enqueue('log', $data);
		}
	}

	/**
	 * Send watch variables
	 */
	protected function _sendVars($action, $vars, $prefix = '') {

		//note that we can't include $GLOBALS because it returns a recursive dependency error
		$super = array('_REQUEST', '_GET', '_POST', '_COOKIE', '_FILES', '_ENV', '_SERVER', '_SESSION');

		$newvars = array();
		foreach ($vars as $key => $val) {
			//note that checking that key = 'GLOBALS' is not 100% reliable. ideally, we'd compare against the actual object, but see comment above
			if ($key == 'GLOBALS' || (in_array($key, $super) && $GLOBALS[$key] === $val)) {
				$key = '<b>' . $prefix . $key . '</b>';
			} else {
				$key = $prefix . $key;
			}
			$newvars[$key] = $val;
		}
		$vars = $newvars;
	
		$this->in->enqueue($action, $vars);
	}

	/**
	 * Parse DocComment style comment
	 *
	 * @param   string   $comment   Raw comment
	 *
	 * @return  mixed
	 */
	protected function _parseDocComment($comment) {

		$clean = preg_match('#\s*/\*+(.*)\*/#s', $comment, $matches);
		$clean = $matches[1];

		$clean =  trim(preg_replace('/\r?\n *\* */', ' ', $comment));

		preg_match_all('/@([a-z]+)\s+(.*?)\s*(?=$|@[a-z]+\s)/s', $clean, $matches);

		if (count($matches[1]) == count($matches[2]) && count($matches[1]) > 0) {
			$info = (object)array_combine($matches[1], $matches[2]);
			$info->raw = $comment;
		} else {
			$info = $comment;
		}

		return $info;
	}

	/**
	 * @param   string   $func   Function name
	 */
	protected function _getFunctionInfo($func, $ref = NULL) {
		
		if (!$ref) {
			$ref = new ReflectionFunction($func);
		}

		$info = new stdClass;
		$info->__PHP_Type = 'function';
		if ($ref->getDocComment()) {
			$info->comments = $this->_parseDocComment($ref->getDocComment());
		}

		$info->name = $func;
		$info->params = array();
		foreach ($ref->getParameters() as $param) {
			$p = "$" . $param->name;
			if ($param->isPassedByReference) {
				$p = '&' . $p;
			}
			if ($param->isDefaultAvailable) {
				$p .= ' = ' . (string)$param->getDefaultValue();
			}
			if ($p->isOptional) {
				$p = '[ ' . $p . ' ]';
			}
			$info->params[] = $p;
		}
		
		if ($ref->getExtensionName()) {
			$info->extension = $ref->getExtensionName();
		}

		if (!$ref->isInternal()) {
			$info->file = $ref->getFileName();
			$info->line = $ref->getStartLine();
		}
		
		return $info;
	}

	/**
	 * Get Class info
	 *
	 * @param   string   $class   Class info
	 */
	public function describeClass($class) {

		$info = new stdClass;
		$info->__PHP_Type = 'class';
		$ref = new ReflectionClass($class);
		
		if (!$ref->isUserDefined()) {
			//return false;
		}
		
		if ($ref->getDocComment()) {
			$info->comments = $this->_parseDocComment($ref->getDocComment());
		}

		$info->name = $class;

		if ($ref->getConstants()) {
			$info->constants = array();
			foreach ($ref->getConstants() as $name => $value) {
				$info->constants[$name] = $value;
			}
		}

		if ($ref->isUserDefined()) {
			$info->user = true;
		}
		if ($ref->getExtensionName()) {
			$info->extension = $ref->getExtensionName();
		}

		$methods = $ref->getMethods();

		if (count($methods)) {
			$info->methods = array();
			foreach ($methods as $method) {
				$info->methods[$method->getName()] = $this->_getFunctionInfo($method->getName(), $method);
			}
		}

		if ($info->user) {
			$info->file = $ref->getFileName();
			$info->line = $ref->getStartLine();
		}

		return $info;
	}

	/**
	 * Get defined info
	 */
	protected function _getDefined() {
		$defined = array();
		$system = array();
		$extensions = array();

		$consts = get_defined_constants(true);
		foreach ($consts as $key => $list) {
			if ($key == 'user') {
				$defined = $list;
			} elseif ($key == 'Core') {
				$system = $list;
			} else {
				$extensions['<b>' . $key . '</b>'] = $list;
			}
		}

		$funcs = get_defined_functions();
		foreach ($funcs as $type => $list) {
			foreach ($list as $func) {
				$info = $this->_getFunctionInfo($func);
				if ($type == 'user') {
					$defined[$func] = $info;
				} elseif ($info->extension) {
					$extensions['<b>' . $info->extension . '</b>'][$func] = $info;
				} else {
					$system[$func] = $info;
				}
			}
		}

		$classes = get_declared_classes();
		foreach ($classes as $class) {
			$info = $this->describeClass($class);
			if ($info->user) {
				$defined[$class] = $info;
			} elseif ($info->extension && $info->extension != 'Core') {
				$extensions['<b>' . $info->extension . '</b>'][$class] = $info;
			} else {
				$system[$class] = $info;			
			}
		}

		uksort($defined, 'strcasecmp');
		uksort($extensions, 'strcasecmp');
		uksort($system, 'strcasecmp');

		foreach ($extensions as &$extension) {
			uksort($extension, 'strcasecmp');
		}

		//$system = array_merge($extensions, $system);
		$defined = array_merge(array('<b>SYSTEM</b>' => $system), $extensions, $defined);
		
		return $defined;
	}

	/**
	 * Parse stack from trace
	 */
	public function getStack(&$trace) {

		$stack = array();

		foreach ($trace as $i => $tr) {

			$trace[$i] = (object)$tr;
			$tr = (object)$tr;

			$index = $i;

			/*
			if (isset($tr->file) && file_exists($tr->file)) {
				$tr->source = file_get_contents($tr->file);
			}
			*/

			if (isset($tr->file)) {
				$index = basename($tr->file);
			}

			if (isset($tr->line)) {
				$index .= ' ' . $tr->line;
				unset($tr->line);
			}
			
			$stack[$index] = $tr;
		}

		if (isset($trace[0]->file) && file_exists($trace[0]->file)) {
			$trace[0]->source = file_get_contents($trace[0]->file);
		}
		
		return $stack;
	}

	/**
	 * Break script
	 */
	public function pauseScript($THIS, $vars = array(), $trace = array(), $new = true) {

		if (!$this->config['active']) {
			return;
		}

		$this->in->write(new DbgQueue());
		$this->out->write(new DbgQueue());

		if ($new) {
			$this->dirty['local'] = true;
			$this->dirty['watch'] = true;
			$this->dirty['global'] = true;
			$this->dirty['defined'] = true;
			$this->dirty['trace'] = true;
			$this->in->enqueue('selectPane', 'debug');

			$this->defines = $this->_getDefined();
		}
	
		if ($this->dirty['local']) {
			ksort($vars);
			if ($THIS) {
				$vars = array('this' => $THIS) + $vars;
			}
			$this->_sendVars('updateLocal', $vars, '$');
			$this->dirty['local'] = false;
		}

		if ($this->dirty['watch']) {
			$this->_sendVars('updateWatch', $this->watch);
			$this->dirty['watch'] = false;
		}

		if ($this->dirty['trace']) {
			$stack = $this->getStack($trace);
			$this->in->enqueue('updateTrace', $stack);
			$this->in->enqueue('updateSource', (object)array('text' => $trace[0]->source, 'line' => $trace[0]->line));
			$this->dirty['trace'] = false;
		}

		//one hour break limit
		$start = time();	
		while (time() - $start < $this->config['timeout']) {
			set_time_limit(60);
			$queue = $this->out->read();

			foreach ($queue->data as $msg) {

				switch ($msg->action) {

					case self::COMMAND_HALT:
						die("Terminated by debugger");

					case self::COMMAND_RELOAD:
						/*echo '<script type="text/javascript">location.reload();</script>';*/
						break;

					case self::COMMAND_EXEC:
						return array($msg->data);
						break;

					case self::COMMAND_RESUME:
						return array();
						
					case self::COMMAND_GET:
						$this->sendGet($msg->data);
						break;
				}
			}

			sleep(1);
		}

		DbgQueueWriter::getInstance('in')->enqueue('alert', 'Timed out');
	
		die("Debugger timed out");		
	}

	public function sendGet($type) {
		switch ($type) {

			case 'watch':
				if ($this->dirty['watch']) {
					$this->_sendVars('updateWatch', $this->watch);
					$this->dirty['watch'] = false;
				}
				break;

			case 'global':
				if ($this->dirty['global']) {
					$this->_sendVars('updateGlobal', $GLOBALS, '$');
					$this->dirty['global'] = false;
				}
				break;

			case 'defines':
				if ($this->dirty['defined']) {
					$this->_sendVars('updateDefined', $this->defines);
					$this->dirty['defined'] = false;
				}
				break;
		}
	}

	/**
	 * Send to watch
	 */
	public function addWatch($name, $val) {
		$this->watch[$name] = $val;
		$this->dirty['local'] = true;
		$this->dirty['watch'] = true;
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
		$__eval = $__debug->pauseScript($this,
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

