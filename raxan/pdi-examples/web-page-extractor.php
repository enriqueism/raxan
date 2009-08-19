<?php
/**
 *  Accessing a Remote HTML Page Example
 */

require_once "../raxan/pdi/gateway.php";

//RichAPI::config('debug',true);

class WebPageExtractor extends RichWebPage {
    protected $query  = 'pre vs iphone';
    protected $url = 'http://search.yahoo.com/search?p=';

    function _init() {
        $this->title('Web Page Extractor');
        $this->loadCSS('master');
        $this->content('<div class="container c30 prepend-top"><form method="post">'.
            '<h2 class="bottom">Web Page Extractor</h2>'.
            '<p>Extract search results from a yahoo search page</p>'.
            '<label>Web Search:</label><br />'.
            '<input type="text" name="query" value="'.$this->query.'" />&nbsp;'.
            '<input type="submit" value="Search" />'.
            '</form><div id="result" class="pad"></div></div>'
        );

        $this->preserveFormContent = true;
    }

    function _load() {
        $q = $this->clientRequest()->text('query');
        if ($q) $this->query = $q;
    }

    function _prerender(){
        // load remote html page
        $url = $this->url.urlencode($this->query);
        $search = new RichWebPage($url);
        // find the search titles (h3)
        $titles = $search['h3']; $html = '';
        foreach($titles->get() as $node){
            $html.= P($node)->html()."<br />\n"; //
        }
        $this['#result']->html($html);
    }
}

RichWebPage::Init('WebPageExtractor');

?>