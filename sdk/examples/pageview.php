<?php

require_once("../raxan/pdi/autostart.php"); 

class MyPage extends RaxanWebPage {

    protected function _config() {
        $this->masterTemplate = 'views/master.homepage.html';
    }

    // -- View handlers

    protected function _indexView() {
        $this->appendView('pageview.welcome.html');
    }

    protected function _helpView() {
        $this->appendView('pageview.help.html');
    }

    protected function _thankyouView() {
        $this->appendView('pageview.thankyou.html');
    }

    protected function _signupView() {
        $this->appendView('pageview.signup-form.html');
        $this->preserveFormContent = true;
    }

    protected function _switcherView() {
        // redirect to the help view
        $this->redirectToView('help');
    }

    // -- Event handlers

    protected function signup($e){
        $name = $this->post->textVal('name');
        // we're in ajax mode so let's use the C() function
        if (!$name) $this->flashmsg('Sign up Error. Please enter a name.','fade','rax-box error');
        else $this->redirectToView ('thankyou'); // redirect to the thankyou view
    }

}


?>

<div class="container append-bottom">
    <h3>Links</h3>
    <div class="pad round border2">
       <a href="pageview.php">Home</a>
     | <a href="pageview.php?vu=signup">Sign Up</a>
     | <a href="pageview.php?vu=switcher">Switch To Help...</a>
    </div>
</div>


