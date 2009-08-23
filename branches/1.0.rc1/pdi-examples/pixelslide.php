<?php

require_once('../raxan/pdi/gateway.php');

class PixelShow extends RichWebPage {

    protected $images;
    
    protected function _init() {
        // load page source 
        $this->source('views/slideshow.html');
        $this->loadCSS('master'); // load raxan css

        // setup image array
        $this->images = array(
            'calendar.png',
            'chart.png',
            'chart_pie.png',
            'clock.png',
            'users.png',
            'folder.png'
        );
        
    }

    protected function _load() {
        // bind a callback function to the buttons
        $this['#next']->bind('#click','.next_click');
        $this['#prev']->bind('#click','.prev_click');

        // display first image
        if (!$this->isPostback) $this->showImage(0);
    }

    protected function showImage($index) {
        $img = isset($this->images[$index]) ? $this->images[$index]: '';
        if ($img) {
            $total = count($this->images);
            // display image with fade-in effect
            C('#slide')->hide()
                ->attr('src','views/images/'.$img)
                ->fadeIn();
            C('#msg')->text('Slide '.($index+1).' of '.$total);
            // set the value to be passed to the event
            C('#next,#prev')->attr('class','v:'.$index);
        }
    }

    // next button callback
    protected function next_click($e) {
        $index = (int)$e->value;
        $this->showImage(++$index);
    }

    // prev button callback
    protected function prev_click($e) {
        $index = (int)$e->value;
        $this->showImage(--$index);
    }

}

$page = new PixelShow();
$page->reply();

?>