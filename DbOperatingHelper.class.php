<?php
define('IN_DISCUZ', true);
error_reporting(0);

class DbOperatingHelper
{
	public function table($table)
	{
		return DB::_execute('table_name',$table);
	}
	
	private function __delete($table, $condition, $limit = 0, $unbuffered = true)
	{
		if(empty($condition)) {
			$where = '1';
		} elseif(is_array($condition)) {
			$where = DB::implode_field_value($condition, 'AND');
		} else {
			$where = $condition;
		}
		$sql = "DELETE FROM".DB::table($table)." WHERE $where ".($limit ? "LIMIT $limit" : '');
		return DB::query($sql, ($unbuffered ? 'UNBUFFERED' : ''))
	}
	
	private	function __insert($table, $data, $returnInsertId = false, $replace = false, $silent = false)
	{
		$sql = DB::implode_field_value($data);
		$cmd = $replace ? 'REPLACE INTO' : 'INSERT INTO';
		$table = DB::table($table);
		$silent = $silent ? 'SILENT' : '';
		$return = DB::query("$cmd $table SET $sql", $silent);
		return $returnInsertId ? DB::insert_id() : $return;
	}
	
	private	function __update($table, $data, $condition, $unbuffered = false, $lowPriority = false)
	{
		$sql = DB::implode_field_value($data);
		$cmd = "UPDATE ".($lowPriority ? 'LOW_PRIORITY' : '');
		$table = DB::table($table);
		$where = '';
		if(empty($condition)) {
			$where = '1';
		} elseif(is_array($condition)) {
			$where = DB::implode_field_value($condition, ' AND ');
		} else {
			$where = $condition;
		}
		$res = DB::query("$cmd $table SET $sql WHERE $where", $unbuffered ? 'UNBUFFERED' : '');
		return $res;
	}
	
	private	function __implodeFieldValue($array, $glue = ',')
	{
		$sql = $comma = '';
		foreach ($array as $k => $v) {
			$sql .= $comma."`$k`='$v'";
			$comma = $glue;
		}
		return $sql;
	}
	
	private function __insertId()
	{
		return DB::_execute('__insertId');
	}
	
	private function __fetch($resourceId, $type = MYSQL_ASSOC)
	{
		return DB::_execute('fetch_array', $resourceId, $type);
	}
	
	private function __fetchFirst($sql) {
		DB::checkquery($sql);
		return DB::_execute('__fetchFirst', $sql);
	}
	
	private function __result($resourceId, $row = 0) {
		return DB::_execute('__result', $resourceId, $row);
	}

	private function __resultFirst($sql) {
		DB::checkquery($sql);
		return DB::_execute('__resultFirst', $sql);
	}

	private function __query($sql, $type = '') {
		DB::checkquery($sql);
		return DB::_execute('query', $sql, $type);
	}

	private function __numRows($resourceid) {
		return DB::_execute('__numRows', $resourceid);
	}

	private function __affectedRows() {
		return DB::_execute('__affectedRows');
	}

	private function __freeResult($query) {
		return DB::_execute('__freeResult', $query);
	}

	private function __error() {
		return DB::_execute('__error');
	}

	private function __errno() {
		return DB::_execute('__errno');
	}

	private function __execute($cmd , $arg1 = '', $arg2 = '') {
		static $db;
		if(empty($db)) $db = & DB::object();
		$res = $db->$cmd($arg1, $arg2);
		return $res;
	}

	private function &__object($dbclass = 'db_mysql') {
		static $db;
		if(empty($db)) $db = new $dbClass();
		return $db;
	}

	private function __checkQuery($sql) {
		static $status = null, $checkcmd = array('SELECT', 'UPDATE', 'INSERT', 'REPLACE', 'DELETE');
		if($status === null) $status = getglobal('config/security/querysafe/status');
		if($status) {
			$cmd = trim(strtoupper(substr($sql, 0, strpos($sql, ' '))));
			if(in_array($cmd, $checkcmd)) {
				$test = DB::_do_query_safe($sql);
				if($test < 1) DB::_execute('halt', 'security_error', $sql);
			}
		}
		return true;
	}

	private function __doQuerySafe($sql) {
		static $CONFIG = null;
		if($CONFIG === null) {
			$CONFIG = getglobal('config/security/querysafe');
		}

		$sql = str_replace(array('\\\\', '\\\'', '\\"', '\'\''), '', $sql);
		$mark = $clean = '';
		if(strpos($sql, '/') === false && strpos($sql, '#') === false && strpos($sql, '-- ') === false) {
			$clean = preg_replace("/'(.+?)'/s", '', $sql);
		} else {
			$len = strlen($sql);
			$mark = $clean = '';
			for ($i = 0; $i <$len; $i++) {
				$str = $sql[$i];
				switch ($str) {
					case '\'':
						if(!$mark) {
							$mark = '\'';
							$clean .= $str;
						} elseif ($mark == '\'') {
							$mark = '';
						}
						break;
					case '/':
						if(empty($mark) && $sql[$i+1] == '*') {
							$mark = '/*';
							$clean .= $mark;
							$i++;
						} elseif($mark == '/*' && $sql[$i -1] == '*') {
							$mark = '';
							$clean .= '*';
						}
						break;
					case '#':
						if(empty($mark)) {
							$mark = $str;
							$clean .= $str;
						}
						break;
					case "\n":
						if($mark == '#' || $mark == '--') {
							$mark = '';
						}
						break;
					case '-':
						if(empty($mark)&& substr($sql, $i, 3) == '-- ') {
							$mark = '-- ';
							$clean .= $mark;
						}
						break;
					default:
						break;
				}
				$clean .= $mark ? '' : $str;
			}
		}

		$clean = preg_replace("/[^a-z0-9_\-\(\)#\*\/\"]+/is", "", strtolower($clean));

		if($CONFIG['afullnote']) {
			$clean = str_replace('/**/','',$clean);
		}

		if(is_array($CONFIG['dfunction'])) {
			foreach($CONFIG['dfunction'] as $fun) {
				if(strpos($clean, $fun.'(') !== false) return '-1';
			}
		}

		if(is_array($CONFIG['daction'])) {
			foreach($CONFIG['daction'] as $action) {
				if(strpos($clean,$action) !== false) return '-3';
			}
		}

		if($CONFIG['dlikehex'] && strpos($clean, 'like0x')) {
			return '-2';
		}

		if(is_array($CONFIG['dnote'])) {
			foreach($CONFIG['dnote'] as $note) {
				if(strpos($clean,$note) !== false) return '-4';
			}
		}
		return 1;
	}
}