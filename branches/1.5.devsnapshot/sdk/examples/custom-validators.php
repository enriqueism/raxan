<?php

require_once("../raxan/pdi/autostart.php");

// add custom phone and money validators
$san = Raxan::dataSanitizer();
$san->addDataValidator('Phone', '/^[0-9]{3}-[0-9]{3}-[0-9]{4}$/'); // using regex
$san->addDataValidator('ZipCode','validate_ZipCode'); // using callback
function validate_ZipCode($value){    // validate zip code
    return preg_match('/^(\d{5}-\d{4})|(\d{5})$/',$value);
}

class CustomValidatorPage extends RaxanWebPage {

    protected $frm;

    protected function _config() {
        $this->preserveFormContent = true;
    }

    protected function _init() {
        $this->source('views/custom-validators.html');
    }

    protected function formSubmit($e) {    // event callback
        $msg = array();

        // validate user input
        if (!$this->post->isPhone('phone')) $msg[] ='* Please enter a valid phone number (format: 123-123-1234).';
        if (!$this->post->isZipCode('zipcode')) $msg[] ='* Please enter a valid US Zip code.';

        if (count($msg)>0) {
            $msg = '<strong>'.implode('<br />',$msg).'</strong>';
            $this->flashmsg($msg,'fade','rax-error-pal pad'); // flash msg to browser
        }
    }

}



?>