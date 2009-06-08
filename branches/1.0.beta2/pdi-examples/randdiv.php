<?php

require_once '../raxan/pdi/gateway.php';


$page = new RichWebPage();
$page->loadCSS('master');

$html = '';
for($i=0;$i<100;$i++) {
    $w = rand(1, 5); $h = rand(1, 5);
    $x = rand(10, 600); $y = rand(10, 300);
    $color = randomColor();  $bcolor = randomColor();
    $html.= '<div class="c'.$w.' r'.$h.'" style="position:absolute;'
        .'border:2px solid #'.$bcolor.'; background:#'.$color.';'
        .'left:'.$x.'px;top:'.$y.'px"></div>';
}

$page['body']->append($html);

// set hover class on client
C('div')->hoverClass('pad'); 

$page->reply();

// generate random hex color
function randomColor() {
    return dechex(rand(20, 254)).dechex(rand(20, 254)).dechex(rand(20, 254));
}


?>