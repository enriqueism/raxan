<?php

require_once '../raxan/pdi/gateway.php';


$page = new RichWebPage();
$page->loadCSS('master');
// load jquery files from the plugins folder
$page->loadScript('jquery');
$page->loadScript('jquery-ui-interactions');

$page['body']->append('
    <div id="msg" class="info c4">X: Y:</div>
    <div id="drag" class="c2 r2 alert" />
');


$page['#drag']->bind('#dragstop','drag_stop');
function drag_stop($e) {
    $x = $e->targetX; $y = $e->targetY;
    C('#msg')->text('X:'.$x.' Y:'.$y);
    C('#drag')->css('background','#'.randomColor())
        ->css('border-color','#'.randomColor());
}

// make the div draggable using the clx
C('#drag')->draggable();

$page->reply();

// generate random hex color
function randomColor() {
    return dechex(rand(10, 254)).dechex(rand(10, 254)).dechex(rand(10, 254));
}
?>