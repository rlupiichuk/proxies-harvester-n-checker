<?php
	define('_SAFE_ACCESS_', true);

	require_once('conf.php');
	require_once(_INCLUDES_DIR_ . 'common.php');

	$db = new DataBase();
	$getter = new Getter();

	$proxies = array_slice($getter->giveMeProxies(), 0, intval(@$_REQUEST['n']));

	Reporter::messOfficial('Got ' . count($proxies) . ' proxies');

	foreach($proxies as $proxy)
		print ("$proxy[px_ip]:$proxy[px_port]\n");
		

	Caller::startBackground(
		Settings::getSetting('_START_CHECKER_ON_GET_'),
		Settings::getSetting('_START_GOOD_CHECKER_ON_GET_'),
		Settings::getSetting('_START_LINKER_ON_GET_'));
?>