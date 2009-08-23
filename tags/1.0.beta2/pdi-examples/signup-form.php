<?php

require_once("../raxan/pdi/gateway.php");

class MyPage extends RichWebPage {

    protected $frm;
    protected $preserveFormContent = true; // redisplay form values

    protected function _load() {
        // load the html page
        $this->source('views/signup-form.html');

        // bind to form submit event
        $this->frm = $this['form']->bind('submit', '.form_submit');
    }

    protected function form_submit($e) {    // event callback
        $msg = array();
        $data = $this->clientRequest();
        $pwd = $data->value('password'); $cpwd = $data->value('cpassword');

        // validate user input
        if (!$data->value('name')) $msg[] ='Please enter your user name.';
        if (!$data->isEmail('email')) $msg[] ='Please enter a valid email adress.';
        if (!$pwd) $msg[] ='Please enter a valid password.';
        else if ($pwd!=$cpwd) $msg[] ='Password typed mismatched.';
        if (!$data->isUrl('website')) $msg[] ='Please enter a valid website.';

        if (count($msg)>0) {
            $msg = '<strong>'.implode('<br />',$msg).'</strong>';
            $this['#msg']->html($msg)->show();
        }
        else {
            // ... code save form data here ...

            // display success message
            $this['form fieldset']->html('<div class="success">Signup was successfull.</div>');
        }
    }
}


RichWebPage::Init('MyPage');

?>