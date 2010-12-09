<?php

require_once '../raxan/pdi/autostart.php';

class NewPage extends RaxanWebPage {
    protected function _config() {
        $this->masterTemplate = 'views/master.homepage.html';
        $this->registerEvent('getphones','.listPhones');
        $this->registerEvent('rateproduct','.setRating');

        $this->loadScript('jquery');
        $this->loadScript('jquery-ui-interactions');
        $this->loadScript('views/jquery.ui.stars.js',true);
        $this->loadCSS('views/jquery.ui.stars.css',true);
    }

    protected function listPhones($e) {
        $phones = $this->Raxan->importCSV('data/phones.csv');
        return $phones;
    }


    protected function setRating($e) {
        $p = $e->val();
        $index = $p['index'];
        $rate = $p['rate'];
        return $rate;
    }

}

?>

<hr class="space" />
<div class="c20 ui-widget ui-widget-shadow rax-fixed-shadow">
    <div class="round white pad">
        <div class="round hlf-pad bmm">
            <span id="nextbtn" class="right ui-icon ui-icon-carat-1-e round lightgray click-cursor" style="margin-left:2px"></span>
            <span id="prevbtn" class="right ui-icon ui-icon-carat-1-w round lightgray click-cursor"></span>
            <h3 class="bottom">Smart Phones</h3>
        </div>
        <p id="infotext">Click the arrows at the top to view the latest Smart Phones in our product catalog.</p>
        <hr class="clear" />
        <form id="frmRating" class="hide">
            Rating: <span id="stars-cap"></span>
            <div id="stars">
                <select name="rating">
                    <option value="1">Very poor</option>
                    <option value="2">Not that bad</option>
                    <option value="3">Average</option>
                    <option value="4">Good</option>
                    <option value="5">Perfect</option>
                </select>
            </div>
            <div id="rateloader" class="hide"><img src="views/images/preloader-arrows.gif" alt="."  align="left"/>&nbsp;<em>Saving....</em></div>
        </form>
        <br class="clear" />
    </div>
</div>

<script type="text/javascript">
    // <![CDATA[
    Raxan.ready(function(){
        var index,phones;
        function showPhones(i) {
            if (!i||i<0) i = 0;
            else if (i>phones.length-1) i = phones.length-1;
            var rate = phones[i]['rate'];
            var html = '<img class="left margin" src="views/images/'+phones[i]['photo']+'" />' + 
                '<h2 class="bottom">'+phones[i]['desc']+'</h2>' +
                phones[i]['details']
            var t = $('#infotext')
            if(!rate) rate = 0;
            t.fadeOut('fast',function(){
                t.html(html).fadeIn();
                $('#stars').stars({disabled:false});
                $('#stars').stars("select",rate);
            });
            index = i;
        }

        $('#prevbtn,#nextbtn').click(function() {
            index = (this.id=='prevbtn') ? --index : ++index;
            if (phones) showPhones(index);
            else {
                $('#infotext').text('Loading....');
                Raxan.dispatchEvent('getphones', function(result,status){
                    if (!status) return;
                    phones = result;
                    showPhones(index);
                    $('#frmRating').show();
                })
            }
        })

        // rating
        $('#stars').stars({
            captionEl: $("#stars-cap"),
            inputType: "select",
            oneVoteOnly: true,
            callback: function(ui, type, value){
                var loader = $('#rateloader');
                loader.show(); $('#stars').hide();
                Raxan.dispatchEvent('rateproduct', {index:index, rate:value}, function(result,status){
                    if (!status) return;
                    loader.hide();
                    ui.select(result);
                    $('#stars').show();
                    phones[index]['rate'] = result;
                })
            }
        });

    })

    // ]]>
</script>