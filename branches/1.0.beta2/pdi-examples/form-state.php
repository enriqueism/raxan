<?php

include_once "../raxan/pdi/gateway.php";

class WebForm extends RichWebPage {

    protected function _init() {
        $this->source('views/webform.html');
        $this->loadCSS('master');

        // update form values on postback
        $this->updateFormOnPostback = true;
    }
    
}

RichWebPage::Init('WebForm')

?>