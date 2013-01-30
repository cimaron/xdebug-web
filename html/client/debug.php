<?php

require_once dirname(__FILE__) . '/../dbgp.php';

class PHPDebuggerNode {

	public $name = '';
	public $type = '';
	public $value = NULL;
	public $children = array();

	public function __construct($name = '', $type = '', $value = NULL, $children = array()) {
		$this->name = $name;
		$this->type = $type;
		$this->value = $value;
		$this->children = $children;
	}
}

/**
 * PHP Debugger
 */
class PHPDebugger {

	const COMMAND_HALT = 'halt';
	const COMMAND_RESUME = 'resume';
	const COMMAND_RELOAD = 'reload';
	const COMMAND_EXEC = 'exec';
	const COMMAND_GET = 'get';

	protected $dbgp = NULL;

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
		'pagelimit' => 40,
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
	
		//header('Access-Control-Allow-Origin: ' . $this->config['domain']);
	
		$this->dbgp = new DBGp(DBGp::CTX_DEBUGGER);

		set_error_handler(array(get_class($this), 'error'));
	}

	public function __destruct() {
		$this->dbgp->sendData('selectPane iframe');
	}

	public function setConfig($key, $value) {
		$this->config[$key] = $value;
	}

	public function getConfig($key) {
		return $this->config[$key];
	}

	public static function error($errno, $errstr, $errfile, $errline, $errcontext) {
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
			self::getInstance()->log($err);
		}
	}

	/**
	 * Sends passed arguments to log
	 */
	public function log() {
		foreach (func_get_args() as $data) {
			$this->dbgp->sendData('log ' . json_encode($this->buildTree($data)));
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
			$tree = $this->buildTree($vars);
		} else {
			$tree = $vars;
		}

		$this->dbgp->sendData($action . ' ' . json_encode($tree));
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

		$node = new PHPDebuggerNode($func, 'function');

		if (!$ref) {
			$ref = new ReflectionFunction($func);
		}

		//Build comments
		if ($ref->getDocComment()) {
			$comment = $this->_parseDocComment($ref->getDocComment());
			$node->children[] = $this->buildTree($comment, 'comments');
		}

		//Build params
		$params = new PHPDebuggerNode('params', 'array');
		foreach ($ref->getParameters() as $i => $param) {
			$p = "$" . $param->name;
			if (isset($param->isPassedByReference) && $param->isPassedByReference) {
				$p = '&' . $p;
			}
			if (isset($param->isDefaultAvailable) && $param->isDefaultAvailable) {
				$p .= ' = ' . (string)$param->getDefaultValue();
			}
			if (isset($param->isOptional) && $param->isOptional) {
				$p = '[ ' . $p . ' ]';
			}
			
			$params->children[] = new PHPDebuggerNode($i, 'string', $p);
		}
		$node->children[] = $params;

		//Get extension
		if ($ref->getExtensionName()) {
			$node->extension = $ref->getExtensionName();
			$node->children[] = new PHPDebuggerNode('extension', 'string', $node->extension);
		}

		if (!$ref->isInternal()) {
			$node->children[] = new PHPDebuggerNode('file', 'string', $ref->getFileName());
			$node->children[] = new PHPDebuggerNode('line', 'integer', $ref->getStartLine());
		}

		return $node;
	}

	/**
	 * Get Class info
	 *
	 * @param   string   $class   Class info
	 */
	public function describeClass($class) {

		$node = new PHPDebuggerNode($class, 'class');

		$ref = new ReflectionClass($class);

		if (!$ref->isUserDefined()) {
			//return false;
		}


		//Build comments
		if ($ref->getDocComment()) {
			$comment = $this->_parseDocComment($ref->getDocComment());
			$node->children[] = $this->buildTree($comment, 'comments');
		}

		//get constants
		if ($ref->getConstants()) {
			$constants = new PHPDebuggerNode('constants', 'hash');
			$node->children[] = $constants;
			foreach ($ref->getConstants() as $name => $value) {
				$constants->children[] = $this->buildTree($value, $name);
			}
		}

		if ($ref->isUserDefined()) {
			$node->user = true;
		}

		//Get extension
		if ($ref->getExtensionName()) {
			$node->extension = $ref->getExtensionName();
			$node->children[] = new PHPDebuggerNode('extension', 'string', $node->extension);
		}

		//get methods
		$methods = $ref->getMethods();
		if (count($methods)) {			
			foreach ($methods as $method) {
				$node->children[] = $this->_getFunctionInfo($method->getName(), $method);
			}
		}

		if (!$ref->isInternal()) {
			$node->children[] = new PHPDebuggerNode('file', 'string', $ref->getFileName());
			$node->children[] = new PHPDebuggerNode('line', 'integer', $ref->getStartLine());
		}

		return $node;
	}

	/**
	 * Get defined info
	 */
	protected function _getDefined() {

		//define root nodes
		$defined = new PHPDebuggerNode('', 'hash');
		$system = new PHPDebuggerNode('PHP', 'hash');
		$extensions = array();

		//get constants
		$consts = get_defined_constants(true);
		foreach ($consts as $key => $list) {

			$list = $this->buildTree($list);

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

				$node = $this->_getFunctionInfo($func);
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

			$node = $this->describeClass($class);
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

		foreach ($trace as $i => $tr) {

			$tr = (object)$tr;
			$trace[$i] = $tr;

			unset($tr->args);

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
				$index .= ':' . $tr->line;
			}
			
			$stack[$index] = $tr;
		}

		if (isset($trace[0]->file) && file_exists($trace[0]->file)) {
			$trace[0]->source = file_get_contents($trace[0]->file);
		} else {
			$trace[0]->source = '';
		}

		if (!isset($trace[0]->line)) {
			$trace[0]->line = 0;
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

		if ($new) {
			$this->dirty['local'] = true;
			$this->dirty['watch'] = true;
			$this->dirty['global'] = true;
			$this->dirty['defined'] = true;
			$this->dirty['trace'] = true;
			$this->dbgp->sendData('selectPane debug');

			$this->defines = $this->_getDefined();
		}

		if ($this->dirty['local']) {
			ksort($vars);
			if ($THIS) {
				$vars = array('this' => $THIS) + $vars;
			} else {
				unset($vars['GLOBALS']);
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
			$this->dbgp->sendData('updateTrace ' . json_encode($this->buildTree($stack)));
			$this->dbgp->sendData('updateSource ' . json_encode(array('text' => $trace[0]->source, 'line' => $trace[0]->line)));
			$this->dirty['trace'] = false;
		}

		//one hour break limit
		$start = time();	
		while (time() - $start < $this->config['timeout']) {
			set_time_limit(60);
			$queue = $this->dbgp->getCommands();

			foreach ($queue as $msg) {
				$msg = explode(' ', $msg);
				if ($msg[1]) {
					$msg[1] = base64_decode($msg[1]);
				}

				switch ($msg[0]) {

					case self::COMMAND_HALT:
						die("Terminated by debugger");

					case self::COMMAND_RELOAD:
						/*echo '<script type="text/javascript">location.reload();</script>';*/
						break;

					case self::COMMAND_EXEC:
						return array($msg[1]);
						break;

					case self::COMMAND_RESUME:
						return array();

					case self::COMMAND_GET:
						$this->sendGet($msg[1]);
						break;
				}
			}

			sleep(1);
		}

		$this->dbg->sendData(array('alert ' . base64_encode('Timed out')));
	
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
					$this->_sendVars('updateDefined', $this->defines, '', false);
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
	
	
	protected function is_hash($array) {
		foreach ($array as $key => $value) {
			if (!is_int($key)) {
				return true;
			}
		}
		return false;
	}

	/**
	 *
	 */
	protected function buildTree(&$data, $name = '', $path = array()) {
		
		$def = new PHPDebuggerNode($name, gettype($data));		
		if ($def->type == 'array' && $this->is_hash($data)) {
			$def->type = 'hash';
		}

		$is_global = false;
		//$is_global = $data === $GLOBALS;
		if ($name === 'GLOBALS' || $name == '$GLOBALS') {
			$is_global = true;
		}

		//recursion detected
		if ($is_global && in_array('$GLOBALS$', $path)) {
			$def->type = "string";
			$def->value = "* RECURSION @ " . array_search('$GLOBALS$', $path, true) . " *";
			return $def;
		}

		if (in_array($data, $path, true)) {
			$def->type = "string";
			$def->value = "* RECURSION @ " . array_search($data, $path, true) . " *";
			return $def;
		}

		if ($is_global) {
			$path[] = '$GLOBALS$';	
		} else {
			$path[] = &$data;
		}

		switch ($def->type) {

			case 'NULL':
			case 'boolean':
			case 'integer':
			case 'double':
				$def->value = $data;
				break;

			case 'string':
				if (strlen($data) <= 512) {
					$def->value = $data;
				} else {
					$def->value = substr($data, 0, 512) . '&hellip; (' . (strlen($data) - 512) . ' more)';
				}
				break;

			case 'unknown type':
			case 'resource':
				$def->value = str_replace('Resource id ', '', (string)$data);
				$def->res_type = get_resource_type($data);
				break;

			case 'hash':
				$def->children = array();
				foreach ($data as $k => $v) {
					$def->children[] = $this->buildTree($v, $k, $path);
				}
				break;

			case 'array':
				if (count($path) > 1) {
					$def->pagelimit = $this->config['pagelimit'];
					
				} else {
					$def->pagelimit = count($data);
				}
			
				$def->pagestart = 0;
				$def->total = count($data);

				$def->children = array();
				$i = 0;

				foreach ($data as $k => $v) {

					if ($i++ > $def->pagelimit) {
						break;
					}

					$def->children[] = $this->buildTree($v, $k, $path);
				}

				break;

			case 'object':
				$def->classname = get_class($data);

				$found = array();

				$ref = new ReflectionClass($data);
				$props = $ref->getProperties();
				foreach ($props as $prop) {
					
					$k = $prop->getName();

					if ($prop->isPrivate() || $prop->isProtected()) {

						$prop->setAccessible(true);

						$v = $prop->getValue($data);
						$node = $this->buildTree($v, $k, $path);
						$node->access = $prop->isProtected() ? 'protected' : 'private';

						$prop->setAccessible(false);

					} elseif ($prop->isPublic()) {
						$found[] = $k;
						$node = $this->buildTree($data->$k, $k, $path);
					}

					$def->children[] = $node;
				}

				//gather non-class variables
				foreach (get_object_vars($data) as $k => $v) {
					if (!in_array($k, $found, true)) {
						$node = $this->buildTree($v, $k, $path);
						$def->children[] = $node;
					}
				}

				break;
		}

		return $def;
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
		$__eval = $__debug->pauseScript(isset($this) ? $this : NULL,
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
