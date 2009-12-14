<?php

require_once('../raxan/pdi/gateway.php');

class LinkPage extends RichWebPage {

    protected $text = array();

    protected function _init() {
        // setup text array
        $this->text[] = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';
        $this->text[] = 'Nam quis tortor nec justo porta scelerisque.';
        $this->text[] = 'Suspendisse pretium nisl ac urna.';
        $this->text[] = 'Donec molestie, mi et porta consectetur. ';
    }
    
    protected function _load() {
        $this->loadCSS('master');
        $this['body']->append('
            <div class="container c25 dbl-pad">
                <div id="menu">
                    <a href="#1">Home</a>&nbsp;|&nbsp;
                    <a href="#2">Products</a>&nbsp;|&nbsp;
                    <a href="#3">Contact</a>&nbsp;|&nbsp;
                    <a href="#4">About</a>
                </div>
                <hr />
                <div id="details" class="box hide"></div>
            </div>
        ');

        // listen to click event
        $this['div#menu a']->bind('#click','.a_click');
    }

    protected function a_click($e) {
        // reset color on all link siblings
        C('this')->parent()->find('a')
            ->css('background','none');
            
        C('this')->css('background','yellow'); // highlight the current link;

        $id = (int)$e->value; // convert to int
        C('#details')->hide()
            ->html($this->text[$id-1])  // display text
            ->fadeIn('slow');   
    }

}

// initialize the web page
RichWebPage::Init('LinkPage'); // alternative to new LinkPage()

?>