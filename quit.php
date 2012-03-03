<?php
	define('_SAFE_ACCESS_', true);

	require_once('conf.php');
	require_once(_INCLUDES_DIR_ . 'common.php');

	$db = new DataBase();

	$cmd = $_REQUEST['file'];
	if(!in_array($cmd, array('check', 'link', 'good'))) {
		Reporter::messError('Bad command');
		return;
	}

	switch ($cmd) {
		case 'check':
			Process::processKillWithFile('check.php');
			break;
		case 'link':
			Process::processKillWithFile('links.php');
			break;
		case 'good':
			Process::processKillWithFile('good.php');
			break;
		default:
			throw new Exception('Command not found: ' . $cmd);
	}

	Process::processKillDead();

	Reporter::messOfficial('Process is killed');
?>