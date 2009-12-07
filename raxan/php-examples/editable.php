<?php require_once('../raxan/pdi/autostart.php'); ?>

<!DOCTYPE html>

<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>Template Binding Demo</title>
    <link href="../raxan/styles/master.css" type="text/css" rel="stylesheet" />
</head>

<body>
    <div class="container c30 prepend-top">
        <h2 class="bottom">Fruit List</h2>
        <hr />
        <h4>Template Binding Demo with CSV data</h4>
        <ul class="c15" id="list1" xt-delegate="a.edit click,edit; a.save click,save,#list1 input">
            <li class="edit hlf-pad"><a class="edit right" href="#{id}">edit</a> {name}</li>
            <li class="edit-alt hlf-pad lightgray"><a class="edit right" href="#{id}">edit</a> {name}</li>
            <li class="save hlf-pad softyellow">
                <span class="right">
                    <a href="#{id}" class="save">save</a> | <a href="editable.php">cancel</a>
                </span>
                <input type="text" value="{name}" name="fruitname" />
            </li>
        </ul>
    </div>
</body>

</html>


<?php

/**
 * Editable Table - An example showing how to use the template binder
 * to create a simple editable grid.
 */


class MyPage extends RaxanWebPage {

    protected $data;
    protected $bindopt; //template options

    protected function _init() {

        // load and cache data into page session
        $this->data = & $this->data('fruits');
        if (!$this->data) {
            $fruits = Raxan::importCSV('fruits.csv');
            $this->data = & $this->data('fruits',$fruits);
        }
        
    }

    protected function _load() {

        // get templates from page
        $tpl  = $this['#list1 li.edit'];
        $tplA = $this['#list1 li.edit-alt'];
        $tplE = $this['#list1 li.save'];
        $this->bindopt = array(
            'tpl' => $tpl->htmlMarkup(),
            'tplAlt' => $tplA->htmlMarkup(),
            'tplEdit' => $tplE->htmlMarkup(),
            'key' => 'id'
        );
        $tplA->remove(); $tplE->remove();
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



?>