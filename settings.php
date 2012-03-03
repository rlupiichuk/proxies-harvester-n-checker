<?php
	define('_SAFE_ACCESS_', true);

	require_once('conf.php');
	require_once(_INCLUDES_DIR_ . 'common.php');

	$db = new DataBase();

	$setting_obj = new Settings();
	$form = $setting_obj->performEditing();
?>
<html>
	<head>
		<title>Settings</title>
		<link href="proxies.css" rel=stylesheet type="text/css">
		<script type="text/javascript" src="general.js"></script>
	</head>
	<body>
		<h1>Settings</h1>
		<hr width="100%">

		<a href="index.php">statistics</a> |
		<?php if($setting_obj->isProcessed() || isset($_REQUEST['restore'])) { ?>
			<a href="settings.php">edit again</a>
		<?php } else {?>
			<a href="index.php">cancel</a> |
			<a onclick="document.setting_form.submit();">save</a> |
			<a onclick="if(confirm('Are you realy want to restore defaults?')) document.location='settings.php?restore=true';">restore defaults</a>
		<?php } ?>
		<table border="0" cellpadding="10" cellspacing="10"><tr><td>
			<?php print $form; ?>
		</td></tr></table>
	</body>
</html>