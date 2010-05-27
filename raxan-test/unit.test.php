<?php
/**
 * Unit Test for Raxan PDI
 */

register_shutdown_function('run_unit_test');
function run_unit_test(){
    $cls = get_declared_classes();

    global $htmlOutputBBuffer;
    $htmlOutputBBuffer = array();
    foreach ($cls as $cn) {
        $r = new ReflectionClass($cn);
        $c = $r->getParentClass();
        if ($c && $c->name=='UnitTest') {
            $o = new $cn; // only run classes that extends UnitTest
        }
    }
    echo implode('',$htmlOutputBBuffer);
}

class UnitTest {

    public static $count = 0;

    public $name;
    public $description;

    protected $entry,$entries = array();

    function __construct() {
        $this->_setup();
        $this->_runTests();
        $this->_teardown();

        global $htmlOutputBBuffer;
        $htmlOutputBBuffer[] = $this->_render();
    }

    protected function _runTests() {
        $fn = get_class_methods($this);
        foreach($fn as $m) {
            if (strpos($m,'test')===0) {
                $this->entries[$m] = array('title'=>'','pass'=>0,'fail'=>0,'exception'=>'','results'=>array());
                $this->entry = & $this->entries[$m];
                try { 
                    $this->{$m}(); 
                }
                catch(Exception $e) {
                    $this->entry['exception'] = $e->__toString();
                }
            }
        }
    }

    protected function _setup() {
        // setup
        if (!isset($this->name)) $this->name = get_class($this);
    }
    protected function _teardown() { 
        // teardown
    }
    
    protected function addEntry($msg,$status,$output) {
        if ($status) $this->entry['pass']++; else $this->entry['fail']++;
        $this->entry['results'][] = array(
            'msg'    => $msg, 'status' => $status ? true : false,
            'output' => $output
        );
    }

    // assert ok
    protected function ok($v,$msg = 'Ok assertion'){
        $s = $v ? true : false;
        if ($v==true) $output = 'true';
        elseif ($v==false) $output = 'false';
        else $output = print_r($v,true);
        $this->addEntry($msg, $s, $output);
    }

    // assert ok true
    protected function okTrue($v,$msg = 'True assertion'){
        $this->ok($v===true, $msg);
    }

    // assert ok false
    protected function okFalse($v,$msg = 'False assertion'){
        $this->ok($v===false, $msg);
    }

    // assert compare variables
    protected function compare($a,$b,$msg = 'Compare assertion') {
        $s = ($a===$b) ? true : false;
        $output = print_r($a,true)." <strong>&lt;compared to&gt;</strong> ".print_r($b,true);
        $this->addEntry($msg, $s, $output);
    }

    // assert compare strings
    protected function compareString($a,$b,$msg = 'Compare String assertion') {
        $s = (strtolower($a)===strtolower($b)) ? true : false;
        $output = print_r($a,true)." <strong>&lt;compared to&gt;</strong> ".print_r($b,true);
        $this->addEntry($msg, $s, $output);
    }

    // assert compare DOM nodes
    protected function compareDOMNode($a,$b,$msg = 'Compare DOM Node assertion') {
        if (!($a instanceof DOMNode) || !($b instanceof DOMNode)) $s = false;
        else $s = $a.isSameNode($b) ? true : false;
        $output = print_r($a,true)." <strong>&lt;compared to&gt;</strong> ".print_r($b,true);
        $this->addEntry($msg, $s, $output);
    }

    // current set test title
    protected function title($txt) {
        if ($this->entry) $this->entry['title'] = $txt;
    }

    protected function _render() {
        $cnt = 0;
        $html = array();
        $html[] = '<h1 style="padding:0;margin:0">'.$this->name.'</h1>';
        $html[] = '<p style="padding:0;margin:0 5px 15px 5px">'.($this->description ? $this->description : '&nbsp;') .'</p>';
        $html[] = '<style>.pass,.fail, .warning { background:green;color:white;padding:5px;margin-top:1px;}'.
             '.fail { background:#700000; font-weight:bold }'.
             '.warning { background:#ffcc00; font-weight:bold }'.
             '.pass-result,.fail-result { background:white; color:green;padding:1px 5px; font-weight:normal }'.
             '.fail-result { color:red; }'.
             '.expand { background:white; padding:1px 3px; color:black; float:right; cursor: pointer }'.
             '.show {display:block} .hide {display:none}'.
             '</style>'.
             '<script type="text/javascript">
                function showHide(elm) {
                    var id = elm.getAttribute("rel");
                    var result = document.getElementById(id);
                    if (elm.innerHTML=="+") {
                        elm.innerHTML = "-";
                        result.setAttribute("class","show");
                    }
                    else {
                        elm.innerHTML = "+";
                        result.setAttribute("class","hide");
                    }
                }
             </script>';
        $total = count($this->entries);
        $pass = $fail = $exception = $empty = 0;
        foreach($this->entries as $n => $entry) {
            $cnt++; $results = '';
            $title = $entry['title'] ? ' - '.$entry['title'] : '';
            if ($entry['fail']||$entry['exception']) $class = 'fail';
            else if ($entry['pass']) $class='pass';
            else $class= 'warning';
            $row = $cnt.'/'.$total.' '.$n.$title.' complete. '.
                $entry['pass'].' passes, '.$entry['fail'].' fails '.
                ($entry['exception'] ? ', 1 exception' : '');
            foreach ($entry['results'] as $c => $r) {
                $results.= '<div class="'.($r['status'] ? 'pass-result' : 'fail-result').'"><strong>'.(++$c).'. '.$r['msg'].'</strong> - '.$r['output'].'</div>';
            }
            if ($entry['exception']) $results.= '<div class="fail-result"><strong>Exception:</strong> '.$entry['exception'].'</div>';
            $row = '<div class="'.$class.'"><div style="clear:both"><span class="expand" rel="t'.$cnt.'" onclick="showHide(this)">+</span>'.
                    $row.'</div><div id="t'.$cnt.'" class="hide">'.$results.'</div></div>';
            $pass+= (int)$entry['pass'];
            $fail+= (int)$entry['fail'];
            $exception+= $entry['exception'] ? 1 : 0 ;
            if (!$entry['pass'] && !$entry['fail'] && !$entry['exception']) $empty++;
            $html[] =  $row;

        }

        $total = $pass+$fail+$exception+$empty;
        $percent = !$total ? 0 : ($pass/$total);
        $progress = '<div style="float:right; margin:10px 10px 0 0; text-align:center; font-size:0.9em">'.
        '<div style="width:100px;padding:1px;background#fff;border:1px solid #555">'.
            '<div style="width:100px;background:#C00000"><div style="width:'.floor(100 * $percent).'px;height:10px;background:#00c000"></div></div>'.
        '</div>'.$pass.'/'.$total.'</div>';
        return $progress.implode('',$html).'<hr /><br />&nbsp;';

    }

}

?>
