<?php
	define('_SAFE_ACCESS_', true);

	$time = time();

	require_once('conf.php');
	require_once(_INCLUDES_DIR_ . 'common.php');

	$db = new DataBase();

	if(_BG_START_BACKGROUND_CHECKING_) {
		Process::processKillDead();
		Process::processFindAlive();
	}

	// ----------- proxies info ---------------

	$db->query("SELECT COUNT(*) v FROM proxies");
	$total = $db->loadValue();

	$db->query("SELECT COUNT(*) v FROM proxies WHERE px_status='post'");
	$post_good = $db->loadValue();

	$db->query("SELECT COUNT(*) v FROM proxies WHERE px_status='get'");
	$get_good = $db->loadValue();

	$db->query("SELECT COUNT(*) v FROM proxies WHERE px_status='not_checked'");
	$not_checked = $db->loadValue();

	$db->query("SELECT COUNT(*) v FROM proxies WHERE px_status='bad'");
	$bad = $db->loadValue();

	$db->query("SELECT COUNT(*) v FROM proxies WHERE px_good_check > 0");
	$ever_good = $db->loadValue();

	$db->query("SELECT COUNT(*) v FROM proxies WHERE px_status = 'post' OR px_status = 'get' OR (px_good_check > 0 AND px_bad_check < " . Settings::getSetting('_CHECK_MAX_BAD_CHECK_') . ") OR px_good_check > px_bad_check*" . Settings::getSetting('_CHECK_MIN_GOOD_PERCENT_'));
	$filtered_ever_good = $db->loadValue();

	$db->query("SELECT COUNT(*) v FROM proxies WHERE (px_status='get' OR px_status='post') AND px_anonymous='y'");
	$anonymous = $db->loadValue();

	$db->query("SELECT COUNT(*) v FROM proxies WHERE (px_status='get' OR px_status='post') AND px_anonymous='n'");
	$not_anonymous = $db->loadValue();

	$db->query("SELECT COUNT(*) v FROM proxies WHERE px_status='post' AND px_anonymous='y'");
	$post_good_anonymous = $db->loadValue();

	$db->query("SELECT COUNT(*) v FROM proxies WHERE px_status='elite' AND px_anonymous='y'");
	$elite_anonymous = $db->loadValue();

	// ----------- links info ---------------

	$db->query("SELECT COUNT(*) v FROM not_checked_links");
	$not_checked_links = $db->loadValue();

	$db->query("SELECT COUNT(*) v FROM good_links");
	$good_links = $db->loadValue();

	$db->query("SELECT COUNT(*) v FROM bad_links");
	$bad_links = $db->loadValue();

	$total_links	= $not_checked_links + $good_links + $bad_links;
	$checked_links	= $good_links + $bad_links;

	$db->query("SELECT COUNT(*) v FROM good_links WHERE lk_origin='user'");
	$checked_user_links = $db->loadValue();

	$db->query("SELECT COUNT(*) v FROM not_checked_links WHERE nc_origin='user'");
	$user_links = $checked_user_links + $db->loadValue();

	$db->query("SELECT COUNT(*) v FROM good_links WHERE lk_origin='page'");
	$good_page_links = $db->loadValue();

	// --------- jobs -----------
	$process_data = Process::processCheckStarted(array('pc_file' => _LOCATION_ . 'check.php'));
	$check_started = !empty($process_data);
	if($check_started)
		$check_begined = $process_data['pc_started'];
	else
		Properties::setProperty('checker_work', 'Finished');

	$process_data = Process::processCheckStarted(array('pc_file' => _LOCATION_ . 'links.php'));
	$links_started = !empty($process_data);
	if($links_started)
		$links_begined = $process_data['pc_started'];
	else
		Properties::setProperty('linker_work', 'Finished');

	$process_data = Process::processCheckStarted(array('pc_file' => _LOCATION_ . 'good.php'));
	$good_check_begined = !empty($process_data);
	if($good_check_begined)
		$good_check_begined = $process_data['pc_started'];
	else
		Properties::setProperty('good_work', 'Stoped');
?>
<html>
	<head>
		<title>Statistics</title>
		<link href="proxies.css" rel=stylesheet type="text/css">
		<script type="text/javascript" src="general.js"></script>
	</head>
	<body>
		<h1>Statistics</h1>
		<hr width="100%">

		<a href="add_test.php">test adding resources</a> |
		<a href="add.php">add more resources</a> |
		<a href="settings.php">settings</a><br />

		<span onclick="justLoad('start.php?file=all', this, 'start all');">start all</span> |
		<span onclick="justLoad('quit.php?file=check', this, 'working...'); justLoad('quit.php?file=good', this, 'working...'); justLoad('quit.php?file=link', this, 'stop all');">stop all</span> |
		<span onclick="reload();">refresh</span>

		<table border="0" cellpadding="10" cellspacing="10">
			<tr>
				<td valign="top" colspan="2">
					<table border="1" cellpadding="5" cellspacing="0">
						<tr>
							<td rowspan="3">
								<b>Works</b>
							</td>
							<td><b>Checker</b></td>
							<td><?php print $check_started ? 'started ' . timeTo($check_begined) . " <a onclick=\"justLoad('quit.php?file=check', this, 'stop');\">stop</a>" : "not started <a onclick=\"justLoad('start.php?file=check', this, 'start');\">start</a>";?></td>
							<td>last update<br /><?php print timeTo(Properties::getProperty('checker_updated'));?></td>
							<td><?php print Properties::getProperty('checker_work');?></td>
						</tr>
						<tr>
							<td><b>Linker</b></td>
							<td><?php print $links_started ? 'started ' . timeTo($links_begined) . " <a onclick=\"justLoad('quit.php?file=link', this, 'stop');\">stop</a>" : "not started <a onclick=\"justLoad('start.php?file=link', this, 'start');\">start</a>";?></td>
							<td>last update<br /><?php print timeTo(Properties::getProperty('linker_updated'));?></td>
							<td><?php print Properties::getProperty('linker_work');?></td>
						</tr>
						<tr>
							<td><b>Good proxies checker</b></td>
							<td><?php print $good_check_begined ? 'started ' . timeTo($good_check_begined) . " <a onclick=\"justLoad('quit.php?file=good', this, 'stop');\">stop</a>" : "not started <a onclick=\"justLoad('start.php?file=good', this, 'start');\">start</a>";?></td>
							<td>last update<br /><?php print timeTo(Properties::getProperty('good_updated'));?></td>
							<td><?php print Properties::getProperty('good_work');?></td>
						</tr>
					</table>
				</td>
			</tr>
			<tr>
				<td valign="top">
					<table border="1" cellpadding="5" cellspacing="0">
						<tr>
							<td colspan="2" align="center"><b>Checker</b></td>
						</tr>
						<tr>
							<td><b>Total count</b></td>
							<td><?php print $total;?></td>
						</tr>
						<tr>
							<td><b>Total good</b></td>
							<td><?php print $post_good + $get_good;?></td>
						</tr>
						<tr>
							<td><b>Post good</b></td>
							<td><?php print $post_good;?></td>
						</tr>
						<tr>
							<td><b>Get good</b></td>
							<td><?php print $get_good;?></td>
						</tr>
						<tr>
							<td><b>Not checked</b></td>
							<td><?php print $not_checked;?></td>
						</tr>
						<tr>
							<td><b>Bad</b></td>
							<td><?php print $bad;?></td>
						</tr>
						<tr>
							<td><b>Ever good</b></td>
							<td><?php print $ever_good;?></td>
						</tr>
						<tr>
							<td><b>Filtered ever good</b></td>
							<td><?php print $filtered_ever_good;?></td>
						</tr>
						<tr>
							<td><b>Anonymous</b></td>
							<td><?php print $anonymous;?></td>
						</tr>
						<tr>
							<td><b>Not anonymous</b></td>
							<td><?php print $not_anonymous;?></td>
						</tr>
						<tr>
							<td><b>Anonymous post</b></td>
							<td><?php print $post_good_anonymous;?></td>
						</tr>
						<tr>
							<td><b>Elite</b></td>
							<td><?php print $elite_anonymous;?></td>
						</tr>
					</table>
				</td>
				<td valign="top">
					<table border="1" cellpadding="5" cellspacing="0">
						<tr>
							<td colspan="2" align="center"><b>Linker</b></td>
						</tr>
						<tr>
							<td><b>Total links count</b></td>
							<td><?php print $total_links;?></td>
						</tr>
						<tr>
							<td><b>Checked links</b></td>
							<td><?php print $checked_links;?></td>
						</tr>
						<tr>
							<td><b>Not checked links</b></td>
							<td><?php print $not_checked_links;?></td>
						</tr>
						<tr>
							<td><b>Good links</b></td>
							<td><?php print $good_links;?></td>
						</tr>
						<tr>
							<td><b>Bad links</b></td>
							<td><?php print $bad_links;?></td>
						</tr>
						<tr>
							<td><b>User links</b></td>
							<td><?php print $user_links;?></td>
						</tr>
						<tr>
							<td><b>Checked user links</b></td>
							<td><?php print $checked_user_links;?></td>
						</tr>
						<tr>
							<td><b>Good page links</b></td>
							<td><?php print $good_page_links;?></td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</body>
</html>

