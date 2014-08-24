<?php
/**
 *  Mobile Weather (WAP/WML) Demo
 *  Requires a WAP enabled mobile phone or a WAP emulator
 */

require_once "../raxan/pdi/autostart.php";

class MobileWeather extends RaxanWebPage {

    protected $query  = 'new york';
    protected $url = 'http://www.wolframalpha.com/input/?i=weather+';

    protected function _config() {
        $this->responseType = 'wml';
    }

    protected function _load() {
        $q = $this->post->textVal('weather');
        if ($q) $this->query = $q;
        $this->weather->val($this->query);
    }

    protected function _prerender(){
        // load remote html page
        if ($this->isPostBack){
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
            $this->result->html($wml);
        }
    }
}


?>
<?xml version="1.0" standalone="yes"?>
<!DOCTYPE wml PUBLIC "-//WAPFORUM//DTD WML 1.1//EN" "http://www.wapforum.org/DTD/wml_1.1.xml">
<wml>
    <card title="Weather Form">
        Enter Location:<br/>
        <input id="weather" name="weather"/><br/>
        <anchor>Send
            <go method="post" href="mobile-weather.php"><postfield name="weather" value="$(weather)"/></go>
        </anchor>
        <p id="result"></p>
    </card>
</wml>
