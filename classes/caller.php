<?php
	defined('_SAFE_ACCESS_') or die('Direct access not permitted');

	class Caller {
		/**
		 * Only this files can be called!
		 *
		 * @var array of strings
		 */
		public static $possible_files = array('check.php', 'links.php', 'good.php');

		public static function call($file, $params = array()) {
			if(!in_array($file, self::$possible_files)) {
				Reporter::messError('Permission denided! File:' . $file);
				return;
			}

			$params['pc_user'] = 'nobody';
			$params['pc_id']   = md5(microtime() + rand(0, 100));

			$shell_cmd = sprintf('nohup ' . _EXECUTABLE_PHP_ . ' %s > temp/' . $file . '.txt 2> /dev/null & echo $!', _INCLUDES_DIR_ . 'call.php' . ' ' . urlencode(_SECRET_CODE_) . ' ' . urlencode(_LOCATION_ . $file) . ' ' . str_replace('&', "\\&", http_build_query($params)));
			Reporter::messOfficial('Shell cmd: ' . $shell_cmd);

			$id = shell_exec($shell_cmd);

			if(!preg_match('/\d+/', $id, $match))
				Reporter::messError('Cannot start process');
			else
				Process::processUpdate($params['pc_id'], array('pc_sys_id' => $match[0]));
		}

		/**
		 * Starts background task
		 *
		 * @param boolean $checker
		 * @param boolean $linker
		 */
		public static function startBackground($checker, $good_checker, $linker) {
			if(_BG_START_BACKGROUND_CHECKING_) {
				Process::processKillDead();
				Process::processFindAlive();

				if($linker) {
					$process_data = Process::processCheckStarted(array('pc_file' => _LOCATION_ . 'links.php'));
					if(empty($process_data)) {
						Caller::call('links.php');
						Reporter::messOfficial('Background links.php started');
					}
				}

				if($good_checker) {
					$process_data = Process::processCheckStarted(array('pc_file' => _LOCATION_ . 'good.php'));
					if(empty($process_data)) {
						Caller::call('good.php');
						Reporter::messOfficial('Background good.php started');
					}
				}

				if($checker) {
					$process_data = Process::processCheckStarted(array('pc_file' => _LOCATION_ . 'check.php'));
					if(empty($process_data)) {
						Caller::call('check.php');
						Reporter::messOfficial('Background check.php started');
					}
				}

				Reporter::messOfficial('Processes are started');
			} else {
				Reporter::messOfficial('Cannot start in background because of conf.php');
			}
		}
	}
?>