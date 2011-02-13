<?php

require_once '../raxan/pdi/autostart.php';

class NewPage extends RaxanWebPage {

    protected $db;
    protected $tplOptions;  // template option

    protected function _config() {
        $this->preserveFormContent = true;
        $this->tplOptions = array(
            'format' => array('qty' => 'number')
        );
    }
    
    protected function _load() {
        if ($this->isPostBack) $this->db = $this->data('inventory');
        if (!$this->db){
            $db = $this->Raxan->importCSV('data/phones.csv');
            $this->db = $this->data('inventory',$db);
        }
    }

    protected function _prerender() {
        $this->inventory->find('tbody')->bind($this->db,$this->tplOptions);
    }

    // -- Event handlers

    protected function selectRowItem($e) {
        $this->tplOptions['key'] = 'id';
        $this->tplOptions['selected'] = 4;
        $this->tplOptions['selectClass'] = 'rax-selected-pal rax-metalic';
    }

    protected function cssRowClass($e) {
        $this->tplOptions['itemClass'] = 'white';
        $this->tplOptions['altClass'] = 'lightgray';              // css class names
        $this->tplOptions['firstClass'] = 'softyellow';
        $this->tplOptions['lastClass'] = 'softgreen';
    }

    protected function callbackRows($e) {
        $this->tplOptions['callback'] = array($this,'renderRow'); // callback
    }

    // render row with styles
    public function renderRow(&$row,$i,&$t,$type,&$fmt,&$cssClass) {
        if ($row['sku']=='IPH01') return false; //skip this row
        else if ($row['sku']=='HTC01') {
            // return custom row text
            return '<tr><td colspan="3" style="color:#fff;background-color:#2b2;"><h3 class="bmm">'.
                '<img src="views/images/'.htmlspecialchars($row['photo']).'" width="48" width="91" class="left rtm" />'.
                htmlspecialchars($row['desc']).'</h3>'.
                htmlspecialchars($row['details']).'</td></tr>'; // custom render
        }
        // change row class when sku==pre
        if ($row['sku']=='RIMB') $cssClass = 'rax-info-pal';
        // format special fields
        $fmt['desc color'] = ($i%2) ?  'blue' : 'green';    // change text color
        if ($row['sku']=='RIMB' || $row['sku']=='IPH01') $fmt['sku bold'] = true; // bold text
        if ($row['qty'] < 200) $fmt['qty style'] = 'background-color:#ffccaa; color:#480000'; // change style
    }

    
}
?>

<!DOCTYPE html>

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
        <title>Template Binder</title>
        <link href="../raxan/ui/css/master.css" type="text/css" rel="stylesheet" />
        <!--[if lt IE 8]><link rel="stylesheet" href="../raxan/ui/css/master.ie.css" type="text/css"><![endif]-->
        <link href="../raxan/ui/css/default/theme.css" type="text/css" rel="stylesheet" />
    </head>

    <body>

        <div class="c30 container prepend-top">
            <form name="form1" action="" method="post" class="bmm">
                <input type="submit" name="btnSelect" id="btnSelect" value="Select DRO1 Item" xt-bind="click, selectRowItem" class="button" />&nbsp;
                <input type="submit" name="btnAltClass" id="btnAltClass" value="Apply CSS Classes" xt-bind="click, cssRowClass" class="button" />&nbsp;
                <input type="submit" name="btnCustom" id="btnCustom" value="Apply callback function" xt-bind="click, callbackRows" class="button" />
            </form>
            <table class="rax-table e100 rax-box-shadow" id="inventory">
                <thead>
                    <tr class="tbl-header">
                        <th>SKU</th><th>Description</th><th>Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <tr id="rowid-{id}" class="{ROWCLASS}">
                        <td>{sku}</td><td>{desc}</td><td>{qty}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </body>

</html>