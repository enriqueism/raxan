<?php

require_once('../raxan/pdi/autostart.php');

class NewPage extends RaxanWebPage {

    protected function _init() {
        // register popup box response events
        $this->registerEvent('confirmOk', '.okShow');
        $this->registerEvent('confirmCancel', '.cancelShow');
        $this->registerEvent('promptResponse', '.promptResponse');
    }

    // -- Event handlers --

    protected function showAlert($e) {
        $msg='This is a message from the server. Click the Ok button to continue.';
        c()->alert($msg);
    }

    protected function showConfirm($e) {
        $msg = "Do you want to learn more about Raxan for PHP?\n\n Click the ok button";
        c()->confirm($msg,_event('confirmOk'),_event('confirmCancel'));
    }

    protected function showPrompt($e) {
        $msg = "Please enter your name";
        c()->prompt($msg,'',_event('promptResponse'));
    }

    protected function okShow($e){
        $this->header1->text('You\'ve clicked the Ok button :)');
    }
    
    protected  function cancelShow($e){
        $this->header1->text('You\'ve clicked the Cancel button :(');
    }

    protected  function promptResponse($e){
        $this->header1->text('You\'ve entered :'.$e->textVal());
    }

}


?>


<p>
    <button xt-bind="click,showAlert">Show Me an Alert box</button> -
    An alert box is used when you want to make sure information comes through to the user.
</p>

<p>
    <button xt-bind="click,showConfirm">Show Me a Confirm box</button> -
    A confirm box is often used if you want the user to verify or accept something.
</p>

<p>
    <button xt-bind="click,showPrompt">Show Me an Input box</button> -
    A prompt box is often used if you want the user to input a value.
</p>

<h2 id="header1"></h2>