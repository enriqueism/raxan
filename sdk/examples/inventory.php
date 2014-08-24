<?php

require_once '../raxan/pdi/autostart.php'; 

class NewPage extends RaxanWebPage {

    protected $db, $selectedId = null;

    protected function _config() {
        $this->preserveFormContent = false;
    }
    
    protected function _load() {
        if ($this->isPostBack) $this->db = $this->data('inventory');
        else {
            $db = $this->Raxan->importCSV('data/phones.csv');
            $this->db = $this->data('inventory',$db);
            $this->rowClick(1);
        }

    }

    protected function _prerender() {
        $this->inventory->find('tbody')->bind($this->db,array(
          'altClass'=>'even',
          'selectClass'=>'rax-selected-pal rax-metalic',
          'key'=>'id',
          'selected' => $this->selectedId
        ));
    }

    protected function rowClick($e) {
        $id = is_int($e) ? $e : $e->intVal();
        $this->selectedId = $id;
        $row = $this->db[$id-1]; // get row data
        $img = '<img src="views/images/'.$row['photo'].'" align="left" class="pad" />';
        $details  = $row['details'];
        $this->info->html($img.$details);
    }
    
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>Phone Inventory</title>
    <link href="../raxan/ui/css/master.css" type="text/css" rel="stylesheet" />
    <!--[if lt IE 8]><link rel="stylesheet" href="../raxan/ui/css/master.ie.css" type="text/css"><![endif]-->
    <link href="../raxan/ui/css/default/theme.css" type="text/css" rel="stylesheet" />
</head>

<body>
    <div class="c35 container prepend-top">
        <h2>Phone Inventory</h2>
        <table id="inventory" class="rax-table column c20 rax-box-shadow">
            <thead>
                <tr class="tbl-header">
                    <th>SKU</th><th>Description</th><th>Qty</th>
                </tr>
            </thead>
            <tbody xt-delegate="tr #click,rowClick" xt-autoupdate>
                <tr id="row{id}" class="{ROWCLASS} mouse-cursor" data-event-value="{id}">
                    <td>{sku}</td><td>{desc}</td><td>{qty}</td>
                </tr>
            </tbody>
        </table>
        <div id="info" class="column c13" xt-autoupdate></div>
    </div>
</body>

</html>