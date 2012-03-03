<?php
echo
"<br><b>REMOTE_ADDR: </b>".		$_SERVER['REMOTE_ADDR'].
"<br>".
"<br><b>HTTP_X_FORWARDED_FOR: </b>".	$_SERVER['HTTP_X_FORWARDED_FOR'].
"<br><b>HTTP_CLIENT_IP: </b>".		$_SERVER['HTTP_CLIENT_IP'].
"<br><b>HTTP_FROM: </b>".		$_SERVER['HTTP_FROM'].
"<br><b>HTTP_FORWARDED: </b>".		$_SERVER['HTTP_FORWARDED'].
"<br>".
"<br><b>HTTP_VIA: </b>".		$_SERVER['HTTP_VIA'].
"<br><b>HTTP_PROXY_CONNECTION: </b>".	$_SERVER['HTTP_PROXY_CONNECTION'];
?>