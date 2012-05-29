<?php
define('IN_DISCUZ',true);
error_reporting(0);

class DbMysplHelper
{
	private $__tablePre;
	private $__version = '';
	private $__queryNum = 0;
	private $__slaveid = 0;
	private $__curLink;
	private $__link = array();
	private $__config = array();
	private $__sqlDebug = array();
	private $__map = array();
	
	private function __dbMysql($__config = array())
	{
		if(!empty($__config)) {
			$this->__setConfig($__config);
		}
	}
	
	private function __setConfig($__config)
	{
		$this->__config = &$__config;
		$this->__tablePre = $__config['1']['tablePre'];
		if(!empty($this->__config['map'])) {
			$this->map = $this->__config['map'];
		}
	}
	
	private function __connect($serverId = 1)
	{
		if(empty($this->__config) || empty($this->__config[$serverId])) {
			$this->halt('config_db_not_found');
		}
		
		$this->link[$serverId] = $this->__dbconnect(
			$this->__config[$serverId]['dbhost'],
			$this->__config[$serverId]['dbuser'],
			$this->__config[$serverId]['dbpw'],
			$this->__config[$serverId]['dbcharset'],
			$this->__config[$serverId]['dbname'],
			$this->__config[$serverId]['pconnect']
		);
		$this->__curLink = $this->link[$serverid];
	}
	
	private function __dbconnect($dbHost, $dbUser, $dbPw, $dbCharset, $dbName, $pconnect)
	{
		$link = null;
		$func = empty($pconnect) ? 'mysql_connect' : 'mysql_pconnect';
		if(!$link = @$func($dbHost, $dbUser, $dbPw, 1)) {
			$this->halt('notconnect');
		} else {
			$this->__cruLink = $link;
			if($this->version() > '4.1') {
				$dbCharset = $dbCharset ? $dbCarset : $this->__config[1]['dbCharset'];
				$serverSet = $dbCharset ? 'character_set_connection='.$dbCharset
				.', character_set_results='.$dbCharset.', character_set_client=binary' : '';
				$serverSet .= $this->version() > '5.0.1' ? ((empty($serverSet) ? '' : ',').'sql_mode=\'\'') : '';
				$serverSet && mysql_query("SET $serverset", $link);
			}
			$dbName && @mysql_select_db($dbName, $link);
		}
		return $link;
	}
	
	private	function __tableName($tableName)
	{
		if(!empty($this->map) && !empty($this->map[$tableName])) {
			$id = $this->map[$tableName];
			if(!$this->link[$id]) {
				$this->connect($id);
			}
			$this->curlink = $this->link[$id];
		} else {
			$this->curlink = $this->link[1];
		}
		return $this->tablepre.$tableName;
	}
	
	private	function __selectDb($dbName)
	{
		return mysql_select_db($dbName, $this->curlink);
	}
	
	private function __fetchArray($query, $resultType = MYSQL_ASSOC)
	{
		return mysql_fetch_array($query, $resultType);
	}
	
	private	function __fetchFirst($sql)
	{
		return $this->__fetchArray($this->query($sql));
	}
	
	private	function __resultFirst($sql) {
		return $this->result($this->query($sql), 0);
	}
	
	private	function __query($sql, $type = '')
	{
		if(defined('DISCUZ_DEBUG') && DISCUZ_DEBUG) {
			$startTime = dmicrotime();
		}
		$func = $type == 'UNBUFFERED' && @function_exists('mysql_unbuffered_query') 
		? 'mysql_unbuffered_query' : 'mysql_query';
		if(!($query = $func($sql, $this->curlink))) {
			if(in_array($this->errno(), array(2006, 2013)) && substr($type, 0, 5) != 'RETRY') {
				$this->connect();
				return $this->query($sql, 'RETRY'.$type);
			}
			if($type != 'SILENT' && substr($type, 5) != 'SILENT') {
				$this->halt('query_error', $sql);
			}
		}

		if(defined('DISCUZ_DEBUG') && DISCUZ_DEBUG) {
			$this->sqldebug[] = array($sql, number_format((dmicrotime() - $starttime), 6), debug_backtrace());
		}

		$this->querynum++;
		return $query;
	}

	private function __affectedRows()
	{
		return mysql_affected_rows($this->curLink);
	}

	private function __error()
	{
		return (($this->curLink) ? mysql_error($this->curLink) : mysql_error());
	}

	private function __errno()
	{
		return intval(($this->curLink) ? mysql_errno($this->curLink) : mysql_errno());
	}

	private function __result($query, $row = 0)
	{
		$query = @mysql_result($query, $row);
		return $query;
	}

	private function __numRows($query)
	{
		$query = mysql_num_rows($query);
		return $query;
	}

	private function __numFields($query)
	{
		return mysql_num_fields($query);
	}

	private function __freeResult($query)
	{
		return mysql_free_result($query);
	}

	private function __insertId()
	{
		return ($id = mysql_insert_id($this->curlink)) >= 0 
		? $id : $this->result($this->query("SELECT last_insert_id()"), 0);
	}

	private function __fetchRow($query)
	{
		$query = mysql_fetch_row($query);
		return $query;
	}

	private function __fetchFields($query)
	{
		return mysql_fetch_field($query);
	}

	private function __version()
	{
		if(empty($this->version)) {
			$this->version = mysql_get_server_info($this->curLink);
		}
		return $this->version;
	}

	private function __close()
	{
		return mysql_close($this->curLink);
	}

	private function __halt($message = '', $sql = '')
	{
		require_once libfile('class/error');
		discuz_error::db_error($message, $sql);
	}
}