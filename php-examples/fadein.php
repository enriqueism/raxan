<?php

/**
 * Fade In Example - Use the Client Extension to modify elements
 * within the client's web browser.
 *
 */

require_once('../raxan/pdi/gateway.php');

$page = new RaxanWebPage();
$page->loadCSS('master');
$page->content('<div id="box" class="margin success c10 r5"  />');

if (!$page->isPostback) 
    C('#box')->hide()->fadeIn(2000,_event('done'));

$page->registerEvent('done', 'fade_out');
function fade_out($e){
    C('#box')
        ->text('Server Says,"Time to Fade Out..."')
        ->fadeOut(3000);
}

$page->reply();

?>