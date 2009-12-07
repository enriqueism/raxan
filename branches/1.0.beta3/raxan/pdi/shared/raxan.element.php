<?php
/**
 * @package Raxan
 */

/**
 * The Raxan Element Class is used to traverse and manipulate a set of matched DOM elements
 */ 
class RaxanElement extends RaxanBase {

    /**
     * Returns and instance of the CLX for the matched selectors
     * @var RaxanClientExtension $client
     */

    protected static $autoid; // auto id for elements. used by matchSelector
    protected static $callMethods; // stores extended methods

    protected $elms;
    protected $stack; // used to store previous match list

    /**
     * Reference to Raxan Web Page
     * @var RaxanWebPage $page
     */
    protected $page;

    /**
     * @var RaxanDOMDocument $doc
     */
    protected $doc, $rootElm, $context, $selector;
    protected $name, 
              $modified; // true if stack was modified

    /**
     * RaxanElement(css,context)
     * RaxanElement(html,context)
     * @param String|DOMNode|DOMNodeList|RaxanElement|Array $css
     * @param DOMNode $context
     * @return RaxanElement
     */
    function __construct($css,$context = null) {
        parent::__construct();

        $this->elms = array();  // setup elements array

        $c = $context;
        $reservedMethods = array('empty','clone');


        // get document
        if ($c == null) $this->doc = null;
        else if ($c instanceof RaxanDOMDocument) {
            $this->doc = $c; $c = null; // context is document so set it null
        }
        else if ($c instanceof DOMNode && $c->ownerDocument instanceof RaxanDOMDocument)
            $this->doc = $c->ownerDocument;
        else $c = $this->doc = null;
        
        $this->doc = ($this->doc) ? $this->doc : RaxanWebPage::Controller()->document();
        $this->page = $this->doc->page;
        
        $this->rootElm = $this->doc->documentElement;
        $css = $css ?  $css : $this->rootElm;
        $this->context = ($c) ? $c : $this->rootElm;    // assign context element

        if (is_string($css)) {
            $this->selector = $css;
            if (!$this->isHTML($css)) $dl = $this->doc->cssQuery($css,$this->context);
            else {
                // handle html
                $this->modified = true;                
                if (!$this->doc->isInit()) $this->doc->initDOMDocument();
                $n = $this->doc->getElementsByTagName('body');
                if ($n->length) {
                    $n = $n->item(0);
                    $f = $this->createFragment('<div>'.$css.'</div>');
                    if ($f) {
                        $f = $n->appendChild($f); // append html to body tag
                        $dl = array();
                        foreach($f->childNodes as $n1)
                            if ($n1->nodeType==1) $dl[] = $n1->cloneNode(true);
                        $n->removeChild($f); // remove element
                    }
                }
            }
        }
        else if ($css instanceof DOMNode) $this->elms[] = $css;
        else if ($css instanceof DOMNodeList) $dl = $css;
        else if ($css instanceof RaxanElement) $dl = $css->get();
        else if (is_array($css)) $dl = $css;

        if (isset($dl) && $dl ) foreach($dl as $n)
            if ($n->nodeType==1) $this->elms[] = $n;

        return $this;
    }

    // call
    public function __call($name,$args){
        if ($name=='empty') return $this->removeChildren();
        elseif ($name=='clone') return $this->cloneNodes();
        elseif (isset(self::$callMethods[$name])) {
            $fn = self::$callMethods[$name];
            if (is_array($fn)) return $fn[0]->{$fn[1]}($this,$args);
            else return $fn($this,$args);
        }
        else throw new Exception('Undefined Method \''.$name.'\'');
    }

    // getter
    public function __get($var) {
        if ($var=='length') return count($this->elms);
        elseif ($var=='page') return $this->page;
        elseif ($var=='client') {
            $sel = $this->matchSelector(true);
            return $this->page->client($sel);
        }
    }

    /**
     * Adds new elements to the selection based on the specified selector(s)
     * @return RaxanElement
     */
    public function add($selector){
        $dl = '';
        if (is_string($selector)) $dl = $this->doc->cssQuery($selector);
        else if ($selector instanceof DOMNode) $this->elms[] = $selector;
        else if ($selector instanceof DOMNodeList) $dl = $selector;
        else if ($selector instanceof RaxanElement) $dl = $css->get();
        else if (is_array($selector)) $dl = $selector;
        if ($dl) foreach($dl as $n) $this->elms[] = $n;
        $this->modified = true;
        return $this;
    }

    /**
     * Adds a css class name to matched elements
     * @return RaxanElement
     */
    public function addClass($cls){
        return $this->modifyClass($cls, 'add');
    }

    /**
     * Add content after matched elements
     * @return RaxanElement
     */
    public function after($content) {
        return $this->insert($content,'after');
    }

    /**
     * Add the previous matched selection to the current selection
     * @return RaxanElement
     */
    public function andSelf() {
        $c = count($this->stack)-1;
        if ($c>=0) $this->add($this->stack[$c]);
        return $this;
    }

    /**
     * Append content to matched elements
     * @return RaxanElement
     */
    public function append($content) {
        return $this->insert($content,'append');
    }

    /**
     * Append matched elements to selector
     * @return RaxanElement
     */
    public function appendTo($selector) {
        $m = P($selector,$this->doc);
        $elms = $m->insert($this,'append',true);
        return $this->stack($elms);
    }

    /**
     * Appends the html of the matched elements to the selected client element. See <sendToClient>
     * @return RaxanElement
     */
    public function appendToClient($selector) {
        return $this->sendToClient($selector, 'append');
    }

    /**
     * Appends an html view file to the matched elements
     * @return RaxanElement
     */
    public function appendView($view) {
        $view = file_get_contents(Raxan::config('views.path').$view);
        if (!$view) return $this;
        else return $this->insert($view,'append');
        
    }

    /**
     * Returns or set attribute on match elements
     * @return RaxanElement or String
     */
    public function attr($name, $val = null) {
        $e = $this->elms;
        if ($val===null)
            return isset($e[0]) ? $e[0]->getAttribute($name) : '';
        foreach($e as $i=>$n) {
            $n->setAttribute($name,$val.'');
        }
        return $this;
    }

    /**
     * Automatically assign unigue ids to matched elements that are without an id
     * @return RaxanElement
     */
    public function autoId($idPrefix = null) {
        $this->modified = true;
        $this->matchSelector(true,$idPrefix);
        return $this;
    }

    /**
     * Add content before matched elements
     * @return RaxanElement
     */
    public function before($content) {
        return $this->insert($content,'before');
    }
    
    /**
     * Binds matched element events to a callback function
     * Can also be used to bind an array or a PDO result set to the matched elements - See Raxan::bindTemplate()
     * @return RaxanElement
     */
    public function bind($type,$data = null, $fn = null) {        
        $sel = !$this->modified ? $this->selector : null ;
        if (is_string($type)) {
            $this->page->bindElements($this->elms, $type, $data, $fn, $sel);
            return $this;
        }
        else {
            // setup template
            if (!$data) $data = array('tpl'=>$this->html());
            else if (!isset($data['tpl']) && !isset($data[0])) {
                $data['tpl'] = $this->html();
            }
            $rt = Raxan::bindTemplate($type, $data); // pass rows,options to bindTemplate()
            return is_string($rt) ? $this->html($rt) : $rt;
        }               
    }

    /**
     * Selects the immediate children of the matched elements
     * @return RaxanElement
     */
    public function children($selector = null){
        return $this->traverse($selector,'firstChild','nextSibling');
    }

    /**
     * An ajax event helper that's used to binds a function to the click event for the matched selection.
     */
    public function click($fn,$serialize = null){
        return $this->bind('#click',array(
            'callback' =>$fn,
            'serialize'=> $serialize,
            'autoDisable'=> true
        ));
    }

    /**
     * Clone matched elements and return clones (alias clone)
     * @return RaxanElement
     */
    public function cloneNodes($deep = null){
        $a = array();
        foreach($this->elms as $n) $a[] = $n->cloneNode(true);
        $this->elms = $a;
        // todo: clone data and events when $deep is true
        return $this;
    }
    
    /**
     * Returns or sets CSS property values
     * @return RaxanElement or String
     */
    public function css($name,$val = null) {
        $isA = false; $a = array(':',';'); $b = array('=','&');
        $retFirst = $val===null && !($isA = is_array($name));
        foreach($this->elms as $i=>$n) {
            $s = $n->getAttribute('style');
            $s = str_replace($a,$b,$s);
            $c = array(); parse_str($s,$c);
            if ($retFirst) return $c[$name]; // return value for first node
            else {
                if ($isA) $c = array_merge($c, $name);
                else if ($val==='') unset($c[$name]);   // remove css value
                else $c[$name] = $val;
                $c = str_replace($b,$a,urldecode(http_build_query($c)));
                if ($c=='') $n->removeAttribute('style');
                else $n->setAttribute('style',$c);
            }            
        }
        return $retFirst ? '' :$this;
    }

    /**
     * Returns or sets news data value for the macted elements
     * @return Mixed
     */
    public function &data($name, $value = null){
        $name = $this->storeName().$name;
        return $this->page->data($name, $value);

    }
    
    /**
     * Binds matched element events to a callback function via event delegation
     * @return RaxanElement
     */
    public function delegate($type,$data = null, $fn = null) {
        $t = trim($type); $elms = $this->elms;
        $sel = $this->matchSelector(true);
        if (($p = strrpos($t,' '))===false) $de = true;
        else {
            $type = substr($t,$p+1-strlen($t)); $de = trim(substr($t,0,$p));
        }
        $this->page->bindElements($elms, $type, $data, $fn, $sel,$de);
        return $this;
    }

    /**
     * Disbable matched elements
     * @return RaxanElement
     */
    public function disable(){ $this->attr('disabled','disabled'); }

    /**
     * Enable matched elements
     * @return RaxanElement
     */
    public function enable(){ $this->attr('disabled',''); }

    /**
     * Revert the currently modified selection to the previously matched selection
     * this works if the selection was modified using filter(), find(), eq(), etc
     * @return RaxanElement
     */
    public function end($all = false) {
        return $this->unstack($all);
        
    }

    /**
     * Reduces the set of matched elements to a single element
     * @return RaxanElement
     */
    public function eq($index) {
        return $this->stack(
            (isset($this->elms[$index])) ?
            array($this->elms[$index]) :
            array()
        );
    }

    /**
     * Remove all elements that does not match the specified selector(s)
     * @return RaxanElement
     */
    public function filter($selector, $_invert = false) {
        $stack = array();
        foreach($this->elms as $i=>$n) {
            $dl = $this->doc->cssQuery($selector, $n, true);
            if (!$dl) continue;
            if ($_invert && !$dl->length) $stack[] = $n;  // filter invert (not)
            else if (!$_invert && $dl->length) $stack[] = $n;
        }
        return $this->stack($stack);
    }

    /**
     * Search matched elements for the specified selector(s)
     * @return RaxanElement
     */
    public function find($selector, $_returnStack = null) {
        $stack = array();
        foreach($this->elms as $i=>$n) {
            $dl = $this->doc->cssQuery($selector, $n);
            foreach($dl as $e) $stack[] = $e;
        }
        return $_returnStack ? $stack : $this->stack($stack);
    }

    /**
     * Returns or sets the float value for a form element
     * @return RaxanElement or float
     */
    public function floatval($v = null) {
        if ($v===null) return floatval($this->val());
        return $this->val(floatval($v));
    }

    /**
     * Returns a single element or an array of element
     * @return DOMElement or Array
     */
    public function get($index = null) { return $this->node($index); }
    public function node($index = null) {
        if ($index===null) return $this->elms;
        else return isset($this->elms[$index])? $this->elms[$index] : null;
    }

    /**
     * Save the state of the matched elements
     * @return RaxanElement
     */
    public function handleSaveState() {
        $pg  = & $this->page; $data = array();
        foreach($this->elms as $n) {
            $id = $n->getAttribute('id');
            $mode = $n->getAttribute('xt-preservestate');
            $n->removeAttribute('xt-preservestate');
            if ($mode=='reset') continue; // don't save reset states
            else $mode = ($mode!='global') ? 'local' : 'global';
            if (!isset($data[$mode])) $data[$mode] = & $this->getStateData($mode);
            if (!isset($data[$mode][$id])) $data[$mode][$id]  = array();
            $this->_saveElmState($n,$data[$mode][$id]);
        }
        return $this;
    }
    
    /**
     * Returns true if one element in the matched selection contains the specified class
     * @return Boolean
     */
    public function hasClass($cls) {
        $cls = trim($cls);
        foreach($this->elms as $i=>$n) {
            $c = $n->getAttribute('class');
            $found = (stripos(" $c ", " $cls ")!==false);
            if ($found) return true;
        }
        return $this;
    }

    /**
     * Hide matched elements (display:none)
     * @return RaxanElement
     */
    public function hide(){ return $this->css('display','none'); }

    /**
     * Sets the inner html content of matach elements. Returns only the inner html of the first matched element.
     * @return RaxanElement or String
     */
    public function html($html=null) {
        foreach($this->elms as $i=>$n) {
            if ($html===null) {
                // return html from first node
                return $this->nodeContent($n);
            }
            else {
                $n = $this->elms[$i] = $this->clearNode($n); // clear node
                $f = $this->createFragment($html);
                if ($f) $n->appendChild($f); // insert html
            }
        }
        return $html===null ? '' : $this;
    }

    /**
     * Returns the html (inner & outter) markup for the first match element
     * @return String
     */
    public function htmlMarkup() {
        return isset($this->elms[0]) ?
            $this->nodeContent($this->elms[0], true) : '';
    }

    /**
     * Add matched elements after all selected elements
     * @return RaxanElement
     */
    public function insertAfter($selector) {
        $elms = P($selector,$this->doc)->insert($this,'after',true);
        return $this->stack($elms);
    }

    /**
     * Add matched elements before all selected elements. 
     * @return RaxanElement */
    public function insertBefore($selector) {
        $elms = P($selector,$this->doc)->insert($this,'before',true);
        return $this->stack($elms);        
    }

    /**
     * Returns or sets the integer value for a form element
     * @return RaxanElement or int
     */
    public function intval($v = null) {
        if ($v===null) return intval($this->val());
        return $this->val(intval($v));
    }

    /**
     * Returns true is if at least one element matches the selector
     * @return Boolean
     */
    public function is($selector) {
        foreach($this->elms as $i=>$n) {
            $dl = $this->doc->cssQuery($selector, $n, true);
            if ($dl->length) return true;
        }
        return false;
    }

    /**
     * Localize matched elements that have a valid locale key/value pair assigned to the langid attribute
     * @return RaxanElement
     */
    public function localize(){
        foreach($this->elms as $n) {
            $nl = $this->doc->xQuery('descendant-or-self::*[@langid]',$n);
            RaxanWebPage::NodeL10n($nl);
        }
    }

    /**
     * Applies a callback to matched elements and returns a new set of elements
     * Can also be used to filter or replace the matched elements
     * @return RaxanElement
     */
    public function map($fn) {
        $stack = array();
        $inx = array_keys($this->elms); $elms = array_values($this->elms);
        $rt = array_map($fn,$inx,$elms); //callback params: $index, $element;
        foreach($rt as $n) 
            if (is_array($n)) $stack = array_merge($stack,$n);
            else if ($n!==null) $stack[] = $n;
        return $this->stack($stack);
    }

    /**
     * Returns the selctor for the match elements
     * @return String
     */
    public function matchSelector($autoId = false, $idPrefix = null){
        if ($this->selector && !$this->modified) $sel = $this->selector;
        else {
            $ids = array();
            if (!$idPrefix) $idPrefix = 'e0x';
            foreach($this->elms as $n) {
                $id = $n->getAttribute('id');
                if ($autoId && !$id) $n->setAttribute('id', $id = self::uniqueId($idPrefix)); // auto assign id
                if ($id) $ids[]='#'.$id;
            }
            $sel = implode(',',$ids);
        }
        return $sel;
    }

    /**
     * Selects the next sibling of the matched elements
     * @return RaxanElement
     */
    public function next($selector = null){
        return $this->traverse($selector,'nextSibling','nextSibling',true);
    }

    /**
     * Selects the next siblings of the matched elements
     * @return RaxanElement
     */
    public function nextAll($selector = null){
        return $this->traverse($selector,'nextSibling','nextSibling'); // select all
    }

    /**
     * Remove element matching the specified selector from the set of match elements
     * @return RaxanElement
     */
    public function not($selector) {
        return $this->filter($selector, true); // return inverted filter set
    }

    /**
     * Selects the parent element of the matched elements
     * @return RaxanElement
     */
    public function parent($selector = null){
        return $this->traverse($selector,'parentNode','parentNode',true);
    }
    
    /**
     * Selects the ancestors of the matched elements
     * @return RaxanElement
     */
    public function parents($selector = null){
        return $this->traverse($selector,'parentNode','parentNode'); // select all
    }

    /**
     * Selects the previous sibling of the matched elements
     * @return RaxanElement
     */
    public function prev($selector = null){
        return $this->traverse($selector,'previousSibling','previousSibling',true);
    }

    /**
     * Selects the previous siblings of the matched elements
     * @return RaxanElement
     */
    public function prevAll($selector = null){
        return $this->traverse($selector,'previousSibling','previousSibling'); // select all
    }

    /**
     * Prepend content to elements
     * @return RaxanElement
     */
    public function prepend($content) {
        return $this->insert($content,'prepend');
    }

    /**
     * Prepend matched elements to selector
     * @return RaxanElement
     */
    public function prependTo($selector) {
        $m = P($selector,$this->doc);
        return $this->stack($m->insert($this,'prepend',true));
    }

    /**
     * Prepends the html of the matched elements to the selected client element. See <sendToClient>
     * @return RaxanElement
     */
    public function prependToClient($selector) {
        return $this->sendToClient($selector, 'append');
    }

    /**
     * Preserves the state of the matched elements
     * @param $mode String - local or global. Local states are preserved during postback. Global states preserved until the session ends.
     * @see handleSaveState
     * @return RaxanElement
     */
    public function preserveState($mode = null) {
        if ($this->length==0) return $this;
        $data = array();
        $fixMode = ($mode!==null) ? true: false;
        $this->page->preserveElementState($this);
        foreach($this->elms as $n) {            
            $imode = $mode;
            if (!$n->hasAttribute('id')) $n->setAttribute('id',$id = self::uniqueId());
            else $id = $n->getAttribute('id');
            if ($fixMode) $n->setAttribute('xt-preservestate',$mode);
            else $imode = trim($n->getAttribute('xt-preservestate'));
            if (!isset($data[$imode])) $data[$imode] = $this->getStateData($imode);
            if (isset($data[$imode][$id])) $this->_loadElmState($n,$data[$imode][$id]); // restore state
        }
        return $this;
    }

    /**
     * Removes the state of the matched form elements
     * @see handleSaveState
     * @return RaxanElement
     */
    public function removeState() {
        $data = array();
        foreach($this->elms as $n) {
            if (!$n->hasAttribute('id')) $n->setAttribute('id',$id = self::uniqueId());
            else $id = $n->getAttribute('id');
            $imode = trim($n->getAttribute('xt-preservestate'));
            $n->setAttribute('xt-preservestate','reset');
            if (!isset($data[$imode])) $data[$imode] = & $this->getStateData($imode);
            if (isset($data[$imode][$id])) unset($data[$imode][$id]);
        }
        return $this;
    }

    /**
     * Remove matched elements from document
     * @return RaxanElement
     */
    public function remove() {
        foreach($this->elms as $i=>$n) {
            $p = $n->parentNode; if($p) $p->removeChild($n);
        }
        $this->elms = array();
        $this->modified = true;
        return $this;
    }

    /**
     * Remove attribute from elements
     * @return RaxanElement
     */
    public function removeAttr($cls) {
        foreach($this->elms as $i=>$n) {
            $n->removeAttribute($cls);
        }
        return $this;
    }

    /**
     * @alias empty() - Remove child nodes from match elements
     * @return RaxanElement
     */
    public function removeChildren() {
        foreach($this->elms as $n) $n->nodeValue ='';
        return this;
    }

    /**
     * Removes css class name from elements
     * @return RaxanElement
     */
    public function removeClass($cls) {
        return $this->modifyClass($cls,'remove');
    }

    /**
     * Remove data from matched elements
     * @return RaxanElement
     */
    public function removeData($name){
        $this->page->removeData($this->storeName().$name);
        return $this;
    }

    /**
     * Replace all selected with matched elements
     * @return RaxanElement
     */
    public function replaceAll($selector){
        $m = P($selector,$this->doc);
        $elms = $m->insert($this,'after',true);
        $m->remove();
        return $this->stack($elms);
    }

    /**
     * Replaces the selected client-side element with the html of the matched elements. See <sendToClient>
     * @return RaxanElement
     */
    public function replaceClient($selector) {
        return $this->sendToClient($selector, 'replace');
    }

    /**
     * Replace matched elements with content
     * @return RaxanElement
     */
    public function replaceWith($content){
        return $this->after($content)->remove();
    }
    
    /**
     * Selects inner child of match elements - used by wrap functions
     * @return RaxanElement
     */
    public function selectInnerChild() {
        foreach($this->elms as $i=>$n) {
            while($n->firstChild && $n->firstChild->nodeType == XML_ELEMENT_NODE)
                $n = $n->firstChild;
            $this->elms[$i] = $n;
        }
        return $this;
    }

    /**
     * Sends the html of the matched elements to the selected client element
     * @param $selector String client-side css selector
     * @param $mode String Used internally. possible values 'insert (default), replace, append, prepend, before, after'
     * @return RaxanElement
     */
    public function sendToClient($selector,$mode=null){
        $h = ''; $delim = ($mode=='update') ? '<<'.time().'>>': '';
        foreach($this->elms as $elm) {
            if ($mode=='update' && $elm->hasAttribute('autoupdate'))
                $elm->removeAttribute('autoupdate');
            $h.= trim($this->nodeContent($elm, true)).$delim;
        }

        if ($mode=='append') C($selector)->append($h);
        else if ($mode=='prepend') C($selector)->prepend($h);
        else if ($mode=='before') C($selector)->before($h);
        else if ($mode=='after') C($selector)->after($h);
        else if ($mode=='replace') C($selector)->replaceWith($h);
        else if ($mode=='update') { // setup custom action
            $a = 'Raxan.iUpdateClient('._var($selector).','._var($h).',"'.$delim.'");';
            array_unshift(RaxanWebPage::$actions,$a);
        }
        else C($selector)->html($h);
        return $this;
    }
   
    /**
     * Show matched elements (display:block)
     * @return RaxanElement
     */
    public function show(){ return $this->css('display','block'); }

    /**
     * Selects the siblings of the matched elements
     * @return RaxanElement
     */
    public function siblings($selector = null){
        return $this->traverse($selector,'siblings','nextSibling'); // select all
    }

    /**
     * Selects a subset of the match elements
     * @return RaxanElement
     */
    public function slice($start, $length = null) {
        return $this->stack(
            $length===null ? array_slice($this->elms, $start) :
            array_slice($this->elms, $start, $length)
        );
    }

    /**
     * Sets or returns a date store name for the matched selection
     * @return RaxanElement or String
     */
    public function storeName($n = null) {
        if ($n!==null) $this->name = $n;
        else {
            if (!$this->name){ // auto-setup collection name
                $id = ($this->elms) ? $this->elms[0]->getAttribute('id') : '';
                $this->name = $id ? 'elm-'.$id : 'elm-'.$this->objId;
            }
            return $this->name;
        }
        return $this;
    }

    /**
     * An ajax event helper that's used to binds a function to the submit event for the matched selection.
     */
    public function submit($fn,$serialize = null){
        return $this->bind('#submit',array(
            'callback' =>$fn,
            'serialize'=> $serialize,
            'autoDisable'=> true
        ));
    }

    /**
     * Returns or set text on match elements
     * @return RaxanElement or String
     */
    public function text($txt=null) {
        $txt = $txt ? htmlspecialchars($txt): $txt;
        foreach($this->elms as $i=>$n) {
            if ($txt===null) return $n->textContent; // read text
            else {
                // insert text
                $n = $this->elms[$i] = $this->clearNode($n);
                $n->nodeValue = $txt;
            }
        }
        return $txt===null ? '' : $this;
    }

    /**
     * Returns or sets the non-html text value for a form element
     * @return RaxanElement or string
     */
    public function textval($txt = null) {
        if ($txt===null) return strip_tags($this->val());
        return $this->val(strip_tags($txt));
    }

    /**
     * Binds a callback function to a timeout event. $msTime  - milliseconds
     * @return RaxanElement
     */
    public function timeout($msTime,$data,$fn = null) {
        $sel = !$this->modified ? $this->selector : null;
        $ajax = substr($msTime,0,1)=='#' ? true :false;
        $ms = intval($ajax ? substr($msTime,1) : $msTime);
        if ($ms<1000) $ms = 1000;
        $type = ($ajax ? '#':'').$ms;
        $this->page->bindElements($this->elms, $type, $data, $fn, $sel);
        return $this;
    }

    /**
     * Toggle css class name
     * @return RaxanElement
     */
    public function toggleClass($cls) {
        return $this->modifyClass($cls,'toggle');
    }

    /**
     * Trigger events on the match elements
     * @return RaxanElement
     */
    public function trigger($type,$args = null) {
        $this->page->triggerEvent($this->elms,$type,$args);
        return $this;
    }

    /**
     * Removes all event handlers for the specified event type
     * @return RaxanElement
     */
    public function unbind($type) {
        $sel = !$this->modified ? $this->selector : null ;
        $this->page->unbindElements($this->elms,$type,$sel);
        return $this;
    }

    /**
     * Removes all duplicate elements from the matched set
     * @return RaxanElement
     */
    public function unique() {
        $uid = time();
        $stack = array();
        foreach($this->elms as $n) {
            // make array unique
            if (isset($n->_unique) && ($n->_unique==$uid)) continue;
            else $n->_unique = $uid;
            $stack[] = $n;
        }
        $this->elms = $stack;
        return $this;
    }

    /**
     * Update client-side elements with the html content of the matched elements. See <sendToClient>
     * @return RaxanElement
     */
    public function updateClient() {
        if ($this->page->isCallback) { // only update if in callback mode
            $selector = $this->matchSelector();
            $this->sendToClient($selector, 'update');
        }
        return $this;
    }

    /**
     * Returns or sets the value for a form element
     * @return RaxanElement or String
     */
    public function val($v = null){ return $this->value($v); } // alias to value();
    public function value($v = null){
        if ($v===null) {    //get
            if (!isset($this->elms[0])) return null;
            else {
                $elm = $this->elms[0];
                $nn = $elm->nodeName;
                // handle select tags
                if ($nn=='textarea') return $this->nodeContent($elm);    // texbareas
                elseif ($nn!='select') return $elm->getAttribute('value');   // inputs and buttons
                else {
                    $multi = $elm->getAttribute('multiple') ? true : false;
                    $values = ($multi) ? array() : '';
                    foreach ($elm->childNodes as $n) {
                        if ($n->nodeType!=1) continue;
                        $sel = $n->getAttribute('selected');
                        if ($sel) {
                            $v = ($v = $n->getAttribute('value')) ? $v : $n->nodeValue;
                            if (!$multi) return $v;
                            else $values[] = $v;
                        }
                    }
                    return $values ? $values : null;
                }
            }
        }
        else {  // set
            $isa = is_array($v);
            foreach($this->elms as $n) {
                $nn = $n->nodeName;
                $fldName = $n->getAttribute('name'); // attribute name
                if (($st = strpos($fldName,'['))!==false) $fldName = substr($fldName,0,$st); // remove [] from name
                $value = ($isa && isset($v[$fldName])) ? $v[$fldName] : $v;
                if ($nn=='textarea') {     // textareas
                    $n->nodeValue='';
                    if ($value && !is_array($value)) {
                        $f = $this->createFragment(htmlspecialchars($value.'',null,$this->doc->charset));
                        if ($f) $n->appendChild($f);
                    }
                }
                elseif ($nn!='select') {        // inputs
                    $at = $n->getAttribute('type');
                    $av = $n->getAttribute('value');
                    if (($at=='radio'||$at=='checkbox') && is_array($value)) {  // index arrays
                        if (in_array($av,$value) || (in_array($fldName,$value))) $n->setAttribute('checked','checked');
                        else $n->removeAttribute('checked');
                    }
                    elseif ($isa && ($at=='radio'||$at=='checkbox')) {          // hash array (name = value)
                        if ($av==$value) $n->setAttribute('checked','checked');
                        else $n->removeAttribute('checked');
                    }
                    elseif (!is_array($value)){ // button, textbox, etc
                        $n->setAttribute('value',$value.'');
                    }
                }
                else {                      // selects
                    $value = is_array($value) ? $value : array($value);
                    foreach ($n->childNodes as $o) {
                        if ($o->nodeType!=1) continue;
                        $ov = $o->getAttribute('value');
                        if (!$ov) $ov = $o->nodeValue;
                        if (in_array($ov,$value)) $o->setAttribute('selected','selected');
                        else $o->removeAttribute('selected');
                    }
                }
                
            }
            return $this;
        }
    }

    /**
     * Wrap matched elements inside the specified HTML content or element.
     * @return RaxanElement
     */
    public function wrap($content) {
        foreach($this->elms as $n) {
            P($n,$this->doc)->wrapAll($content);
        }
        return $this;
    }

    /**
     * Wrap all matched elements inside the specified HTML content or element
     * @return RaxanElement
     */
    public function wrapAll($content) {
        P($content,$this->doc)
            ->cloneNodes()
            ->insertAfter($this->get(0))
            ->selectInnerChild()
            ->append($this);
        return $this;
    }

    /**
     *  Private/protected Function -------------------------------
     *  ------------------------------------------------
     */

    // load state info
    protected function _loadElmState($elm,$data) {
        $htm =  isset($data['_html']) ? $data['_html'] : '';
        $htm =  $data['_html'];
        unset($data['_html']);
        foreach($data as $n=>$v) $elm->setAttribute($n,$v);
        $elm = $this->clearNode($elm); // clear node
        $f = $this->createFragment($htm);
        if ($f) $elm->appendChild($f); // insert html
    }

    // save state info
    protected function _saveElmState($elm,&$data) {
        foreach($elm->attributes as $a) $data[$a->name] = $a->value ;
        $data['_html'] = $this->nodeContent($elm);
    }

    // returns state data
    protected function & getStateData($type = 'local') {
        $dtKey = 'RaxanGlobal_State'; $dtName  = '__state__';
        if ($type == 'global') { // global state
            return Raxan::data($dtKey,$dtName,array(),true);
        }
        else { // local state
           $pg  = & $this->page;
           return $pg->data($dtName,array(),true);
        }
    }

    // returns the html content of an elment
    protected function nodeContent($n, $outer=false) {
        $d = $this->page->flyDOM(); // DOM with xhtml doctype
        //$n = $d->importNode($n->cloneNode(true),true);
        $n = $d->importNode($n,true);   // @todo: test to see if clone is faster 
        $h = str_replace('&#13;','',$d->saveXML($n)); // save and cleanup xhtml code
        $n = $this->doc->importNode($n,true);
        // remove outer tags
        if (!$outer) $h = substr($h,strpos($h,'>')+1,-(strlen($n->nodeName)+3));
        return $h;
    }

    // Traverse siblings, children and parent nodes of matched elements
    protected function traverse($selector,$prop1,$prop2,$first = false){
        $stack = array();
        $siblings = $prop1=='siblings';
        foreach($this->elms as $n) {
            $fc = ($siblings) ? $n->parentNode->firstChild : $n->{$prop1};
            while ($fc) {
                $notSame = (!$siblings || ($siblings && !$n->isSameNode($fc)));
                if ($fc->nodeType == XML_ELEMENT_NODE && $notSame) {
                    $found = null;
                    if ($selector===null) $found = $stack[] = $fc;
                    else {
                        $rt = $this->doc->cssQuery($selector, $fc, true);
                        if ($rt && $rt->length) $found = $stack[] = $fc;
                    }
                    if ($first && $found) break;
                }
                $fc = $fc->{$prop2};
            }
        }
        return $this->stack($stack);
    }

    // Create and return a DOM fragment node
    protected function createFragment($html) {
        if (trim($html)==='') return false;
        $frag = $this->doc->createDocumentFragment();
        $ok = @$frag->appendXML($html); // I really wish there was an appendXML() method
        if(!$ok) {
            // if unknown entities or xhtml errors? decode entities and try again
            $charset = $this->doc->charset;
            $ok = @$frag->appendXML(html_entity_decode($html.'',null,$charset));
            if(!$ok) {  // final attempt - the long and hard way :(
                $dom = new DOMDocument();
                @$dom->loadHTML('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'.
                               '<html><head><meta http-equiv="Content-Type" content="text/html; charset='.$charset.'"/></head>'.
                               '<body>'.$html.'</body></html>');
                $n = $dom->getElementsByTagName('body'); $n = $n->item(0);
                $n = $frag->ownerDocument->importNode($n,true);
                $l = $n->childNodes->length;
                for ($i=0; $i<$l; $i++)  {
                    $c = $n->childNodes->item(0);
                    $frag->appendChild($c);
                }
            }
        }
        return $frag;
    }

    // Clear node
    protected function clearNode($n) {
        $n->nodeValue = '';
        return $n;
    }

    // Insert content into DOM
    protected function insert($content,$pos,$retNodes = false){
        if ($content && ($isFragment = is_string($content)))
            $content = array($this->createFragment($content));
        elseif ($content instanceof DOMNode ) $content =  array($content);
        elseif ($content instanceof DOMNodeList ) ;//$content =  $content;
        elseif ($content instanceof RaxanElement ) $content =  $content->get();
        else return $this;
        if ($retNodes) $newNodes = array();
        foreach($this->elms as $i=>$n){
            foreach($content as $c => $node) {
                $same = $n->ownerDocument->isSameNode($node->ownerDocument);
                if (!$same) $node = $n->ownerDocument->importNode($node,true);
                else if ($i > 0 || $isFragment) $node = $node->cloneNode(true); // clone objects
                switch ($pos) {
                    case 'after':
                        $p = $n->parentNode;
                        if ($p) $node = $p->insertBefore($node,$n->nextSibling);
                        break;
                    case 'append':
                        $node = $n->appendChild($node);
                        break;
                    case 'before':
                        $p = $n->parentNode;
                        if ($p) $node = $p->insertBefore($node,$n);
                        break;
                    case 'prepend':
                        $node = $n->insertBefore($node,$n->firstChild);
                        break;
                }
                if ($retNodes) $newNodes[] = $node;
            }
        }
        return  $retNodes ? $newNodes : $this;
    }
    
    protected function isHTML($str){
        return substr(trim($str),0,1)=='<';
    }
    
    // Modify class attribute 
    protected function modifyClass($classes,$mode){
        if (!$classes) return $this;
        $classes = explode(' ',$classes);
        foreach($this->elms as $i=>$n) {
            $c = $n->getAttribute('class');
            foreach($classes as $cls) if ($cls) {
                $found = (stripos(" $c ", " $cls ")!==false);
                if ($mode=='toggle' && !$found) $c.=' '.$cls;
                else if ($mode=='toggle') $mode = 'remove';
                if ($mode=='add' && !$found) $c.=' '.$cls;
                else if ($mode=='remove' && $found) $c = str_replace(" $cls ", ' '," $c ");
            }
            $c = trim($c);
            if($c=='') $n->removeAttribute('class');
            else $n->setAttribute('class',$c);
        }
        return $this;
    }

    // Replaces the current matched elements with new array or elements
    protected function stack($elms) {
        if (is_array($elms)) {
            if (!isset($this->stack)) $this->stack = array();
            $this->stack[] = $this->elms; // save previous list
            $this->elms = $elms;
            $this->modified = true;
        }
        return $this;
    }

    // Restore previously matched elements 
    protected function unstack($all = false) {
        $hasStack = isset($this->stack) && $this->stack;
        if ($hasStack) {
            if (!$all) $elms = isset($this->stack) ?  array_pop($this->stack) : $this->elms;
            else {
                $elms = $this->stack[0];
                unset($this->stack);
            }
            if ($elms) $this->elms = $elms;
        }
        return $this;
    }

    // static Methods
    // -------------------------------------------

    /**
     * Adds a custom method to the RaxanElement Class. Use addMethod($object) to add multiple methods from an object
     */
    public static function addMethod($name,$callback = null) {
        if(!self::$callMethods) self::$callMethods = array();
        if ($callback===null && is_object($name)) { // add methods from an object
            $obj = $name; $names = get_class_methods($obj);
            foreach($names as $name)
                if($name[0]!='_') self::$callMethods[$name] = array($obj,$name); // don't add names that begins with '_'
        }
        else {
            if (!is_callable($callback)) Raxan::throwCallbackException($callback);
            self::$callMethods[$name] = $callback;
        }
    }

    /**
     * Returns a unique id
     * @return String
     */
    protected static function uniqueId($prefix = 'e0x') {
        return $prefix.(++self::$autoid);
    }

}

/**
 *  Raxan User Interface Element
 */
abstract class RaxanUIElement extends RaxanElement {

    protected $implementsLoad = false;
    protected $implementsRender = false;

    protected $_isPrerender = false;

    public function __construct($css,$context = null) {
        parent::__construct($css,$context);
        if ($this->implementsLoad||$this->implementsRender) {
            $this->page->registerUIElement($this);
        }
    }

    // Event Handlers
    protected function _load() { }       // invoked when page is loaded
    protected function _prerender() { }  // invoked when page is been rendered

    /**
     * Triggers the _load event handler to load addition data
     * @return RaxanUIElement
     */
    public function loadUI() {
        if($this->implementsLoad) $this->_load();
        return $this;
    }

    /**
     * Triggers the _prerender event handler to render the UI element.
     * @return RaxanUIElement
     */
    public function renderUI() {
        if($this->implementsRender && !$this->_isPrerender)
            $this->_prerender();
        return $this;
    }

}

?>