<?php
	define('_SAFE_ACCESS_', true);

	if(isset($_REQUEST['showed'])) {
		require_once('conf.php');
		require_once(_INCLUDES_DIR_ . 'common.php');

		$db = new DataBase();
		$filter = new ProxiesFilter();

		$links   =& $filter->filterLinks($_REQUEST['proxy_list']);
		$proxies =& $filter->filterProxies($_REQUEST['proxy_list']);

		$count_links   = count($links);
		$count_proxies = count($proxies);
?>
<html>
	<head>
		<title>Done! Found <?php print $count_proxies;?> proxies</title>
		<link href="proxies.css" rel=stylesheet type="text/css">
	</head>
	<body>
		<h1>Test adding proxies</h1>
		<hr width="100%">

		<a href="index.php">statistics</a> |
		<a href="add_test.php">test add more</a>
		<table border="0" cellpadding="10" cellspacing="10"><tr><td>
			<b>Done<br> Found <?php print $count_proxies;?> proxies and <?php print $count_links;?> links</b><br />

			<?php if(count($proxies) > 0) { ?>
			<h2>Proxies</h2>
			<pre><?php
				$result = '';
				foreach($proxies as $proxy)
					$result .= $proxy['ip'] . ':' . $proxy['port'] . "\n";
				print substr($result, 0, -1);
			?></pre>
			<?php } ?>
			<?php if(count($links) > 0) { ?>
			<h2>Links</h2>
			<pre><?php print implode("\n", $links);?></pre>
			<?php } ?>
		</td></tr></table>
	</body>
</html>
<?php
	} else {
?>
<html>
	<head>
		<title>Test adding proxies</title>
		<link href="proxies.css" rel=stylesheet type="text/css">
	</head>
	<body>
		<h1>Test adding proxies</h1>
		<hr width="100%">

		<a href="index.php">statistics</a> |
		<a href="index.php">cancel</a> |
		<a onclick="document.add_form.submit();">test add</a>
		<table border="0" cellpadding="10" cellspacing="10"><tr><td>
			<form name="add_form" action="add_test.php" method="POST">
				<input type="hidden" name="showed" value="yes">
				Enter links and proxies there:<br />
				<textarea name="proxy_list" cols="120" rows="20"></textarea><br />
				<input type="submit" value="test add">
			</form>
		</td></tr></table>
	</body>
</html>
<?php
	}
?>