<?php

include dirname(__FILE__) . '/../../include/io.php';

/**
 * Acts as a proxy server for DBGp Debugger
 */
class DBGpProxy {

	protected static $connected = NULL;
	protected static $queue = array();

	protected $server = NULL;
	protected $client = NULL;

	protected $id = 0;
	protected $reader = NULL;
	protected $writer = NULL;
	protected $control = NULL;

	protected $buffer = '';

	protected static $prompt = '';
	protected static $prompt_buffer = '';
	protected static $tid = 0;

	protected static $help = array(
		'status' => array(),
		'feature_get' => array(),
		'feature_set' => array(),
		'run' => array(),
		'step_into' => array(),
		'step_over' => array(),
		'step_out' => array(),
		'stop' => array(),
		'detach' => array(),
		'breakpoint_set' => array(),
		'breakpoint_get' => array(),
		'breakpoint_update' => array(),
		'breakpoint_remove' => array(),
		'breakpoint_list' => array(),
		'stack_depth' => array(),
		'stack_get' => array(),
		'context_names' => array(),
		'context_get' => array(),
		'typemap_get' => array(),
		'property_get' => array(),
		'property_set' => array(),
		'property_value' => array(),
		'source' => array(),
		'stdout' => array(),
		'stderr' => array(),
		
	);

	/**
	 * Log action + message to screen
	 *
	 * @param   string   $action    Action
	 * @param   string   $message   Message
	 */
	protected static function log($action, $message = '') {

		preg_match_all("/rows.([0-9]+);.columns.([0-9]+);/", strtolower(exec('stty -a | grep columns')), $output);
		if (sizeof($output) == 3) {
			$width = $output[2][0];
		} else {
			$width = 80;
		}

		$padding = strlen($action) + 2;

		$message = explode("\n", $message);
		for ($i = 0; $i < count($message); $i++) {

			$line = str_split($message[$i], $width - $padding);

			for ($j = 0; $j < count($line); $j++) {
				if ($i == 0 && $j == 0) {
					echo "\033[10L\033[1F\n";
					echo $action . ": " . $line[$j] . "\n";
				} else {
					echo str_repeat(' ', $padding) . $line[$j] . "\n";
				}
			}
		}

		//echo str_repeat("-", $width) . "\n";	
		echo "\n\n";

		self::drawPrompt();
	}

	/**
	 * New client connection
	 *
	 * @param   object     $server   The server class
	 * @param   resource   $client   The connection socket
	 * @param   string     $input    Anything sent, if anything was sent
	 */
	public static function connect(&$server, &$client, $input) {

		$proxy = new DBGpProxy($server, $client);
		$server->hook("INPUT", array($proxy, "processSocketInput"));

		if (self::$connected) {
			self::$queue[] = $proxy;
			self::log("queued", $proxy->id . '@' . $proxy->client->lookup_hostname());
			return;
		}

		self::$connected = $proxy;
		$proxy->control->write("connect $proxy->id\n", false);
		self::log("connected", $proxy->id . '@' . $proxy->client->lookup_hostname());

		self::redrawPrompt();
	}

	/**
	 * Client disconnected
	 *
	 * @param   object     $server   The server class
	 * @param   resource   $client   The connection socket
	 * @param   string     $input    Anything sent, if anything was sent
	 */
	public static function disconnect(&$server, &$client, $input) {

		//send disconnect message and get next in queue
		if (self::$connected->client === $client) {

			//finish current connection, empty queues
			$proxy = self::$connected;
			$server->unhook("INPUT", array($proxy, "processSocketInput"));

			$proxy->writer->write("");
			$proxy->reader->write("");
			$proxy->control->write("disconnect $proxy->id\n");

			self::log("disconnected", $proxy->id . '@' . $proxy->client->lookup_hostname());
			self::redrawPrompt();

			//get next in queue and flush buffered data
			$proxy = array_shift(self::$queue);
			self::$connected = $proxy;

			if ($proxy) {
				$proxy->control->write("connect $proxy->id\n");
				$proxy->writer->write($proxy->buffer);
				$proxy->buffer = '';
				self::log("resuming", $proxy->queue);
			}

			return;
		}

		//not the current connection, so look for and remove from queue
		foreach (self::$queue as $i => $proxy) {
			if ($proxy->client === $client) {
				self::log("removing", $proxy->id);
				unset(self::$queue[$i]);
				return;
			}
		}
	}

	/**
	 * Idle event
	 */
	public static function idle() {

		self::processCli();

		if (self::$connected) {
			self::$connected->processFileInput();
		}
	}

	protected static function drawPrompt() {

		if (!self::$prompt) {

			if (self::$connected) {
				self::$prompt = self::$connected->id . '@' . self::$connected->client->lookup_hostname() . "$ ";
			} else {
				self::$prompt = '[dbgp]$ ';
			}
		}

		echo self::$prompt . self::$prompt_buffer;
	}

	protected static function redrawPrompt() {
		echo "\033[0F\033[K";
		self::$prompt = "";
		self::drawPrompt();
	}

	/**
	 * Read commands from STDIO and execute
	 */
	protected static function processCli() {
		global $server;

		//$line = trim(fgets(STDIN)); // reads one line from STDIN
		if (!self::$prompt) {
			self::drawPrompt();
		}

		stream_set_blocking(STDIN, 0);
		$data = fgets(STDIN);

		self::$prompt_buffer .= $data;
		$pos = strpos(self::$prompt_buffer, "\n");

		if ($pos !== false) {

			$line = trim(substr(self::$prompt_buffer, 0, $pos));

			self::$prompt = '';
			self::$prompt_buffer = substr(self::$prompt_buffer, $pos + 1);

			switch ($line) {

				case '':
					break;

				case 'help':
					echo "\nList of commands:\n\n";
					echo implode("\n", array_keys(self::$help));
					echo "\n\nFor help on a specific command, type 'help [command]'\n\n";
					break;

				case 'exit':
				case 'quit':

					//shut down server socket
					socket_shutdown($server->master_socket, 1);
					usleep(500);
					socket_shutdown($server->master_socket, 0);
					socket_close($server->master_socket);

					die();
					break;

				case 'close':
					if (self::$connected) {
						$server->disconnect(self::$connected->client->server_clients_index);
					}
					break;
	
				default:
					if (self::$connected) {
						
						//add transaction id
						$line .= ' ';
						list($command, $rest) = explode(' ', $line, 2);
						$line = trim($command . ' -i ' . self::$tid++ . ' ' . $rest);

						//format and send
						$line .= chr(0);
						socket_write(self::$connected->client->socket, $line, strlen($line));
						echo "sent: $line\n\n";

					} else {
						echo "invalid command: $line\n\n";
					}
			}

		}

	}

	/**
	 * Constructor
	 *
	 * @param   object     $server   The server class
	 * @param   resource   $client   The connection socket
	 */
	public function __construct($server, $client) {

		$this->id = uniqid();

		$this->server = $server;
		$this->client = $client;

		$this->reader = IOWriter::getInstance($this->id . '.in');
		$this->writer = IOWriter::getInstance($this->id . '.out');
		$this->control = IOWriter::getInstance('status');
	}

	/**
	 * Destructor
	 */
	public function __destruct() {
		if (self::$connected === $this) {
			$this->control->write("disconnect $this->id\n");
			$this->reader->destroy();
			$this->writer->destroy();
		}
	}

	/**
	 * Handle socket input
	 *
	 * @param   object     $server   The server class
	 * @param   resource   $client   The connection socket
	 * @param   string     $input    Anything sent, if anything was sent
	 */
	public function processSocketInput(&$server, &$client, $input) {

		$this->buffer .= $input;
		
		if (self::$connected !== $this) {
			return;
		}

		while (true) {

			$first = strpos($this->buffer, chr(0));

			if ($first === false) {
				return;
			}

			$second = strpos($this->buffer, chr(0), $first + 1);

			if ($second === false) {
				return;
			}

			//$data_length = substr($input, 0, strpos($this->buffer, chr(0)));
			//$packet_length = strlen($data_length) + 1 + (int)$data_length + 1;

			//echo "data_length: $data_length\n";
			//echo "packet_length: $packet_length\n";

			$packet_length = $second + 1;
			$packet = substr($this->buffer, 0, $packet_length);
			$this->buffer = substr($this->buffer, $packet_length + 1);

			$data = substr($packet, $first + 1, $second - $first - 1);

			$dom = new DOMDocument;
			$dom->preserveWhiteSpace = FALSE;
			$dom->loadXML($data);
			$dom->formatOutput = TRUE;
			$xml = $dom->saveXml();
			$xml = str_replace('  ', '    ', $xml);

			self::log("received", $xml);

			$this->writer->write($packet);
		}

	}

	/**
	 * Handle input from file
	 */
	protected function processFileInput() {
		$input = $this->reader->read();
		if ($input) {
			self::log("sent", $input);
			socket_write($this->client->socket, $input, strlen($input));
		}
	}
}



