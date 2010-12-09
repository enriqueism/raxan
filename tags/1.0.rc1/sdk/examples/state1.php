<?php

require_once '../raxan/pdi/autostart.php';

class NewPage extends RaxanWebPage {

    protected function _config() {
        $this->preserveFormContent = false;
    }

}

?>

<!DOCTYPE html>

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
        <title>State Management</title>
        <link href="../raxan/ui/css/master.css" type="text/css" rel="stylesheet" />
        <!--[if lt IE 8]><link rel="stylesheet" href="../raxan/ui/css/master.ie.css" type="text/css"><![endif]-->
        <link href="../raxan/ui/css/default/theme.css" type="text/css" rel="stylesheet" />
        <style type="text/css">
            #boxLocal, #boxSession { padding-bottom:20px}
            #boxLocal div, #boxSession div {
                padding:2px;
                border-bottom: solid 1px #eecc00;
            }
        </style>
    </head>

    <body>
        <div class="container c40 prepend-top">
            <h2>State Management</h2>
            <hr />
            <p>These boxes are from the previous page.</p>
            <h3>Box with Local State</h3>
            <div id="boxLocal" class="rax-box info tpm bmm c15" xt-preservestate="local" xt-autoupdate></div>
            <hr class="space" />
            <h3 class="bmm">Box with Session State</h3>
            <p>This box should retain it's content from the previous page</p>
            <div id="boxSession" class="rax-box alert tpm bmm c15" xt-preservestate="session" xt-autoupdate></div>
            <hr class="space" />
            <a href="state.php" class="button continue">Go to previous page</a>
        </div>
    </body>

</html>
