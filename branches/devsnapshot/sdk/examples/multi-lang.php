<?php
include_once "../raxan/pdi/autostart.php";

// Set timezone - also needed when using E_STRICT
Raxan::config('site.timezone', 'America/Jamaica');

class MultiLangPage extends RaxanWebPage {

    protected $lang = 'en';

    protected function _init() {
        $this->loadCSS('master');
        $this->loadTheme('default');
    }

    protected function changeLocale($e) {
        $this->lang = $e->value;
        if (in_array($this->lang, array('en', 'es', 'fr'))) {
            $this->Raxan->setLocale($this->lang);
        }
    }

    protected function _prerender() {
        $dt = $this->Raxan->cDate(); // get date
        $f = $this->Raxan->locale('date.long'); // get date format
        $this->date1->text($dt->format($f));

        // higlight the selected link
        $link = $this['div a[href~="' . $this->lang . '"]'];
        $link->addClass('continue bold');
    }

}
?>

<div class="container prepend1 prepend-top">
    <div class="append-bottom" xt-delegate=".button click,changeLocale">
        <a href="lang.php#en" class="button">English</a>&nbsp;
        <a href="lang.php#es" class="button">Spanish</a>&nbsp;
        <a href="lang.php#fr" class="button">French</a>
    </div>
    <h2 id="date1"></h2>
</div>
