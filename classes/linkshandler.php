<?php
	defined('_SAFE_ACCESS_') or die('Direct access not permitted');

	define('_LINKS_REFRESH_TIME_',		Settings::getSetting('_LINKS_REFRESH_TIME_')); // default 48*60 //minutes
	define('_LINKS_USER_REFRESH_TIME_',	Settings::getSetting('_LINKS_USER_REFRESH_TIME_')); // default 	7*24*60 //minutes
	// depth for user links
	define('_LINKS_DEPTH_',				Settings::getSetting('_LINKS_DEPTH_')); // default 1 //must be not big! max~3
	define('_LINKS_COUNT_',				Settings::getSetting('_LINKS_COUNT_')); // default 20
	define('_LINKS_PROCESS_PER_TIME_',	3*_LINKS_COUNT_);

	define('_LINKS_MIN_BAD_RECHECK_',	Settings::getSetting('_LINKS_MIN_BAD_RECHECK_')); // default 2
	define('_LINKS_MAX_SUXX_PROCENT_',	Settings::getSetting('_LINKS_MAX_SUXX_PROCENT_')); // default 0.2

	class LinksHandler {
		private $links = array();
		private $fathers = array();
		private $process_not_checked = true;
		private $links_crotcher;

		public function LinksHandler() {
			$this->links_crotcher = new LinksCrotcher();
		}

		public function updateLinks() {
			global $db;

			Properties::cleverSetProperty('linker_work', 'Force update links');

			$db->query(
					"(SELECT lk_url FROM good_links gl WHERE lk_origin='user' AND " .
						"NOT EXISTS(SELECT * FROM not_checked_links WHERE nc_url=lk_url) AND " .
						"NOT EXISTS(SELECT * FROM good_links lc WHERE lc.lk_father=gl.lk_id)) UNION " .
					"(SELECT lk_url FROM good_links WHERE lk_origin!='user' AND " .
						"lk_bad_find > " . _LINKS_MIN_BAD_RECHECK_ . " AND " .
						"lk_good_find < lk_bad_find*" . _LINKS_MAX_SUXX_PROCENT_ . ")");

			$bad_user_links =& $db->loadArray();

			if(!empty($bad_user_links)) {
				$db_data = array();
				foreach($bad_user_links as $bl)
					$db_data[] = array('bl_url' => $bl);
				$db->perform('bad_links', $db_data, 'insert_ignore');

				$db->query('DELETE FROM good_links WHERE ' . LinksCrotcher::formatOR($bad_user_links, 'lk_url'));
			}

		}

		public function loadLinks() {
			global $db;

			Properties::cleverSetProperty('linker_work', 'Loading links');

			$this->links = array();
			$this->fathers = array();
			$db->query('SELECT nc_url, nc_father FROM not_checked_links WHERE nc_origin!=\'user\' LIMIT ' . _LINKS_PROCESS_PER_TIME_);
			$res =& $db->loadResult();

			foreach($res as $link_info) {
				$this->links[] = $link_info['nc_url'];
				$this->fathers[] = $link_info['nc_father'];
			}

			if(empty($this->links)) {
				$db->query('SELECT lk_id, lk_url, lk_father FROM good_links WHERE lk_origin!=\'user\' AND lk_refresh_date < ' . getFormatedTime(_LINKS_REFRESH_TIME_) . ' ORDER BY lk_refresh_date LIMIT ' . _LINKS_PROCESS_PER_TIME_);
				$res =& $db->loadResult();

				foreach($res as $link_info) {
					$this->links[$link_info['lk_id']] = $link_info['lk_url'];
					$this->fathers[$link_info['lk_id']] = $link_info['lk_father'];
				}

				$this->process_not_checked = false;
			} else
				$this->process_not_checked = true;
		}

		public function findProxiesLinks() {
			global $db;

			$this->updateLinks();

			while(true) {
				$db->query('SELECT IF(' .
						'EXISTS(SELECT * FROM not_checked_links)OR ' .
						'EXISTS(SELECT * FROM good_links WHERE  ' .
							'(lk_origin!=\'user\' AND lk_refresh_date < ' . getFormatedTime(_LINKS_REFRESH_TIME_) . ') OR ' .
							'(lk_origin=\'user\' AND lk_refresh_date < ' . getFormatedTime(_LINKS_USER_REFRESH_TIME_) . ')), 1, 0) v');
				if($db->loadValue() == 0)
					break;

				$this->loadLinks();

				while(!empty($this->links)) {
					$this->links_crotcher->loadAndParsePages($this->links, $this->process_not_checked, $this->fathers);
					$this->commitLinks();

					$this->loadLinks();
				}

				$this->processUserLinks();
			}

			Properties::cleverSetProperty('linker_work', 'Finished');
		}

		public function getLinksFromSE() {
			$links = array();

			// TODO:
			// need to download links from google

			return $links;
		}

		private function processUserLinks() {
			global $db;

			$pd = new PageDownloader();
			$pf = new ProxiesFilter();

			Properties::cleverSetProperty('linker_work', 'Process user links');

			while(true) {
				$db->query('SELECT nc_url FROM not_checked_links WHERE nc_origin=\'user\' LIMIT 1');
				$user_links =& $db->loadArray();

				if(empty($user_links)) {
					$db->query('SELECT lk_url FROM good_links WHERE lk_origin=\'user\' AND lk_refresh_date < ' . getFormatedTime(_LINKS_USER_REFRESH_TIME_) . ' LIMIT 1');
					$user_links =& $db->loadArray();
				}

				list(,$processed_url) = each($user_links);

				Properties::cleverSetProperty('linker_work', 'Process user link: ' . $processed_url);
				Reporter::messDebug('Load \'user\' link');

				$db->query('SELECT IF(EXISTS(SELECT * FROM not_checked_links WHERE nc_origin!=\'user\'), 1, 0) v');
				if(empty($user_links) || $db->loadValue())
					break;

				// commit as processed
				$db->query("INSERT INTO good_links(lk_url, lk_refresh_date, lk_origin, lk_hash) VALUES (" . DataBase::prepare($processed_url) . ", NOW(), 'user', " . DataBase::prepare($this->getRandom()) . ") ON DUPLICATE KEY UPDATE lk_refresh_date=NOW()");
				$db->query('SELECT lk_id FROM good_links WHERE lk_url=' . DataBase::prepare($processed_url));

				$father = $db->loadValue();

				// process links
				for($i=0; $i<_LINKS_DEPTH_; $i++) {
					$new_links = array();

					while(!empty($user_links)) {
						Reporter::messOfficial('Count of user links :' . count($user_links));

						$piece_links = array_slice($user_links, 0, _LINKS_COUNT_);
						array_splice($user_links, 0, _LINKS_COUNT_);

						Reporter::messDebug('Links', $piece_links);

						$pd->setUrls($piece_links);
						$pages =& $pd->download(PD_BODY_ONLY);

						$good_links = array();
						$good_hashes = array();
						$bad_links = array();

						foreach($pages as $key => $page_src) {
							if($hash = $this->links_crotcher->parsePage($page_src, $father, $piece_links[$key], false)) {
								$good_links[] = $piece_links[$key];
								$good_hashes[] = $hash;
							} else
								$bad_links[] = $piece_links[$key];

							$new_links = array_unique(
												array_merge($new_links, $pf->filterLinks($page_src, $piece_links[$key])));
						}

						unset($pages);

						$this->links_crotcher->addLinks($good_links, $father, 'page', true, 'good', $good_hashes);
						$this->links_crotcher->addLinks($bad_links, $father, 'page', true, 'bad');
					}

					Reporter::messOfficial('Found ' . count($new_links) . ' links on step ' . $i);

					$user_links = $new_links;
				}

				if(_LINKS_DEPTH_ > 0)
					$this->links_crotcher->addLinks($user_links, $father, 'page');

				unset($user_links);

				$db->query('DELETE FROM not_checked_links WHERE nc_url=' . DataBase::prepare($processed_url));
			}

			Reporter::messOfficial("Finish loading 'user' links");
		}

		private function commitLinks() {
			// All commit processed in LinksCrotcher
			Reporter::messOfficial('Commit ' . count($this->links) . ' links');
		}

		private function getRandom() {
			return strtoupper(md5(microtime()));
		}
	}
?>