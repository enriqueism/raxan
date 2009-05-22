<?php

/**
 * The Rich Element Class is used to traverse and manipulate a set of matched DOM elements
 * @package Raxan
 */ 
class RichElement extends RichAPIBase {

    /**
     * Returns and instance of the CLX for the matched selectors
     * @var RichClientExtension $client
     */

    protected static $autoid; // auto id for elements. used by matchSelector

    protected $elms;
    protected $stack; // used to store previous match list

    /**
     * Reference to Rich Web Document
     * @var RichWebPage $page
     */
    protected $page;

    /**
     * @var RichDOMDocument $doc 
     */
    protected $doc, $rootElm, $context, $selector;
    protected $name, 
              $modified; // true if stack was modified

    /**
     * RichElement(css,context)
     * RichElement(html,context)
     * @param String|DOMNode|DOMNodeList|RichElement|Array $css
     * @param DOMNode $context
     * @return RichElement
     */
    function __construct($css,$context = null) {
        parent::__construct();

        $this->elms = array();  // setup elements array

        $c = $context;
        $reservedMethods = array('empty','clone');


        // get document
        if ($c == null) $this->doc = null;
        else if ($c instanceof RichDOMDocument) {
            $this->doc = $c; $c = null; // context is document so set it null
        }
        else if ($c instanceof DOMNode && $c->ownerDocument instanceof RichDOMDocument)
            $this->doc = $c->ownerDocument;
        else $c = $this->doc = null;
        
        $this->doc = ($this->doc) ? $this->doc : RichWebPage::Controller()->document();
        $this->page = $this->doc->page;
        
        $this->rootElm = $this->doc->documentElement;
        $css = $css ?  $css : $this->rootElm;
        $this->context = ($c) ? $c : $this->rootElm;    // assign context element

        if (is_string($css)) {
            $this->selector = $css;
            if (!$this->isHTML($css)) $dl = $this->doc->cssQuery($css,$this->context);
            else {
                 // append html to body tag
                 $n = $this->doc->getElementsByTagName('body');
                 if ($n->length) {
                     $n = $n->item(0);
                     $f = $this->createFragment('<div>'.$css.'</div>');
                     if ($f) {
                        $f = $n->appendChild($f);
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
        else if ($css instanceof RichElement) $dl = $css->get();
        else if (is_array($css)) $dl = $css;

        if (isset($dl) && $dl ) foreach($dl as $n)
            if ($n->nodeType==1) $this->elms[] = $n;

        return $this;
    }

    // call
    public function __call($name,$args){
        if ($name=='empty') return $this->removeChildren();
        elseif ($name=='clone') return $this->cloneNodes();
        else throw new Exception('Undefined Method \''.$name.'\'');
    }

    // getter
    public function __get($var) {
        if ($var=='length') return count($this->elms);
        elseif ($var=='page') return $this->page;
        elseif ($var=='client') {
            return $this->page->client($this->matchSelector(true));
        }
    }

    /**
     * Adds new elements to the selection based on the specified selector(s)
     * @return RichElement
     */
    public function add($selector){
        $dl = '';
        if (is_string($selector)) $dl = $this->doc->cssQuery($selector);
        else if ($selector instanceof DOMNode) $this->elms[] = $selector;
        else if ($selector instanceof DOMNodeList) $dl = $selector;
        else if ($selector instanceof RichElement) $dl = $css->get();
        else if (is_array($selector)) $dl = $selector;
        if ($dl) foreach($dl as $n) $this->elms[] = $n;
        $this->modified = true;
        return $this;
    }

    /**
     * Adds a css class name to matched elements
     * @return RichElement
     */
    public function addClass($cls){
        return $this->modifyClass($cls, 'add');
    }

    /**
     * Add content after matched elements
     * @return RichElement
     */
    public function after($content) {
        return $this->insert($content,'after');
    }

    /**
     * Add the previous matched selection to the current selection
     * @return RichElement
     */
    public function andSelf() {
        $c = count($this->stack)-1;
        if ($c>=0) $this->add($this->stack[$c]);
        return $this;
    }

    /**
     * Append content to matched elements
     * @return RichElement
     */
    public function append($content) {
        return $this->insert($content,'append');
    }

    /**
     * Append matched elements to selector
     * @return RichElement
     */
    public function appendTo($selector) {
        $m = P($selector,$this->doc);
        $elms = $m->insert($this,'append',true);
        return $this->stack($elms);
    }

    /**
     * Appends an html view file to the matched elements
     * @return RichWebPage
     */
    public function appendView($view) {
        $view = file_get_contents(RichAPI::config('views.path').$view);
        if (!$view) return $this;
        else return $this->insert($view,'append');
        
    }

    /**
     * Returns or set attribute on match elements
     * @return RichElement or String
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
     * Add content before matched elements
     * @return RichElement
     */
    public function before($content) {
        return $this->insert($content,'before');
    }
    
    /**
     * Binds matched element events to a callback function
     * Can also be used to bind an array or a PDO result set to the matched elements - See RichAPI::bindTemplate()
     * @return RichElement
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
            $rt = RichAPI::bindTemplate($type, $data); // pass rows,options to bindTemplate()
            return is_string($rt) ? $this->html($rt) : $rt;
        }               
    }

    /**
     * Selects the immediate children of the matched elements
     * @return RichElement
     */
    public function children($selector = null){
        return $this->traverse($selector,'firstChild','nextSibling');
    }

    /**
     * Clone matched elements and return clones (alias clone)
     * @return RichElement
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
     * @return RichElement or String
     */
    public function css($name,$val = null) {
        $isA = false; $a = array(':',';'); $b = array('=','&');
        $retFirst = $val===null && !($isA = is_array($name));
        foreach($this->elms as $i=>$n) {
            $s = $n->getAttribute('style');
            $s = str_replace($a,$b,$s);
            $c = array(); parse_str($s,$c);
            if ($retFirst) return $c[$name] = $val; // return value for first node
            else {
                if ($isA) $c = array_merge($c, $name); else $c[$name] = $val;
                $c = str_replace($b,$a,urldecode(http_build_query($c)));
                $n->setAttribute('style',$c);
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
     * @return RichElement
     */
    public function delegate($type,$data = null, $fn = null) {
        $sel = !$this->modified ? $this->selector : null ;
        $this->page->bindElements($this->elms, $type, $data, $fn, $sel,true);
        return $this;
    }

    /**
     * Disbable matched elements
     * @return RichElement
     */
    public function disable(){ $this->attr('disabled','disabled'); }

    /**
     * Enable matched elements
     * @return RichElement
     */
    public function enable(){ $this->attr('disabled',''); }

    /**
     * Revert the currently modified selection to the previously matched selection
     * this works if the selection was modified using filter(), find(), eq(), etc
     * @return RichElement 
     */
    public function end() {
        return $this->unstack();
    }

    /**
     * Reduces the set of matched elements to a single element
     * @return RichElement
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
     * @return RichElement
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
     * @return RichElement
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
     * Returns a single element or an array of element
     * @return DOMElement or Array
     */
    public function get($index = null) { return $this->node($index); }
    public function node($index = null) {
        if ($index===null) return $this->elms;
        else return isset($this->elms[$index])? $this->elms[$index] : null;
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
     * @return RichElement
     */
    public function hide(){ $this->css('display','none'); }

    /**
     * Returns or set html on mtach elements
     * @return RichElement or String
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
     * Add matched elements after all selected elements
     * @return RichElement
     */
    public function insertAfter($selector) {
        $elms = P($selector,$this->doc)->insert($this,'after',true);
        return $this->stack($elms);
    }

    /**
     * Add matched elements before all selected elements. 
     * @return RichElement */
    public function insertBefore($selector) {
        $elms = P($selector,$this->doc)->insert($this,'before',true);
        return $this->stack($elms);        
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
     * Localize matched elements that have a valid locale key/value pair assigned to the langid attribbte
     * @return RichElement
     */
    public function localize(){
        foreach($this->elms as $n) {
            $nl = $this->doc->xQuery('descendant-or-self::*[@langid]',$n);
            RichWebPage::NodeL10n($nl);
        }
    }

    /**
     * Applies a callback to matched elements and returns a new set of elements
     * Can also be used to filter or replace the matched elements
     * @return RichElement
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
    public function matchSelector($autoId = false){
        if ($this->selector && !$this->modified) $sel = $this->selector;
        else {
            $ids = array();
            foreach($this->elms as $n) {
                $id = $n->getAttribute('id');
                if ($autoId && !$id) $n->setAttribute('id', $id = 'e0x'.self::$autoid++); // auto assign id
                if ($id) $ids[]='#'.$id;
            }
            $sel = implode(',',$ids);
        }
        return $sel;
    }

    /**
     * Selects the next sibling of the matched elements
     * @return RichElement
     */
    public function next($selector = null){
        return $this->traverse($selector,'nextSibling','nextSibling',true);
    }

    /**
     * Selects the next siblings of the matched elements
     * @return RichElement
     */
    public function nextAll($selector = null){
        return $this->traverse($selector,'nextSibling','nextSibling'); // select all
    }

    /**
     * Remove element matching the specified selector from the set of match elements
     * @return RichElement
     */
    public function not($selector) {
        return $this->filter($selector, true); // return inverted filter set
    }

    /**
     * Selects the parent element of the matched elements
     * @return RichElement
     */
    public function parent($selector = null){
        return $this->traverse($selector,'parentNode','parentNode',true);
    }
    
    /**
     * Selects the ancestors of the matched elements
     * @return RichElement
     */
    public function parents($selector = null){
        return $this->traverse($selector,'parentNode','parentNode'); // select all
    }

    /**
     * Selects the previous sibling of the matched elements
     * @return RichElement
     */
    public function prev($selector = null){
        return $this->traverse($selector,'previousSibling','previousSibling',true);
    }

    /**
     * Selects the previous siblings of the matched elements
     * @return RichElement
     */
    public function prevAll($selector = null){
        return $this->traverse($selector,'previousSibling','previousSibling'); // select all
    }

    /**
     * Prepend content to elements
     * @return RichElement
     */
    public function prepend($content) {
        return $this->insert($content,'prepend');
    }

    /**
     * Prepend matched elements to selector
     * @return RichElement
     */
    public function prependTo($selector) {
        $m = P($selector,$this->doc);
        return $this->stack($m->insert($this,'prepend',true));
    }

    /**
     * Remove matched elements from document
     * @return RichElement
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
     * @return RichElement
     */
    public function removeAttr($cls) {
        foreach($this->elms as $i=>$n) {
            $n->removeAttribute($cls);
        }
        return $this;
    }

    /**
     * @alias empty() - Remove child nodes from match elements
     * @return RichElement
     */
    public function removeChildren() {
        foreach($this->elms as $n) $n->nodeValue ='';
        return this;
    }

    /**
     * Removes css class name from elements
     * @return RichElement
     */
    public function removeClass($cls) {
        return $this->modifyClass($cls,'remove');
    }

    /**
     * Remove data from matched elements
     * @return RichElement
     */
    public function removeData($name){
        $this->page->removeData($this->storeName().$name);
        return $this;
    }

    /**
     * Replace matched elements with content
     * @return RichElement
     */
    public function replaceWith($content){
        return $this->after($content)->remove();
    }

    /**
     * Replace all selected with matched elements
     * @return RichElement
     */
    public function replaceAll($selector){
        $m = P($selector,$this->doc);
        $elms = $m->insert($this,'after',true);
        $m->remove();
        return $this->stack($elms);
    }

    /**
     * Selects inner child of match elements - used by wrap functions
     * @return RichElement
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
     * Show matched elements (display:block)
     * @return RichElement
     */
    public function show(){ $this->css('display','block'); }

    /**
     * Selects the siblings of the matched elements
     * @return RichElement
     */
    public function siblings($selector = null){
        return $this->traverse($selector,'siblings','nextSibling'); // select all
    }

    /**
     * Selects a subset of the match elements
     * @return RichElement 
     */
    public function slice($start, $length = null) {
        return $this->stack(
            $length===null ? array_slice($this->elms, $start) :
            array_slice($this->elms, $start, $length)
        );
    }

    /**
     * Sets or returns a date store name for the matched selection
     * @return RichElement or String
     */
    public function storeName($n = null) {
        if ($n!==null) $his->name = $n;
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
     * Returns or set text on match elements
     * @return RichElement or String
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
     * Binds a callback function to a timeout event. $msTime  - milliseconds
     * @return RichElement
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
     * @return RichElement
     */
    public function toggleClass($cls) {
        return $this->modifyClass($cls,'toggle');
    }

    /**
     * Trigger events on the match elements
     * @return RichElement
     */
    public function trigger($type,$args = null) {
        $this->page->triggerEvent($this->elms,$type,$args);
        return $this;
    }

    /**
     * Removes all event handlers for the specified event type
     * @return RichElement
     */
    public function unbind($type) {
        $sel = !$this->modified ? $this->selector : null ;
        $this->page->unbindElements($this->elms,$type,$sel);
        return $this;
    }

    /**
     * Removes all duplicate elements from the matched set
     * @return RichElement
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
     * Returns or sets the value for a form element
     * @return RichElement or String
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
                elseif ($nn!='select') return $elm->getAttribute('value');   // inputs
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
                $fldName = $n->getAttribute('name'); // atribute name
                if (($st = strpos($fldName,'['))!==false) $fldName = substr($fldName,0,$st); // remove [] from name
                $value = ($isa && isset($v[$fldName])) ? $v[$fldName] : $v;
                if ($nn=='textarea') {      // textareas
                    $n->nodeValue='';
                    if ($value && !is_array($value)) {
                        $f = $this->createFragment(htmlspecialchars($value.''));
                        if ($f) $n->appendChild($f);
                    }
                }
                elseif ($nn!='select') {    // inputs
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
                    elseif (!is_array($value)){
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
     * @return RichElement
     */
    public function wrap($content) {
        foreach($this->elms as $n) {
            P($n,$this->doc)->wrapAll($content);
        }
        return $this;
    }

    /**
     * Wrap all matched elements inside the specified HTML content or element
     * @return RichElement
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

    // returns the html content of an elment
    protected function nodeContent($n, $outer=false) {
        $d = $this->page->flyDOM(); // dom with xhtml doctype
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
        if (!$html) return false;
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
        if ($content && is_string($content))
            $content = array($this->createFragment($content));
        elseif ($content instanceof DOMNode ) $content =  array($content);
        elseif ($content instanceof DOMNodeList ) ;//$content =  $content;
        elseif ($content instanceof RichElement ) $content =  $content->get();
        else return $this;
        if ($retNodes) $newNodes = array();
        foreach($this->elms as $i=>$n){
            foreach($content as $node) {
                $same = $n->ownerDocument->isSameNode($node->ownerDocument);
                if (!$same) $node = $n->ownerDocument->importNode($node,true);
                else if ($i > 0) $node = $node->cloneNode(true); // clone objects
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
    protected function unstack() {
        $elms = isset($this->stack) ?  array_pop($this->stack) : $this->elms;
        if ($elms) $this->elms = $elms;
        return $this;
    }

}


?>