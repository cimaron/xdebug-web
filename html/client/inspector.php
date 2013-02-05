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


class PHPDebuggerInspector {

	protected $config = array(
		'pagelimit' => 40,
	);

	/**
	 * Constructor
	 *
	 * @param   array   $config   Configuration overrides	 
	 */
	public function __construct($config = array()) {
		$this->config = array_merge($this->config, $config);
	}


	public function inspect(&$data, $name = '', $path = array()) {
		return $this->buildTree($data, $name, $path);
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
			$def->type = "";
			$def->value = "* RECURSION @ " . array_search('$GLOBALS$', $path, true) . " *";
			return $def;
		}

		if (in_array($data, $path, true)) {
			$def->type = "";
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

				if (is_nan($data)) {
					$data = "NaN";
				} elseif (is_infinite($data)) {
					$data = "Infinity";
				}

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

	/**
	 * Build function description tree
	 *
	 * @param   string   $func   Function name
	 * @param   object   $ref    Reflection instance or NULL
	 * @param   boool    $details   Detailed info
	 */
	public function describeFunction($func, $ref = NULL, $details = true) {

		$node = new PHPDebuggerNode($func, 'function');

		if (!$ref) {
			$ref = new ReflectionFunction($func);
		}

		//Get extension
		if ($ref->getExtensionName()) {
			$node->extension = $ref->getExtensionName();
			$node->children[] = new PHPDebuggerNode('extension', 'string', $node->extension);
		}

		if ($details) {
	
			//Build comments
			if ($ref->getDocComment()) {
				$comment = $this->_parseDocComment($ref->getDocComment());
	
				foreach ($comment as $i => $part) {
					$comment[$i] = $this->buildTree($part->value, $part->key);
					$comment[$i]->type = 'comment';
				}

				$node->children = array_merge($comment, $node->children);
			}
	
			if (!$ref->isInternal()) {
				$file = array(
					'file' => $ref->getFileName(),
					'line' => $ref->getStartLine(),
					'name' => basename($ref->getFileName()),
				);
				array_unshift($node->children, new PHPDebuggerNode('file', 'file', $file));
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

			if (!empty($params->children)) {
				$node->children[] = $params;
			}

		}

		return $node;
	}


	/**
	 * Build class description tree
	 *
	 * @param   string   $class     Class info
	 * @param   boool    $details   Detailed info
	 */
	public function describeClass($class, $details = true) {

		$node = new PHPDebuggerNode($class, 'class');

		$ref = new ReflectionClass($class);

		if ($ref->isUserDefined()) {
			$node->user = true;
		}

		//Get extension
		if ($ref->getExtensionName()) {
			$node->extension = $ref->getExtensionName();
			$node->children[] = new PHPDebuggerNode('extension', 'string', $node->extension);
		}

		if ($details) {

			//Build comments
			if ($ref->getDocComment()) {
				$comment = $this->_parseDocComment($ref->getDocComment());
	
				foreach ($comment as $i => $part) {
					$comment[$i] = $this->buildTree($part->value, $part->key);
					$comment[$i]->type = 'comment';
				}

				$node->children = array_merge($comment, $node->children);
			}
	
			if (!$ref->isInternal()) {
				$file = array(
					'file' => $ref->getFileName(),
					'line' => $ref->getStartLine(),
					'name' => basename($ref->getFileName()),
				);
				array_unshift($node->children, new PHPDebuggerNode('file', 'file', $file));
			}

			//get constants
			if ($ref->getConstants()) {
				$constants = new PHPDebuggerNode('constants', 'hash');
				$node->children[] = $constants;
				foreach ($ref->getConstants() as $name => $value) {
					$constants->children[] = $this->buildTree($value, $name);
				}
			}

			//get methods
			$methods = $ref->getMethods();
			if (count($methods)) {			
				foreach ($methods as $method) {
					//$node->chilren[] = new PHPDebuggerNode($method->getName(), 'string', $method->getName());
					$node->children[] = $this->describeFunction($method->getName(), $method);
				}
			}

		}

		return $node;
	}

	/**
	 * Parse DocComment style comment
	 *
	 * @param   string   $comment   Raw comment
	 *
	 * @return  mixed
	 */
	protected function _parseDocComment($comment) {
	
		$info = array();

		$comment = preg_replace('#^(\s*)/\*+#', '$1*', $comment);
		$comment = preg_replace('#\*+/\s*$#', '', $comment);

		preg_match_all('#^\s*\*\s*(@.+)#m', $comment, $matches, PREG_SET_ORDER);

		foreach ($matches as $param) {

			if (preg_match('#@([^\s]+)\s+(.*)#', $param[1], $parts)) {

				$name = $parts[1];
				$value = $parts[2];

				$info[] = (object)array('key' => $name, 'value' => $value);
			}

			$comment = str_replace($param, '', $comment);
		}

		$comment = trim(preg_replace('#^\s*\*[^\S\n]*#m', '', $comment));

		if ($comment) {
			array_unshift($info, (object)array('key' => 'description', 'value' => $comment));
		}

		return $info;
	}

}


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

