<?php
/**
 * Example Viewer
 */

require_once "../raxan/pdi/autostart.php";

class NewPage extends RaxanWebPage {
    
    protected function _config() {
        $this->degradable = true;
        $this->masterTemplate = 'views/master.example.php';
    }

    protected function _init() {
        $this->appendView("example-viewer.html");
    }
    
    protected function _load() {
        $id = $this->get->id;
        $viewSource = $this->get->textVal('view-source');
        $examples = Raxan::importCSV('data/examples.csv');
        foreach($examples as $example) {
            if ($example['id']==$id) {

                // set page title and tip
                $title = htmlspecialchars($example['title']);
                $tip = $example['tip']? $example['tip'] : $example['desc'];
                $this->title($title);
                $this->title->html($title);
                $this->tips->text($tip);

                // load php file
                $file = $example['filename'] ? $example['filename'] : $example['id'].'.php';
                $frame = $this->frame1;
                $frame->attr('src',$file);

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
                    if (substr($example['html'],-3)=='.js') // show Javascript code
                        $this->htmlpanel->find('code')->attr('class','javascript');
                }

                // get data source
                if ($example['data']) {
                    $this->datafile->text(' ('.$example['data'].')');
                    $this->datasource->html(htmlspecialchars(file_get_contents($example['data'])));
                    $this->datapanel->show();
                }
            }
        }
        
        // only view source code
        if ($viewSource=='on') $this->frame1->remove();
        else if ($viewSource=='off') $this->sourceCode->remove();
        
    }

}

?>

