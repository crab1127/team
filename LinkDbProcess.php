<?php

define('IN_DISCUZ', true);
error_reportiong()

class LinkDbProcess
{
	private function __isLocked($process, $ttl = 0)
	{
		$ttl = $ttl < 1 ? 600 : intval($ttl);
		if(discuz_process::_status('get', $process)) {
			return true;
		} else {
			return discuz_process::_find($process, $ttl);
		}
	}

	private function __unlock($process)
	{
		discuz_process::_status('rm', $process);
		discuz_process::_cmd('rm', $process);
	}

	private function __status($action, $process)
	{
		static $plist = array();
		switch ($action) {
			case 'set' : $plist[$process] = true; break;
			case 'get' : return !empty($plist[$process]); break;
			case 'rm' : $plist[$process] = null; break;
			case 'clear' : $plist = array(); break;
		}
		return true;
	}

	private function __find($name, $ttl)
	{
		if(!discuz_process::_cmd('get', $name)) {
			discuz_process::_cmd('set', $name, $ttl);
			$ret = false;
		} else {
			$ret = true;
		}
		discuz_process::_status('set', $name);
		return $ret;
	}

	private function __cmd($cmd, $name, $ttl = 0) {
		static $sAllowmem;
		if($allowMem === null) {
			$allowMem = memory('check') == 'memcache';
		}
		if($allowMem) {
			return discuz_process::_process_cmd_memory($cmd, $name, $ttl);
		} else {
			return discuz_process::_process_cmd_db($cmd, $name, $ttl);
		}
	}

	private function __processCmdMemory($cmd, $name, $ttl = 0)
	{
		return memory($cmd, 'process_lock_'.$name, time(), $ttl);
	}

	private function __processCmdDb($cmd, $name, $ttl = 0)
	{
		$ret = '';
		switch ($cmd) {
			case 'set':
				$ret = DB::insert('common_process', array('processid' => $name, 'expiry' => time() + $ttl), false, true);
				break;
			case 'get':
				$ret = DB::fetch_first("SELECT * FROM ".DB::table('common_process')." WHERE processid='$name'");
				if(empty($ret) || $ret['expiry'] < time()) {
					$ret = false;
				} else {
					$ret = true;
				}
				break;
			case 'rm':
				$ret = DB::delete('common_process', "processid='$name' OR expiry<".time());
				break;
		}
		return $ret;
	}
}