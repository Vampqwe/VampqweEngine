<?php
declare(strict_types = 1);
class Session extends AccountManagementSystem {

	private Redis $Redis;

    public function __construct () {
		$_COOKIE["name"] = "session";
		$_COOKIE["session_id"] = Helper::getUUIDv4();
		try{
			$this->Redis = new Redis();
			$Config = new Config();
			$host = (string) $Config->getConfig('sessionHandler.save_session_path_host');
			$port = (int) $Config->getConfig('sessionHandler.save_session_path_port');
			$this->Redis->connect($host, $port);
		}catch (RedisException $rExc) {
			echo $rExc->getMessage();
		}catch (ConfigException $confExc) {
			echo $confEx->getMessage();
		}

    }
	
	public function setSession () {
	}
	
	public function getSession () {
	}
}