<?php
/**
 * Table Plugin Demo
 *
 * Usage:
 * ------------------
 *  $t = new Table(array(
 *       'name' =>'Name',
 *       'address' => 'Address'
 *  ),$data);
 *  $t->itemStyle = 'background:beige';
 *  $t->altItemStyle = 'background:#eee';
 *  $t->appendTo('body');
 */

class Table extends RichPlugin {

    public $itemStyle;
    public $altItemStyle = 'background:#eee';

    protected $source;
    protected $cols = array();
    
    protected $implementPrerender = true;

    function __construct($columns = null,$data = null){
        parent::__construct('<table />');
        $this->attr('id', $this->getUniqueId('wtable'));
        if ($columns) $this->addColumn($columns);
        $this->source = $data;
    }

    function addColumn($fieldName,$title = '') {
        $c = & $this->cols;
        if  (is_string($fieldName)) $c[$fieldName] = $title ? $title : $fieldName;
        else if (is_array($fieldName)) {
            foreach($fieldName as $n=>$v) {
                if (is_numeric($n)) $n = $v;
                $c[$n] = $v ? $v : $n;
            }
        }
        return $this;
    }

    public function dataSource($data) {
        $this->source = $data;
        return $this;
    }

    public function render() {
        $b = $h = '';
        foreach($this->cols as $fld=>$title) {
            $h.= '<th>'.$title.'</th>';
            $b.= '<td>{'.$fld.'}</td>';
        }
        $itemStyle = $this->itemStyle ? ' style="'.$this->itemStyle.'" ' : '';
        $altItemStyle = $this->altItemStyle ? ' style="'.$this->altItemStyle.'" ' : '';
        $tpl = '<tr'.$itemStyle.'>'.$b.'</tr>';
        $tplAlt = '<tr'.$altItemStyle.'>'.$b.'</tr>';
        $this->html('<thead><tr>'.$h.'</tr></thead><tbody></tbody>');
        if ($this->source) {
            $h = RichAPI::bindTemplate($this->source,array('tpl'=>$tpl,'tplAlt'=>$tplAlt));
            $this->find('tbody')->html($h)->end();
        }
        return $this;
    }
}

?>