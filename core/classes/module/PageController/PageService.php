<?php
declare(strict_types = 1);
class PageService {



    public function __construct (
        private DataBase $db,
        private Logger $log, 
        private Config $config,) {}

    public function getPage () {
    }
}