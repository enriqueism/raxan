<?php

require_once('../raxan/pdi/gateway.php');

RichAPI::loadPlugin('plugins/table.php', true);

class MyPage extends RichWebPage {

    protected function _load() {
        $this->title('Table Plugin Demo');

        $data = RichAPI::importCSV('addressbook.csv');

        $btn = $this['<a id="btnhide" />']->appendTo('body');
        $btn->text('Toggle Address Column');
        $hidecolumn = isset($_GET['toggle']) ? true : false;
        $btn->attr('href','csv-table.php'.(!$hidecolumn ? '?toggle=1': ''));

        $tbl =  new Table();
        $tbl->addColumn('name','Name');
        if (!$hidecolumn) $tbl->addColumn('address','Address');
        $tbl->addColumn('country','Country');
        $tbl->dataSource($data);
        $tbl->appendTo('body'); // append to the body tag
        $this->tbl = $tbl;

    }

}

RichWebPage::Init('MyPage');


?>