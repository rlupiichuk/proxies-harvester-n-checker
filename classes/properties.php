<?php
	defined('_SAFE_ACCESS_') or die('Direct access not permitted');

	class Properties {
		public static function getProperty($name) {
			global $db;

			$db->query('SELECT pt_value FROM properties WHERE pt_name=' . DataBase::prepare($name));
			if(!$value = $db->loadFirst())
				throw new Exception('Cannot found property: ' . $name . ' for get');

			return $value['pt_value'];
		}

		public static function setProperty($name, $value) {
			global $db;

			$db->query('UPDATE properties SET pt_value=' . DataBase::prepare($value) . ' WHERE pt_name=' . DataBase::prepare($name));
		}

		public static function cleverSetProperty($name, $value) {
			self::setProperty($name, $value);

			switch($name) {
				case 'checker_work':
					self::setProperty('checker_updated', 'now');
					break;

				case 'linker_work':
					self::setProperty('linker_updated', 'now');
					break;

				case 'good_work':
					self::setProperty('good_updated', 'now');
					break;
			}
		}
	}
?>