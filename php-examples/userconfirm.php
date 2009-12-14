<?php

require_once('../raxan/pdi/gateway.php');

$page = new RaxanWebPage();

if (!$page->isPostback)
    C()->confirm("Do you want to learn more about Raxan for PHP?\n\n Click the ok button",_event('ok'),_event('cancel'));

$page->registerEvent('ok', 'ok_show');
$page->registerEvent('cancel', 'cancel_show');
function ok_show($e){
    $e->page()->content('You\'ve clicked the Ok button :)');
}
function cancel_show($e){
    $e->page()->content('You\'ve clicked the Cancel button :(');
}

$page->reply();

?>
