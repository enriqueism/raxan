<?php
/**
 * Web Form Example - Use the preserveFormcontent property to
 * preserve form values during postback
 *
 */

include_once "../raxan/pdi/autostart.php";

class WebForm extends RaxanWebPage {

    protected function _config() {
        // preserve form values on postback
        $this->preserveFormContent = true;
    }

    protected function _init() {
        $this->source('views/form-state.html');
        $this->loadCSS('master');
        $this->loadTheme('default');
    }

    protected function  _load() {
        if ($this->isPostBack) 
           $this->flashmsg('Form successfully submitted! Click here to close.','bounce','rax-box success close');
    }
    
}

?>