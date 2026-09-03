<?php
declare(strict_types = 1);
class Session extends AccountManagementSystem {

	private Redis $Redis;

    public function __construct () {
		$_COOKIE["name"] = "session";
		$_COOKIE["session_id"] = Helper::getUUIDv4();
		$config = $this->Config;
		$this->Redis = new Redis();
		$this->Redis->connect($config->getConfig('sessionHandler.save_session_path_host'),
								$config->getConfig('sessionHandler.save_session_path_port'));
    }
	
	public function setSession () {
	}
	
	public function getSession () {
	}
}