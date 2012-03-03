<?php
     define('_SAFE_ACCESS_', true);

	 require_once('conf.php');
     require_once(_INCLUDES_DIR_ . 'common.php');

      $db = new DataBase();

	$db->query("SELECT * FROM proxies WHERE px_good_check>0");

	$proxies = $db->loadResult();
	foreach($proxies as $key => $value)
		echo $value['px_ip'].':'.$value['px_port'].'<br />';
?>