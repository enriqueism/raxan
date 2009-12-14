<?php  require_once '../raxan/pdi/autostart.php'; ?>

<p>
    Lorem ipsum cu nam impedit efficiantur, ei aperiri dissentiet eos, mea dico error saperet in. 
    Vidisse pertinax deterruisset id vel, dicunt audire labitur his eu. Pro magna propriae at, 
    augue choro quodsi est eu. Tota cotidieque reformidans ei qui, ad dicit impetus persequeris pri, 
    harum accommodare id per. Mediocrem quaerendum cu has, habeo inermis nominati eu sed.
</p>
<div id="box" style="color:red"></div>

<?php

Raxan::loadPlugin('plugins/showdate.php',true);

class NewPage extends RaxanWebPage {

    protected $masterTemplate = 'template.html';

   
}

?>