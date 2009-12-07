<?php

require_once('../raxan/pdi/gateway.php');

class MyPage extends RichWebPage {

    protected function _load() {

        // add some content to the page
        $this->content('<input id="mybutton" type="button" value="Click Me"  /><div id="msg" />');

        // bind a callback function to the mybutton input element
        $this['#mybutton']->bind('click','.button_click');

        // note the dot (.) in .button_click – This tells the framework to look for
        // the button_click function the current page object.
    }

    // callback function
    protected function button_click($e) {
        // select the #msg element and set html to hello world
        $this['#msg']->html('Hello World');
    }
}

$page = new MyPage();
$page->reply();

?>