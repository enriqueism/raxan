<?php

require_once "../raxan/pdi/autostart.php";

include "example.html";

class NewPage extends RaxanWebPage {
    protected $degradable = true;

    protected function _load() {
        $id = htmlspecialchars($_GET['id']);
        $examples = Raxan::importCSV('examples.csv');
        foreach($examples as $example) {
            if ($example['file']==$id) {

                // set page title and tip
                $title = htmlspecialchars($example['title']);
                $tip = $example['tip']? $example['tip'] : $example['desc'];
                $this->title($title);
                $this->title->html($title);
                $this->tips->text($tip);

                // load php file
                $file = $example['file'].'.php';
                $frame = $this->frame;
                if ($example['file']!='mobile-weather') $frame->attr('src',$file);
                else $frame->replaceWith('<a href="'.$file.'">Click here to view the Mobile Weather Page</a>');

                // get source
                $src = htmlspecialchars(file_get_contents($file));
                $src = str_replace('../raxan','raxan',$src);
                $this->phpsource->html($src);

                // get plugin source
                if ($example['plugin']) {
                    $this->pluginfile->text(' ('.$example['plugin'].')');
                    $this->pluginsource->html(htmlspecialchars(file_get_contents($example['plugin'])));
                    $this->pluginpanel->show();
                }

                // get html source
                if ($example['html']) {
                    $this->htmlfile->text(' ('.$example['html'].')');
                    $this->htmlsource->html(htmlspecialchars(file_get_contents($example['html'])));
                    $this->htmlpanel->show();
                }

                // get data source
                if ($example['data']) {
                    $this->datafile->text(' ('.$example['data'].')');
                    $this->datasource->html(htmlspecialchars(file_get_contents($example['data'])));
                    $this->datapanel->show();
                }
            }
        }
    }

}

?>

