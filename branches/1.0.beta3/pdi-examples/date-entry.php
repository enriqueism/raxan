<?php

include_once "../raxan/pdi/gateway.php";

// Set timezone - also needed when using E_STRICT
RichAPI::config('site.timezone','America/Jamaica');

class DateEntry extends RichWebPage {

    protected function _init() {
        $this->preserveFormContent = true;
        $this->appendView('date-form.html'); // append view to the body tag
    }

    protected function _load() {                
        $this['#btnSend']->bind('click',array(
            'callback'  => '.button_click',
            'serialize' => ':input'   // serialize and return form input values
        ));
    }

    protected function button_click($e){
        $rq = $this->clientRequest();
        $f = $rq->text('format');
        $dt = $rq->date('date',$f);
        if(!$dt) $dt = 'Invalid date';
        $this['#msg']->text($dt);
    }

}

$p = new DateEntry();
$p->reply();

?>
