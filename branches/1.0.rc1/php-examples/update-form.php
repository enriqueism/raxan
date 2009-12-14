<?php

require_once("../raxan/pdi/autostart.php");

// add custom phone and money validators
$san = Raxan::dataSanitizer();
$san->addDataValidator('Phone', '/^[0-9]{3}-[0-9]{3}-[0-9]{4}$/'); // using regex
$san->addDataValidator('ZipCode','validate_ZipCode'); // using callback
function validate_ZipCode($value){    // validate zip code
    return preg_match('/^(\d{5}-\d{4})|(\d{5})$/',$value);
}

class MyPage extends RaxanWebPage {

    protected $frm;
    protected $preserveFormContent = true; // redisplay form values

    protected function _load() {
        // load the html page
        $this->source('views/update-form.html');

        // bind to form submit event
        $this->frm = $this['form']->bind('submit', '.form_submit');
    }

    protected function form_submit($e) {    // event callback
        $msg = array();
        $request = $this->clientRequest();

        // validate user input
        if (!$request->isPhone('phone')) $msg[] ='Please enter a valid phone number (format: 123-123-1234).';
        if (!$request->isZipCode('zipcode')) $msg[] ='Please enter a valid US Zip code.';

        if (count($msg)>0) {
            $msg = '<strong>'.implode('<br />',$msg).'</strong>';
            $this['#msg']->html($msg)->show();
        }
    }

}



?>