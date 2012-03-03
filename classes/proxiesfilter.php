<?php
	defined('_SAFE_ACCESS_') or die('Direct access not permitted');

	class ProxiesFilter {

		/**
		 * Return all links from page source
		 *
		 * @param string $text_with_links
		 * @param string $page_link
		 * 		link for this page, for complite local links
		 * @return array
		 */
		public function filterLinks($text_with_links, $page_link = false) {
			static $regexp = "~(?:(?:(?:src|href|url)=[\"'`]?)|(http://))([^\s\"'`<>\\\\()]+)~iS";

			if(preg_match_all($regexp, $text_with_links, $matches)) {
				$links =& $matches[2];

				foreach($links as $key => &$link)
					if($matches[1][$key] == 'http://')
						$link = 'http://' . $link;

				if($page_link) {
					foreach($links as $key => &$link) {
						if(($new_link = compliteLink($link, $page_link)) !== false)
							$link = $new_link;
						else
							unset($links[$key]);
					}
				}


				foreach ($links as $key => &$link)
					if(($new_link = $this->filterLink($link)) !== false)
						$link = $new_link;
					else
						unset($links[$key]);

				return array_unique($links);
			} else
				return array();
		}

		/**
		 * This function distinguish proxeis from simple text string
		 * function returns array of proxies (ip:port)
		 *
		 * @param string $proxy_text
		 * @return array proxy_list
		 */
		function &filterProxies($proxy_text) {
			static $regexp = null;
			if(is_null($regexp)) {
				$ip_range		= '(25[0-5]|2[0-4]\d|[01]?\d\d|\d)';
				$port			= '(\d{1,5})';

				$temp_sep		= '(?:(?:<[^>]*>)?\s+)*';
				$ip_separator	=  $temp_sep . '\.' . $temp_sep;
				$port_separator	= '(?:(?:<[^>]*>\s*)+|[\s;:,\]\|]+|\s*ports\s*)+';

				$regexp = "/$ip_range$ip_separator$ip_range$ip_separator$ip_range$ip_separator$ip_range$port_separator$port/iS";
			}

			$result = array();

			if(preg_match_all($regexp, $proxy_text, $match)) {

				$proxies_count = count($match[0]);

				for ($i=0; $i<$proxies_count; $i++)
					$result[] = $match[1][$i] . '.' .$match[2][$i] . '.' . $match[3][$i] . '.' . $match[4][$i] . ':' . $match[5][$i];

				return $this->removeDup($result);
			} else
				return $result;
		}

		function &removeDup(&$proxies) {
			if(empty($proxies)) {$temp = array(); return $temp;}

			$entrega = array();
			$unique_proxies = array_unique($proxies);

			foreach($unique_proxies as $up) {
				list($ip, $port) = explode(':', $up);
				$entrega[] = array('ip' => $ip, 'port' => $port);
			}

			return $entrega;
		}

		private function deleteSid($link) {
			$result = preg_replace('~[\w_]*(?:sid|session)=[^&]*&?~i', '', $link);

			if($result[strlen($result)-1] == '?' || $result[strlen($result)-1] == '&')
				return substr($result, 0, -1);
			else
				return $result;
		}

		private function filterLink($link) {
			static $regexp  = null;
			static $regexp2 = null;
			if(is_null($regexp)) {
				$accepted_domains = "\.edu|\.gov|\.mil|\.net|\.org|\.biz|\.pro|\.info|\.tv|\.com|\.ae|\.af|\.ag|\.ai|\.al|\.am|\.an|\.ao|\.aq|\.ar|\.as|\.at|\.au|\.aw|\.az|\.ba|\.bb|\.bd|\.be|\.bf|\.bg|\.bh|\.bi|\.bj|\.bm|\.bn|\.bo|\.br|\.bs|\.bt|\.bv|\.bw|\.by|\.bz|\.ca|\.cc|\.cf|\.cg|\.ch|\.ci|\.ck|\.cl|\.cm|\.cn|\.co|\.cr|\.cu|\.cv|\.cx|\.cy|\.cz|\.de|\.dj|\.dk|\.dm|\.do|\.dz|\.ec|\.ee|\.eg|\.eh|\.es|\.et|\.fi|\.fj|\.fk|\.fm|\.fo|\.fr|\.ga|\.gd|\.ge|\.gf|\.gh|\.gi|\.gl|\.gm|\.gn|\.gp|\.gq|\.gr|\.gt|\.gu|\.gw|\.gy|\.hk|\.hm|\.hn|\.hr|\.ht|\.hu|\.id|\.ie|\.il|\.in|\.io|\.iq|\.ir|\.is|\.it|\.jm|\.jo|\.jp|\.ke|\.kg|\.kh|\.ki|\.km|\.kn|\.kp|\.kr|\.kw|\.ky|\.kz|\.la|\.lb|\.lc|\.li|\.lk|\.lr|\.ls|\.lt|\.lu|\.lv|\.ly|\.ma|\.mc|\.md|\.mg|\.mh|\.ml|\.mm|\.mn|\.mo|\.mp|\.mq|\.mr|\.ms|\.mt|\.mu|\.mv|\.mw|\.mx|\.my|\.mz|\.na|\.nc|\.ne|\.nf|\.ng|\.ni|\.nl|\.no|\.np|\.nr|\.nt|\.nz|\.om|\.pa|\.pe|\.pf|\.pg|\.ph|\.pk|\.pl|\.pm|\.pn|\.pr|\.pt|\.pw|\.py|\.qa|\.re|\.ro|\.ru|\.rw|\.sa|\.sb|\.sc|\.sd|\.se|\.sg|\.sh|\.si|\.sj|\.sk|\.sl|\.sm|\.sn|\.so|\.sr|\.st|\.sv|\.sy|\.sz|\.td|\.tf|\.tg|\.th|\.tj|\.tk|\.tm|\.tn|\.to|\.tp|\.tr|\.ts|\.tt|\.tv|\.tw|\.tz|\.ua|\.ug|\.uk|\.us|\.uy|\.uz|\.va|\.vc|\.ve|\.vg|\.vi|\.vn|\.vu|\.wf|\.ws|\.ye|\.za|\.zm|\.zr|\.zw";

				$playlists = "\.asx|\.ifo|\.lap|\.lst|\.m3u|\.pls)";
				$video = "\.aac|\.ape|\.3g2|\.3gp|\.asf|\.avi|\.bik|\.dat|\.divx|\.flac|\.lpac|\.mac|\.m1v|\.m2v|\.mkv|\.mov|\.mp2v|\.mp4|\.mpe|\.mpeg|\.mpg|\.mpv|\.mpv2|\.ogm|\.qt|\.ram|\.rm|\.rv|\.smk|\.swf|\.vob|\.wm|\.wmv|\.xvid";
				$sound = "\.ac3|\.aif|\.aifc|\.aiff|\.au|\.bpl|\.b4s|\.it|\.kar|\.mat|\.mid|\.midi|\.mka|\.mod|\.mp1|\.mp2|\.mp3|\.mpa|\.nsa|\.ogg|\.paf|\.pvf|\.ra|\.rmi|\.s3m|\.sd2|\.sds|\.sf|\.snd|\.stm|\.voc|\.wav|\.wax|\.wpl|\.wma|\.wmx|\.w64|\.xi|\.xm";
				$photo = "\.bmp|\.dib|\.rle|\.cr2|\.crw|\.dcr|\.djv|\.djvu|\.iw4|\.emf|\.eps|\.fpx|\.gif|\.icl|\.icn|\.ico|\.cur|\.ani|\.iff|\.lbm|\.ilbm|\.jp2|\.jpx|\.jpk|\.j2k|\.jpc|\.j2c|\.jpg|\.jpeg|\.jpe|\.jif|\.jfif|\.thm|\.mrw|\.nef|\.orf|\.pbm|\.pcd|\.pcx|\.dcx|\.pef|\.pgm|\.pic|\.pict|\.pct|\.pix|\.png|\.ppm|\.psd|\.psp|\.raf|\.ras|\.032|\.tif|\.tiff|\.raw|\.mos|\.fff|\.cs1|\.bay|\.rsb|\.sgi|\.rgb|\.rgba|\.bw|\.int|\.inta|\.srf|\.tga|\.ttf|\.ttc|\.wbm|\.wbmp|\.x3f|\.xbd|\.xpm";
				$archive = "\.deb|\.rpm|\.rar|\.zip|\.gzip|\.7z|\.cab|\.uue|\.jar|\.ace|\.arj|\.arc|\.bz2|\.gz|\.zlib|\.lha|\.lzh|\.taz|\.tbz|\.tgz|\.z|\.sqx|\.zoo|\.ha|\.uha|\.rk|\.r\d{2}|\.a\d{2}|\.c\d{2}|\.lzw";
				$cd_images = "\.mds|\.ccd|\.cue|\.iso|\.bwt|\.cdi|\.nrg|\.pdi";
				$other = "\.exe|\.msi|\.pdf|\.chm|\.css|\.js";

				$ip_range = '(?:25[0-5]|2[0-4]\d|[01]?\d\d|\d)';
				$port = '(?:\d{1,5})';

				$stop_words = preg_replace('~[\n\r]+~', '|', trim(Settings::getSetting('_STOP_WORDS_')));


				$regexp  = "~^https?://(?:[^/]+(?:$accepted_domains)[^/]*|$ip_range\.$ip_range\.$ip_range\.$ip_range(?::$port)?)(?:/[^\s\\\\]*)?$~iS";
				$regexp2 = "~(?:(?:$sound|$photo|$archive|$cd_images|$other)(?:[/?]|$))" . ($stop_words ? "|(?:$stop_words)" : '') . "~iS";
			}

			if(!preg_match($regexp, $link) || preg_match($regexp2, $link))
				return false;

			$link = $this->deleteSid(str_replace('&amp;', '&', trim($link))) . '#';
			$link = substr($link, 0, strpos($link, '#'));

			return $link;
		}
	}
?>