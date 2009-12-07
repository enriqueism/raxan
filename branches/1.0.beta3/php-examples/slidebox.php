<?php

require_once('../raxan/pdi/gateway.php');

$page = new RaxanWebPage();
$page->loadCSS('master');
$page->content('<div id="me" class="c2 r2 success above" align="center">Click Me</div>');

$page['#me']->bind('#click','me_click');
function me_click($e){
    C('#me')->animate(array('left'=>rand(20,550),'top'=>rand(20,200)));
}

$page->reply();

?>