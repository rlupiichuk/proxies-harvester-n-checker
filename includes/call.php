<?php
	define('_SAFE_ACCESS_', true);
	define('_CALL_ACCESS_', true);

	require_once('../conf.php');
	require_once(_INCLUDES_DIR_ . 'common.php');

	if(!defined('_BG_START_BACKGROUND_CHECKING_') || !_BG_START_BACKGROUND_CHECKING_) {
		Reporter::messOfficial('Please set in conf.php _BG_START_BACKGROUND_CHECKING_ to true');
		exit(0);
	}

	// reset timeout
	set_time_limit(_BG_THREAD_TIMEOUT_);

	////////////////////////////////////
	// CHECKING SETTINGS AND SECURITY //
	////////////////////////////////////
	// needed to paste variables to script
	if(!ini_get('register_argc_argv')) {
		Reporter::messError("ERROR: You must turn register_argc_argv On in you php.ini file for this to work\neg.\n\nregister_argc_argv = On");
		exit(1);
	}

	if(!@$_SERVER['argv'][1] || !@$_SERVER['argv'][2] || !@$_SERVER['argv'][3]) {
		Reporter::messError('Calling call.php with no params');
		exit(2);
	}

	if(_SECRET_CODE_ !== urldecode($_SERVER['argv'][1])) {
		Reporter::messError('Secret code not match in call.php');
		exit(3);
	}

	////////////////////////////////////
	// LOADING COMMON FILES AND FUNCS //
	////////////////////////////////////
	require_once(_INCLUDES_DIR_ . 'common.php');

	$db = new DataBase();
	parse_str($_SERVER['argv'][3]);

	Process::processStart($pc_id, $pc_user, urldecode($_SERVER['argv'][2]));

	register_shutdown_function(array('Process', 'processComplete'), $pc_id);

	// turn off reporter
	$GLOBALS['_REPORTER_MODE_'] = 'none';

	if(!@include(urldecode($_SERVER['argv'][2]))) {
		Reporter::messError('Calling file not existing file in call.php');
		exit(4);
	}
?>