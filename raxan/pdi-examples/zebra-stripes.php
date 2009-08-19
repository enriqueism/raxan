<?php

require_once("../raxan/pdi/gateway.php");

class MyZebraStripes extends RichWebPage {

    protected function _init() {
        // load the html page
        $this->source('views/zebra-stripes.html');
    }

    protected function _load() {
        // bind to click event
        $this['#btnToggle']->bind('click','.toogle_click');
        // apply stipes on first load
        if (!$this->isPostback) {
            $this->stripeRows();
        }
    }

    protected function toogle_click($e) {
        $v = $e->value;
        if ($v == 'off') $this['#btnToggle']->attr('href','#on');
        else {
            $this->stripeRows();
            $this['#btnToggle']->attr('href','#off');
        }
    }
    
    protected function stripeRows() {
        $rows = $this['table tr:even'];
        $rows->css('background','#D1EC95'); // add a background color
    }
}

RichWebPage::Init('MyZebraStripes');

?>