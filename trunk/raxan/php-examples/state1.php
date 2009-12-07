<?php require_once '../raxan/pdi/autostart.php'; ?>

<!DOCTYPE html>

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
        <title>State Management</title>
        <link href="../raxan/styles/master.css" type="text/css" rel="stylesheet" />
    </head>

    <body>
        <div class="container c40 prepend-top">
            <h2>State Management</h2>
            <hr />
            <p>This is the Message Box from the previous page:</p>
            <div id="box" xt-preservestate="global"></div>
            <a href="state.php">Go back to page</a>
        </div>
    </body>

</html>

<?php

class NewPage extends RaxanWebPage {

    protected $preserveFormContent = false;

}

?>