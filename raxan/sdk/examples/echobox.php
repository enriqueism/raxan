<?php
    require_once '../raxan/pdi/autostart.php';

    class EchoPage extends RaxanWebPage {

        protected function _init() {
            $this->appendView('echo.html'); // append the echo.html view to the page
        }

        protected function echoMessage($e) {
            $message = $this->post->textVal('text1'); // sanitize input text
            $message = 'You have entered <span>&quot;'.$message.'&quot;</span>';
            $this->echoText->html($message); // echo message to page
       }

    }
?>

