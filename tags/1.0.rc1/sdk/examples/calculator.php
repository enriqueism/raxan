<?php

require_once '../raxan/pdi/autostart.php'; 

class MyCalculatorPage extends RaxanWebPage {

    protected function _config() {
        $this->preserveFormContent = true;
        // enable/disable debugging
        $this->Raxan->config('debug',false);
    }
    
    protected function add(){
        $a = (int)$this->text1->val();
        $b = (int)$this->text2->val();
        $txt = $this->txtresult;
        $txt->val($a+$b);
        $txt->updateClient();
    }

}

?>

<!DOCTYPE html>

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
        <title>Calculator</title>
    </head>

    <body>
        <form name="calcForm" action="" method="post">
            <label>Add:</label><br />
            <input type="text" name="text1" id="text1" value="" size="5" /> +
            <input type="text" name="text2" id="text2" value="" size="5" />&nbsp;
            <input type="submit" name="addbtn" id="addbtn" value="Add" xt-bind="#click,add,form,,#loader" />&nbsp;
            <input type="text" name="txtresult" id="txtresult" value="" size="5" />
        </form>
        <div id="loader" style="display:none">Loading...</div>
    </body>

</html>