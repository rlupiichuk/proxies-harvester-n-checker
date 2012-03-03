<?php
	define('_SAFE_ACCESS_', true);

	require_once('conf.php');
	require_once(_INCLUDES_DIR_ . 'common.php');

	$db = new DataBase();

	/**
	 * Return array of proxies
	 *
	 * @param integer $number
	 * @param string $anonymous
	 * @param string $quality
	 * @return array
	 */
	function getProxies($number, $anonymous, $quality) {
		global $db;

		$_REQUEST['n'] = $number;
		$_REQUEST['a'] = $anonymous;
		$_REQUEST['q'] = $quality;

		$getter = new Getter();

		$proxies = array_slice($getter->giveMeProxies(), 0, intval(@$_REQUEST['n']));

		Reporter::messOfficial('Got ' . count($proxies) . ' proxies');

		$string_proxies = array();
		foreach($proxies as $proxy)
			$string_proxies[] = "$proxy[px_ip]:$proxy[px_port]";

		return $string_proxies;
	}

	$server = new SoapServer('getproxies.wsdl');
	$server->addFunction('getProxies');
	$server->handle();

	Caller::startBackground(
		Settings::getSetting('_START_CHECKER_ON_GET_'),
		Settings::getSetting('_START_GOOD_CHECKER_ON_GET_'),
		Settings::getSetting('_START_LINKER_ON_GET_'));
?>