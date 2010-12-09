<?php

require_once '../raxan/pdi/autostart.php'; 

class NewPage extends RaxanWebPage {

    protected function resetComments($e) {
        $mode = $e->textVal()=='session' ? 'Session' : 'Local';
        $box = $this->findById('box'.$mode);
        $box->removeState(strtolower($mode))->text('');
    }

    protected function addComment($e) {
        $mode = $e->textVal()=='session' ? 'Session' : 'Local';
        $txt = $this->post->textVal('txt'.$mode);
        $box = $this->findById('box'.$mode);
        $box->append("<div>$txt</div>");
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
            .local { margin:0 40px 0 20px; }
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
            <form name="form1" action="" method="post">
                <div class="local rax-backdrop column c17">
                    <div class="rax-content-pal pad">
                        <h3 class="bottom">Session State</h3>
                        <hr />
                        <label>Enter a comment:</label><br />
                        <input class="textbox" type="text" name="txtSession" id="txtSession" value="" size="33" />&nbsp;
                        <input class="button process" type="submit" name="btnSession" id="btnSession" value="Add Comment"
                               xt-bind="click,addComment" data-event-value="session" />&nbsp;
                        <div id="boxSession" class="rax-box alert tpm bmm c15" xt-preservestate="session" xt-autoupdate></div>
                        <input class="button cancel" type="submit" name="btnResetSession" id="btnResetSession" value="Reset State"
                               xt-bind="click,resetComments" data-event-value="session" />&nbsp;
                    </div>
                </div>

                <div class="rax-backdrop column c17">
                    <div class="rax-content-pal pad">
                        <h3 class="bottom">Locale State</h3>
                        <hr />
                        <label>Enter a comment:</label><br />
                        <input class="textbox" type="text" name="txtLocal" id="txtLocal" value="" size="33" />&nbsp;
                        <input class="button process" type="submit" name="btnLocal" id="btnLocal" value="Add Comment"
                               xt-bind="click,addComment" data-event-value="local" />&nbsp;
                        <div id="boxLocal" class="rax-box info tpm bmm c15" xt-preservestate="local" xt-autoupdate></div>
                        <input class="button cancel" type="submit" name="btnResetLocal" id="btnResetLocal" value="Reset State"
                               xt-bind="click,resetComments" data-event-value="local"/>&nbsp;
                    </div>
                </div>
                <div class="clear pad">
                    <hr class="clear "/>
                    <input class="button ok" type="submit" name="btnost" id="btnpost" value="Reload page" />&nbsp;
                    <a class="button continue" href="state1.php">Load another page</a>
                </div>
            </form>

        </div>
    </body>

</html>
