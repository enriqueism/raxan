<?php

require_once '../raxan/pdi/autostart.php'; 

Raxan::loadPlugin('plugins/showdate.php',true);

class NewPage extends RaxanWebPage {

    protected function _config() {
        $this->masterTemplate = 'views/master.homepage.html';
    }
   
}

?>

<h3>Date/Time Plugin test page</h3>
<p>
    Lorem ipsum cu nam impedit efficiantur, ei aperiri dissentiet eos, mea dico error saperet in.
    Vidisse pertinax deterruisset id vel, dicunt audire labitur his eu. Pro magna propriae at,
    augue choro quodsi est eu. Tota cotidieque reformidans ei qui, ad dicit impetus persequeris pri,
    harum accommodare id per. Mediocrem quaerendum cu has, habeo inermis nominati eu sed.
</p>
<form name="form1" action="" method="post">
    <input type="submit" name="submit1" id="submit1" value="Reload page" class="button" />
</form>