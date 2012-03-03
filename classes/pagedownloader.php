<?php
	defined('_SAFE_ACCESS_') or die('Direct access not permitted');

	define('_DL_TIMEOUT_',		60);
	define('_DL_THREADS_',		50);
	define('_DL_MAX_FILESIZE_',	1024*1024); // 1 Mb

	define('PD_BODY_ONLY',	-1);
	define('PD_BODY',		1);
	define('PD_HEADER',		2);
	define('PD_COOKIE',		4);
	define('PD_URL',		8);
	define('PD_ALL',		PD_BODY | PD_HEADER | PD_COOKIE | PD_URL);

	//PageDownloader::$_proxies_worker = new Proxies();

	class PageDownloader {
		public $timeout = _DL_TIMEOUT_; // in seconds
		public $max_threads = _DL_THREADS_;//links on one curl_multi
		public $urls = array();

		// see options in $op_array
		function PageDownloader($options = array()) {
			$op_array = array(
				'timeout',
				'max_threads'
			);

			foreach($op_array as $option)
				if(isset($options[$option]))
					$this->$option = $options[$option];
		}

		function clearUrls() {
			$this->urls = array();
			$this->conf = array();
		}

		function setUrls($urls) {
			$this->urls = $urls;
			$this->conf = array();
		}

		private function &downloadBodyOnlyWithoutProxies() {
			$result = array();
			reset($this->urls);

			$mh = curl_multi_init();

			while ((list($key, $url) = each($this->urls)) != null) {
				$conn[$key] = curl_init(str_replace('&amp;', '&', $url));

		        curl_setopt($conn[$key], CURLOPT_FAILONERROR, 1);
		        if(!@curl_setopt($conn[$key], CURLOPT_FOLLOWLOCATION, 1))
					Reporter::messWarning('CURLOPT_FOLLOWLOCATION error');
		        curl_setopt($conn[$key], CURLOPT_MAXREDIRS, 1);
				curl_setopt($conn[$key], CURLOPT_ENCODING, '');
				curl_setopt($conn[$key], CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($conn[$key], CURLOPT_TIMEOUT, $this->timeout);
				curl_setopt($conn[$key], CURLOPT_BUFFERSIZE, 4096);

				curl_setopt($conn[$key], CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.5; Windows NT 5.0)');

				curl_multi_add_handle ($mh, $conn[$key]);
			}

			$check_buzz = 0;

			do {
				$mrc = curl_multi_exec($mh, $active);

				reset($conn);

				while(list($key,) = each($conn)) {
					$size = @curl_getinfo($conn[$key], CURLINFO_SIZE_DOWNLOAD);

					if($size > _DL_MAX_FILESIZE_) {
						curl_multi_remove_handle($mh, $conn[$key]);
						@curl_close($conn[$key]);
						unset($conn[$key]);

						Reporter::messWarning('Max filesize error forced url:' . $this->urls[$key]);
					}
				}

				if($check_buzz++ > 10000)
					break;
			} while ($mrc == CURLM_CALL_MULTI_PERFORM);

			$check_buzz = 0;

			while ($active && $mrc == CURLM_OK) {
				// wait for network
				if (curl_multi_select($mh) != -1)
					do {
						$mrc = curl_multi_exec($mh, $active);

						if($check_buzz++ > 10000)
							break(2);
					} while ($mrc == CURLM_CALL_MULTI_PERFORM);
			}

			if ($mrc != CURLM_OK)
				Reporter::messWarning("Curl multi read error $mrc");

			// retrieve data
			reset($conn);
			while(list($key,) = each($conn)) {
				if(isset($conn[$key]) && curl_errno($conn[$key]) === 0) {
					$content = curl_multi_getcontent($conn[$key]);

					if($content != '')
						$result[$key] = $content;
					else
						Reporter::messWarning("Curl error on handle " . $key . ": null page/no header  returned");
				} else
					Reporter::messWarning("Curl error on handle " . $key . (isset($conn[$key]) ? ": " . curl_error($conn[$key]) : ''));

				curl_multi_remove_handle($mh, $conn[$key]);
				curl_close($conn[$key]);
			}

			curl_multi_close($mh);

			return $result;
		}


		/**
		 * This function download pages with many threads
		 *
		 * @param int $info_level
		 *
		 * @return if (!$body_only) array([0] => array('body' => 'bla bla bla',url' => 'http://yo.co.uk', 'cookie' => 'sfsjhk', 'header' => 'OK 200.....'), ...)
		 *			else array([0] => 'bla bla bla', [1] => 'bla2', ...);
		 */
		public function &download($info_level = PD_ALL) {
			if($info_level == PD_BODY_ONLY)
				return $this->downloadBodyOnlyWithoutProxies();
			else
				throw new Exception('Now supported only PD_BODY_ONLY');
		}
	}
?>