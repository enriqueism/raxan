<?php
/**
 *  Mobile Weather Demo
 *  Requires a WAP enabled mobile phone or a WAP emulator
 */

require_once "../raxan/pdi/gateway.php";

class MobileWather extends RaxanWebPage {
    protected $query  = 'new york';
    protected $url = 'http://www.wolframalpha.com/input/?i=weather+';

    function _init() {
        $this->responseType = 'wml';
        $this->source('wml:page'); // load blank XML page
        $this['card']->attr('title','Weather Form');

        $this->content('Enter Location:<br/>
            <input id="weather" name="weather"/><br/>
            <anchor>Send
                <go method="post" href="mobile-weather.php"><postfield name="weather" value="$(weather)"/></go>
            </anchor>
            <p id="result"></p>'
        );
    }

    function _load() {
        $q = $this->clientRequest()->text('weather');
        if ($q) $this->query = $q;
        $this['#weather']->val($this->query);
    }

    function _prerender(){
        // load remote html page
        if ($this->isPostback){
            $url = $this->url.urlencode($this->query);
            $wr = new RaxanWebPage($url); // get page from WolframAlpha
            // get data from page
            $title = $wr['img#i_0100_1']->attr('alt');
            $data = $wr['img#i_0200_1']->attr('alt');
            if (!$data) $wml = 'No information for the location.';
            else {
                // format data
                $bar = '-----------------------';
                $wml = $title.'<br />'.$bar.'<br />'.
                    '<b>'.str_replace(array('\\n','|'),array('<br />'.$bar.'<br /><b>','</b>:<br />'), $data).'</b>';
            }
            $this['#result']->html($wml);
        }
    }
}

RaxanWebPage::Init('MobileWather');

?>