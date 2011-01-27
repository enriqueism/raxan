<?php
/**
 * Editable Table - An example showing how to use the template binder
 * to create a simple editable grid.
 */

require_once('../raxan/pdi/autostart.php'); 

class MyPage extends RaxanWebPage {

    protected $fruits;
    protected $bindopt; //template options

    protected function _init() {
        // load and cache data in page session
        $this->fruits = & $this->data('fruits');
        if (!$this->fruits) {
            $fruits = $this->Raxan->importCSV('data/fruits.csv');
            $this->fruits = & $this->data('fruits',$fruits);
        }        
    }

    protected function _load() {
        // get templates from page
        $tplE = $this['#list1 li.edit'];
        $this->bindopt = array(
            'tplEdit' => $tplE->outerHtml(),
            'key' => 'id'
        );
        $tplE->remove();
    }

    protected function edit($e) {
        $v = $e->intVal();
        $this->bindopt['edited'] = $v;
    }

    protected function save($e) {
        $id = $e->intVal();
        $name = $this->post->textVal('fruitname');
        // find record and update data
        foreach($this->fruits as $i=>$row)
            if ($id==$row['id']) $this->fruits[$i]['name'] = $name;
    }

    protected function _prerender() {
        $this->list1->bind($this->fruits,$this->bindopt);
    }
}

?>

<!DOCTYPE html>

<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>Template Binding Demo</title>
    <link href="../raxan/ui/css/master.css" type="text/css" rel="stylesheet" />
    <!--[if lt IE 8]><link rel="stylesheet" href="../raxan/ui/css/master.ie.css" type="text/css"><![endif]-->
    <link href="../raxan/ui/css/default/theme.css" type="text/css" rel="stylesheet" />
    <style type="text/css">
        a:hover { text-decoration: none; color: #800000; }
    </style>
</head>

<body>
    <div class="container c30 prepend-top">
        <h2 class="bottom">Fruit List</h2>
        <hr />
        <div class="rax-backdrop">
            <div class="white pad">
                <h4>Template Binding Demo with CSV data</h4>
                <ul class="c15" id="list1" xt-delegate="a.edit click,edit; a.save click,save,#list1 input; a.cancel click;">
                    <li class="{ROWCLASS} item r1">
                        <a class="edit right" href="#{id}">
                            <img class="align-middle" src="views/images/pencil.png" alt="Edit" width="12" height="12" />
                            &nbsp;edit
                        </a>{name}
                    </li>
                    <li class="edit hlf-pad softyellow">
                        <span class="right">
                            <a href="#{id}" class="save">
                                <img class="align-middle" src="views/images/accept.png" alt="Save" width="12" height="12" />
                                &nbsp;save
                            </a> | <a class="cancel" href="editable.php">cancel</a>
                        </span>
                        <input type="text" value="{name}" name="fruitname" />
                    </li>
                </ul>
            </div>
        </div>
    </div>
</body>

</html>
