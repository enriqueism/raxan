<?php require_once '../raxan/pdi/autostart.php'; ?>

<div id="msg" class="info c4" xt-autoupdate>X: Y:</div>
<div id="drag" class="c2 r2 alert" xt-bind="#dragstop,dragStop" />

<?php


class DragDropPage extends RaxanWebPage {

    protected function _load() {
        $this->loadCSS('master');
        // load jquery files from the plugins folder
        $this->loadScript('jquery');
        $this->loadScript('jquery-ui-interactions');

        $this->drag->client->draggable();
    }

    protected function dragStop($e) {
        $x = (int)$e->targetX;
        $y = (int)$e->targetY;
        $this->msg->text('X:'.$x.' Y:'.$y);  
        $d = $this->drag; // update box color
        $d->client->css('background','#'.$this->randomColor());
        $d->client->css('border-color','#'.$this->randomColor());
    }

    // generate random hex color
    protected function randomColor() {
        return dechex(rand(10, 254)).dechex(rand(10, 254)).dechex(rand(10, 254));
    }
}



?>