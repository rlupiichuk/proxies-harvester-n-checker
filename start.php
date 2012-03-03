<?php
	define('_SAFE_ACCESS_', true);

	require_once('conf.php');
	require_once(_INCLUDES_DIR_ . 'common.php');

	$db = new DataBase();

	$cmd = $_REQUEST['file'];
	if(!in_array($cmd, array('check', 'link', 'good', 'all'))) {
		Reporter::messError('Bad command');
		return;
	}

	switch ($cmd) {
		case 'check':
			Caller::startBackground(true, false, false);
			break;
		case 'link':
			Caller::startBackground(false, false, true);
			break;
		case 'good':
			Caller::startBackground(false, true, false);
			break;
		case 'all':
		default:
			Caller::startBackground(true, true, true);
	}
?>