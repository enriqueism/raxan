<?php

require_once('../raxan/pdi/autostart.php');


class MenuPage extends RaxanWebPage {

    
    function _load() {
        $this->loadCSS('master');
        $this->content('<input id="cmdclick" type="button" value="Menu" name="cmdclick" />');
        
        $this['#cmdclick']->bind('#click','.show_menu');
        $this['.menu a']->delegate('click','.menu_click');
    }

    protected function menu_click($e) {
        P('#cmdclick')->val('Menu > '.$e->value);
        $this->data('state',0);
    }

    protected function show_menu($e){
        // check if menu on or off
        $state = & $this->data('state');
        if(!$state) $state=1; else $state=0;
        // load menu content
        $context = file_get_contents('views/context.html');
        $c =  C('#cmdclick');
        // show/hide menu element
        if ($state) $c->after($context);
        else $c->next()->remove();
    }
}


?>