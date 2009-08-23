<?php

require_once('../raxan/pdi/gateway.php');

$page = new RichWebPage();
$page->content('<input id="button" type="button" value="Click me" />');

$page['#button']->bind('#click','button_click');
function button_click($e){
    C()->prompt('Please enter your name','name here...',_event('#entry'));
}
//echo "cc";
$page->registerEvent('entry', 'name_entry');
function name_entry($e){
    C('body')->append('Your name is: '.$e->value);
}

$page->reply();

?>
