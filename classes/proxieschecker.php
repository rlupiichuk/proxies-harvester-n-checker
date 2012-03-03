<?php
	defined('_SAFE_ACCESS_') or die('Direct access not permitted');

	define('_CHECK_COUNT_',			Settings::getSetting('_CHECK_COUNT_')); // default 70 // max count of threads
	define('_CHECK_TIMEOUT_',		Settings::getSetting('_CHECK_TIMEOUT_')); // default 7 // in seconds
	define('_CHECK_REFRESH_GOOD_',	Settings::getSetting('_CHECK_REFRESH_GOOD_')); // default 5 * 60 // in seconds
	define('_CHECK_RELOAD_GOOD_',	Settings::getSetting('_CHECK_RELOAD_GOOD_')); // default 30 * 60 // in seconds
	define('_CHECK_LOAD_PER_TIME_',	5 * _CHECK_COUNT_); // count of not checked proxies to load in each time

	// when check proxy which have status 'checking' and time starting checking _CHECK_KILL_AFTER_ later
	define('_CHECK_KILL_AFTER_',	5); // in minutes
	define('_CHECK_URL_',			Settings::getSetting('_CHECK_URL_')); // default 'http://online-pharma.info/myip/'

	define('_CHECK_MAX_BAD_CHECK_',		Settings::getSetting('_CHECK_MAX_BAD_CHECK_')); // default 10
	define('_CHECK_MIN_GOOD_PERCENT_',	Settings::getSetting('_CHECK_MIN_GOOD_PERCENT_')); // default 0.05 // 0..1
	define('_CHECK_TIME_TO_RECHECK_',	Settings::getSetting('_CHECK_TIME_TO_RECHECK_')); // default 12 * 60 // in minutes

	define('_CHECK_MAX_QUICK_TIME_',	Settings::getSetting('_CHECK_MAX_QUICK_TIME_')); // default 15 // in seconds

	class ProxiesChecker {
		private $last_good_list_reload = 0;

		private $good_list = array();
		private $to_check_list = array();
		private $my_ip;

		/**
		 * This url must return line like this:
		 * <br><b>REMOTE_ADDR: </b>0.0.0.0<br><br><b>HTTP_X_FORWARDED_FOR: </b><br><b>HTTP_CLIENT_IP: </b><br><b>HTTP_FROM: </b><br><b>HTTP_FORWARDED: </b><br><br><b>HTTP_VIA: </b><br><b>HTTP_PROXY_CONNECTION: </b>
		 */
		private $url = _CHECK_URL_;

		function ProxiesChecker() {
			$ch = curl_init($this->url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$my_ip_page = curl_exec($ch);
			$ip = '\d+\.\d+\.\d+\.\d+';

			if(preg_match_all("/(?:REMOTE_ADDR: <\/b>($ip)<br>|HTTP_X_FORWARDED_FOR: <\/b>($ip)<br>)/U", $my_ip_page, $res)) {
				$this->my_ip = array_map('trim', @$res[1]);
				// clean empty fields
				foreach($this->my_ip as $key => $value)
					if(!$value)
						unset($this->my_ip[$key]);
			} else
				Reporter::messError('Not working url: ' . _CHECK_URL_);

			Reporter::messDebug('My ips', $this->my_ip);
		}

		public function loadGood() {
			global $db;

			Properties::cleverSetProperty('good_work', 'Loading good proxies');

			$db->query("SELECT px_ip, px_port FROM proxies WHERE px_status = 'post' OR px_status = 'get' OR (px_good_check > 0 AND px_bad_check < " . _CHECK_MAX_BAD_CHECK_ . ") OR px_good_check > px_bad_check*" . _CHECK_MIN_GOOD_PERCENT_);
			$this->good_list =& $db->loadResult();

			Reporter::messOfficial('Load ' . count($this->good_list) . ' good proxies');

			$this->last_good_list_reload = time();
		}

		public function updateStatus() {
			global $db;

			Properties::cleverSetProperty('checker_work', 'Force updating status');

			$db->query("UPDATE proxies SET px_status='not_checked' WHERE px_check_date < " . getFormatedTime(_CHECK_TIME_TO_RECHECK_) . " AND (px_bad_check < " . _CHECK_MAX_BAD_CHECK_ . ' OR px_good_check > px_bad_check*' . _CHECK_MIN_GOOD_PERCENT_ . ')');
		}

		public function quickFindGood($number, $quality, $anonymous, array $not_allowed_proxies) {
			if(empty($this->my_ip)) {
				Reporter::messError('My ip is empty');
				return false;
			}

			$time = time();

			$good_proxies = array();
			$possibly_good = array();
			while(true) {
				if(count($good_proxies) >= $number)
					break;

				if(empty($possibly_good)) {
					$possibly_good =& $this->loadPossiblyGood($not_allowed_proxies, $quality, $anonymous);
					$not_allowed_proxies = array_merge($not_allowed_proxies, $possibly_good);

					if(empty($possibly_good))
						break;
				}

				$checking_list = array_slice($possibly_good, 0, _CHECK_COUNT_);
				array_splice($possibly_good, 0, _CHECK_COUNT_);

				$elite_list = array();
				$anonymous_list = array();
				$not_anonymous_list = array();
				$bad_list = array();

				$this->checkProxyList($checking_list, $elite_list, $anonymous_list, $not_anonymous_list, $bad_list);

				$db_list =& $this->formatDataForDb($bad_list, 'bad');
				$this->updateProxiesTable($db_list, array('px_ip', 'px_port', 'px_status', 'px_anonymous', 'px_check_date'), false);

				if(!empty($elite_list) || !empty($anonymous_list)) {
					$good_list = $elite_list + $anonymous_list;
					$result_al =& $this->checkPost($good_list, true);
				} else
					$result_al = array('post_anonymous_list' => array(), 'post_not_anonymous_list' => array(), 'post_bad_list' => array());

				if(!empty($not_anonymous_list))
					$result_nal =& $this->checkPost($not_anonymous_list, false);
				else
					$result_nal = array('post_anonymous_list' => array(), 'post_not_anonymous_list' => array(), 'post_bad_list' => array());

				switch ($anonymous) {
					case 'y':
						switch ($quality) {
							case 'p':
								$good_proxies = array_merge($good_proxies, $result_al['post_anonymous_list']);
								break;
							case 'g':
								$good_proxies = array_merge($good_proxies, $result_al['post_bad_list']);
								break;
							case 'e':
								$good_proxies = array_merge($good_proxies, $result_al['elite_list']);
								break;
							default:
								$good_proxies = array_merge($good_proxies, $anonymous_list);
						}
						break;
					case 'n':
						switch ($quality) {
							case 'p':
								$good_proxies = array_merge($good_proxies, $result_al['post_not_anonymous_list'], $result_nal['post_not_anonymous_list'], $result_nal['post_anonymous_list']);
								break;
							case 'g':
								$good_proxies = array_merge($good_proxies, $result_al['post_bad_list'], $result_nal['post_bad_list']);
								break;
							case 'e':
								$good_proxies = array();
								break;
							default:
								$good_proxies = array_merge($good_proxies, $result_al['post_not_anonymous_list'], $not_anonymous_list);
						}
						break;
					default:
						switch ($quality) {
							case 'p':
								$good_proxies = array_merge($good_proxies, $result_al['post_anonymous_list'], $result_al['post_not_anonymous_list']);
								break;
							case 'g':
								$good_proxies = array_merge($good_proxies, $result_al['post_bad_list'], $not_anonymous_list);
								break;
							case 'e':
								$good_proxies = array_merge($good_proxies, $result_al['elite_list']);
								break;
							default:
								$good_proxies = array_merge($good_proxies, $anonymous_list, $not_anonymous_list);
						}
				}

				if(time()-$time > _CHECK_MAX_QUICK_TIME_)
					break;
			}

			return $good_proxies;
		}

		public function check() {
			if(empty($this->my_ip)) {
				Reporter::messError('My ip is empty');
				return false;
			}

			//$this->checkGoodList();
			//$last_good_list_refresh = time();

			Properties::cleverSetProperty('checker_work', 'Checking');

			while(true) {
				if(empty($this->to_check_list)) {
					$this->load();

					if(empty($this->to_check_list))
						break;
				}

				$checking_list = array_slice($this->to_check_list, 0, _CHECK_COUNT_);
				array_splice($this->to_check_list, 0, _CHECK_COUNT_);

				$elite_list = array();
				$anonymous_list = array();
				$not_anonymous_list = array();

				$this->checkPiece($checking_list, $elite_list, $anonymous_list, $not_anonymous_list);
				//$this->good_list = array_merge($this->good_list, $anonymous_list, $not_anonymous_list);

				// refresh good list
				//if(time()-$last_good_list_refresh > _CHECK_REFRESH_GOOD_) {
				//	$this->checkGoodList();
				//	$last_good_list_refresh = time();
				//}
			}

			//$this->checkGoodList();

			Properties::cleverSetProperty('checker_work', 'Finished');

			return true;
		}

		public function forceCheckingGood() {

			while(true) {
				Properties::cleverSetProperty('good_work', 'Force checking good proxies');
				$this->checkGoodList();

				//if(empty($this->good_list)) {
				//	Reporter::messOfficial('No good proxies found :(');
				//	break;
				//}

				Properties::cleverSetProperty('good_work', 'Sleeping');

				sleep(_CHECK_REFRESH_GOOD_);
			}
		}

		private function checkPiece(array &$checking_list, array &$elite_list, array &$anonymous_list, array &$not_anonymous_list) {
			$bad_list = array();

			$this->checkProxyList($checking_list, $elite_list, $anonymous_list, $not_anonymous_list, $bad_list);

			if(!empty($elite_list) || !empty($anonymous_list)) {
				$good_list = $elite_list + $anonymous_list;
				$this->checkPost($good_list, true);
			}

			if(!empty($not_anonymous_list))
				$this->checkPost($not_anonymous_list, false);

			if(!empty($bad_list)) {
				$db_list =& $this->formatDataForDb($bad_list, 'bad');
				$this->updateProxiesTable($db_list, array('px_ip', 'px_port', 'px_status', 'px_anonymous', 'px_check_date'), false);
			}
		}

		private function getWhereForPossiblyGood($quality, $anonymous) {
			switch ($quality) {
				case 'p':
					$where = 'px_status=\'post\'';
					break;
				case 'g':
					$where = 'px_status=\'get\'';
					break;
				case 'e':
					$where = 'px_status=\'elite\'';
					break;
				default:
					$where = '(px_good_check > 0 AND ' .
						'px_good_check > px_bad_check*' . _CHECK_MIN_GOOD_PERCENT_ . ')';
			}

			switch ($anonymous) {
				case 'y':
					$where .= ' AND px_anonymous=\'y\'';
					break;
				case 'n':
					$where .= ' AND px_anonymous=\'n\'';
					break;
			}

			return $where;
		}

		private function &loadPossiblyGood(array &$not_allowed_proxies, $quality, $anonymous) {
			global $db;

			$not_allowed = '';
			foreach($not_allowed_proxies as $proxy)
				$not_allowed .= '(px_ip=' . DataBase::prepare($proxy['px_ip']) . ' AND px_port=' . DataBase::prepare($proxy['px_port']) . ')OR';
			$not_allowed = substr($not_allowed, 0, -2);

			$where = $this->getWhereForPossiblyGood($quality, $anonymous);
			$query = 'SELECT px_ip, px_port FROM proxies WHERE ' .
					($where ? "($where)" : '1') .
					(empty($not_allowed) ? '' : ' AND NOT (' . $not_allowed . ')') .
				' ORDER BY px_good_check/(px_bad_check+1) DESC LIMIT ' . _CHECK_LOAD_PER_TIME_;

			$db->query($query);

			$proxies =& $db->loadResult();
			Reporter::messOfficial('Load ' . count($proxies) . ' proxies');

			return $proxies;
		}

		/**
		 * This function must load next portion of not checked proxies
		 *
		 * @todo Fix conflict
		 */
		private function load() {
			global $db;

			$db->query("SELECT px_ip, px_port FROM proxies WHERE px_status='not_checked' OR (px_status='checking' AND px_check_date < " . getFormatedTime(_CHECK_KILL_AFTER_) . ") ORDER BY px_good_check/(px_bad_check+1) DESC LIMIT " . _CHECK_LOAD_PER_TIME_);
			$this->to_check_list =& $db->loadResult();

			if(!empty($this->to_check_list)) {
				// set status 'checking'
				$query = 'UPDATE proxies SET px_status=\'checking\', px_check_date=NOW() WHERE ';
				foreach($this->to_check_list as $proxy)
					$query .= '(px_ip=' . DataBase::prepare($proxy['px_ip']) . ' AND px_port=' . DataBase::prepare($proxy['px_port']) . ')OR';
				$db->query(substr($query, 0, -2));
			}

			Reporter::messOfficial('Load ' . count($this->to_check_list) . ' proxies to check');
		}

		private function checkProxyList(array &$proxy_list, array &$elite, array &$anonymous, array &$not_anonymous, array &$bad, $method = 'get') {
			$elite			= array();
			$anonymous		= array();
			$not_anonymous	= array();
			$bad			= array();
			$conn			= array();

			$mh = curl_multi_init();

			foreach ($proxy_list as $key => $proxy){
				$conn[$key] = curl_init($this->url);

				if($method == 'post')
					curl_setopt($conn[$key], CURLOPT_POST, 1);
				curl_setopt($conn[$key], CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($conn[$key], CURLOPT_TIMEOUT, _CHECK_TIMEOUT_);
				curl_setopt($conn[$key], CURLOPT_PROXY, $proxy['px_ip'].':'.$proxy['px_port']);
				curl_setopt($conn[$key], CURLOPT_FOLLOWLOCATION, 0);

				curl_multi_add_handle ($mh, $conn[$key]);
			}

			do {
				$mrc = curl_multi_exec($mh, $active);
			} while ($mrc == CURLM_CALL_MULTI_PERFORM);

			while ($active and $mrc == CURLM_OK) {
				// wait for network
			 	if (curl_multi_select($mh) != -1)
					do {
				    	$mrc = curl_multi_exec($mh, $active);
					} while ($mrc == CURLM_CALL_MULTI_PERFORM);
			}

			if ($mrc != CURLM_OK)
				Reporter::messError("Curlmulti read error $mrc");

			// retrieve data
			foreach ($proxy_list as $key => $proxy) {
	 			if (($err = curl_error($conn[$key])) == '') {
					$content = curl_multi_getcontent($conn[$key]);

					if(substr($content, 0, 24) == '<br><b>REMOTE_ADDR: </b>') {
						$check = true;
						foreach($this->my_ip as $ip)
							if(strpos($content, $ip) !== false) {
								$check = false;
								break;
							}

						if($check) {
							if(preg_match('~<br><b>REMOTE_ADDR: </b>(?:25[0-5]|2[0-4]\d|[01]?\d\d|\d)\.(?:25[0-5]|2[0-4]\d|[01]?\d\d|\d)\.(?:25[0-5]|2[0-4]\d|[01]?\d\d|\d)\.(?:25[0-5]|2[0-4]\d|[01]?\d\d|\d)(?:\:\d{1,5})?<br><br><b>HTTP_X_FORWARDED_FOR: </b><br><b>HTTP_CLIENT_IP: </b><br><b>HTTP_FROM: </b><br><b>HTTP_FORWARDED: </b><br><br><b>HTTP_VIA: </b><br><b>HTTP_PROXY_CONNECTION: </b>~', $content))
								$elite[] = $proxy;
							else
								$anonymous[] = $proxy;
						} else
							$not_anonymous[] = $proxy;

					} else
						$bad[] = $proxy;
	 			} else {
	 				Reporter::messWarning('Curl error on handle ' . $key . ': ' . $err);
	 				$bad[] = $proxy;
	 			}

				 curl_multi_remove_handle($mh, $conn[$key]);
				 curl_close($conn[$key]);
			}

			curl_multi_close($mh);

			Reporter::messDebug('Count of checking proxies for method "' . $method . '" ', count($proxy_list));
			Reporter::messDebug('Anonymous', $anonymous);
			Reporter::messDebug('Not anonymous', $not_anonymous);
			Reporter::messDebug('Not working', $bad);
		}

		private function &checkPost(array &$proxies, $is_anonymous) {
			$elite_list = array();
			$post_anonymous_list = array();
			$post_not_anonymous_list = array();
			$post_bad_list = array();

			$this->checkProxyList($proxies, $elite_list, $post_anonymous_list, $post_not_anonymous_list, $post_bad_list, 'post');

			Reporter::messOfficial('Found ' . (count($elite_list) + count($post_anonymous_list) + count($post_not_anonymous_list)) . ' good ' . ($is_anonymous ? 'anonymous ' : 'not anonymous ') . 'proxies and ' . count($post_bad_list) . ' get_only proxies');

			$db_list =& $this->formatDataForDb($elite_list, 'elite', $is_anonymous);
			$this->updateProxiesTable($db_list, array('px_ip', 'px_port', 'px_status', 'px_anonymous', 'px_check_date'), true);

			$db_list =& $this->formatDataForDb($post_anonymous_list, 'post', $is_anonymous);
			$this->updateProxiesTable($db_list, array('px_ip', 'px_port', 'px_status', 'px_anonymous', 'px_check_date'), true);

			$db_list =& $this->formatDataForDb($post_not_anonymous_list, 'post', $is_anonymous);
			$this->updateProxiesTable($db_list, array('px_ip', 'px_port', 'px_status', 'px_anonymous', 'px_check_date'), true);

			$db_list =& $this->formatDataForDb($post_bad_list, 'get', $is_anonymous);
			$this->updateProxiesTable($db_list, array('px_ip', 'px_port', 'px_status', 'px_anonymous', 'px_check_date'), true);

			unset($db_list);

			return array(
				'elite_list'				=> $elite_list,
				'post_anonymous_list'		=> $post_anonymous_list,
				'post_not_anonymous_list'	=> $post_not_anonymous_list,
				'post_bad_list'				=> $post_bad_list);
		}

		private function checkGoodList() {
			if(time() - $this->last_good_list_reload > _CHECK_RELOAD_GOOD_ || empty($this->good_list)) {
				Reporter::messOfficial('Force reloading good list');
				$this->loadGood();
			}


			Reporter::messOfficial('Recheck good list which contance ' . count($this->good_list) . ' proxies');
			if(empty($this->good_list)) return false;

			$checking_list = array();
			$refreshed_good_list = array();
			while(!empty($this->good_list)) {
				$checking_list = array_slice($this->good_list, 0, _CHECK_COUNT_);
				array_splice($this->good_list, 0, _CHECK_COUNT_);

				$elite_list = array();
				$anonymous_list = array();
				$not_anonymous_list = array();

				$this->checkPiece($checking_list, $elite_list, $anonymous_list, $not_anonymous_list);
				$refreshed_good_list = array_merge($refreshed_good_list, $elite_list, $anonymous_list, $not_anonymous_list);
			}

			$this->good_list = $refreshed_good_list;

			Reporter::messOfficial('After recheck good list containce ' . count($this->good_list) . ' proxies');

			return true;
		}

		private function &formatDataForDb(array &$proxy_list, $status, $is_anonymous = true) {
			$db_list = array();
			foreach($proxy_list as $element)
				$db_list[] = array(
								'px_ip'			=> $element['px_ip'],
								'px_port'		=> $element['px_port'],
								'px_status'		=> $status,
								'px_anonymous'	=> $is_anonymous ? 'y' : 'n',
								'px_check_date'	=> 'now');

			return $db_list;
		}

		private function updateProxiesTable(array &$data, array $fields, $is_good) {
			Reporter::messOfficial('Add ' . count($data) . ($is_good ? ' good' : ' bad') . ' proxies to base');

			if(empty($data))
				return;

			global $db;

			$query = 'INSERT INTO proxies (';
			foreach($fields as $field)
				$query .= $field . ', ';
			$query = substr($query, 0, -2) . ') VALUES';

			reset($data);
			while(list(, $row) = each($data)) {
				$query .= '(';
				foreach($row as $field)
					$query .= DataBase::prepare($field) . ', ';
				$query = substr($query, 0, -2) . '), ';
			}

			$query = substr($query, 0, -2);

			$changes_values = ' ON DUPLICATE KEY UPDATE ';
			foreach($fields as $field)
				$changes_values .= $field . '=values(' . $field . '), ';

			if($is_good)
				$changes_values .= 'px_good_check=px_good_check+1';
			else
				$changes_values .= 'px_bad_check=px_bad_check+1';

			$db->query($query . ' ' . $changes_values);
		}
	}
?>