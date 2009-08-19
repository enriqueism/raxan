<?php

require_once "../raxan/pdi/gateway.php";

RichElement::addMethod('zebra', 'zebra_tables_extension');
RichElement::addMethod('resizable', 'resizable_extension');

class MyPage extends RichWebPage {

    function _init() {
        $this->source('views/custom-methods.html');
        $this->title('Plugin Test');
        $this->loadCSS('master');

        $this['table']
            ->addClass('success')
            ->zebra('lightgray','white'); // call custom function

        $this['#box']
            ->resizable(array('handles'=>'all'));   // call custom function
            
    }
   
}

// Resizable Custom Method
function resizable_extension($elm,$args){
    $o = isset($args[0]) ? $args[0]  : null;
    $elm->page->loadScript('jquery-ui-interactions');
    $elm->client->resizable($o);
    return $elm;
}

// Zebra Table Custom Method
function zebra_tables_extension($elm,$args){
    $t1 = isset($args[0]) ? $args[0] : '';
    $t2 = isset($args[1]) ? $args[1] : '';
    if ($t2) $elm->find('tr:odd')->addClass($t2)->end();
    return $elm->find('tr:even')->addClass($t1)->end();
}

RichWebPage::Init('MyPage');

?>