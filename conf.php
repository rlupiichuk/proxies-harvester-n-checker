<?php
	defined('_SAFE_ACCESS_') or die('Direct access not permitted');

	define('_LOCATION_', dirname(__FILE__) . '/');

	define('_INCLUDES_DIR_',	_LOCATION_ . 'includes/');
	define('_CLASSES_DIR_',		_LOCATION_ . 'classes/');
	define('_BYTECODE_DIR_',	_LOCATION_ . 'bytecode/');

	define('_DIRECT_URL_',		'http://localhost/proxies/');

	// database options
	define('_DSN_', 'mysql://root:@localhost/proxies');

	define('_PROXIES_CHECK_TIMEOUT_',		60*60); // in seconds
	define('_PROXIES_GOOD_CHECK_TIMEOUT_',	60); // in seconds
	define('_LINKS_CHECK_TIMEOUT_',			60*60); // in seconds

	// 3   = _REPORTER_EXCEPTION_ON_ERROR_ | _REPORTER_EXCEPTION_ON_WARNING_
	// 248 = All messages 
	// 255 = _REPORTER_ALL_MESSAGES_
	$GLOBALS['_REPORTER_MODE_'] = 1;

	// if this option setted to true each time when possible/needed script will start checking in backbround
	define('_BG_START_BACKGROUND_CHECKING_',	true);

	if(_BG_START_BACKGROUND_CHECKING_) {
		define('_BG_THREAD_TIMEOUT_',	12*60*60); // seconds

		define('_EXECUTABLE_PHP_',	'php5-cgi');

		// any leter sequence
		define('_SECRET_CODE_', 'asd89D7hnuyDSLfsak');
	}
?>
