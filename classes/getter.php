<?php
	defined('_SAFE_ACCESS_') or die('Direct access not permitted');

	define('_GET_MAX_GOOD_TIME_',	Settings::getSetting('_GET_MAX_GOOD_TIME_')); // in seconds
	define('_GET_MAX_NUMBER_',		900);

	class Getter {
		private $number;
		private $quality;
		private $anonymous;

		public function giveMeProxies() {
			global $db;

			$more_good_proxies = array();

			$db->query($this->formatQuery());
			$good_proxies =& $db->loadResult();
			if(count($good_proxies) < $this->number) {
				$proxies_checker = new ProxiesChecker();
				$more_good_proxies = $proxies_checker->quickFindGood($this->number - count($good_proxies), $this->quality, $this->anonymous, $good_proxies);
			}

			return array_merge($good_proxies, $more_good_proxies);
		}

		private function formatQuery() {
			// number of needed proxies
			$this->number = intval(@$_REQUEST['n']);
			if($this->number <= 0) $this->number = 20;
			if($this->number > _GET_MAX_NUMBER_) $this->number = _GET_MAX_NUMBER_;

			$query = "SELECT * FROM proxies WHERE ";

			// quality of proxies
			switch (@$_REQUEST['q']) {
				case 'g': // get
					$query .= "px_status='get' AND ";
					$this->quality = 'g';
					break;
				case 'p': // post
					$query .= "px_status='post' AND ";
					$this->quality = 'p';
					break;
				case 'e': // elite
					$query .= "px_status='elite' AND ";
					$this->quality = 'e';
					break;
				case 'a': // get & post
				default:
					$query .= "(px_status='post' OR px_status='get') AND ";
					$this->quality = 'a';
			}

			// anonymous/not anonymous
			switch (@$_REQUEST['a']) {
				case 'y':
					$query .= "px_anonymous='y'";
					$this->anonymous = 'y';
					break;
				case 'n':
					$query .= "px_anonymous='n'";
					$this->anonymous = 'n';
					break;
				default:
					$query .= '1';
					$this->anonymous = 'a';
			}

			$query .= ' AND px_check_date > ' . getFormatedTime(_GET_MAX_GOOD_TIME_, 'second') . ' LIMIT ' . $this->number;

			return $query;
		}
	}
?>
