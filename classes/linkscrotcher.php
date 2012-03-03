<?php
	defined('_SAFE_ACCESS_') or die('Direct access not permitted');

	define('_CHECK_MAX_BAD_CHECK_',		Settings::getSetting('_CHECK_MAX_BAD_CHECK_'));
	define('_CHECK_MIN_GOOD_PERCENT_',	Settings::getSetting('_CHECK_MIN_GOOD_PERCENT_'));

	define('_LC_LINKS_PER_TIME_',		30);
	define('_LC_MIN_COUNT_PROXIES_',	Settings::getSetting('_LC_MIN_COUNT_PROXIES_')); // default 5

	class LinksCrotcher {
		private $pd;
		private $pf;

		public function LinksCrotcher() {
			$this->pd = new PageDownloader();
			$this->pf = new ProxiesFilter();
		}

		/**
		 * Adds links to db
		 *
		 * @param array $links
		 * @param 'user'|'page'|'google'|'msn'|'yahoo' $origin
		 */
		public function addLinks(array &$links, $father, $origin, $checked = false, $quality = 'good', array &$hashes = array()) {
			global $db;

			$db_data = array();

			if($checked) {
				if($quality == 'good') {
					if(count($links) != count($hashes))
						Reporter::messError('Cannot allocate hashes');

					$keys = array_flip($links);
					$new_links =& $this->getNotExistingLinks($links, 'good_links');

					foreach($new_links as $link)
						$db_data[] = array('lk_url' => $link, 'lk_father' => $father, 'lk_origin' => $origin, 'lk_refresh_date' => 'now', 'lk_hash' => $hashes[$keys[$link]]);

					$db->perform('good_links', $db_data, 'insert_ignore');
				} else {
					$new_links =& $this->getNotExistingLinks($links, 'bad_links');

					foreach($new_links as $link)
						$db_data[] = array('bl_url' => $link);

					$db->perform('bad_links', $db_data, 'insert_ignore');
				}
			} else {
				$new_links =& $this->getNotExistingLinks($links, 'not_checked_links');

				foreach($new_links as $link)
					$db_data[] = array('nc_url' => $link, 'nc_father' => $father, 'nc_origin' => $origin);

				$db->perform('not_checked_links', $db_data, 'insert_ignore');
			}

			unset($db_data);
		}

		private function &getNotExistingLinks(array &$links, $table_to_insert) {
			if(empty($links)) {
				$new_links = array();
				return $new_links;
			}

			global $db;

			switch ($table_to_insert) {
				case 'good_links':
					$field1 = 'nc_url';
					$table1 = 'not_checked_links';

					$field2 = 'bl_url';
					$table2 = 'bad_links';
					break;

				case 'bad_links':
					$field1 = 'nc_url';
					$table1 = 'not_checked_links';

					$field2 = 'lk_url';
					$table2 = 'good_links';
					break;

				case 'not_checked_links':
					$field1 = 'lk_url';
					$table1 = 'good_links';

					$field2 = 'bl_url';
					$table2 = 'bad_links';
					break;

				default:
					Reporter::messError('Not existing table passed');
			}

			$db->query(
				'(SELECT ' . $field1 . ' FROM ' . $table1 . ' WHERE ' . $this->formatOR($links, $field1) . ') UNION ' .
				'(SELECT ' . $field2 . ' FROM ' . $table2 . ' WHERE ' . $this->formatOR($links, $field2) . ')');
			$existing_links =& $db->loadArray();

			$new_links = array_diff($links, $existing_links);
			unset($existing_links);

			return $new_links;
		}

		public static function formatOR(array &$values, $field, $table = '', $add_exists = false) {
			if(empty($values))
				return '1';

			$sql = $add_exists ? 'EXISTS(SELECT * FROM ' . $table . ' WHERE ' : '';

			foreach($values as $value)
				$sql .= $field . '=' . DataBase::prepare($value) . ' OR ';

			return substr($sql, 0, -4) . ($add_exists ? ')' : '');
		}


		public function parsePage(&$source, $father = null, $page_link = false, $need_find_links = true) {
			global $db;

			$proxies =& $this->pf->filterProxies($source);

			Reporter::messOfficial('Found ' . count($proxies) . ' proxies' . ($page_link ? ' on ' . $page_link : ''));

			$hash_string = '';
			$db_list = array();
			foreach($proxies as $proxy) {
				$db_list[] = array(
						'px_ip'		=> $proxy['ip'],
						'px_port'	=> $proxy['port'],
						'px_status'	=> 'not_checked');

				$hash_string .= $proxy['ip'] . ':' . $proxy['port'] . "\n";
			}
			$db->perform('proxies', $db_list, 'insert_ignore');
			$count_new_proxies = $db->affectedRows();

			$has_proxies = (count($proxies) > _LC_MIN_COUNT_PROXIES_) && $this->isNotBadProxy($proxies);
			$has_new_proxies = $has_proxies && ($count_new_proxies > 0);

			Reporter::messOfficial($has_new_proxies ? 'Found new proxies' : 'Not found new proxies');

			if($need_find_links && $has_new_proxies) {
				$links =& $this->pf->filterLinks($source, $page_link);
				$this->addLinks($links, $father, 'page', false);
			}

			unset($proxies);
			unset($links);
			unset($db_list);

			return $has_proxies ? md5($hash_string) : false;
		}

		public function loadAndParsePages(array &$links, $process_not_checked, array &$fathers = array()) {
			global $db;

			Properties::cleverSetProperty('linker_work', 'Force crotching ' . ($process_not_checked ? 'not checked' : 'good links') . ' links');

			$this->pd->setUrls($links);

			$pages =& $this->pd->download(PD_BODY_ONLY);

			Reporter::messOfficial('Count of downloaded pages ' . count($pages));

			foreach($links as $key => $link) {
				Reporter::messOfficial('Process ' . $link);

				if(isset($pages[$key]) && ($hash = $this->parsePage($pages[$key], @$fathers[$key], $link))) {
					if($process_not_checked) {
						$db->query('INSERT IGNORE INTO good_links(lk_url, lk_origin, lk_father, lk_refresh_date, lk_hash) ' .
							'SELECT nc_url, nc_origin, nc_father, NOW(), ' . DataBase::prepare($hash) . ' FROM not_checked_links WHERE nc_url=' . DataBase::prepare($link));

						$db->query('DELETE FROM not_checked_links WHERE nc_url=' . DataBase::prepare($link));
					} else {
						$db->query('SELECT IF(EXISTS(SELECT * FROM good_links WHERE lk_hash=' . DataBase::prepare($hash) . '), 1, 0) v');

						if($db->loadValue())
							$db->query('UPDATE good_links SET lk_refresh_date=NOW(), lk_bad_find=lk_bad_find+1 WHERE lk_url=' . DataBase::prepare($link));
						else {
							$db->query('UPDATE good_links SET lk_refresh_date=NOW(), lk_good_find=lk_good_find+1, lk_hash=' . DataBase::prepare($hash) . ' WHERE lk_url=' . DataBase::prepare($link));

							if($db->affectedRows() == 0) {
								$db->query('INSERT IGNORE INTO bad_links(bl_url) VALUES(' . DataBase::prepare($link) . ')');
								$db->query('DELETE FROM good_links WHERE lk_url=' . DataBase::prepare($link));
							}
						}
					}
				} else {
					$db->query('INSERT IGNORE INTO bad_links(bl_url) VALUES(' . DataBase::prepare($link) . ')');

					if($process_not_checked)
						$db->query('DELETE FROM not_checked_links WHERE nc_url=' . DataBase::prepare($link));
					else
						$db->query('DELETE FROM good_links WHERE lk_url=' . DataBase::prepare($link));
				}
			}

			unset($pages);
		}

		private function isNotBadProxy(array &$proxies) {
			if(empty($proxies))
				return false;

			global $db;

			$query = 'SELECT COUNT(*) FROM proxies WHERE (';

			foreach($proxies as $proxy)
				$query .= '(px_ip=' . DataBase::prepare($proxy['ip']) . ' AND px_port=' . DataBase::prepare($proxy['port']) . ')OR';

			$db->query(substr($query, 0, -2) . ') AND px_status=\'bad\' AND px_bad_check > ' . _CHECK_MAX_BAD_CHECK_ . ' AND px_good_check < px_bad_check*' . _CHECK_MIN_GOOD_PERCENT_);

			return $db->loadValue() < count($proxies);
		}
	}
?>