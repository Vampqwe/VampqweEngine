<?php
declare(strict_types = 1);
class PageController {



    public function __construct (
        private Logger $log, 
        private Config $config,
        private Template $template) {}

    public function getPage () {
    }
}
