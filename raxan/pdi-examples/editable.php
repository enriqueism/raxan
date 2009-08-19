<?php

require_once('../raxan/pdi/gateway.php');

class MyPage extends RichWebPage {

    protected $data;
    protected $bindopt;

    protected function _init() {


        $this->loadCSS('master');
        $this->title('Template Binding Demo');
        $this->content('<div class="container c30 prepend-top">'.
            '<h2 class="bottom">Fruit List</h2><hr /><h4>Template Binding Demo with CSV data</h4>'.
            '<ul class="c15" id="list1" /></div>'
        );
        
        // load and cache data into page session
        $this->data = & $this->data('fruits');
        if (!$this->data) {
            $fruits = RichAPI::importCSV('fruits.csv');
            $this->data = & $this->data('fruits',$fruits);
        }

        // setup templates
        $tpl = '<li class="hlf-pad"><a class="edit right" href="#{id}">edit</a> {name}</li>';
        $tplA = '<li class="hlf-pad lightgray"><a class="edit right" href="#{id}">edit</a> {name}</li>';
        $tplE = '<li class="hlf-pad softyellow"><span class="right"><a class="save" href="#{id}">save</a> | <a href="editable.php">cancel</a></span><input type="text" value="{name}" name="fruitname" /></li>';
        $this->bindopt = array(
            'tpl' => $tpl,
            'tplAlt' => $tplA,
            'tplEdit' => $tplE,
            'key' => 'id'
        );

        // setup events
        $this['#list1 a.edit']->delegate('click','.edit');
        $this['#list1 a.save']->delegate('click',array('callback'=>'.save','serialize'=>'#list1 input'));
        
    }

    protected function edit($e) {
        $v = (int)$e->value;
        $this->bindopt['edited'] = $v;
    }

    protected function save($e) {
        $id = (int)$e->value;
        $name = $this->clientRequest()->text('fruitname');
        // find record and update data
        foreach($this->data as $i=>$row) {
            if ($row['id']==$id) $this->data[$i]['name'] = $name;
        }
    }

    function _prerender() {
        $this['#list1']->bind($this->data,$this->bindopt);
    }
}

RichWebPage::Init('MyPage');

?>