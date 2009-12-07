<?php require_once('../raxan/pdi/autostart.php');  ?>

<!DOCTYPE html>

<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>Pixel Slide show</title>
    <link href="../raxan/styles/master.css" type="text/css" rel="stylesheet" />
</head>

<body>
    <div class="container c30">
        <h2>Click the arrows to change the slide</h2>
        <div class="prepend1" xt-autoupdate>
            <a id="prev" href="#" xt-bind="#click,prevClick" ><img src="views/images/back.png" /></a>
            <img id="slide" src="" width="64" height="64" />
            <a id="next" href="#" xt-bind="#click,nextClick"><img  src="views/images/next.png" /></a>
            <div id="msg"></div>
        </div>
    </div>
</body>

</html>
<?php



class PixelShow extends RaxanWebPage {

    protected $images, $slideNo;
    
    protected function _load() {
        // setup image array
        $this->images = array(
            'calendar.png','chart.png','chart_pie.png',
            'clock.png','users.png','folder.png'
        );

        $this->slideNo = & $this->data('slide',0,true);
    }

    protected function _prerender() {
        // display image
        $this->showImage();
    }

    protected function showImage($index = null) {
        $index = $index ? $index : $this->slideNo ;

        $img = isset($this->images[$index]) ? $this->images[$index]: '';
        if ($img) {
            // display image with fade-in effect
            $this->slide->attr('src','views/images/'.$img)
                 ->client->hide()->fadeIn();
            $total = count($this->images);
            $this->msg->text('Slide '.($index+1).' of '.$total);
        }
    }

    // next button callback
    protected function nextClick($e) {
        $this->slideNo++;
        $total = count($this->images) - 1;
        if ($this->slideNo > $total) $this->slideNo = $total;
        if ($this->isCallback) $this->showImage();
    }

    // prev button callback
    protected function prevClick($e) {
        $this->slideNo--;
        if ($this->slideNo <0 ) $this->slideNo = 0;
        if ($this->isCallback) $this->showImage();
    }

}

?>