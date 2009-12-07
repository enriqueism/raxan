<?php

require_once "../raxan/pdi/autostart.php";


class HomePage extends RaxanWebPage {
    
    protected function _init() {
        $this->source('views/php-examples.html');
    }
    
    protected function _load() {
        $examples = Raxan::importCSV('examples.csv');
        $this->list->bind($examples);
    }

}


?>
