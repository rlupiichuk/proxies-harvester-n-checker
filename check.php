<?php
	if(!defined('_SAFE_ACCESS_'))
		define('_SAFE_ACCESS_', true);

	$time = time();

	require_once('conf.php');
	require_once(_INCLUDES_DIR_ . 'common.php');

	$db = new DataBase();

	if(_BG_START_BACKGROUND_CHECKING_ && !defined('_CALL_ACCESS_'))
		die('Direct access not permitted');


	set_time_limit(_PROXIES_CHECK_TIMEOUT_);

	$checker = new ProxiesChecker();
	$checker->updateStatus();
	$checker->check();

	Reporter::messOfficial('Done in ' . (time()-$time) . ' sec.');
?>