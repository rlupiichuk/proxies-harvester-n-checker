<?php
	defined('_SAFE_ACCESS_') or die('Direct access not permitted');

	require_once(_CLASSES_DIR_ . 'reporter.php');

	function __autoload($class_name) {
		require_once(_CLASSES_DIR_ . strtolower($class_name) . '.php');
	}

	function getFormatedTime($value, $measure = 'minute') {
		 return DataBase::prepare(
		 			date('Y-m-d H:i:s', strtotime('-' . $value . ' ' . $measure)));
	}

	function compliteLink($link_to_complite, $link_to_complite_with) {
		static $last_parse = array('link' => '', 'host' => '', 'dir' => '');

		if($last_parse['link'] == $link_to_complite_with) {
			$host = $last_parse['host'];
			$dir = $last_parse['dir'];
		} else {
			$url_info = parse_url($link_to_complite_with);
			if(!isset($url_info['host'])) {
				Reporter::messWarning('Cannot find host in url: ' . $link_to_complite_with);
				$host = $dir = $last_parse['dir'] = $last_parse['host'] = '';
			} else {
				$host = (@$url_info['scheme'] ? $url_info['scheme'] : 'http') . '://' . $url_info['host'] . (@$url_info['port'] ? ':'.$url_info['port'] : '');

				if(strrpos($url_info['path'], '/') !== false)
					$dir = $host . substr($url_info['path'], 0, strrpos($url_info['path'], '/') + 1);
				else
					$dir = $host . '/';

				$last_parse['link'] = $link_to_complite;
				$last_parse['host'] = $host;
				$last_parse['dir'] = $dir;
			}
		}

		if($host != '') {
			if(substr($link_to_complite, 0, 7) != 'http://') {
				if($link_to_complite[0] == '/')
					return $host . $link_to_complite;
				else
					return $dir . $link_to_complite;
			} else
				return $link_to_complite;
		} else
			if(substr($link_to_complite, 0, 7) == 'http://')
				return $link_to_complite;
			else
				return false;
	}

	function timeTo($time) {
		$new_time = strtotime('now') - strtotime($time);

		$days = floor($new_time / 86400);

		return  ($days > 0 ? $days . ' days ' : '') . date('H:i:s', $new_time) . ' ago';
	}

	function emptyNbsp($value) {
		if(empty($value))
			return '&nbsp;';
		else
			return $value;
	}
?>