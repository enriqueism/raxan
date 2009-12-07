<?php require_once('../raxan/pdi/autostart.php'); ?>

<!DOCTYPE html>

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
        <title>Hello World</title>
    </head>

    <body>
        <form name="form1" action="" method="post">
            <input id="mybutton" type="button" value="Click Me" xt-bind="click,buttonClick"  />
            <div id="msg" />
        </form>
    </body>

</html>


<?php

class MyPage extends RaxanWebPage {

    // callback function
    protected function buttonClick($e) {
        // select the #msg element and set html to hello world
        $this->msg->html('Hello World');
    }
}


?>