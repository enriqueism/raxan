<?php

require_once "../raxan/pdi/autostart.php";

class MyPage extends RaxanWebPage {

    function _init() {
        $this->source('views/custom-methods.html');

        // call custom zerba function
        $this->table1->zebra('softyellow','white');
            
    }
   
}

// add custom methods to RaxanElement
RaxanElement::addMethod('zebra', 'zebra_tables_extension');

// Zebra Table Custom Method
function zebra_tables_extension($elm,$args){
    $t1 = isset($args[0]) ? $args[0] : '';
    $t2 = isset($args[1]) ? $args[1] : '';
    if ($t2) $elm->find('tr:odd')->addClass($t2)->end();
    return $elm->find('tr:even')->addClass($t1)->end();
}

?>