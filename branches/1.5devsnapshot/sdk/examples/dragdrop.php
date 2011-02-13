<?php
/**
 * Web Store Drag & Drop example
 */

require_once '../raxan/pdi/autostart.php';

class WebStorePage extends RaxanWebPage {

    protected function _init() {
        $this->source('views/dragdrop.html');
    }

    protected function  _load() {
        // listen to basket drops
        $this->basket->bind('#drop','.basketDrop');

        // make basket droppable - see the jQuery docs for droppable options
        $this->basket->droppable();

        // make items draggable - see jQuery docs for draggable options
        $this['.items img']->draggable(array('revert'=>'invalid'));
    }
    
    protected function basketDrop($e) {
        // get ID from draggable
        $id = $e->uiDraggable->getAttribute('id');
        $id = intval(substr($id,3));
        if ($id) {
            $item = $this->findById('itm0'.$id);
            $html = '<img src="'.$item->attr('src').
                    '" width="32" /> '.$item->attr('alt').'<br />';
            // use the c() function to update items inside the browser
            c('#itm0'.$id)->remove();
            c('#basket-items')->append($html);
        }
    }

}

?>