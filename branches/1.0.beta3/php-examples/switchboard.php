<?php

require_once("../raxan/pdi/gateway.php");

class MyPage extends RichWebPage {
    
    function _switchboard($action) {
        switch ($action) {

            case 'help' :
                $this->source('views/help.html');
                break;

            case 'thankyou' :
                $this->source('views/thankyou.html');
                break;

            case 'signup':
                $this->source('views/signup-form.html');
                $this['form [name="name"]']->preserveState();
                $this['form']->submit('.signup');
                break;


            case 'switcher' :
                    // use the switchTo() method to redirect
                    // to another action
                    $this->switchTo('help'); 
                    break;
            
            default :
                    $this->source('views/welcome.html');
                    break;
        }

        $url = $_SERVER['PHP_SELF'];
        $this['.container']->prepend(
            '<div class="pad">'.
            '   <a href="'.$url.'">Home</a>'.
            ' | <a href="'.$url.'?sba=signup">Signup</a>'.
            ' | <a href="'.$url.'?sba=switcher">Switch To Help...</a>'.
            '</div>'
        );
    }

    function signup($e){
        $rq = $this->clientRequest();
        // we're in ajax mode so let's use the C() function
        if (!$rq->text('name')) C()->alert('Sign up Error. Please enter a name.');
        else C()->switchTo('thankyou');
    }

}

RichWebPage::Init('MyPage');


?>