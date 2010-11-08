<?php
/**
 *  Accessing a Remote HTML Page Example
 */
require_once "../raxan/pdi/autostart.php";

class WebPageExtractor extends RaxanWebPage {

    protected $url, $query;

    protected function _config() {
        $this->preserveFormContent = true;
        $this->query = 'latest news';
        $this->url = 'http://search.yahoo.com/search?p=';
        $this->Raxan->config('debug',false); // enable or disable debug
    }

    protected function _load() {
    }

    protected function search($e) {
        $q = $this->post->textVal('txtQuery');
        // load remote html page
        $url = $this->url . urlencode($q);
        $searchPage = new RaxanWebPage($url);
        // find the search titles (h3)
        $titles = $searchPage->find('h3');
        $html = '';
        foreach ($titles->get() as $node) {
            $html.= '<div class="bmm">'.P($node)->html() . "</div>\n"; //
        }
        $this->result->html($html);
    }

}
?>

<!DOCTYPE html>

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
        <title>Web Page Extractor</title>
        <link href="../raxan/ui/css/master.css" type="text/css" rel="stylesheet" />
        <!--[if lt IE 8]><link rel="stylesheet" href="../raxan/ui/css/master.ie.css" type="text/css"><![endif]-->
        <link href="../raxan/ui/css/default/theme.css" type="text/css" rel="stylesheet" />

    </head>

    <body>
        <div class="container c30 prepend-top">
            <form method="post">
                <div class="rax-box success">
                    <h2 class="bottom box-title">Web Page Extractor</h2>
                    Extract search results from a yahoo search page
                    <hr />
                    <label>Web Search:</label><br />
                    <input type="text" id="txtQuery" name="txtQuery" value="latest news" class="textbox"/>&nbsp;
                    <input type="submit" value="Search" xt-bind="click,search" class="button" />
                    <hr class="space"/>
                    <div id="result" class="rax-content-pal round pad"></div>
                </div>
            </form>
        </div>
    </body>

</html>
