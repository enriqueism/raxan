<?php require_once '../raxan/pdi/autostart.php'; ?>

<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>Table</title>
    <link href="../raxan/styles/master.css" type="text/css" rel="stylesheet" />
</head>

<body>
    <div class="c35 container prepend-top">
        <h2>Phone Inventory</h2>
        <table id="inventory" class="column c20">
            <thead>
                <tr class="tbl-header">
                    <th>SKU</th><th>Description</th><th>Qty</th>
                </tr>
            </thead>
            <tbody xt-delegate="tr #click,rowClick" xt-autoupdate>
                <tr id="row{id}" class="mouse-cursor v:{id}">
                    <td>{sku}</td><td>{desc}</td><td>{qty}</td>
                </tr>
            </tbody>
        </table>
        <div id="info" class="column c13" xt-autoupdate></div>
    </div>
</body>

</html>

<?php

class NewPage extends RaxanWebPage {

    protected $db;
    protected $preserveFormContent = false;

    protected function _load() {

        if ($this->isPostBack) $this->db = $this->data('inventory');
        else {
            $db = Raxan::importCSV('inventory.csv');
            $this->db = $this->data('inventory',$db);
        }

        $this->inventory->find('tbody')->bind($this->db);
    }

    protected function rowClick($e) {
        $id = $e->intval();

        $this['#row'.$id]
            ->css('background','#ffcc00')
            ->siblings()->css('background','#fff');

        $row = $this->db[$id-1]; // get row data
        $img = '<img src="views/images/'.$row['photo'].'" align="left" class="pad" />';
        $details  = $row['details'];
        $this->info->html($img.$details);
    }
    
}
?>