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
            <form name="form1" action="" method="post">
                <label>Enter a comment:</label><br />
                <input type="text" name="text1" id="text1" value="" />&nbsp;
                <input type="submit" name="submit1" id="submit1" value="Add Comment" xt-bind="click,addComment" />&nbsp;
                <input type="submit" name="btnpost" id="btnpost" value="Submit" />
                <div id="box" class="pad" xt-preservestate="global" xt-autoupdate></div>
                <input type="submit" name="resetbtn" id="resetbtn" value="Reset" xt-bind="click,resetComments"/>&nbsp;
                <a href="state1.php">Load another page</a>
            </form>
            
        </div>
    </body>

</html>

<?php

class NewPage extends RaxanWebPage {

    protected $degradable = false;
    protected $serializeOnPostBack = '';
    protected $preserveFormContent = false;


    protected function resetComments($e) {
        $this->box->text('')->removeState();
    }

    protected function addComment($e) {
        //$this->box->css('background','#ffcc00');
        $p = $this->sanitizePostBack();
        $this->box->append($p->text('text1').'<br />');
    }

}

?>