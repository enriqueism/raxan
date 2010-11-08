<?php

require_once '../raxan/pdi/autostart.php';

class ImageSearchPage extends RaxanWebPage {
    protected function _config() {
        $this->masterTemplate = 'views/master.homepage.html';
    }
    
    protected function _init() {
        $this->registerEvent('search','.searchFiles');
        $this->loadScript('jquery');
    }

    protected function searchFiles($e) {
        $query = $e->matchVal('/[a-z0-9\*]+/i');
        $query = substr($query,0,30);
        $pth = 'views/images/';
        $filter = '{'.$pth.$query.'.jpg,'.$pth.$query.'.png,'.$pth.$query.'.gif}';
        $files = (!$query||$query=='*') ? 
            array() : glob($filter,GLOB_BRACE);
        return $files;
    }
}

?>

<div class="right c16 clip">
    <img id="imgviewer" src="" class="hide" alt="image">
</div>

<div class="left c20 rax-backdrop">
    <div class="round rax-content-pal pad">
        <form name="form1" action="" method="post">
            <div class="ctrl-group">
                <strong>Image Search</strong><br />
                <input type="text" name="txtquery" id="txtquery" value="" class="textbox" size="30" maxlength="30">&nbsp;
                <input type="button" name="button1" id="button1" value="Search" class="button">
            </div>
        </form>
        <div id="result" class="r9 scrollable">Enter image file name (e.g phone, *phone)</div>
    </div>
</div>

<script type="text/javascript">
    // <![CDATA[
    Raxan.ready(function(){
        $('#button1').click(function(){
            var query = $('#txtquery').val();
            $('#result').html('Searching...');
            Raxan.dispatchEvent('search',query,function(result,success){
                if (!success) return;
                var f,li = '';
                for (f in result) 
                    li+= '<div><span class="ui-icon ui-icon-carat-1-e left"><\/span>'+
                    '<a class="left" href="'+result[f]+'">'+result[f]+'<\/a><br class="clear" /><\/div>';
                li = 'Showing results for '+query+'<hr />'+li+'';
                $('#result').html(li);
            })
        })
        $('#result').delegate('a','click',function(e){
            $('#imgviewer').attr('src',$(this).attr('href')).show();
            e.preventDefault();
            return false;
        })
    })
    // ]]>
</script>