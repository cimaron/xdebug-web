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


class DBGpPacket {

	const APPID = '1984';

	/**
	 * Error response codes
	 */
	const ERROR_NO_ERROR            = 0;
	const ERROR_PARSE_ERROR         = 1;
	const ERROR_DUPLICATE_ARGUMENTS = 2;
	const ERROR_INVALID_OPTIONS     = 3;
	const ERROR_NOT_IMPLEMENTED     = 4;
	const ERROR_NOT_AVAILABLE       = 5;

	const ERROR_FILE_OPEN          = 100;
	const ERROR_FILE_STREAM_FAILED = 101;
	
	const ERROR_BREAKPOINT_SET           = 200;
	const ERROR_BREAKPOINT_NOT_SUPPORTED = 201;
	const ERROR_BREAKPOINT_INVALID       = 202;
	const ERROR_BREAKPOINT_NO_CODE       = 203;
	const ERROR_BREAKPOINT_INVALID_STATE = 204;
	const ERROR_BREAKPOINT_NOT_EXISTS    = 205;
	const ERROR_BREAKPOINT_EVAL          = 206;
	const ERROR_BREAKPOINT_INVALID_EXPR  = 207;

	const ERROR_DATA_GET_PROPERTY    = 300;
	const ERROR_DATA_STACK_DEPTH     = 301;
	const ERROR_DATA_CONTEXT_INVALID = 302

	const ERROR_ENCODING_NOT_SUPPORTED = 900;
	const ERROR_INTERNAL_EXCEPTION     = 998;
	const ERROR_UNKOWN                 = 999;

	/**
	 * Prepare doctype
	 */
	public static function dt() {
		return '<?xml version="1.0" encoding="iso-8859-1"?>';
	}

	/**
	 * Transform hash into tag attributes
	 *
	 * @param   array   $attr   Hash of key => value
	 *
	 * @return  string
	 */
	public static attributes($attr) {
	
		$out = array();
		foreach ($attr as $key => $value) {
			$out[] = $key . '="' . htmlentities($value) . '"';
		}
		$out = implode(' ', $out);

		return $out;
	}

	/**
	 * Transform string into data segment
	 *
	 * @param   string   $data   Data
	 *
	 * @return  string
	 */
	public static data($data) {
	
		//@todo: do some other encoding if nested CDATA here?
	
		$out = '<![CDATA[' . $data . ']]>';

		return $out;
	}

	/**
	 * Prepare init packet
	 *
	 * @return  string
	 */
	public static function init() {

		$attr = array(
			'appid' => "1984",
			//'idekey' => "IDE_KEY",
			//'session' => "DBGP_COOKIE",
			'thread' => getmypid(),
			//'parent' => "PARENT_APPID",
			'language' => "PHP",
			'protocol_version' => "1.0",
			'fileuri' => "file://" . $_SERVER['SCRIPT_FILENAME'],
		);

		$data = '';

		$init = sprintf('<init %s>%s</init>', self::attributes($attr), self::data($data));

		return self::dt() . $init;
	}

	/**
	 * Prepare close packet
	 *
	 * @return  string
	 */
	public static function close() {

		$attr = array(
			'thread' => getmypid(),
		);

		$data = '';

		$close = sprintf('<close %s>%s</close>', self::attributes($attr), self::data($data));

		return self::dt() . $close;
	}

	/**
	 * Prepare response packet
	 *
	 * @param   string   $command    Command
	 * @param   string   $trans_id   Transaction ID
	 * @param   array    $attr       Other attributes
	 * @param   string   $data       Data
	 *
	 * @return  string
	 */
	public static function response($command, $trans_id, $attr = array(), $data = '', $encode = true) {

		$attr = array_merge($attr, array(
			'command' => $command,
			'transaction_id' => $trans_id,
		));

		$response = sprintf('<response %s>%s</response>', self::attributes($attr), $encode ? self::data($data) : $data);

		return self::dt() . $response;
	}

	/**
	 * Prepare error packet
	 *
	 * @param   string   $command    Command
	 * @param   string   $trans_id   Transaction ID
	 * @param   array    $attr       Other attributes
	 * @param   string   $code       Error code
	 * @param   string   $message    Error message
	 *
	 * @return  string
	 */
	public static function error($command, $trans_id, $attr = array(), $code = self::ERROR_NO_ERROR, $message = '') {

		$eattr = array(
			'code' => $code,
		);

		$error = sprintf('<error %s><message>%s</message></error>', self::attributes($eattr), self::data($message));

		return self::response($command, $trans_id, $attr, $error, false);				
	}

}

