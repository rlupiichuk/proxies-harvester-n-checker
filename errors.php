<?php
	define('_SAFE_ACCESS_', true);

	require_once('conf.php');
	require_once(_INCLUDES_DIR_ . 'common.php');

	$db = new DataBase();
	$errors_count = 10;

	if(isset($_REQUEST['cmd']) && $_REQUEST['cmd'] == 'clear')
		$db->query('TRUNCATE TABLE errors');

	$db->query('SELECT er_date, er_message, er_trace FROM errors ORDER BY er_date DESC LIMIT ' . $errors_count);
	$errors =& $db->loadResult();
?>
<html>
	<head>
		<title>Last <?php print $errors_count;?> errors</title>
		<link href="proxies.css" rel=stylesheet type="text/css">
		<script type="text/javascript" src="general.js"></script>
	</head>
	<body>
		<h1>Last <?php print $errors_count;?> errors</h1>
		<hr width="100%">

		<a href="errors.php?cmd=clear">clear</a> |
		<a href="errors.php">refresh</a><br />

		<table border="1" cellpadding="5" cellspacing="0">
			<tr>
				<td valign="top"><b>Date</b></td>
				<td valign="top"><b>Message</b></td>
				<td valign="top"><b>Trace</b></td>
			</tr>
		<?php
			foreach($errors as $error) {
		?>
			<tr>
				<td valign="top"><?php print $error['er_date'];?></td>
				<td valign="top"><?php print nl2br($error['er_message']);?></td>
				<td valign="top"><?php print nl2br($error['er_trace']);?></td>
			</tr>
		<?php
			}
		?>
		</table>
	</body>
</html>