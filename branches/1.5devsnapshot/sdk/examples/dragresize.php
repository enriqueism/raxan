<?php

require_once '../raxan/pdi/autostart.php'; 

class DragResizePage extends RaxanWebPage {

    protected function _load() {
        $this->loadCSS('master');  // load master css
        $this->loadTheme('default');  // load default theme
        $this->dragme->draggable(); // make element draggable
        $this->resizeme->resizable(); // make element resizable
    }

    protected function dragStop($e) {
        $x = (int)$e->targetX;
        $y = (int)$e->targetY;
        $this->msg->text('Moved to X:'.$x.' Y:'.$y);
        // update box color on client
        $this->dragme->client->css(array(
            'background' => '#'.$this->randomColor(),
            'border-color' => '#'.$this->randomColor()
        ));                
    }

    // generate random hex color
    protected function randomColor() {
        return dechex(rand(10, 254)).dechex(rand(10, 254)).dechex(rand(10, 254));
    }
}

?>

<div class="container pad">
    <div id="msg" class="rax-box info c7 bmm" xt-autoupdate>X: Y:</div>
    <div id="dragme" class="rax-box alert c3 r2" xt-bind="#dragstop,dragStop">
        Drag Me
    </div>

    <div id="resizeme" class="rax-box success c3 r2 prepend-top" xt-bind="#dragstop,dragStop">
        Resize Me
    </div>
</div>

