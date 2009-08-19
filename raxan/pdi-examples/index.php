<?php

require_once '../raxan/pdi/gateway.php';

$page = new RichwebPage('views/pdi-examples.html');

// list beta files
$d = dir('./'); $files = array();
$exclude = array('index.php','gateway.config.php');
while (false !== ($entry = $d->read())) {
    if (!in_array($entry,$exclude)  && strpos($entry,'.php')!==false) {
        $files[$entry] = '<div class="info column c10 tpm"><a href="'.$entry.'">'.$entry.'</a></div>';
    }
}

ksort($files);
$page['#list']->html(implode( ' ',array_values($files)));

$page->reply();

?>
