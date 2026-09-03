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
		$this->DataBase = new DataBase();
        $this->Map = new Map();
		$this->Logger = new Logger();
        $this->Config = new Config();
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