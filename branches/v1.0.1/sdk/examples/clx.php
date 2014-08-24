<?php

require_once '../raxan/pdi/autostart.php';

class CLXPage extends RaxanWebPage {
    protected function _init() {
        // register fadeout event
        $this->registerEvent('fadeOut', '.fadeOut');
    }

    protected function fadeIn($e) {
        $clx = c('#boxFade');
        $clx->fadeIn(2000,_event('#fadeOut'));
    }

    protected function fadeOut($e){
        $clx = c('#boxFade');
        $clx->fadeOut(3000);
    }

    protected function slideDownPanel($e) {
        $data = 'Name: Mary Jane<br />
            Address: 35 Cyberspace Drive<br />
            Email: mj@gmail.com<br />';
        c('#personal')->html($data)->slideDown(); // show info
        c('#btnSlideUp')->removeAttr('disabled')->removeClass('disabled'); // enable button
    }

    protected function slideUpPanel($e) {
        c('#personal')->slideUp();
        c('#btnSlideUp')->attr('disabled','disabled')->addClass('disabled'); // hide info and disable bbutton
    }

    protected function animateBox($e) {
        c('#boxAnimate')->css(array(
          'left'  => '0px',
          'width'  => '20px',
          'height'  => '20px',
          'display'  => 'block'
        ))->animate(array(
            'borderWidth'=>'10px',
            'width'=>'200px',
            'height'=>'200px',
            'left'=>'150px',
            'top'=>'50px',
        ))->animate(array(
            'borderWidth'=>'0',
            'width'=>'0',
            'height'=>'0',
            'top'=>'0',
            'left'=>'400px',
        ));
    }

}

?>

<!DOCTYPE html>

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
        <title>Client Extension</title>
        <link href="../raxan/ui/css/master.css" type="text/css" rel="stylesheet" />
        <!--[if lt IE 8]><link rel="stylesheet" href="../raxan/ui/css/master.ie.css" type="text/css"><![endif]-->
        <link href="../raxan/ui/css/default/theme.css" type="text/css" rel="stylesheet" />
    </head>

    <body>
        <div class="container prepend-top">
            <div class="rax-backdrop c10 column">
                <div class="rax-content-pal round pad text-center" >
                    <h3 class="rax-alert-pal hlf-pad">Client Extension</h3>
                    <form name="form1" action="" method="post">
                        <div class="ctrl-group">
                            <input class="button c8" type="submit" name="btnFadeInOut" id="btnFadeInOut" value="Fade In/Out" xt-bind="#click,fadeIn" />
                        </div>
                        <div class="ctrl-group">
                            <input class="button c8" type="submit" name="btnSlideDown" id="btnSlideDown" value="Slide Down Panel" xt-bind="#click,slideDownPanel" />
                        </div>
                        <div class="ctrl-group">
                            <input class="button disabled c8" type="submit" name="btnSlideUp" id="btnSlideUp" value="Slide Up Panel" disabled="disabled" xt-bind="#click,slideUpPanel" />
                        </div>
                        <div class="ctrl-group">
                            <input class="button c8" type="submit" name="btnAnimate" id="btnAnimate" value="Animate Box" xt-bind="#click,animateBox" />
                        </div>
                    </form>
                </div>
            </div>
            <div class="column last c20 prepend2">
                <div id="boxFade" class="rax-box success c10 margin hide">
                    <h3>Fade In/Out</h3>
                    <p>This box will fade-out in 2 seconds</p>
                </div>
                <div id="personal" class="rax-box alert pad hide"></div>
                <div id="boxAnimate" class="border1 above hide"></div>
            </div>
        </div>
    </body>

</html>
