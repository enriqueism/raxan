<?php

require_once '../raxan/pdi/autostart.php';

class SearchPage extends RaxanWebPage {

    protected function _config() {
        $this->masterTemplate = '../examples/views/master.example.php';
    }

    protected function _load() {
        $query = $this->get->textVal('q');
        $pth = '../docs/'; $results = array();
        $files = dir($pth);        
        while (($file = $files->read())!=false) {
            if (substr($file,-5)!='.html') continue;
            $content = file_get_contents($pth.$file);
            $title = (preg_match('/\<h2\>(.*)\<\/h2\>/', $content,$m)) ?  $m[1] :'';
            $content = trim(strip_tags($content));
            if (($p = stripos($content,$query))) {
                $p = ($p<60) ?  0 : $p-60;
                if (!$title) $title = substr($content,0,strpos($content," - Raxan User Guide"));
                $title = str_replace('&amp;','&',$title);
                $snippet = substr($content,$p,90);
                if ($snippet) {
                    $snippet = '...'.str_ireplace($query,'<strong class="quiet">'.$query.'</strong>',$snippet).' ...';
                }
                $results[] = array('file'=>$file,'title'=>$title,'snippet'=>$snippet);
            }
        }
        if (!$results) $this->divResults->html('<h4>No results found</h4>');
        else $this->divResults->bind($results,array(
            'format'=>array('snippet'=>'html')
        ));
        $this->hdrResult->html('Search results for &quot;<span class="color-orange">'.$query.'</span>&quot;');

        // set title
        $this['title']->text('Raxan User Guide');
        $this['#header h2']->text('Raxan User Guide');
        $this['#header input[name="q"]']->val($query);
        $this['#header a:contains(Examples)']->removeAttr('class') // remove class attr from menus
            ->attr('href','../examples');
    }

}


?>

<h2 id="hdrResult"></h2>
<hr />
<div id="divResults" class="c20 prepend1">
    <div class="bmm">
        <h4 class="bottom"><a href="../docs/{file}">{title}</a></h4>
        <span class="quiet">{snippet}</span>
    </div>
</div>