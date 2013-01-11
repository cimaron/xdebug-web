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

	protected $in = NULL;
	protected $out = NULL;

	protected $watch = array();
	protected $dirty = array(
		'local' => true,
		'watch' => false,
		'global' => true,
		'trace' => true,
	);

	protected $config = array(
		'timeout' => 3600,   //one hour
		'active' => true,
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
	
		$this->in = DbgQueueWriter::getInstance('in');
		$this->out = DbgQueueWriter::getInstance('out');
	}

	public function __destruct() {
		$this->in->enqueue('selectPane', 'iframe');
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
	protected function _getFunctionInfo($func) {
		$ref = new ReflectionFunction($func);

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
		
		$info->file = $ref->getFileName();
		$info->line = $ref->getStartLine();
		
		return $info;
	}

	/**
	 * @param   string   $class   Class info
	 */
	protected function _getClassInfo($class) {

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
				$extensions[$key] = $list;
			}
		}

		$funcs = get_defined_functions();
		foreach ($funcs as $type => $list) {
			foreach ($list as $func) {
				$info = $this->_getFunctionInfo($func);
				if ($type == 'user') {
					$defined[$func] = $info;
				} elseif ($info->extension) {
					$extensions[$info->extension][$func] = $info;
				} else {
					$system[$func] = $info;
				}
			}
		}

		$classes = get_declared_classes();
		foreach ($classes as $class) {
			$info = $this->_getClassInfo($class);
			if ($info->user) {
				$defined[$class] = $info;
			} elseif ($info->extension && $info->extension != 'Core') {
				$extensions[$info->extension][$class] = $info;
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

		$system = array_merge($extensions, $system);
		$defined = array_merge(array('<b>SYSTEM</b>' => $system), $defined);
		
		return $defined;
	}

	/**
	 * Break script
	 */
	public function pauseScript($vars = array(), $trace = array(), $new = true) {

		if (!$this->config['active']) {
			return;
		}

		$this->in->write(new DbgQueue());
	
		if ($new) {
			$this->dirty['local'] = true;
			$this->dirty['watch'] = true;
			$this->dirty['global'] = true;
			$this->dirty['defined'] = true;
			$this->dirty['trace'] = true;
			$this->in->enqueue('selectPane', 'debug');
		}
	
		if ($this->dirty['local']) {
			ksort($vars);
			$this->_sendVars('updateLocal', $vars, '$');
			$this->dirty['local'] = false;
		}
		
		if ($this->dirty['watch']) {
			$this->_sendVars('updateWatch', $this->watch);
			$this->dirty['watch'] = false;
		}

		if ($this->dirty['global']) {
			$this->_sendVars('updateGlobal', $GLOBALS, '$');
			$this->dirty['global'] = false;
		}

		if ($this->dirty['defined']) {
			$this->_sendVars('updateDefined', $this->_getDefined());
			$this->dirty['defined'] = false;
		}

		foreach ($trace as $i => $tr) {
			$tr = (object)$tr;
			if ($tr->file && file_exists($tr->file)) {
				$tr->source = file_get_contents($tr->file);
			}
			$trace[$i] = $tr;
		}

		if ($this->dirty['trace']) {
			$this->in->enqueue('updateTrace', $trace);
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
				}
			}

			sleep(1);
		}
		
		DbgQueueWriter::getInstance('in')->enqueue('alert', 'Timed out');
	
		die("Debugger timed out");		
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

define(PAUSE, '
	do {
		$__debug = PHPDebugger::getInstance();
		$__eval = $__debug->pauseScript(
			$__debug->diff(get_defined_vars(), array("__debug", "__eval", "__stmt", "__e")),
			debug_backtrace(),
			!isset($__eval));
		foreach ($__eval as $__stmt) {
			try {
				$__e = eval("return (" . $__stmt . ");");
			} catch (Exception $__e) {				
			}
			$__debug->addWatch($__stmt, $__e);
		}
	} while (count($__eval));
	unset($__debug, $__eval, $__stmt, $__e);
');

