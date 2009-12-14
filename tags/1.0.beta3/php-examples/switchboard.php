<?php require_once '../raxan/pdi/autostart.php'; ?>

<!DOCTYPE html>

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
        <title>Switchboard</title>
        <link href="../raxan/styles/master.css" type="text/css" rel="stylesheet" />
    </head>

    <body>
        <div class="container c35">
            <div class="pad">
               <a href="switchboard.php">Home</a>
             | <a href="switchboard.php?sba=signup">Signup</a>
             | <a href="switchboard.php?sba=switcher">Switch To Help...</a>
            </div>
        </div>

    </body>

</html>

<?php

class MyPage extends RaxanWebPage {

    
    protected function _switchboard($action) {
        switch ($action) {

            case 'help' :
                $this->appendView('help.html');
                break;

            case 'thankyou' :
                $this->appendView('thankyou.html');
                break;

            case 'signup':
                $this->appendView('signup-form.html');
                $this->preserveFormContent = true;
                break;

            case 'switcher' :
                    // use the switchTo() method to redirect
                    // to another action
                    $this->switchTo('help'); 
                    break;
            
            default :
                    $this->appendView('welcome.html');
                    break;
        }

    }

    protected function signup($e){
        $post = $this->sanitizePostBack();

        // we're in ajax mode so let's use the C() function
        if (!$post->text('name')) C()->alert('Sign up Error. Please enter a name.');
        else C()->switchTo('thankyou');
    }

}

?>