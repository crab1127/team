<?php

define('IN_DISCUZ',true);
error_reporting(0);

class DiscuzSession
{
	private $__sid = null;
	private $__var;
	private $__isNew = false;
	private $__newGuest = array('sid' => 0, 'ip1' => 0, 'ip2' => 0, 'ip3' => 0,	'ip4' => 0,
	'uid' => 0, 'username' => '', 'groupid' => 7, 'invisible' => 0, 'action' => 0,
	'lastactivity' => 0, 'fid' => 0, 'tid' => 0, 'lastolupdate' => 0);
	private $__old =  array('sid' =>  '', 'ip' =>  '', 'uid' =>  0);

	private function __discuzSession($sid = '', $ip = '', $uid = 0)
	{
		$this->__old = array('sid' =>  $sid, 'ip' =>  $ip, 'uid' =>  $uid);
		$this->var = $this->__newGuest;
		if(!empty($ip)) {
			$this->init($sid, $ip, $uid);
		}
	}

	public function set($key, $value)
	{
		if(isset($this->__newGuest[$key])) {
			$this->var[$key] = $value;
		} elseif ($key == 'ip') {
			$ips = explode('.', $value);
			$this->set('ip1', $ips[0]);
			$this->set('ip2', $ips[1]);
			$this->set('ip3', $ips[2]);
			$this->set('ip4', $ips[3]);
		}
	}

	public function get($key) {
		if(isset($this->__newGuest[$key])) {
			return $this->var[$key];
		} elseif ($key == 'ip') {
			return $this->get('ip1').'.'.$this->get('ip2').'.'.$this->get('ip3').'.'.$this->get('ip4');
		}
	}

	private function __init($sid, $ip, $uid)
	{
		$this->__old = array('sid' =>  $sid, 'ip' =>  $ip, 'uid' =>  $uid);
		$session = array();
		if($sid) {
			$session = DB::fetch_first("SELECT * FROM ".DB::table('common_session').
				" WHERE sid='$sid' AND CONCAT_WS('.', ip1,ip2,ip3,ip4)='$ip'");
		}

		if(empty($session) || $session['uid'] != $uid) {
			$session = $this->create($ip, $uid);
		}

		$this->var = $session;
		$this->sid = $session['sid'];
	}

	private function __create($ip, $uid)
	{

		$this->__isNew = true;
		$this->var = $this->__newGuest;
		$this->set('sid', random(6));
		$this->set('uid', $uid);
		$this->set('ip', $ip);
		$uid && $this->set('invisible', getuserprofile('invisible'));
		$this->set('lastactivity', time());
		$this->sid = $this->var['sid'];
		return $this->var;
	}

	private function __delete()
	{

		global $G;
		$onlinehold = $G['setting']['onlinehold'];
		$guestspan = 60;

		$onlinehold = time() - $onlinehold;
		$guestspan = time() - $guestspan;

		$condition = " sid='{$this->sid}' ";
		$condition .= " OR lastactivity<$onlinehold ";
		$condition .= " OR (uid='0' AND ip1='{$this->var['ip1']}' AND ip2='{$this->var['ip2']}' AND ip3='
		{$this->var['ip3']}' AND ip4='{$this->var['ip4']}' AND lastactivity>$guestspan) ";
		$condition .= $this->var['uid'] ? " OR (uid='{$this->var['uid']}') " : '';
		DB::delete('common_session', $condition);
	}

	private function __update() {
		global $G;
		if($this->sid !== null) {

			$data = daddslashes($this->var);
			if($this->isnew) {
				$this->delete();
				DB::insert('common_session', $data, false, false, true);
			} else {
				DB::update('common_session', $data, "sid='$data[sid]'");
			}
			$G['session'] = $data;
			dsetcookie('sid', $this->sid, 86400);
		}
	}

	private function __onlineCount($type = 0) {
		$condition = $type == 1 ? ' WHERE uid>0 ' : ($type == 2 ? ' WHERE invisible=1 ' : '');
		return DB::result_first("SELECT count(*) FROM ".DB::table('common_session').$condition);
	}

}