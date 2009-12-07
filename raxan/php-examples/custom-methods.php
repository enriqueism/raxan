<?php

require_once "../raxan/pdi/autostart.php";

RaxanElement::addMethod('zebra', 'zebra_tables_extension');
RaxanElement::addMethod('resizable', 'resizable_extension');

class MyPage extends RaxanWebPage {

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
    $elm->page->loadCSS('<style type="text/css">
    /* jQuery Resizable UI css */
    .ui-resizable { position: relative;}
    .ui-resizable-handle { position: absolute;font-size: 0.1px;z-index: 99999; display: block;}
    .ui-resizable-disabled .ui-resizable-handle, .ui-resizable-autohide .ui-resizable-handle { display: none; }
    .ui-resizable-n { cursor: n-resize; height: 7px; width: 100%; top: -5px; left: 0px; }
    .ui-resizable-s { cursor: s-resize; height: 7px; width: 100%; bottom: -5px; left: 0px; }
    .ui-resizable-e { cursor: e-resize; width: 7px; right: -5px; top: 0px; height: 100%; }
    .ui-resizable-w { cursor: w-resize; width: 7px; left: -5px; top: 0px; height: 100%; }
    .ui-resizable-se { cursor: se-resize; width: 12px; height: 12px; right: 1px; bottom: 1px; }
    .ui-resizable-sw { cursor: sw-resize; width: 9px; height: 9px; left: -5px; bottom: -5px; }
    .ui-resizable-nw { cursor: nw-resize; width: 9px; height: 9px; left: -5px; top: -5px; }
    .ui-resizable-ne { cursor: ne-resize; width: 9px; height: 9px; right: -5px; top: -5px;}'."\n".
    '</style>');
    return $elm;
}

// Zebra Table Custom Method
function zebra_tables_extension($elm,$args){
    $t1 = isset($args[0]) ? $args[0] : '';
    $t2 = isset($args[1]) ? $args[1] : '';
    if ($t2) $elm->find('tr:odd')->addClass($t2)->end();
    return $elm->find('tr:even')->addClass($t1)->end();
}

?>