<?php

require_once '../raxan/pdi/gateway.php';


$page = new RichWebPage();
$page->appendView('dragdrop.html');

$page->loadCSS('master');
// load jquery files from the plugins folder
$page->loadScript('jquery');
$page->loadScript('jquery-ui-interactions');

// listen to basket drops
$page['#basket']->bind('#drop',array(
    'callback'=>'basket_drop'
));
function basket_drop($e) {
    // get ID from draggable
    $id = $e->uiDraggable->getAttribute('id');
    $id = intval(substr($id,3));
    if ($id) {
        $item = P('#itm0'.$id);
        $html = '<img src="'.$item->attr('src').'" width="32" /> '
                .$item->attr('alt').'<br />';
        C('#basket-items')->append($html);
        C('#itm0'.$id)->remove();
    }

}

// use the CLX to make the items draggable.
// for more information on draggables and droppables. See the jQuery documentation
C('.items img')->draggable(array('revert'=>'invalid'));
C('#basket')->droppable();

$page->reply();

?>