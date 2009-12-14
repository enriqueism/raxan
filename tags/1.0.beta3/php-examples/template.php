<?php require_once '../raxan/pdi/autostart.php'; ?>


<h2>Hello World!</h2>

                
<?php

class NewPage extends RaxanWebPage {

    protected $masterTemplate = 'template.html';
    protected $preserveFormContent = false;

}
?>