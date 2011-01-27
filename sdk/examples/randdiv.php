<?php

require_once '../raxan/pdi/autostart.php';


class NewPage extends RaxanWebPage {

    protected function _load() {
        $this->loadCSS('master');
        $this->loadTheme('default');

        $html = '';
        for($i=0;$i<100;$i++) {
            $w = rand(1, 5); $h = rand(1, 5);
            $x = rand(100, 600); $y = rand(10, 300);
            $color = $this->randomColor();  
            $bcolor = $this->randomColor();
            $html.= '<div class="c'.$w.' r'.$h.'" style="position:absolute;'
                .'border:2px solid #'.$bcolor.'; background:#'.$color.';'
                .'left:'.$x.'px;top:'.$y.'px"></div>';
        }
        $this->append($html);

        // set hover class on client
        c('div')->hoverClass('pad');
    }

    // generate random hex color
    protected function randomColor() {
        return dechex(rand(20, 254)).dechex(rand(20, 254)).dechex(rand(20, 254));
    }

}


?>
<form name="form1" action="" method="post">
    <input type="submit" name="submit1" id="submit1" value="Reload page" class="button" />
</form>