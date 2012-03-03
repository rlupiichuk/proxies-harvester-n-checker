<?php
	defined('_SAFE_ACCESS_') or die('Direct access not permitted');

	class Settings {
		private $errors = array();

		public static function getSetting($name) {
			global $db;

			$db->query('SELECT st_type, st_value FROM settings WHERE st_name=' . DataBase::prepare($name));
			if(!$value = $db->loadFirst())
				throw new SettingNotFoundException('Cannot found setting: ' . $name);

			if($value['st_type'] != 'text')
				settype($value['st_value'], $value['st_type']);

			return $value['st_value'];
		}

		private function setSetting($name, $value) {
			global $db;

			$db->query('SELECT st_type FROM settings WHERE st_name=' . DataBase::prepare($name));
			if(!$value_type = $db->loadValue())
				throw new SettingNotFoundException('Cannot found setting: ' . $name);

			if($value_type != 'text')
				settype($value, $value_type);
			$db->query('UPDATE settings SET st_value=' . DataBase::prepare($value) . ' WHERE st_name=' . DataBase::prepare($name));
		}

		public function isProcessed() {
			return isset($_REQUEST['showed']) && empty($this->errors);
		}

		public function performEditing() {
			if($this->isProcessed()) {
				global $db;

				$db->query('SELECT * FROM settings');
				$settings =& $db->loadResult();

				$this->errors = array();
				foreach($settings as $setting) {
					if(isset($_REQUEST[$setting['st_name']])) {
						if(preg_match('~^' . $setting['st_regexp'] . '$~i', $_REQUEST[$setting['st_name']]))
							$this->setSetting($setting['st_name'], $_REQUEST[$setting['st_name']]);
						else
							$this->errors[] = $setting['st_name'];
					} else
						throw new SettingNotFoundException('Cannot find in $_REQUEST setting: ' . $setting['st_name']);
				}

				if(empty($this->errors))
					return '<b>Saved</b>';
				else
					return $this->htmlForm();
			} else {
				if(isset($_REQUEST['restore']))
					return $this->restoreDefaults();
				else
					return $this->htmlForm();
			}
		}

		public function restoreDefaults() {
			global $db;

			$db->query('UPDATE settings SET st_value=st_default WHERE st_default != \'\'');

			return '<b>Defaults restored</b>';
		}

		private function htmlForm() {
			global $db;

			$db->query('SELECT * FROM settings ORDER BY st_group');
			$settings =& $db->loadResult();

			$form = '<form name="setting_form" method="POST"><input type="hidden" name="showed" value="yes" /><table border="1" cellpadding="5" cellspacing="0">' .
					'<tr>
						<td align="center"><b>Name</b></td>
						<td align="center"><b>Value</b></td>
						<td align="center"><b>Default</b></td>
						<td align="center"><b>Measure</b></td>
						<td align="center"><b>Type</b></td>
						<td align="center"><b>Description</b></td>
					</tr>';

			$back_group = null;
			foreach ($settings as $setting) {
				if($back_group != $setting['st_group']) {
					if(!is_null($back_group))
						$form .= '<tr><td colspan="6"><hr width="100%"></td></tr>';
					$back_group = $setting['st_group'];
				}

				$value = (isset($_REQUEST[$setting['st_name']]) ? $_REQUEST[$setting['st_name']] : $setting['st_value']);

				switch ($setting['st_type']) {
					case 'boolean':

						$field_edit =
							'<select name="' . $setting['st_name'] . '">' .
								'<option value="0" ' . ($value ? '' : 'selected="selected"') . '>false</option>' .
								'<option value="1" ' . ($value ? 'selected="selected"' : '') . '>true</option>' .
							'</select>';
						break;

					case 'text':
						$field_edit = '<textarea name="' . $setting['st_name'] . '" cols="18" rows="4">' . $value . '</textarea>';
						break;

					case 'integer':
					case 'string':
					default:
						$field_edit = '<input type="text" name="' . $setting['st_name'] . '" value="' . $value . '" size="'  . (($setting['st_type'] === 'string') ? '18' : '5') . '" />';
						break;
				}

				$form .=
					'<tr>' .
						'<td align="center"><b>' . (in_array($setting['st_name'], $this->errors) ? '<font color="red">' . $setting['st_human_name'] . '<font>' : $setting['st_human_name']) . '</b></td>' .
						'<td align="center">' . $field_edit . '</td>' .
						'<td align="center">' . emptyNbsp($setting['st_type'] == 'boolean' ? ($setting['st_default'] ? 'true' : 'false') : ($setting['st_type'] == 'text' ? '<div style="border: 1px solid gray; width: 120px; height: 65px; overflow: auto;"><pre>' . $setting['st_default'] . '</pre></div>' : $setting['st_default'])) . '</td>' .
						'<td align="center">' . emptyNbsp(ucfirst($setting['st_measure'])) . '</td>' .
						'<td>' . ucfirst($setting['st_type']) . '</td>' .
						'<td>' . emptyNbsp(nl2br($setting['st_desc'])) . '</td>' .
					'</tr>';
			}

			$form .= '</table><br /><input type="submit" value="save"/></form>';

			return $form;
		}
	}

	class SettingNotFoundException extends Exception {
		public function SettingNotFoundException($message) {
			parent::__construct($message, 1);
		}
	}
?>