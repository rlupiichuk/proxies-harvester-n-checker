<?php
	defined('_SAFE_ACCESS_') or die('Direct access not permitted');

	define('_REPORTER_EXCEPTION_ON_ERROR_',		1);
	define('_REPORTER_EXCEPTION_ON_WARNING_',	2);
	define('_REPORTER_EXCEPTION_ON_NOTICE_',	4);
	define('_REPORTER_MESSAGE_ON_ERROR_',		8);
	define('_REPORTER_MESSAGE_ON_WARNING_',		16);
	define('_REPORTER_MESSAGE_ON_NOTICE_',		32);
	define('_REPORTER_MESSAGE_ON_DEBUG_',		64);
	define('_REPORTER_MESSAGE_ON_OFFICIAL_',	128);

	define('_REPORTER_ALL_MESSAGES_', _REPORTER_MESSAGE_ON_ERROR_ | _REPORTER_MESSAGE_ON_WARNING_ | _REPORTER_MESSAGE_ON_NOTICE_ | _REPORTER_MESSAGE_ON_DEBUG_ | _REPORTER_MESSAGE_ON_OFFICIAL_);

	if(!isset($GLOBALS['_REPORTER_MODE_']))
		$GLOBALS['_REPORTER_MODE_'] =
			_REPORTER_EXCEPTION_ON_ERROR_ |
			_REPORTER_EXCEPTION_ON_WARNING_;

	class Reporter {

		private static function exception($message) {
				throw new Exception($message . '<br />');

			if(($GLOBALS['_REPORTER_MODE_'] & _REPORTER_MESSAGE_ON_ERROR_ && $type == 'exception') ||
			   ($GLOBALS['_REPORTER_MODE_'] & _REPORTER_MESSAGE_ON_WARNING_ && $type == 'warning') ||
			   ($GLOBALS['_REPORTER_MODE_'] & _REPORTER_MESSAGE_ON_NOTICE_ && $type == 'notice'))
				print $message . '<br />';
		}

		private static function message($message) {
			print str_pad($message . '<br />', 4096);
		}

		public static function messOfficial($message) {
			if($GLOBALS['_REPORTER_MODE_'] & _REPORTER_MESSAGE_ON_OFFICIAL_)
				self::message('<b>[Official]</b> ' . $message);
		}

		public static function messNotice($message) {
			if($GLOBALS['_REPORTER_MODE_'] & _REPORTER_EXCEPTION_ON_NOTICE_)
				self::exception('<b>[Notice]</b> ' . $message);

			if($GLOBALS['_REPORTER_MODE_'] & _REPORTER_MESSAGE_ON_NOTICE_)
				self::message('<b>[Notice]</b> ' . $message);
		}

		public static function messWarning($message) {
			if($GLOBALS['_REPORTER_MODE_'] & _REPORTER_EXCEPTION_ON_WARNING_)
				self::exception('<b>[Warning]</b> ' . $message);

			if($GLOBALS['_REPORTER_MODE_'] & _REPORTER_MESSAGE_ON_WARNING_)
				self::message('<b>[Warning]</b> ' . $message);
		}

		public static function messError($message) {
			if($GLOBALS['_REPORTER_MODE_'] & _REPORTER_EXCEPTION_ON_ERROR_)
				self::exception('<b>[Error]</b> ' . $message);

			if($GLOBALS['_REPORTER_MODE_'] & _REPORTER_MESSAGE_ON_ERROR_)
				self::message('<b>[Error]</b> ' . $message);
		}

		public static function messDebug($message, $var = null) {
			if($GLOBALS['_REPORTER_MODE_'] & _REPORTER_MESSAGE_ON_DEBUG_)
				self::message('<b>[Debug]</b> ' . $message . (is_null($var) ? '' : '<br />' . print_r($var, true)));
		}
	}

	function exceptionHandler(Exception $exception) {
		global $db;

		print $exception->getMessage();
		print '<b>[Trace]</b><pre>' . $exception->getTraceAsString() . '</pre>';

		if(is_object($db) && $db->isAlive())
			$db->perform('errors', array(array(
				'er_date'		=> 'now',
				'er_message'	=> $exception->getMessage(),
				'er_trace'		=> $exception->getTraceAsString())));

		exit();
	}

	function errorHandler($errno, $errstr, $errfile, $errline) {
		$errortype = array (
			E_ERROR              => 'Error',
			E_WARNING            => 'Warning',
			E_PARSE              => 'Parsing Error',
			E_NOTICE             => 'Notice',
			E_CORE_ERROR         => 'Core Error',
			E_CORE_WARNING       => 'Core Warning',
			E_COMPILE_ERROR      => 'Compile Error',
			E_COMPILE_WARNING    => 'Compile Warning',
			E_USER_ERROR         => 'User Error',
			E_USER_WARNING       => 'User Warning',
			E_USER_NOTICE        => 'User Notice',
			E_STRICT             => 'Runtime Notice',
			E_RECOVERABLE_ERROR  => 'Catchable Fatal Error'
		);

		if($errno != E_WARNING && $errno != E_CORE_WARNING && $errno != E_COMPILE_WARNING && $errno != E_USER_WARNING && $errno != E_NOTICE && $errno != E_USER_NOTICE && $errno != E_STRICT) {
			if($GLOBALS['_REPORTER_MODE_'] & _REPORTER_EXCEPTION_ON_ERROR_)
				throw new Exception(
					'<b>[' . $errortype[$errno] . ']</b> ' . $errstr . "\nfile:$errfile\nline:$errline<br />"
					);

			if($GLOBALS['_REPORTER_MODE_'] & _REPORTER_MESSAGE_ON_ERROR_)
				print '<b>[' . $errortype[$errno] . ']</b> ' . $errstr . "\nfile:$errfile\nline:$errline<br />";

			exit();
		}

		if($errno == E_WARNING || $errno == E_CORE_WARNING || $errno == E_COMPILE_WARNING || $errno == E_USER_WARNING || $errno == E_NOTICE || $errno == E_USER_NOTICE || $errno == E_STRICT) {
			if($GLOBALS['_REPORTER_MODE_'] & _REPORTER_EXCEPTION_ON_WARNING_)
				throw new Exception(
					'<b>[' . $errortype[$errno] . ']</b> ' . $errstr . "\nfile:$errfile\nline:$errline<br />"
					);

			if($GLOBALS['_REPORTER_MODE_'] & _REPORTER_MESSAGE_ON_WARNING_)
				print '<b>[' . $errortype[$errno] . ']</b> ' . $errstr . "\nfile:$errfile\nline:$errline<br />";
		}
	}

	set_exception_handler('exceptionHandler');
	set_error_handler('errorHandler');
?>
