<?php

class DBGpPacket {

	public static function init() {

		$packet = array(
			'init' => array(
				'appid' => "1984",
				//'idekey' => "IDE_KEY",
				//'session' => "DBGP_COOKIE",
				'thread' => getmypid(),
				//'parent' => "PARENT_APPID",
				'language' => "PHP",
				'protocol_version' => "1.0",
				'fileuri' => "file://" . $_SERVER['SCRIPT_FILENAME'],
			)
		);

		return self::encode($packet);
	}

	public static function close() {
	
		$packet = array(
			'close' => array(
				'thread' => getmypid(),
			)
		);
	
		return self::encode($packet);
	}

	public static function response($name, $data) {
		return self::encode(array('action' => $name, 'data' => $data));
	}

	protected function encode($info) {
		$encoded = json_encode($info);
		return $encoded;
	}

}
