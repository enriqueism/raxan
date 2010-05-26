<?php
/**
 * Raxan Element
 * @package Raxan
 */

/**
 * Raxan Element Class
 * This class is used to traverse and manipulate a set of matched DOM elements
 * @property-read RaxanWebPage $page Reference to Raxan Web Page
 * @property RaxanClientExtension $client Returns an instance of the CLX for the matched selectors
 * @property int $length Returns the number of matched elements
 * @property RaxanDOMDocument $doc
 * @property DOMElement $_rootElm
 * @property mixed $_context
 * @property string $_selector
 * @method RaxanElment empty() Removes all children of the matched elements
 * @method RaxanElment clone() Clone matched elements and return clones
 */
class RaxanElement extends RaxanBase {

    protected static $callMethods; // stores extended methods

    protected $elms;
    protected $doc, $_rootElm, $_context, $_selector;

    protected $_stack; // used to store previous match list
    protected $_storeName,
              $_length,
              $_modifiedStack, // true if stack was modified
              $_customValidity,
              $_validInp,
              $_invalidInp;

    /**
     * RaxanElement(css,context)
     * RaxanElement(html,context)
     * @param mixed $css CSS selector string, array of DOMElements, DOMNode, DOMNodeList or RaxanElement
     * @param DOMNode $context
     * @return RaxanElement
     */
    function __construct($css,$context = null) {
        parent::__construct();

        $this->_length = 0;     // set length to 0
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
        
        $this->doc = ($this->doc) ? $this->doc : RaxanWebPage::controller()->document();        
        
        $this->_rootElm = $this->doc->documentElement;
        $css = $css ?  $css : $this->_rootElm;
        $this->_context = ($c) ? $c : $this->_rootElm;    // assign context element

        if (is_string($css)) {
            $this->_selector = $css;
            if (!$this->isHTML($css)) $dl = $this->doc->cssQuery($css,$this->_context);
            else {
                // handle html
                $this->_modifiedStack = true;
                if (!$this->doc->isInit()) $this->doc->initDOMDocument();
                $n = $this->doc->getElementsByTagName('body');
                if ($n->length) {
                    $n = $n->item(0);
                    $f = $this->page->createFragment('<div>'.$css.'</div>');
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
        else if ($css instanceof DOMNode) { $this->elms[] = $css; $this->_length = 1; }
        else if ($css instanceof DOMNodeList) $dl = $css;
        else if ($css instanceof RaxanElement) $dl = $css->get();
        else if (is_array($css)) $dl = $css;

        if (isset($dl) && $dl ) foreach($dl as $n)
            if ($n->nodeType==1)  {
                $this->elms[] = $n;
                $this->_length++;
            }

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
        if ($var=='page') return $this->doc->page;
        if ($var=='length') {
            return !$this->_modifiedStack ?
                $this->_length : count($this->elms);
        }
        elseif ($var=='client') {
            $sel = $this->matchSelector(true);
            return $this->doc->page->client($sel);
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
        $this->_modifiedStack = true;
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
        $c = count($this->_stack)-1;
        if ($c>=0) $this->add($this->_stack[$c]);
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
     * Appends the html of the matched elements to the selected client element. 
     * @return RaxanElement
     */
    public function appendToClient($selector) {
        return $this->page->updateClient($selector, 'append',$this->elms);
    }

    /**
     * Appends an html view file to the matched elements
     * @return RaxanElement
     */
    public function appendView($view,$selector = null,$data = null) {
        $view = $this->page->getView($view,$selector,$data);
        return (!$view) ? $this : $this->insert($view,'append');        
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
        $this->_modifiedStack = true;
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
     * Attach an event and a callback function to the matched elements events
     * This method is also used to bind an array or a PDO result set to the matched elements - See Raxan::bindTemplate()
     * @example Usage:<br />
     *  <p>$elm->bind($type, $fn);</p>
     *  <p>$elm->bind($type, $data, $fn);</p>
     *  <p>$elm->bind($array, $options); // for use when binding to a datatset or array</p>
     * @param string $type Event type. Example click
     * @param mixed $data Optional. Event data to be passed to the callback function
     * @param mixed $fn Callback function
     * @return RaxanElement
     */
    public function bind($type,$data = null, $fn = null) {
        $sel = !$this->_modifiedStack ? $this->_selector : null ;
        if (is_string($type)) {
            $this->page->bindElements($this->elms, $type, $data, $fn, $sel,false);
            return $this;
        }
        else {
            // setup template
            $opt = $data; $data = $type; // swap values;
            if (!$opt) $opt= array('tpl'=>$this->html());
            else if (!isset($opt['tpl']) && !isset($opt[0])) {
                $opt['tpl'] = $this->html();
            }
            if (isset($this->isUIElement) && $this->_bindData($data,$opt)===true)  return $this; // return if handled by ui
            $rt = Raxan::bindTemplate($data, $opt); // pass rows,options to bindTemplate()
            return is_string($rt) ? $this->html($rt) : $rt;
        }               
    }

    /**
     * Check validity on form fields.
     * Supports basic HTML5 validation for email, date, number and url input types.
     * The pattern, required, min and max attributes are also supported. Currenly min/max is only supported for numeric inputs (type=number)
     * @param boolean $markInvalid Optional. Mark invalid form fields
     * @param string $cssClass Optional. Required class
     * @param mixed $callback Optional. Custom validation callback
     */
    public function checkValidity($markInvalid = false,$cssClass = null,$callback = null) {
        $errors = $values = array();
        $page = $this->doc->page;
        $xpath = '//*[@name][not(descendant-or-self::*[@disabled])]'; // find form elements that are not disabled
        $isAjax = $page->isAjaxRequest;
        $fields = $this->findByXPath($xpath,true);
        $sanitize = Raxan::getSharedSanitizer();
        $fn = ($callback && is_callable($callback)) ? $callback : null;
        if ($fn) $callByArray = is_array($fn);
        $cssClass = $cssClass ? $cssClass : 'required';
        foreach($fields as $fld) {
            $invalid = ''; $rt = null;
            $pattern = $fld->getAttribute("pattern");
            $required = $fld->hasAttribute("required");
            $maxlength = $fld->getAttribute("maxlength");
            $checked = $fld->hasAttribute("checked");
            $novalidate = $fld->hasAttribute("novalidate");
            $min = $fld->getAttribute("min");   
            $max = $fld->getAttribute("max");   
            $id = $fld->getAttribute("id");

            if ($novalidate) continue;

            $name = $fld->getAttribute("name");
            if (($lbPos = strpos($name,'['))!==false) $name = substr($name,0,$lbPos); // remove [] from name

            $type = $inpType = $fld->getAttribute("type");
            if ($type=='') $type = $fld->nodeName;
            $type = trim(strtolower($type));

            if ($type=='button'||$type=='submit'||$type=='image'||$type=='object') continue;

            if ($type=='textarea')
                $value = $page->nodeContent($fld);
            else if ($type=='select') {
                $oElm = $this->elms; $this->elms = array($fld);
                $value = $this->val();  // get selected values
                if ($lbPos && is_array($value)) $lbPos = false; // set lbPos so it's not detected when storeing $value
                $this->elms = $oElm;
            }
            else $value = $fld->getAttribute("value");

            // value missing
            if ($required && (
                $value=='' ||
                ( ($type=='checkbox'||$type=='radio') &&
                  ($checked!='checked' && !isset($_POST[$name])) // support for radio/checkbox group
                )
            )) $invalid = 'valueMissing';
            // pattern mismatch
            if ($pattern && !preg_match($pattern,(string)$value)) $invalid = 'patternMismatch';
            // too long - maxlength
            if ($maxlength && ($type == 'text'||$type=='textarea') && strlen((string)$value)>$maxlength) $invalid = 'tooLong';
            // type mismatched
            if ($value!=='' && (
                ($type == 'email' && !$sanitize->isEmail($value)) ||
                ($type == 'url' && !$sanitize->isUrl($value) ) ||
                ($type == 'number' && !$sanitize->isNumeric($value)) ||
                ($type == 'date' && !$sanitize->isDate($value,'Y-m-d')) ||
                ($type == 'month' && !$sanitize->isDate($value,'Y-m'))
            )) $invalid = 'typeMismatch';
            // range - min / max 
            if ($value && $type=='number' && !$invalid) {
                 if ($min && $value<$min) $invalid = 'rangeUnderflow';
                 if ($max && $value>$max) $invalid = 'rangeOverflow';
            }

            // callback
            if ($fn) {
                if (!$callByArray) $rt = $fn($fld,$name,$value,$markInvalid,$invalid);  // function callback (string or anonymous function)
                else $rt = $fn[0]->{$fn[1]}($fld,$name,$value,$markInvalid,$invalid);   // object callback
                if ($rt===false || is_string($rt)) $this->_customValidity[$name] = $rt || 'customError'; // rt can 1. false (invalid), 2. true (valid) or 3. string (custom message)
            }

            // custom error
            if (isset($this->_customValidity[$name])) $invalid = $this->_customValidity[$name];

             // store input error message
            if ($invalid) {
                $errors[$name] = ($msg = Raxan::locale($invalid,$value)) ? $msg : $invalid;
            }
            else {
                 // store input values
                if ($type=='select' && !$value) $value = null;  // don't store values for unselected selects
                else if (($inpType=='checkbox'||$inpType=='radio') && $checked=='') $value  = null; // don't store values for unchecked inputs
                else if (!isset($values[$name])) $values[$name] = $lbPos ? array($value) : $value;  // set single value. supports pphp [] input array
                else {
                    // append multiple values
                    if (!is_array($values[$name])) $values[$name] = array($values[$name]);
                    $values[$name][] = $value;
                }
            }

            // mark invalid fields
            if ($markInvalid) {
                if ($invalid) {
                    if ($id && $isAjax) c("#$id")->addClass($cssClass);
                    else $fld->setAttribute('class',$fld->getAttribute('class').' '.$cssClass);
                }
                else if ($id && $isAjax)
                    c("#$id")->removeClass($cssClass);
            }

        }

        $this->_validInp = $values;
        $this->_invalidInp = $errors;

        return count($errors)==0 ? true : false;
    }
    /**
     * Returns an array of invalid field names and their associated validation message as reported by checkvalidity()
     * @return array
     */
    public function invalidInputs() {
        return $this->_invalidInp;
    }
    
    /**
     * Returns an array valid field names and their associated values as reported by checkvalidity()
     * @return array
     */
    public function validInputs() {
        return $this->_validInp;
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
     * @return RaxanElement
     */
    public function click($fn,$serialize = null){
        return $this->bind('#click',array(
            'callback' =>$fn,
            'serialize'=> $serialize,
            'autoDisable'=> true
        ));
    }

    /**
     * Clone matched elements and select the clones (alias to the clone() method)
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
     * Attach an event and a callback function to the selector of the matched elements via event delegation
     * @example Usage:<br />
     *  <p>$elm->delegate($selector, $type, $fn);</p>
     *  <p>$elm->delegate($selector, $type, $data, $fn);</p>
     * @param string $selector CSS selector
     * @param string $type Event type. Example click
     * @param mixed $data Optional. Event data to be passed to the callback function
     * @param mixed $fn Callback function
     * @return RaxanElement
     */
    public function delegate($selector,$type,$data = null, $fn = null) {
        $t = trim($type); $elms = $this->elms;
        $sel = $this->matchSelector(true);
       $de = $selector ? $selector : true;
        $this->page->bindElements($elms, $type, $data, $fn, $sel, $de);
        return $this;
    }

    /**
     * Disbable matched elements
     * @return RaxanElement
     */
    public function disable(){ $this->attr('disabled','disabled'); }
    
    /**
     * Make component draggable.
     * @param array $opt Optional. See jQuery Dragabbles
     * @return RaxanElement
     */
    public function draggable($opt = null) {
        $this->page->loadScript('jquery-ui-interactions');
        $this->client->draggable($opt);
        return $this;
    }

    /**
     * Make component accept dropped items and raise the drop event
     * @param array $opt. Optional. See jQuery Droppables
     * @return RaxanElement
     */
    public function droppable($opt = null) {
        $this->page->loadScript('jquery-ui-interactions');
        $this->client->droppable($opt);
        return $this;
    }

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
     * Highlight or expose the matched elements on the screen
     * @param mixed $opt Optional. Set to false to remove expose
     * @return RaxanElement
     */
    public function expose($opt = null) {
        $this->page->loadScript('jquery-tools');
        $hide = ($opt===false) ? true : false;
        if (!isset($opt['api'])) $opt['api'] = true;
        $api = $this->client->expose($opt);
        if ($opt['api']) {
            if ($hide) $api->close(); else $api->load();
        }
        return $this;
    }

    /**
     * Fades in the matched elements
     * @param mixed $speed Optional. Values include fast, normal, slow or milliseconds. Defaults to normal
     * @return RaxanElement
     */
    public function fadeIn($speed = null) {
        $cli = $this->client;
        if ($speed!==null) $cli->fadeIn($speed); else $cli->fadeIn();
        return $this;
    }

    /**
     * Fades out the matched elements
     * @param mixed $speed Optional. Values include fast, normal, slow or milliseconds. Defaults to normal
     * @return RaxanElement
     */
    public function fadeOut($speed = null) {
        $cli = $this->client;
        if ($speed!==null) $cli->fadeOut($speed); else $cli->fadeOut();
        return $this;
    }

    /**
     * Set or return the form input values of the matched elements
     * @param array $data
     * @return mixed Returns Array or RaxanElement
     */
    public function inputValues($data = null) {
        $this->findByXPath('//*[@name]');
            if ($data!==null) $this->val($data) ;
            else {
                $v = array();
                $elms = $this->elms; $this->elms = array();
                foreach($elms as $elm) {
                    $this->elms[0] = $elm;
                    $name = $elm->getAttribute('name');
                    if (($lbPos = strpos($name,'['))!==false) $name = substr($name,0,$lbPos); // remove [] from name
                    if ($name) {
                        $val = $this->val();
                        if ($lbPos && is_array($val)) $lbPos = false;
                        if (!isset($v[$name])) $v[$name] = $lbPos ? array($val) : $val;  // set single value, supports pphp [] input array
                        else {
                            // append multiple values
                            if (!is_array($v[$name])) $v[$name] = array($v[$name]);
                            $v[$name][] = $val;
                        }
                    }
                }
                $this->elms = $elms;
            }
        $this->end();
        return ($data===null) ? $v : $this;
    }

    /**
     * Reduces the matched elements to those that match the selector
     * @param string $selector CSS selector
     * @param boolean $_invert Reduces the set to elements that did NOT match the selector
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
     * Search matched elements for the specified selector(s). Returns the matched results of the search
     * @return RaxanElement Can return either an RaxanElement or an array of matched DOMElement
     */
    public function find($selector, $returnArray = false) {
        $stack = array();
        foreach($this->elms as $i=>$n) {
            $dl = $this->doc->cssQuery($selector, $n);
            foreach($dl as $e) $stack[] = $e;
        }
        return $returnArray ? $stack : $this->stack($stack);
    }

    /**
     * Search matched elements for the specified xpath. Returns the matched results of the search
     * @return RaxanElement Can return either an RaxanElement or an array of matched DOMElement
     */
    public function findByXPath($xpath, $returnArray = false) {
        $stack = array();
        foreach($this->elms as $i=>$n) {
            $dl = $this->doc->xQuery($xpath, $n);
            if ($dl) foreach($dl as $e) $stack[] = $e;
        }
        return $returnArray ? $stack : $this->stack($stack);
    }
    
    /**
     * Returns or sets the float value for a form element
     * @return RaxanElement or float
     */
    public function floatVal($v = null) {
        $s = Raxan::getSharedSanitizer();
        return ($v===null) ? $s->floatVal($this->val()) : $this->val($s->floatVal($v));
    }

    /**
     * Returns a single element or an array of element
     * @return mixed Returns a single DOMElement or an array of DOMelements if index is null
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
     * @return RaxanElement
     */
    public function hide(){ return $this->css('display','none'); }

    /**
     * Returns or sets the height of the container element
     * @return RaxanUIContainer
     */
    public function height($h = null) {
        if (is_numeric($h)) $h = $h.'px';
        return $this->css('height',$h);
    }

    /**
     * Sets the inner html content of matach elements. Returns only the inner html of the first matched element.
     * @return RaxanElement or String
     */
    public function html($html=null) {
        $page = $this->page;
        // check for ui content node
        $contentNode = (isset($this->isUIElement) && $this->contentElement) ? $this->contentElement  : null;
        foreach($this->elms as $i=>$n) {
            if ($contentNode) $n = $contentNode;
            if ($html===null) {
                // return html from first node
                return $page->nodeContent($n);
            }
            else {
                $n = $this->elms[$i] = $this->clearNode($n); // clear node
                $f = $page->createFragment($html);
                if ($f) $n->appendChild($f); // insert html
            }
        }
        return $html===null ? '' : $this;
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
    public function intVal($v = null) {
        $s = Raxan::getSharedSanitizer();
        return ($v===null) ? $s->intVal($this->val()) : $this->val($s->intVal($v));
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
     * Adds or remove a modal mask to matched elements. The inverse of expose
     * @param mixed $label Optional. Set label to false to remove mask
     * @return RaxanElement
     */
    public function mask($label = null) {
        $this->page->loadScript('jquery-ui-utils');
        $hide = ($label===false) ? true : false;
        $cli = $this->client;
        if ($hide) $cli->unmask();
        else if ($label) $cli->mask($label);
        else $cli->mask();
        return $this;
    }

    /**
     * Returns the selctor for the match elements
     * @return String
     */
    public function matchSelector($autoId = false, $idPrefix = null){
        if ($this->_selector && !$this->_modifiedStack) $sel = $this->_selector;
        else {
            $ids = array();
            $page = $this->page;
            foreach($this->elms as $n) {
                $id = $n->getAttribute('id');
                if ($autoId && !$id) $n->setAttribute('id', $id = $page->uniqueElmId($idPrefix)); // auto assign id
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
     * Returns the outer html of the first matched element
     * @return string
     */
    public function outerHtml() {
        $n = isset($this->elms[0]) ?
            $this->page->nodeContent($this->elms[0],true) : '';
        return $n;
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
     * Absolutely positioning an element relative to the window, document, a particular element, or the cursor/mouse
     * @param array $opt Set jQuery Position utility
     * @return RaxanElement
     */
    public function position($opt) {
        $this->page->loadScript('jquery-ui-utils');
        $this->client->position($opt);
        return $this;
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
     * Prepends the html of the matched elements to the selected client element.
     * @return RaxanElement
     */
    public function prependToClient($selector) {
        return $this->page->updateClient($selector, 'prepend', $this->elms);
    }

    /**
     * Preserves the state of the matched elements
     * @param string $mode Mode can be set to either local or session. Local states are preserved during postback. Session states preserved until the session ends.
     * @return RaxanElement
     */
    public function preserveState($mode = null) {
        if ($this->length==0) return $this;
        $page = $this->page;
        $mode = ($mode=='session') ? 'session' : 'local';
        foreach($this->elms as $elm) {
            $id = $elm->getAttribute('id');
            if (!$id) $elm->setAttribute('id',$id = $page->uniqueElmId());
            if (!$page->isLoaded) {
                $page->restoreElement($elm, $id, $mode); // restore state
            }
            $page->saveElement($elm, $id, $mode); // save state
        }
        return $this;
    }

    /**
     * Removes the state of the matched elements
     * @param string $mode Mode can be set to either local or session. Defaults to local
     * @return RaxanElement
     */
    public function removeState($mode = null) {
        if ($this->length==0) return $this;
        $page = $this->page;
        $mode = ($mode=='session') ? 'session' : 'local';
        foreach($this->elms as $elm) {
            $id = $elm->getAttribute('id');
            $page->saveElement($elm, $id, $mode, true); // reset state
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
        $this->_modifiedStack = true;
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
     * Replaces the selected client-side element with the html of the matched elements. 
     * @return RaxanElement
     */
    public function replaceClient($selector) {
        return $this->page->updateClient($selector, 'replace', $this->elms);
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
     * Set custom validity message for form fields
     * @example
     *  <p>$elm->setCustomValidity($array);</p>
     *  <p>$elm->setCustomValidity($name,$message);</p>
     */
    public function setCustomValidity($name,$messgae) {
        if (!$this->_customValidity) $this->_customValidity = array();
        if (is_array($name)) $this->_customValidity = array_merge($this->_customValidity, $name);
        else $this->_customValidity[$name] = $message;
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
     * Hide the matched elements using the slide up animation effect
     * @param mixed $speed Optional. Values include fast, normal, slow or milliseconds. Defaults to normal
     * @return RaxanElement
     */
    public function slideUp($speed = null) {
        $cli = $this->client;
        if ($speed!==null) $cli->slideUp($speed); else $cli->slideUp();
        return $this;
    }

    /**
     * Show the matched elements using the slide down animation effect
     * @param mixed $speed Optional. Values include fast, normal, slow or milliseconds. Defaults to normal
     * @return RaxanElement
     */
    public function slideDown($speed = null) {
        $cli = $this->client;
        if ($speed!==null) $cli->slideDown($speed); else $cli->slideDown();
        return $this;
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
        if ($n!==null) $this->_storeName = $n;
        else {
            if (!$this->_storeName){ // auto-setup collection name
                $id = ($this->elms) ? $this->elms[0]->getAttribute('id') : '';
                $this->_storeName = $id ? 'elm-'.$id : 'elm-'.$this->objId;
            }
            return $this->_storeName;
        }
        return $this;
    }

    /**
     * An ajax event helper that's used to binds a function to the submit event for the matched selection.
     * @return RaxanElement
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
        $txt = $txt ? htmlspecialchars($txt.'',null,$this->doc->charset): $txt;
        $contentNode = (isset($this->isUIElement) && $this->contentElement) ? $this->contentElement  : null;
        foreach($this->elms as $i=>$n) {
            if ($contentNode) $n = $contentNode;
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
    public function textVal($txt = null) {
        $s = Raxan::getSharedSanitizer();
        return ($txt===null) ? $s->textVal($this->val()) : $this->val($s->textVal($txt));
    }

    /**
     * Binds a callback function to a timeout event. $msTime  - milliseconds
     * @return RaxanElement
     */
    public function timeout($msTime,$data,$fn = null) {
        $sel = !$this->_modifiedStack ? $this->_selector : null;
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
     * @param string $type Event Type. Use the @local suffix to only trigger local events
     * @param mixed $args Optional arugments to to be passed to event handlers
     * @param object $eObject Optional. Original event object to passed to handlers
     * @return RaxanElement
     */
    public function trigger($type,$args = null,$eObject = null) {
        $this->page->triggerEvent($this->elms,$type,$args,$eObject);
        return $this;
    }

    /**
     * Removes all event handlers for the specified event type
     * @return RaxanElement
     */
    public function unbind($type) {
        $sel = !$this->_modifiedStack ? $this->_selector : null ;
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
     * Update client-side elements with the html content of the matched elements. 
     * @return RaxanElement
     */
    public function updateClient() {
        if ($this->page->isAjaxRequest) { // only update if in ajax mode
            $selector = $this->matchSelector();
            $this->page->updateClient($selector, 'update', $this->elms);
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
                if ($nn=='textarea') return $this->page->nodeContent($elm);    // texbareas
                elseif ($nn!='select') return $elm->getAttribute('value');   // inputs and buttons
                else {
                    $multi = $elm->hasAttribute('multiple') ? true : false;
                    $values = ($multi) ? array() : '';
                    foreach ($elm->childNodes as $n) {
                        if ($n->nodeType!=1) continue;
                        $sel = $n->hasAttribute('selected');
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
            $page = $this->page;
            $isa = is_array($v);
            foreach($this->elms as $n) {
                $value = null;
                $nn = $n->nodeName;
                $fldName = $n->getAttribute('name'); // attribute name
                if (($st = strpos($fldName,'['))!==false) $fldName = substr($fldName,0,$st); // remove [] from name
                if ($isa && isset($v[$fldName])) $value =  $v[$fldName];
                else if (!$isa) $value = $v;
                if ($nn=='textarea') {     // textareas
                    $n->nodeValue='';
                    if ($value && !is_array($value)) {
                        $f = $page->createFragment(htmlspecialchars($value.'',null,$this->doc->charset));
                        if ($f) $n->appendChild($f);
                    }
                }
                elseif ($nn!='select') {        // inputs
                    $at = $n->getAttribute('type');
                    $av = $n->getAttribute('value');
                    if ($at=='button' || $at=='submit') continue;
                    elseif (($at=='radio'||$at=='checkbox') && is_array($value)) {  // index arrays
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
                        if ($ov=='') $ov = $o->nodeValue;
                        if (in_array($ov,$value)) $o->setAttribute('selected','selected');
                        else $o->removeAttribute('selected');
                    }
                }
                
            }
            return $this;
        }
    }

    /**
     * Returns or sets the width of the container element
     * @return RaxanUIContainer
     */
    public function width($w = null) {
        if (is_numeric($w)) $w = $w.'px';
        return $this->css('width',$w);
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
     *  Private/protected Methods ----------------------
     *  ------------------------------------------------
     */

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

    // Clear node
    protected function clearNode($n) {
        $n->nodeValue = '';
        return $n;
    }

    // Insert content into DOM
    protected function insert($content,$pos,$retNodes = false){
        if ($content && ($isFragment = is_string($content)))
            $content = array($this->page->createFragment($content));
        elseif ($content instanceof DOMNode ) $content =  array($content);
        elseif ($content instanceof DOMNodeList ) ;//$content =  $content;
        elseif ($content instanceof RaxanElement ) $content =  $content->get();
        else return $this;
        if ($retNodes) $newNodes = array();
        // check for ui content node
        $contentNode = (isset($this->isUIElement) && $this->contentElement) ? $this->contentElement  : null;
        foreach($this->elms as $i=>$n){
            if ($contentNode && ($pos=='append'||$pos=='prepend')) $n = $contentNode;
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
            if (!isset($this->_stack)) $this->_stack = array();
            $this->_stack[] = $this->elms; // save previous list
            $this->elms = $elms;
            $this->_modifiedStack = true;
        }
        return $this;
    }

    // Restore previously matched elements 
    protected function unstack($all = false) {
        $hasStack = isset($this->_stack) && $this->_stack;
        if ($hasStack) {
            if (!$all) $elms = isset($this->_stack) ?  array_pop($this->_stack) : $this->elms;
            else {
                $elms = $this->_stack[0];
                unset($this->_stack);
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


}

?>