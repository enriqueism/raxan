<?php

/**
 * Web Form Example - Use the preserveFormcontent property to
 * preserve form values during postback
 *
 */

include_once "../raxan/pdi/autostart.php";

class WebForm extends RaxanWebPage {

    // update form values on postback
    protected $preserveFormContent = true;

    protected function _init() {
        $this->source('views/webform.html');
        $this->loadCSS('master');
    }
    
}


?>