<?php
	define('_SAFE_ACCESS_', true);

	if(isset($_REQUEST['showed'])) {
		require_once('conf.php');
		require_once(_INCLUDES_DIR_ . 'common.php');

		$db = new DataBase();
		$filter = new ProxiesFilter();

		$links =& $filter->filterLinks($_REQUEST['proxy_list']);

		// add new links
		$lc = new LinksCrotcher();

		if(count($links) > 0) {
			$db->query('SELECT ((SELECT COUNT(*) FROM good_links WHERE ' . LinksCrotcher::formatOR($links, 'lk_url') . ') + (SELECT COUNT(*) FROM bad_links WHERE ' . LinksCrotcher::formatOR($links, 'bl_url') . ') + (SELECT COUNT(*) FROM not_checked_links WHERE ' . LinksCrotcher::formatOR($links, 'nc_url') . ')) v');
			$count_new_links = count($links) - $db->loadValue();
		} else
			$count_new_links = 0;

		$lc->addLinks($links, null, @$_REQUEST['as_page_links'] ? 'page' : 'user');
		unset($lc);

		$count_links = count($links);

		$proxies =& $filter->filterProxies($_REQUEST['proxy_list']);

		$db_list = array();
		foreach($proxies as $proxy)
			$db_list[] = array(
				'px_ip' => $proxy['ip'],
				'px_port' => $proxy['port'],
				'px_status' => 'not_checked');

		$db->perform('proxies', $db_list, 'insert_ignore');
		$count_proxies = count($proxies);
		$count_new_proxies = $count_proxies > 0 ? $db->affectedRows() : 0;

		Caller::startBackground(
			Settings::getSetting('_START_CHECKER_ON_ADD_'),
			Settings::getSetting('_START_GOOD_CHECKER_ON_ADD_'),
			Settings::getSetting('_START_LINKER_ON_ADD_'));
?>
<html>
	<head>
		<title>Done! Found <?php print $count_new_proxies;?> new proxies</title>
		<link href="proxies.css" rel=stylesheet type="text/css">
	</head>
	<body>
		<h1>Adding proxies</h1>
		<hr width="100%">

		<a href="index.php">statistics</a> |
		<a href="add.php">add more</a>
		<table border="0" cellpadding="10" cellspacing="10"><tr><td>
			<b>Done<br> Found <?php print $count_proxies;?> proxies(new <?php print $count_new_proxies;?>) and <?php print $count_links;?> links(new <?php print $count_new_links; ?>)</b><br />

		</td></tr></table>
	</body>
</html>
<?php
	} else {
?>
<html>
	<head>
		<title>Adding proxies</title>
		<link href="proxies.css" rel=stylesheet type="text/css">
	</head>
	<body>
		<h1>Adding proxies</h1>
		<hr width="100%">

		<a href="index.php">statistics</a> |
		<a href="index.php">cancel</a> |
		<a onclick="document.add_form.submit();">save</a>
		<table border="0" cellpadding="10" cellspacing="10"><tr><td>
			<form name="add_form" action="add.php" method="POST">
				<input type="hidden" name="showed" value="yes">
				Enter links and proxies there:<br />
				<input type="checkbox" name="as_page_links" id="as_page_links" checked="1"/><label for="as_page_links"><font color="grey">add links as page links</font></label><br />
				<textarea name="proxy_list" cols="120" rows="20"></textarea><br />
				<input type="submit" value="save">
			</form>
		</td></tr></table>
	</body>
</html>
<?php
	}
?>