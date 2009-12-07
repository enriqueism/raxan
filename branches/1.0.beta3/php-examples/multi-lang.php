<?php

include_once "../raxan/pdi/gateway.php";

// Set timezone - also needed when using E_STRICT
Raxan::config('site.timezone','America/Jamaica');

class MultiLangPage extends RaxanWebPage {

    protected $lang = 'en';

    protected $html = '<p><a href="lang.php#en">English</a> |
                      <a href="lang.php#es">Spanish</a> | 
                      <a href="lang.php#fr">French</a>
                      <div id="date" /></p>';

    protected function _init() {
        $this->content($this->html);
    }

    protected function _load() {        
        $this['p a']->bind('click',array(
            'callback' => '.lang_click',
         ));
    }

    protected function lang_click($e){
        $this->lang = $e->value;
        if (in_array($this->lang,array('en','es','fr'))) {
            Raxan::setLocale($this->lang);
        }
    }

    protected function _prerender() {
        $dt = Raxan::cDate(); // get date
        $f = Raxan::locale('date.long'); // get date format
        $this['#date']->text($dt->format($f));

        // higlight the select link
        $this['p a[href~="'.$this->lang.'"]']
            ->css('font-weight','bold')
            ->css('color','green');
    }
}

$p = new MultiLangPage(); $p->reply();

?>
