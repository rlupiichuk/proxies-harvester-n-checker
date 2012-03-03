<?php
	defined('_SAFE_ACCESS_') or die('Direct access not permitted');

	class DataBase {
		private $is_select = false;
		private $db_link;
		private $res;

		public public function DataBase($dsn = _DSN_) {
			preg_match('/^([\w\d_\.]+):\/\/([\w\d_\.]+):([\w\d_\.]*)@([\w\d_\.]+)\/([\w\d_\.]+)$/i', $dsn, $matches);

			if (!$this->db_link = mysql_connect($matches[4], $matches[2], $matches[3])) Reporter::messError(mysql_error());
			if (!mysql_select_db($matches[5], $this->db_link))
				Reporter::messError('Cannot select db');

			register_shutdown_function(array(&$this, '_closeConnection'));
		}

		public function query($query) {
			Reporter::messDebug('Query:', substr($query, 0, 500));

			if (!is_null($this->res) && $this->is_select) mysql_free_result($this->res);

			if (!$this->res = mysql_query($query, $this->db_link)) Reporter::messError('Query error.<br />Query:' . $query . '<br />Mysql say: ' . mysql_error());
			$this->is_select = (strtolower(substr($query, 0, 6)) == 'select');
		}

		public function &loadResult($key = null) {
			$result = array();

			if(is_null($key))
				while ($row = mysql_fetch_array($this->res, MYSQL_ASSOC))
					$result[] = $row;
			else
				while ($row = mysql_fetch_array($this->res, MYSQL_ASSOC)) {
					$arr_key = $row[$key];
					unset($row[$key]);

					$result[$arr_key] = $row;
				}

			return $result;
		}

		public function &loadArray() {
			$result = array();

			while ($row = mysql_fetch_row($this->res))
				$result[] = $row[0];

			return $result;
		}

		public function &loadArrayWithKey($key) {
			$result = array();

			while ($row = mysql_fetch_assoc($this->res)) {
				$row_key = $row[$key];
				unset($row[$key]);

				$result[$row_key] = array_shift($row);
			}

			return $result;
		}

		public function &loadField($field = null) {
			$result = array();

			if (is_null($field))
				while($row =mysql_fetch_row($this->res))
					$result[] = $row[0];
			else
				while($row = mysql_fetch_array($this->res, MYSQL_ASSOC))
					$result[] = $row[$field];

			return $result;
		}

		public function loadFirst() {
			return mysql_fetch_assoc($this->res);
		}

		public function loadValue() {
			$value = mysql_fetch_row($this->res);
			return $value[0];
		}

		public function affectedRows() {
			return mysql_affected_rows($this->db_link);
		}

		public function perform($table, $data, $action = 'insert', $where = '1', $last_values_keys = array()) {
			if(empty($data))
				return null;

			reset($data);
			$ignore = '';

			switch($action) {
			case 'insert_ignore':
				$ignore = 'ignore ';
			case 'insert':
				$query = 'insert ' . $ignore . 'into ' . $table . ' (';
				list(,$row) = each($data);
				$fields = array_keys($row);
				foreach($fields as $field)
					$query .= $field . ', ';
				$query = substr($query, 0, -2) . ') values ';

				reset($data);
				while(list(, $row) = each($data)) {
					$query .= '(';
					foreach($fields as $field)
						$query .= $this->_prepareValue($row[$field]) . ', ';
					$query = substr($query, 0, -2) . '), ';
				}

				$query = substr($query, 0, -2);

				break;

			case 'delete':
				$query = 'delete from ' . $table . ' where ';

				foreach($data as $key => $value)
					if(is_numeric($key))
						$query .= $value . ' and ';
					else $query .= $key . '=' . $value . ' and ';

				$query .= $where;

				break;

			case 'update':
				$query = 'update ' . $table . ' set ';

				foreach($data as $key => $value)
					if(is_numeric($key))
						$query .= $value . ' and ';
					else $query .= $key . '=' . $this->_prepareValue($value) . ', ';

				$query = substr($query, 0, -2) . ' where ' . $where;
				break;

			case 'insert_update':
				$query = 'insert into ' . $table . ' (';
				list(,$row) = each($data);
				$fields = array_keys($row);
				foreach($fields as $field)
					$query .= $field . ', ';
				$query = substr($query, 0, -2) . ') values ';

				reset($data);
				while(list(, $row) = each($data)) {
					$query .= '(';
					foreach($fields as $field)
						$query .= $this->_prepareValue($row[$field]) . ', ';
					$query = substr($query, 0, -2) . '), ';
				}

				$query = substr($query, 0, -2);

				$changes_values = '';
				foreach($fields as $field)
					if(!in_array($field, $last_values_keys))
						$changes_values .= $field . '=values(' . $field . '), ';
				$changes_values = substr($changes_values, 0, -2);

				if($changes_values != '')
					$query .= ' on duplicate key update ' . $changes_values;

				break;

			default:
				Reporter::messError('Action "' . $action . '" not found');
				break;
			}

			return $this->query($query);
		}

		public function _closeConnection() {
			@mysql_close($this->db_link);
		}

		private function _prepareValue($value) {
			return self::prepare($value);
		}

		public static function prepare($value) {
			if(is_bool($value))
				if($value) $value = '1';
				else $value = '0';

			if($value === 'now') $prepared_value = 'NOW()';
			elseif($value === 'null' || is_null($value)) $prepared_value = 'NULL';
			else $prepared_value = "'" . mysql_real_escape_string($value) . "'";

			return $prepared_value;
		}

		public function isAlive() {
			return mysql_ping($this->db_link);
		}
	}
?>