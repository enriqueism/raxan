<?php

require_once '../raxan/pdi/autostart.php';

class FlashPage extends RaxanWebPage {

    protected $closeIcon;

    protected function _config() {
        $this->closeIcon = '<span class="close ui-icon ui-icon-close right click-cursor"></span>';
    }

    // -- Event handlers

    protected function flashMessage($e) {
        $this->flashmsg('This is a Normal Message');
    }

    protected function flashMessageById($e) {
        $this->flashmsg($this->closeIcon.'Message for DOM element #personalmsg',null,'rax-box alert','personalmsg');
    }

    protected function effects($e) {
        $effect = $e->textVal();
        $this->flashmsg($this->closeIcon.'Message with <strong>'.$effect.'</strong> effect',$effect,'rax-box');
    }

    protected function expose($e) {
        $this->flashmsg($this->closeIcon.'Message with expose options','fade','rax-box error',null,array(
            'color'=>'#eee',
            'closeOnClick' => true,
            'closeOnEsc' => true
        ));
    }

}

?>

<!DOCTYPE html>

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
        <title>Flash Message</title>
        <link href="../raxan/ui/css/master.css" type="text/css" rel="stylesheet" />
        <!--[if lt IE 8]><link rel="stylesheet" href="../raxan/ui/css/master.ie.css" type="text/css"><![endif]-->
        <link href="../raxan/ui/css/default/theme.css" type="text/css" rel="stylesheet" />
    </head>

    <body>
        <div class="container prepend-top">
            <div class="rax-backdrop c18 column">
                <div class="rax-content-pal round pad" >
                    <h3 class="rax-alert-pal hlf-pad">Flash Message</h3>
                    <form name="form1" action="" method="post">
                        <div class="ctrl-group">
                            <input class="button c8" type="submit" value="Flash Message" xt-bind="#click,flashMessage" />&nbsp;
                            <input class="button c8" type="submit" value="Flash By Id" xt-bind="#click,flashMessageById" />
                        </div>
                        <div class="ctrl-group">
                            <input class="button c8" type="submit" value="Bounce Effect" xt-bind="#click,effects" data-event-value="bounce" />&nbsp;
                            <input class="button c8" type="submit" value="Fade In Effect" xt-bind="#click,effects" data-event-value="fade" />
                        </div>
                        <div class="ctrl-group">
                            <input class="button c8" type="submit" value="Pulsate Effect" xt-bind="#click,effects"  data-event-value="pulsate" />&nbsp;
                            <input class="button c8" type="submit" value="Clip Effect" xt-bind="#click,effects"  data-event-value="clip" />
                        </div>
                        <div class="ctrl-group">
                            <input class="button c8" type="submit" value="Drop Effect" xt-bind="#click,effects"  data-event-value="drop" />&nbsp;
                            <input class="button c8" type="submit" value="Drop Down Effect" xt-bind="#click,effects"  data-event-value="drop-up" />
                        </div>
                        <div class="ctrl-group">
                            <input class="button c8" type="submit" value="Explode" xt-bind="#click,effects"  data-event-value="explode" />&nbsp;
                            <input class="button c8" type="submit" value="Puff Effect" xt-bind="#click,effects"  data-event-value="puff" />
                        </div>
                        <div class="ctrl-group">
                            <input class="button c8" type="submit" value="Slide Effect" xt-bind="#click,effects"  data-event-value="slide" />&nbsp;
                            <input class="button c8" type="submit" value="Slide Up Effect" xt-bind="#click,effects"  data-event-value="slide-down" />
                        </div>
                        <div class="ctrl-group">
                            <input class="button c8" type="submit" value="Expose Message" xt-bind="#click,expose"  />&nbsp;
                            <input class="button c8" type="submit" value="Custom Message" xt-bind="#click,effects" data-event-value="custom"  />
                            <script type="text/javascript">
                                Raxan.ready(function(){
                                    $('#msg').bind('flashmsg',function(e, mode) {
                                        var fl = $('.rax-flash-msg',this); // get the .rax-flash-msg element 
                                        var fx = fl.attr("data-flash-fx"); // get fx
                                        if (fx=="custom") {
                                            if (mode=='on') fl.addClass('success').fadeIn('slow');
                                            else if (mode=='off') fl.stop().hide('slide');
                                        }
                                    })
                                })
                            </script>
                        </div>
                    </form>
                </div>
            </div>
            <div class="column last c20 prepend2">
                <div id="msg" class="flashmsg"></div>
                <div id="personalmsg" class="flashmsg tpm"></div>
            </div>
        </div>
    </body>

</html>
