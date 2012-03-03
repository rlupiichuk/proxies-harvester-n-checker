<?php
	defined('_SAFE_ACCESS_') or die('Direct access not permitted');

	class Process {
		static function processStart($pc_id, $pc_user, $pc_file) {
			global $db;

			$db->perform('processes', array(
				 array(
				 	'pc_id'		=> $pc_id,
				 	'pc_started'=> 'now',
				 	'pc_user'	=> $pc_user,
				 	'pc_file'	=> self::prepareFile($pc_file),
				 	'pc_status'	=> '1')), 'insert_update');
		}

		static function processKill($pc_id) {
			global $db;

			$db->query("SELECT pc_sys_id FROM processes WHERE pc_id='$pc_id'");
			exec('kill ' . $db->loadValue());

			self::processComplete($pc_id);
		}

		static function processKillWithFile($file) {
			if(!in_array($file, Caller::$possible_files))
				Reporter::messError('File ' . $file . ' not found in processKillWithFile');

			$id = intval(shell_exec('ps ax | grep ' . _EXECUTABLE_PHP_ . ' | grep ' . $file . ' | grep call.php'));
			Reporter::messOfficial($file . ' process will be killed id: ' . $id);
			if($id)
				shell_exec('kill ' . $id);
		}

		/**
		 * This funtion update properties of existing process
		 *
		 * @param process id $pc_id
		 * @param array of new properties $new_properties
		 */
		static function processUpdate($pc_id, $new_properties) {
			global $db;

			$new_properties['pc_id'] = $pc_id;
			$db->perform('processes', array($new_properties), 'insert_update');
		}

		static function processComplete($pc_id) {
			global $db;

			$db->query("UPDATE processes SET pc_status='0' WHERE pc_id='$pc_id'");
		}

		static function processCheckStarted(array $params) {
			global $db;

			if(isset($params['pc_file']))
				$params['pc_file'] = self::prepareFile($params['pc_file']);

			$query = 'SELECT * FROM processes WHERE ';
			foreach($params as $key => $value)
				if(in_array($key, array('pc_id', 'pc_user', 'pc_file')))
					$query .= $key . '=' . DataBase::prepare($value) . ' AND ';
			$query .= "pc_status='1' LIMIT 1";

			$db->query($query);

			return $db->loadFirst();
		}

		static function processKillDead() {
			global $db;

			$db->query("SELECT pc_id, pc_sys_id, pc_file FROM processes WHERE pc_status='1'");
			$processes =& $db->loadResult();

			foreach($processes as $process) {
				$id = trim(shell_exec('ps ax | grep ' . _EXECUTABLE_PHP_ . ' | grep ' . urlencode($process['pc_file'])));

				if(strpos($id, 'call.php') === false)
					$db->query("UPDATE processes SET pc_status='0' WHERE pc_id='$process[pc_id]'");
			}
		}

		static function processFindAlive() {
			global $db;

			foreach(Caller::$possible_files as $file) {
				$line = trim(shell_exec('ps ax | grep ' . _EXECUTABLE_PHP_ . ' | grep ' . $file));

				if($line && strpos($line, 'call.php') !== false) {
					$db->query("SELECT IF(EXISTS(SELECT * FROM processes WHERE pc_status='1' AND pc_file='" . _LOCATION_ . $file . "'), 1, 0) as _f_");
					if($db->loadValue() == 0) {
						$db->perform('processes', array(
							array(
								'pc_id'		=> md5(microtime() + rand(0, 100)),
								'pc_sys_id'	=> intval($line),
								'pc_file'	=> self::prepareFile(_LOCATION_ . $file),
								'pc_status'	=> '1',
								'pc_user'	=> 'nobody',
								'pc_started'=> 'now')), 'insert_update', '1', array('pc_id', 'pc_started'));
					}
				}
			}
		}

		private static function prepareFile($file) {
			return str_replace("\\", '/', $file);
		}
	}
?>