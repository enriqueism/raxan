<?php

require_once('../raxan/pdi/gateway.php');

class SlidePanel extends RichWebPage {
    
    protected function _init() {
        // load page source 
        $this->source('views/slideoutpanel.html');
        $this->loadCSS('master'); // load raxan css
    }

    protected function _load() {
        // bind a callback function to the buttons
        $this['#btnshow']->bind('#click','.button_click');
    }

    // button callback
    protected function button_click($e) {
        $data = 'Name: Mary Jane<br />
            Address: 35 Cyberspace Drive<br />
            Email: mj@gmail.com<br />';
        // the above info could have been retrieved from a database
        C('#personal')->html($data)->slideDown('fast'); // show data
        C('#btnshow')->hide(); // hide button
    }

}

$page = new SlidePanel();
$page->reply();

?>