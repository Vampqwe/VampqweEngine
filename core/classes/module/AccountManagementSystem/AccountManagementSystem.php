<?php
declare(strict_types = 1);
class AccountManagementSystem {

    protected Config $Config;
    private DataBase $DataBase;
    private Map $Map;
    private Logger $Logger;
	
	private string $inputAccMail;
	private string $inputAccPass;
	private string $inputAccPassRep;

    public function __construct () {
        $this->Map = new Map();
		$this->Logger = new Logger();
        $this->Config = new Config();
        $this->DataBase = DataBase::getInstance($this->Config, $this->Logger);
        new Session();

    }
	
    public function addDataAccount () {

    }

    public function registration () {
    }

    public function authorization () {
    }

    public function authentication () {

    }
}