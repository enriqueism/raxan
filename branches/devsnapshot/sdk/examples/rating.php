<?php

require_once '../raxan/pdi/autostart.php';

class NewPage extends RaxanWebPage {
    protected function _config() {
        $this->masterTemplate  = 'views/master.homepage.html';
    }

    protected function _init() {
        $this->loadScript('jquery-ui-interactions');
        $this->loadScript('views/jquery.ui.stars.js',true);
        $this->loadCSS('views/jquery.ui.stars.css',true);
        // register rateproduct event
        $this->registerEvent('rateproduct','.setRating');
    }

    protected function setRating($e) {
        $rate = $e->intVal();
        if ($rate < 1) $rate = 1;
        $this->flashmsg('Rating successfully applied','bounce','rax-alert-pal pad close');
        return $rate;
    }
    
}

?>

<form method="post">
    <h3>Rate this product</h3>
    <div class="flashmsg"></div>
    <img src="views/images/droid.jpg" alt="Driod" width="123" height="132" class="column"/>
    <div class="column">
        <h3 class="bottom">Droid</h3>
        <p>Mediocrem quaerendum cu has, habeo inermis nominati eu sed.</p>
        Rating: <span id="stars-cap"></span>
        <div id="stars">
            <select name="rating">
                <option value="1">Very poor</option>
                <option value="2">Not that bad</option>
                <option value="3">Average</option>
                <option value="4" selected="selected">Good</option>
                <option value="5">Perfect</option>
            </select>
        </div>
        <div id="rateloader" class="hide"><img src="views/images/preloader-arrows.gif" alt="."  align="left"/>&nbsp;<em>Saving....</em></div>
    </div>
</form>

<script type="text/javascript">
    //<![CDATA[
    Raxan.ready(function(){
        $('#stars').stars({
            captionEl: $("#stars-cap"),
            inputType: "select",
            oneVoteOnly: true,
            callback: function(ui, type, value){
                var loader = $('#rateloader');
                loader.show(); $('#stars').hide();
                // dispatch server-side event
                Raxan.dispatchEvent('rateproduct', value, function(result,status){
                    if (!status) return;
                    loader.hide();
                    ui.select(result);
                    $('#stars').show();
                })
            }
        });
    })
    //]]>
</script>