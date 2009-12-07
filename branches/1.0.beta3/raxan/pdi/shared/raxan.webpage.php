<?php
/**
 * Raxan Web Page
 * Copyright Raymond Irving 2008
 * @date: 10-Dec-2008
 * @package Raxan
 */



// Load Raxan Element
include_once(dirname(__FILE__).'/raxan.element.php');

/**
 * Rch web Page query shortcut
 * @param $selector Mixed - css selector, html, DOMNode
 * @param $content DOMNode
 * @return RaxanElement
 */
function P($selector = '', $context = null){
    return RaxanWebPage::controller()->find($selector,$context);
}

/**
 * Raxan Client Extention Wrapper
 * @param $selector Mixed - css selector, html
 * @return RaxanClientExtention
 */
function C($selector = '', $context = null){
    return RaxanWebPage::controller()->client($selector,$context);
}

/**
 * Creates a reference to a client-side function
 * @return RaxanClientVariable
 */
function _fn($str,$name = null,$registerGlobal = false){
     RaxanWebPage::loadClientExtension();
    $v = new RaxanClientVariable($str,null,true);
    return $v;
}

/**
 * Creates a reference to the client-side $trigger function. Used to trigger an event callback to the server from the client.
 * @return RaxanClientVariable
 */
function _event($type,$value = null,$target = 'page'){
    // get value from first argument. If first argument is an event then pass event to trigger
    $code = 'var e,v=arguments[0],data=arguments[1]; if (v && v.stopPropagation) {e=v;v=null};';
    $code.= '$trigger("'.Raxan::escapeText($target).'",'.
         ($type!==null ? '"'.Raxan::escapeText($type).'"' : '""').','.
         ($value!==null ? '"'.Raxan::escapeText($value).'"' : 'v').',null,{event:e,data:data}'.
    ')';
    return _fn($code);
}

/**
 * Creates a reference to a client-side javascript variable
 * @return RaxanClientVariable
 */
function _var($str, $name = null, $registerGlobal = false){
    RaxanWebPage::loadClientExtension();
    $v = new RaxanClientVariable($str, $name, false,$registerGlobal);
    return $v;
}

/**
 * Use for traversing and manipulating HTML DOM elements
 */
class RaxanWebPage extends RaxanBase implements ArrayAccess  {

    public static $vars = array();          // client-side local variables.
    public static $varsGlobal = array();    // client-side global variables.
    public static $actions = array();       // client-side actions

    public $clientPostbackUrl;
    public $isLoaded = false;
    public $isEmbedded,$embedOptions;
    public $isPostBack, $isCallback, $isAuthorized;
    public $isCache, $isDataStorageLoaded;
    public $responseType = 'html';          // html,xhtml,xhtml/html,xml,wml,
    public $defaultBindOptions = array();   // default bind options

    protected static $eventId = 1;
    protected static $cliExtLoaded = false;
    protected static $mPage = null;     // page controller or master page
    protected static $callMethods;      // stores extended methods

    protected $localizeOnResponse;      // Automatically insert language strings into element with the langid attribute set to valid locale key/value pair
    protected $initStartupScript;       // loads the raxan startup.js script
    protected $resetDataOnFirstLoad;    // reset page data on first load
    protected $preserveFormContent;     // preserve form values on post back
    protected $disableInlineEvents;     // disables the processing on inline events
    protected $scriptBehind = '';       // sets the javascript code behind file
    protected $showRenderTime;          // shows the render time of the page
    protected $serializeOnPostBack;     // default selector value for matched elements to be serialize on postback
    protected $degradable;              // enable accessible mode for links, forms and submit buttons when binding to an event
    protected $masterTemplate;
    protected $masterContentBlock = '.master-content';


    /**
     * @var RaxanDOMDocument $doc
     */
    protected $doc = null;   // document
    protected $pageOutput;   // output buffer
    protected $_startTime, $_endResponse, $_startReply;
    protected $_charset, $_headers, $_contentType;
    protected $_storeName, $_postRqst;
    protected $_flyDOM, $_scripts = array();
    protected $_eCache = array();    // element cache
    protected $_uiElms;     //registered ui elements
    protected $_stateObjects;
    protected $_dataStore, $_dataReset = false;

    // Page request handlers
    protected function _init() {}
    protected function _authorize() { return true; }
    protected function _reset ()  { return true; }
    protected function _load() {}
    protected function _switchboard($action) {}
    protected function _prerender() {}
    protected function _postrender() {}
    protected function _reply() {}
    protected function _finalize() {}

    public function __construct($xhtml='', $charset = null, $type=null) {
        parent::__construct();

        $t = & $this;

        // set start time
        if ($t->showRenderTime) $t->_startTime = Raxan::startTimer();

        // initailize Raxan
        if (!Raxan::$isInit) Raxan::init();

        // apply default page settings to the specified page object
        $c = Raxan::config();
        if (!isset($t->localizeOnResponse)) $t->localizeOnResponse = $c['page.localizeOnResponse'];
        if (!isset($t->showRenderTime)) $t->showRenderTime = $c['page.showRenderTime'];
        if (!isset($t->initStartupScript)) $t->initStartupScript = $c['page.initStartupScript'];
        if (!isset($t->resetDataOnFirstLoad)) $t->resetDataOnFirstLoad = $c['page.resetDataOnFirstLoad'];
        if (!isset($t->preserveFormContent)) $t->preserveFormContent = $c['page.preserveFormContent'];
        if (!isset($t->disableInlineEvents)) $t->disableInlineEvents = $c['page.disableInlineEvents'];
        if (!isset($t->masterTemplate)) $t->masterTemplate = $c['page.masterTemplate'];
        if (!isset($t->serializeOnPostBack)) $t->serializeOnPostBack = $c['page.serializeOnPostBack'];
        if (!isset($t->degradable)) $t->degradable = $c['page.degradable'];
        // Deprecated. Use preserveFormContent instead
        if (isset($t->updateFormOnPostback)) $t->preserveFormContent = $t->updateFormOnPostback; // @todo: remove in future release

        // set clientPostbackUrl
        $t->clientPostbackUrl = Raxan::currentURL();

        // load default settings, charset, etc
        $t->_charset = $charset ? $charset : $c['site.charset'];
        $t->doc = self::createDOM(null, $t->_charset);
        $t->doc->page = $t;
        $t->source($xhtml,$type); // set document source
        if (self::$mPage==null) self::controller($t);

        // init postback variables
        $t->isPostBack = $t->isPostback = count($_POST) ? true : false; // @todo: deprecate $t->isPostback
        $t->isCallback = isset($_POST['_ajax_call_']) ? true : false;
        if (isset($_GET['embed'])) {
            $em = $_GET['embed'];
            $opt = ($t->isEmbedded = isset($em['js'])) ? $_GET['embed']['js'] : '';
            $t->embedOptions = $opt;
        }

        // call page  _init, _switchboard and _load
        $t->_endResponse = false;
        if (Raxan::$isDebug) Raxan::debug('Page _init()');
        $t->_init();
        Raxan::triggerSysEvent('page_init',$t);

        if (Raxan::$isDebug) Raxan::debug('Page _authorize()');
        $t->isAuthorized = !$t->_endResponse && $this->_authorize();
        if ($t->isAuthorized || $t->_endResponse) {

            // switchboard
            $a = isset($_GET['sba']) ? $_GET['sba'] : '';   // get switchboad action (sba)
            if (!$t->_endResponse) $t->_switchboard($a);

            // bind inline events
            if (Raxan::$isDebug) Raxan::debug('Page - Bind Inline Events');
            $this->bindInlineEvents();

            // preserve state
            $this->findByXPath('//*[@xt-preservestate]')->preserveState();
            
            if (Raxan::$isDebug) Raxan::debug('Page _load()');
            if (!$t->_endResponse) {
                $t->_load(); $t->isLoaded = true;
                $t->loadUIElements();
                Raxan::triggerSysEvent('page_load',$t);
            }                     

            if ($t->preserveFormContent && $t->isPostback) {
                if (Raxan::$isDebug) Raxan::debug('Page - Restore Form State');
                $t->updateFields(); // update form fields on postbacks
            }

        }
        else {            
            // if authorization failed then return HTTP: 403
            Raxan::sendError(Raxan::locale('unauth_access'), 403);
        }

    }

    public function __destruct() {
        $this->_finalize();
        $this->doc = null; // discard dom object
    }

    // call
    public function __call($name,$args){
        if (isset(self::$callMethods[$name])) {
            $fn = self::$callMethods[$name];
            if (is_array($fn)) return $fn[0]->{$fn[1]}($this,$args);
            else return $fn($this,$args);
        }
        else throw new Exception('Undefined Method \''.$name.'\'');
    }

    /**
     * Returns a matched element by id using the variable name
     * @param String $id
     * @return RaxanElement
     */
    public function __get($id) {
        $ec = & $this->_eCache;
        if (isset($ec[$id]) && ($e = $ec[$id])) // check element cache
            if (($n = $e->get(0)) && $n->parentNode) {
                return $e->end(true); // reset stack if exist
            }
            else unset($ec[$id]);
        if (($e = $this->findById($id))) return $ec[$id] = $e;
        throw new Exception('Page element \''.$id.'\' or property not found');
    }

    /**
     * Adds an HTTP header to the page
     * @return RaxanWebPage
     */
    public function addHeader($hdr) {
        if (!$this->_headers) $this->_headers = array();
        $this->_headers[] = $hdr;
        return $this;
    }

    /**
     * Adds a block of CSS to the webpage
     * @return RaxanWebPage
     */
    public function addCSS($style) {
        $this->loadCSS('<style type="text/css"><![CDATA[ '."\n".$style."\n".' ]]></style>');
        return $this;
    }

    /**
     * Adds a block of Javascript to the webpage
     * @return RaxanWebPage
     */
    public function addScript($script,$startupEvent = null) {
        if ($startupEvent && strpos('ready,load,unload,',$startupEvent.',')!==false) {
            $script = "Raxan.".$startupEvent."(function(){\n".$script."\n})";
            $this->initStartupScript = true;
        }
        $this->loadScript('<script type="text/javascript"><![CDATA[ '."\n".$script."\n]]></script>");
        return $this;
    }

    /**
     * Appends the specified content to the page master content block element
     * @return RaxanWebPage
     */
    public function append($html) {
        $s = $this->masterContentSelecor();
        $this->find($s)->append($html);
        return $this;
    }

    /**
     * Appends an html view to the page master content block element
     * @return RaxanWebPage
     */
    public function appendView($view) {
        $view = file_get_contents(Raxan::config('views.path').$view);
        if ($view) {
            $s = $this->masterContentSelecor();
            $this->find($s)->append($view);
        }
        return $this;
    }

    /**
     * Binds an event to the page body element
     * @return RaxanWebPage
     */
    public function bind($type,$data = null ,$fn = null) {
        $this->findByXPath('//body')->bind($type, $data, $fn);
        return $this;
    }

    /**
     * Bind Inline events to page callback method
     * @return RaxanWebPage
     */
    public function bindInlineEvents() {
        if ($this->disableInlineEvents) return $this;
        $elms = $this->doc->xQuery('//*[@xt-bind]|//*[@xt-delegate]|//*[@xt-autoupdate]');
        if ($elms->length > 0) {
            $l = $elms->length;
            for($i=0;$i<$l;$i++) {
                $elm = $elms->item($i);
                $attr = ($a = $elm->getAttribute('xt-bind')) ? 'B:'.str_replace(';',';B:',$a) : ''; // bind to multiple events
                $attr.= ($a = $elm->getAttribute('xt-delegate')) ? ($attr ? ';':'').'D:'.str_replace(';',';D:',$a) : ''; // bind to multiple events
                $attr.= (($a = $elm->getAttribute('xt-autoupdate'))!='true' && $a) ? ($attr ? ';':'').'A:'.$a : '';
                $events = explode(';',$attr);
                $elm->removeAttribute('xt-bind');
                $elm->removeAttribute('xt-delegate');
                foreach($events as $e) {
                    if (!$e) continue;
                    $sel = ''; $belms = null;
                    $mode = $e[0]; $e = substr($e,2);
                    $type = explode(',',trim($e),5); $de = false;
                    $o = array('callback'=>(isset($type[1]) && ($t = trim($type[1])) ? '.'.$t : null));
                    if (isset($type[2]) && ($t = trim($type[2])))
                        $o[($mode=='A' ? 'repeat' : 'serialize')] =  ($mode=='A' && ($t=='true'||$t=='repeat')) ? true : $t;
                    if (isset($type[3]) && ($t = trim($type[3])))
                        $o[($mode=='A' ? 'serialize' : 'autoDisable')] =  ($t=='true') ? true : $t;
                    if (isset($type[4]) && ($t = trim($type[4]))) $o['autoToggle'] = ($t=='true') ? true : $t;
                    if ($mode=='D') {   // delegate event
                        $t = trim($type[0]);
                        if (($p = strrpos($t,' '))===false) $de = true;
                        else {
                            $de = substr($t,0,$p);
                            $type[0] = substr($t,$p+1-strlen($t)); 
                            $id = $elm->getAttribute('id');
                            if (!$id) $elm->setAttribute('id',$id = 'e0'.self::$eventId++);
                            $sel = '#'.$id;
                        }                        
                    }
                    $belms = is_array($belms) ? $belms : array($elm);
                    $this->bindElements($belms, $type[0], $o, null, $sel, $de);
                }
            }
        }
        return $this;
    }

    /**
     * Binds an array of elements to a client-side event - used by RaxanElement
     * @return RaxanWebPage
     */
    public function bindElements(&$elms,$type,$data,$fn = null,$selector = '',$delegate = false) {
        if (!$this->events) $this->events = array();
        if (!isset($this->events['selectors'])) $this->events['selectors'] = array();
        $e = & $this->events; $type = trim($type);
        $accessible = $extras = $isOpt = false;
        $selector = trim($selector);
        $local = $global = $ids = $prefTarget = $serialize = $value = $script =  '';
        $delay = $autoDisable = $autoToggle = $inputCache = $repeat = $switchTo = $extendedOpt = '';
        if (substr($type,-6)=='@local') {$type = substr($type,0,-6); $local = true; } // check if local access
        elseif (substr($type,-7)=='@global') {$type = substr($type,0,-7); $global = true; } // check if global access
        // setup event options
        $hndPrefix = 'e:'; // flag events handlers as external (client or global access)
        if ($local) $hndPrefix = 'l:'; // flag event as local (private)
        else {
            // get default options
            $opts = $this->defaultBindOptions;
            if ($this->serializeOnPostBack) $opts['serialize'] = $this->serializeOnPostBack;
            if ($this->degradable) $opts['accessible'] = true;
            if ($fn===null) {
                $isOpt = (is_array($data) && !isset($data[1])) ; // check if data is a callback function
                if (!$isOpt) {
                    $fn = $data; $data = null;
                }
            }
            if ($isOpt) $opts = ($opts) ? $opts+$data : $data;
            else if ($opts) {
                $isOpt = true;
                $opts['data'] = $data;
                $opts['callback'] = $fn;
            }

            if ($isOpt) {
                $extras = $delegate ? true : $extras;
                $fn = isset($opts['callback']) ? $opts['callback'] : null;
                $value = isset($opts['value']) ? $extras = $opts['value'] : null;
                $prefTarget = isset($opts['prefTarget']) ? $extras = $opts['prefTarget'] : null;
                $serialize = isset($opts['serialize']) ? $extras = $opts['serialize'] : null;
                $script = isset($opts['script']) ? $extras = $opts['script'] : null;
                $delay = isset($opts['delay']) ? $extras = $opts['delay'] : null;
                $autoDisable = isset($opts['autoDisable']) ? $extras = $opts['autoDisable'] : null;
                $autoToggle = isset($opts['autoToggle']) ? $extras = $opts['autoToggle'] : null;
                $inputCache = isset($opts['inputCache']) ? $extras = $opts['inputCache'] : null;
                $accessible = isset($opts['accessible']) && $opts['accessible']==true ? true : false;
                $repeat = isset($opts['repeat']) ? $opts['repeat'] : null;
                $switchTo = isset($opts['switchTo']) ? $opts['switchTo'] : null;
                $data = isset($opts['data']) ? $opts['data'] : null; // get data object
                $extendedOpt = ($delay||$autoDisable||$autoToggle||$inputCache||$repeat||$switchTo) ? true : false;
                if ($delay && $delay!==true && $delay < 200) $delay = 200; // make sure delay is not < 200ms
            }
            // assign selector to client-side event options
            $opts = array(
                'type' => $type,
                'ptarget' => $prefTarget,   // prefered target element id@url. @url is optional
                'serialize' => $serialize,  // form selector values to be serialized
                'value' => $value,          // value to be passed back to the event from the client
                'script' => $script,
                'repeat' => $repeat,
                'delegate' => $delegate,
                'delay' => $delay,
                'autoDisable' => $autoDisable,
                'autoToggle' => $autoToggle,
                'inputCache' => $inputCache,
                'switchTo' => $switchTo,
                '_extendedOpt' => $extendedOpt // this will cause the system to append options to last paramater of $bind
            );
        } 
        // bind elements to event
        if (substr($type,0,1)=='#') $type = substr($type,1); // remove ajax event callback symbol (#)
        $callback = array($fn,$data,$global);
        // suport for .function_name - will callabck a method on the current page
        if (is_string($callback[0]) && substr($callback[0],0,1)=='.') {
            $callback[0] = array($this, substr($callback[0],1));
        }

        // setup delegate target and selector
        $dlgSelector = $selector;
        if ($delegate && $delegate!==true) { // check if delegate is a string
            $dlgSelector = str_replace(',',' '.$delegate.',',$selector).' '.$delegate;
            if ($accessible) { // locate child elements for delagates if any
                $elms = $this->find($dlgSelector)->get();
                $delegate = true;
            }
        }
        $dlgPTarget = $accessible && $delegate ? urlencode(($prefTarget) ? $prefTarget : $dlgSelector) : '';

        if (!$delegate || $accessible) foreach($elms as $n) {
            $id = $n->getAttribute('id'); // get id
            // setup non-delegate callback and element id
            if (!$delegate) { 
                if (!$id) $n->setAttribute('id', $id = 'e0'.self::$eventId++); // auto assign id
                if (!$local && !$selector) {
                    if(!$ids) $ids = array();
                    $ids[]='#'.$id;
                }
                $hkey = $hndPrefix.$id.'.'.$type;
                if (!isset($e[$hkey])) $e[$hkey] = array();
                $e[$hkey][] = & $callback;
            }

            if ($accessible && ($type=='click'||$type=='submit')) {  // make forms, links and button accessible
                $name = $n->nodeName;
                $target  = $dlgPTarget ? $dlgPTarget : $id;
                if ($name=='a' || $name=='area') { // links
                    $href = explode('#',$n->getAttribute('href'),2);
                    $val = !$value && isset($href[1]) ? trim($href[1]) : $value;  // use anchor (hash) as value
                    $query = explode('?',$href[0],2);
                    $href[0] = $query[0].'?'.(isset($query[1]) ? $query[1].'&' : '').
                    '_e[target]='.$target.($val ? '&_e[value]='.urlencode($val) : '').
                    '&_e[tok]='.Raxan::$postBackToken.($switchTo ? '&sba='.$switchTo : '');
                    if (isset($href[1])) $href[0].='&'.rand(1,90); // add a random value to the url to make
                    $url = implode('#',$href);                     // it clickable when a # is present.
                    $n->setAttribute('href',$url);
                }
                elseif ($name=='form') {    // forms
                    $htm = '<input type="hidden" name="_e[type]" value="submit" />'."\n".
                           '<input type="hidden" name="_e[target]" value="'.$target.'" />'."\n".
                           '<input type="hidden" name="_e[tok]" value="'.Raxan::$postBackToken.'" />'."\n".
                           ($value ? '<input type="hidden" name="_e[value]" value="'.htmlspecialchars($value).'" />' :'');
                    $f = $this->doc->createDocumentFragment();
                    $f->appendXML($htm);
                    $n->appendChild($f);
                }
                elseif ($name=='input'||$name=='button') {    // submit buttons
                    if (($inpType=$n->getAttribute('type'))=='submit' || $inpType=='image') {
                        $name = $n->getAttribute('name');                        
                        if (!$name) {
                            if (!$id) $n->setAttribute('id', $id = 'e0'.self::$eventId++); // auto assign id
                            $n->setAttribute('name',$name = $id);
                        }
                        // find parent form
                        $f = $this->findByXPath('//input[@id="'.$id.'"]/ancestor::form[not(//input[@name="_e[tok]"])]');
                        if ($f->length) $f->prepend('<input type="hidden" name="_e[tok]" value="'.Raxan::$postBackToken.'" />');
                        $name = ($inpType=='image' ? $name.'_x':$name);
                        $e['button:'.$name] = $id.'|'.$value; // store native button name inside event array
                    }
                }
            }
        }

        $selector = $ids ? implode(',',$ids): $selector;
        if (!$local && $selector) {
            $skey = 's:'.$selector.':'.$type;
            if (!isset($e['selectors'][$selector])) $e['selectors'][$selector] = array();
            // check if a selector option already exist for this event
            if (!isset($e[$skey]) || ($extras && !in_array($opts,$e['selectors'][$selector]))) {
                $e['selectors'][$selector][] =  $opts; // attach event options
                $e[$skey] = true;
            }
            // bind event to a delegate
            if ($delegate) {
                $hkey = $hndPrefix.$dlgSelector.'.'.$type;
                if (!isset($e[$hkey])) $e[$hkey] = array();
                $e[$hkey][] = & $callback;
            }
        }
        return $this;
    }

    /**
     * Returns an instance of the RaxanClientExtension class
     * @return RaxanClientExtension
     */
    public function client($selector = null, $context = null) {
        self::loadClientExtension();
        return new RaxanClientExtension($selector,$context);
    }


    /**
     * This method has been deprecated and will be removed from future releases. Use sanitizePostBack
     * @deprecated
     * @see sanitizePostBack
     */
    public function clientRequest($incGetRequest = false) { return $this->sanitizePostBack($incGetRequest); }


    /**
     * Returns or sets the main (body or card) content for an HTML or WML document
     * @return RaxanWebPage or HTML String
     */
    public function content($html = null) {
        $s = $this->masterContentSelecor();
        $c = $this->find($s)->html($html);
        return is_string($c) ? $c : $this;
    }

    /**
     * Creates and returns a DOMElement
     * @return DOMElement
     */
    public function createElement($name,$value=null,$attribs = null) {
        $elm = $this->doc->createElement($name,$value);
        if ($attribs) foreach($attribs as $n=>$v) $elm->setAttributes($n,$v);
        return $elm;
    }

    /**
     * Set page content type. e.g. text/html
     * @return RaxanWebPage
     */
    public function contentType($type,$charset = null) {
        $charset =  ($charset!==null) ? ($this->_charset = $charset) :$this->_charset;
        $this->_contentType = 'Content-Type: '.$type.'; charset='.$charset .'';
        return $this;
    }

    /**
     * Makes the page content downloadable
     * @return RaxanWebPage
     */
    public function contentDisposition($fileName,$transEncode = null, $fileSize = null) {
        $this->addHeader('Content-Disposition: attachment; filename="'.$fileName.'"');
        if ($transEncode) $this->addHeader('Content-Transfer-Encoding: '.$transEncode);
        if ($fileSize) $this->addHeader('Content-Length: '.$fileSize);
    }

    /**
     * Sets or returns names data value
     * @return Mixed
     */
    public function &data($key,$value = null,$setValueIfNotIsSet = false){
        if (!$this->isDataStorageLoaded) $this->initDataStorage();
        $s = & $this->_dataStore;
        if (!$this->_dataReset && $this->resetDataOnFirstLoad) {
            $this->_dataReset = true; $tokenMatch = false;
            if ($this->degradable && !isset($_POST['_e']) && isset($_GET['_e'])) {
                $g = $_GET['_e'];
                $tokenMatch = isset($g['tok']) ? $g['tok']==Raxan::$postBackToken : false;
            }
            $pb = $this->isPostback|$tokenMatch; $lp = $s->read('_rxLastPage_');
            if (!$pb || !$lp || ($pb && $lp != $_SERVER['PHP_SELF'])) {
                if (Raxan::$isDebug) Raxan::debug('Page _reset()');
                if ($this->_reset()) $s->resetStore(); // reset page data store
            }
            $s->write('_rxLastPage_', $_SERVER['PHP_SELF']); // save last page
        }
        if ($value!==null) {
            $sv = $setValueIfNotIsSet;
            if (!$sv || ($sv && !$s->exists($key))) return $s->write($key,$value);
        }
        return $s->read($key);
    }

    /**
     * Returns or sets the page data storage handler
     * @return RaxanDataStorage
     */
    public function dataStorage(RaxanDataStorage $store = null) {
        if ($store===null && !$this->isDataStorageLoaded) $this->initDataStorage();
        else {
            $this->$isDataStorageLoaded = true;
            $this->_dataStore = $store;
        }
        return $this->_dataStore;
    }

    /**
     * Returns RaxanDOMDocument instance
     * @return RaxanDOMDocument
     */
    public function document() {
        return $this->doc;
    }

    /**
     * Ends page event execution and send content to the client. No further events will be processed
     * @return RaxanWebPage
     */
    public function endResponse() {
        $this->_endResponse = true;        
        return $this;
    }

    /**
     * Search the page for matched elements
     * @return RaxanElement
     */
    public function find($css,$context = null){
        $context = $context ? $context : $this->doc;
        return new RaxanElement($css,$context);
    }

    /**
     * Find and returns a RaxanElement by Id
     * @return RaxanElement
     */
    public function findById($id){
        $elm = $this->getElementById($id);
        if (!$elm) $elm = ' ';
        return new RaxanElement($elm);
    }

    /**
     * Find and return matched elements based on specified xpath
     * @return RaxanElement
     */
    public function findByXPath($pth){
        $dl = $this->doc->xQuery($pth);
        if (!$dl) $dl = ' ';
        return new RaxanElement($dl);
    }

    /**
     * Returns a DOMElement based on the Id value
     * @returns DOMElement
     */
    public function getElementById($id){
        $elm = $this->doc->getElementById($id);
        if ($elm) return $elm;
        else {
            $id = preg_replace('/[^a-zA-Z0-9\_\-]/', '\1', $id); // clean target id - prevent xpath injection
            $dl = $this->doc->xQuery('//*[@id=\''.$id.'\'][1]'); // find DOMElement
            return ($dl && $dl->length) ? $dl->item(0) : null;
        }
    }
    
    /**
     * Returns a copy of a reuseable DOM
     * @return DOMDocument
     */
    public function flyDOM() {
        if ($this->_flyDOM) return $this->_flyDOM;
        else {
            $dom = new DOMDocument('1.0',$this->_charset);
            $dom->loadXML(self::pageCode('html',$this->_charset));
            return $this->_flyDOM = $dom;
        }
    }

    /**
     * Handle client Postback/Callback events
     * @return RaxanWebPage
     */
    protected function handleClientEventRequest(&$eventResult){
        $events = & $this->events;
        $e = isset($_POST['_e']) ? $_POST['_e'] : null;
        if (!$e && isset($_GET['_e'])) $e = $_GET['_e'];
        if ($e && $events) {
            $rt = '';
            // get handlers
            $id = isset($e['target']) ? trim($e['target']) : '';    // defaults to nothing. Don't raise an event if a target was not specified
            if (!$id && isset($e['tok'])) { // check for native button clicks
                $names = array_keys($_POST);
                foreach ($names as $name) if (isset($events['button:'.$name])) {
                    list($id,$val) = explode('|',$events['button:'.$name],2);
                    $e['value'] = $val;
                    break;
                }
            }
            $type = isset($e['type']) ? $e['type'] : 'click';
            $tokenMatch = isset($e['tok']) && $e['tok']==Raxan::$postBackToken ? true : false ;
            $hnd = 'e:'.$id.'.'.$type;
            $hndlrs = isset($events[$hnd]) ? $events[$hnd] : null;
            if ($hndlrs) {
                if (Raxan::$isDebug) Raxan::debug('Raise '.htmlspecialchars($type).' Event For '.htmlspecialchars($id));
                // check if delegate
                $delegate = isset($events['selectors'][$id]) ? true : false;
                if($delegate) $e['target'] = null;
                else $e['target'] = $this->getElementById($id); // lookup target element
                if (isset($e['uiSender'])) $e['uiSender'] = $this->getElementById($e['uiSender']);
                if (isset($e['uiHelper'])) $e['uiHelper'] = $this->getElementById($e['uiHelper']);
                if (isset($e['uiDraggable'])) $e['uiDraggable'] = $this->getElementById($e['uiDraggable']);
                $e = new RaxanWebPageEvent($type,$this,$e); // convert data from client to event object
                foreach ($hndlrs as $hnd) {
                    if ($hnd[2] || $tokenMatch) { // only invoke if global access or token matched
                        if ($hnd[0]!==null && !$this->triggerHandle($hnd, $e)) break;
                    }
                }
                $eventResult = $e->result;
            }
        }
        return $this;
    }

    /**
     * Localize nodes with the langid attribute set to a valid lcoale key/value pair
     * @return RaxanWebPage
     */
    protected function handleNodeL10n(){
        if (!$this->localizeOnResponse) return true;
        $nl = $this->doc->xQuery('//*[@langid]');
        self::nodeL10n($nl);
        return $this;
    }

    /**
     * Send debugging information to client
     * @return RaxanWebPage
     */
    protected function handleDebugResponse(){
        $d = Raxan::debugOutut();
        $o = Raxan::config('debug.output');
        if ($o=='popup'||$o=='embedded')
            $d = '<pre><code><strong>Debug Output</strong><hr />'.$d.'</code></pre>';
        if ($o=='alert') C()->alert($d);
        else if ($o=='popup') C()->popup('','rxPDI_Debug','width=500, scrollbars=yes')->html($d);
        else if ($o=='console') C()->evaluate('(window.console)? window.console.log("'.Raxan::escapeText($d).'"):""');
        else if ($o=='embedded') {
            if ($this->isCallback) C('#rxPDI_Debug')->remove(); // remove if in callback
            C('body')->append('<div id="rxPDI_Debug" style="padding:5px;border:3px solid #ccc;background:#fff"></div>');
            C('#rxPDI_Debug')->html($d);
        }
        return $this;
    }


     /**
     * Inserts JavaScript into the html document
     * @return RaxanWebPage
      */
    protected function handleScripts() {
        $charset = $this->_charset;
        $inc = $raxan = $css = $js = '';
        $actions = $this->buildActionScripts();
        // build scripts
        $url = Raxan::config('raxan.url');
        foreach($this->_scripts as $s=>$i) {
            $tag = substr($s,0,4); $s = substr($s,4);
            if ($tag=='JSI:'){
                if ($i===1) $js.= $s."\n";
                else $inc.= 'h.include("'.$s.'"'.($i ? ',true':'').');';
            }
            elseif ($tag=='CSS:') {
                if ($i===1) $css.=$s;
                else $css.= '<link href="'.($i ? $s : $url.'styles/'.$s.'.css').'" type="text/css" rel="stylesheet" />'."\n";
                if ($s=='master') $css.='<!--[if IE]><link href="'.$url.'styles/master.ie.css" type="text/css" rel="stylesheet" /><![endif]-->'."\n";
            }
        }
        $sb = $this->scriptBehind;
        if ($sb || $inc || $actions || $this->initStartupScript || $this->isEmbedded) {
            $raxan = '<script type="text/javascript" src="'.$url.'startup.js">'.$sb.'</script>'."\n";
            if ($inc || $actions) {
                $actions = iconv($charset,$charset.'//IGNORE',preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S','',$actions)); // clean up encoding and remove ascii ctrl chars
                $inc =  '<script type="text/javascript"><![CDATA[ var _PDI_URL ="'.Raxan::escapeText($this->clientPostbackUrl).'",h=Raxan;'.$inc.$actions." ]]></script>\n";
            }
        }
        $inc = $css.$raxan.$inc.$js;
        if ($inc) {
            $hd = $this->findByXPath('/html/head');
            if ($hd->length) $hd->append($inc); // check for <head> tag. add if necessary
            else $this->findByXPath('/html/body')->before('<head>'.$inc.'</head>'); 
        }

        return $this;
    }

    /**
     *  Halt and exit page processing while displaying a message
     */
    public function halt($msg = null) {
        echo $msg; exit();
    }

    /**
     *  Add CSS stylesheet to document
     * @return RaxanWebPage
     */
    public function loadCSS($css,$extrn = false) {
        $css = trim($css);
        $embed = (stripos($css,'<')!==false);
        if (!isset($this->_scripts['CSS:'.$css])) {
            $this->_scripts['CSS:'.$css] = $embed ? 1 : $extrn;
        }
        return $this;
    }

    /**
     *  Add Javascript to document
     * @return RaxanWebPage
     */
    public function loadScript($js,$extrn = false, $_priority = 0) {
        $js = trim($js);
        $s = & $this->_scripts;
        if (isset($s['RegJS:'.$js])) { // check if script was registered
            $js = $s['RegJS:'.$js];
            if ($js===tue) return; // script manually loaded by user
            $extrn = true;
        }
        $embed = (stripos($js,'<script')!==false);
        if (!isset($s['JSI:'.$js])) {
            $state = $embed ? 1 : $extrn;
            if ($_priority==1) $this->_scripts = array('JSI:'.$js=> $state) + $s;
            else $this->_scripts['JSI:'.$js] = $state;
        }
        return $this;
    }

    /**
     * Loads JavaScript Code behind file
     * @return RaxanWebPage
     */
    public function loadScriptBehind($pth = null) {
        if ($pth===null) $pth = '-';
        else if ($pth && substr($pth,-1)=='/') $pth.='-';
        $this->scriptBehind  =  '/'.$pth.'/';
        return $this;
    }

    /**
     * Prepends the specified html content to the page master content block element
     * @return RaxanWebPage
     */
    public function prepend($html) {
        $s = $this->masterContentSelecor();
        $this->find($s)->prepend($html);
        return $this;
    }
    
    /**
     * Implement state management for the sepcified RaxanElement.
     * This will trigger callback the element when the state is to be saved.
     * @return RaxanWebPage
     */
    public function preserveElementState(RaxanElement $rElm) {
        if (!isset($this->_stateObjects)) $this->_stateObjects = array();
        $this->_stateObjects[] = $rElm;
        return $this;
    }
    
    /**
     * Redirect client to the specified url
     */
    public function redirectTo($url){
        header('Location: '.$url);
        exit();
    }
    
    /**
     * Registers a event to be triggered from the client using page as the target id
     * Example: $trigger('page','buttonClick');
     * @return RaxanWebPage
     */
    public function registerEvent($type,$data,$fn = null){
        $elms = array();
        $this->bindElements($elms, $type, $data, $fn, 'page',true);
        return $this;
    }

    /**
     * Registers a UI Element with the current web page.
     * @param RaxanUIElement $ui
     * @return RaxanWebPage
     */
    public function registerUIElement(RaxanUIElement $ui) {
        if (!isset($this->_uiElms)) $this->_uiElms = array();
        $this->_uiElms[$ui->objectId()] = $ui;
        if ($this->isLoaded) $ui->loadUI();
        return $this;
    }

    /**
     * Registers a javascript file by name
     * Use the loadScript() method to load the script by name.
     * @param $name - Name of script
     * @param $url - Url for the script. Set to True to is the script was loaded manually.
     * @return RaxanWebPage
     */
    public function registerScript($name,$url){
        $this->_scripts['RegJS:'.$name] = $url;
        return $this;
    }

    /**
     * Render and return html content
     * @return String     
     */
    public function render($type = 'html') {
        $this->pageOutput = $result = '';
        if (!$this->_endResponse) {
            $this->handleClientEventRequest($result); // handle client events request
        }

        // find auto updat elements
        $auto = $this->findByXPath('//*[@xt-autoupdate]');
        if ($auto->length){
            $auto->autoId()->removeAttr('xt-autoupdate');
            if ($this->isCallback) $auto->updateClient();
        }

        // respond to ajax callback
        if ($this->isCallback) {
            $charset = $this->_charset; $rt = '';
            if (Raxan::$isDebug) {
                Raxan::debug('Handle Ajax Response');
                $this->handleDebugResponse();
            }
            $this->handleNodeL10n();    // proccess tags with langid
            $a = $this->buildActionScripts(false); // exclude events from actions
            $a = iconv($charset,$charset.'//IGNORE',$a);  // clean up action scripts charset encoding
            if (isset($result)) $rt = is_string($rt) ?  iconv($charset,$charset.'//IGNORE',$result) : '';
            $json =  Raxan::JSON('encode',array( '_result' => $rt, '_actions' => $a ));
            if ($_POST['_ajax_call_']=='iframe') {
                $json = '<form><textarea>'.htmlspecialchars($json).'</textarea></form>';
                $html = self::pageCode('html',$this->_charset,$json);
            }
            $this->pageOutput = $json;
        }
        // response to standard postback
        elseif (!$this->isCallback) {
            if (!$this->_endResponse) {
                if (Raxan::$isDebug) Raxan::debug('Page _prerender()');
                $this->_prerender();     // call _prerender event and render registered ui elements
                $this->renderUIElements();
                Raxan::triggerSysEvent('page_prerender',$this);
            }
            if (Raxan::$isDebug) {
                Raxan::debug('Handle Full Page Response');
                $this->handleDebugResponse();
            }
            $this->handleScripts();      // build scripts
            $this->handleNodeL10n();    // proccess tags with langid
            // check if is embedded
            if (!$this->isEmbedded) $this->pageOutput = $this->doc->source(null,$type);
            else {
                // handle embedded mode  
                $opt = $this->embedOptions;
                $noxjs = stripos($opt,'noxjs')!==false;
                $noxcss = stripos($opt,'noxcss')!==false;
                $tags= '//title | //base | //meta ';
                if ($noxcss) $tags.='|//link';           // no external css
                if ($noxjs) $tags.='|//script[@src]';;   // no external js
                $this->findByXPath($tags)->remove();
                $rx = '#<(\!doctype|html|head|body)[^>]*>|</(html|head|body)>#is';  // remove tags
                $h = trim(preg_replace($rx,'',$this->doc->source(null,$type)));
                // handle js embeds
                $h = substr(str_replace('<script','<\script',Raxan::JSON('encode', $h)),1,-1);
                if ($noxjs) $h = 'document.write("'.$h.'");';
                else {
                    $a = explode('<\/script>',$h,2);
                    $h = 'if (!self.RaxanPreInit) RaxanPreInit = [];';
                    $h.= 'RaxanPreInit[RaxanPreInit.length] = function(){ document.write("'.$a[1].'") }; ';
                    $h.= 'if (!self.Raxan) document.write("'.$a[0].'<\/script>");'; // load the raxan startup script
                    $h.= 'else RaxanPreInit[RaxanPreInit.length-1]();';
                }
                $this->pageOutput = $h;
            }
            if (!$this->_endResponse) {
                $this->_postrender();        // call _postrender event
                Raxan::triggerSysEvent('page_postrender',$this);
            }
        }
        
        // save element state
        if (isset($this->_stateObjects))  {
            foreach($this->_stateObjects as $r) $r->handleSaveState();         
        }

        // return html or json string
        return $this->pageOutput;
    }
    
    /**
     * Sends data to client
     * @return RaxanWebPage
     */
    public function reply($responseType = null) {
        
        $this->_startReply = true;
        
        $rt = $responseType;
        if ($rt===null) $rt = $this->responseType;
        else $this->responseType = $rt;

        if ($rt=='xhtml/html') {
            $rt = (isset($_SERVER['HTTP_ACCEPT']) &&
                strpos( $_SERVER['HTTP_ACCEPT'], "application/xhtml+xml" )) ? 'xhtml': 'html';
        }

        switch ($rt) {
            case 'wml':
            case 'xml':
            case 'xhtml':
                $content = $this->render('xml');
                $ctype = 'text/xml';
                if ($rt=='wml') $ctype = 'text/vnd.wap.wml';
                else if ($rt=='xhtml') {
                    $ctype = 'application/xhtml+xml';
                    $content = preg_replace('/&#13;|&#xD;/','',$content);
                }
                if (!$this->_contentType) $this->contentType($ctype);
                break;
            default: //html
                $content = $this->render('html');
                if (!$this->_contentType) $this->contentType( $this->isEmbedded ? 'text/javascript' : 'text/html');
                if ($rt=='html') { // change existing doctype to HTML5 Doctype
                    $content = preg_replace('/<!DOCTYPE[^>]*>/si',"<!DOCTYPE html >",$content,1);
                }
                break;
        }

        // send headers
        header($this->_contentType); 
        if ($this->_headers) foreach($this->_headers as $s) @header($s);
        
        // send content to client (eg. html, xml, json, etc)
        echo $content;
        if (Raxan::$isDebug) Raxan::debug('Page _reply()');
        if (!$this->_endResponse) {
            $this->_reply(); // call _reply event
            Raxan::triggerSysEvent('page_reply', $this);
        }
        
        if ($this->showRenderTime && !$this->isCallback)
            echo '<div>Render Time: '.Raxan::stopTimer($this->_startTime).'</div>';

        return $this;
    }

    /**
     * Remove Page data
     * @return RaxanWebPage
     */
    public function removeData($name = null){
        if (!$this->isDataStorageLoaded) $this->initDataStorage();
        $this->_dataStore->remove($name);
        return $this;
    }

    /**
     * Returns data sanitizer object with get and/or post values. Defaults to post values
     * @return RaxanDataSanitizer
     */
    public function sanitizePostBack($incGETRequest = false) {
        $array = $incGETRequest ? array_merge($_GET,$_POST): null;
        if (!isset($this->_postRqst)) $this->_postRqst = Raxan::dataSanitizer($array,$this->_charset);
        return $this->_postRqst;
    }

    /**
     * Sends an error page or message to the web browser and stop page processing.
     * @return void
     */
    public function sendError($msg, $code = null) {
        Raxan::sendError($msg, $code);
    }

    /**
     * Set or returns the page html/xml source. Source can be a file, url or <html> tags
     * @return RaxanWebPage or String
     */
    public function source($src = null,$srcType = 'html') {
        
        if ($src===null) return $this->doc->source(null,$srcType);
        else if ($this->masterTemplate) { // set master template
            $tpl = file_get_contents($this->masterTemplate);
            $this->doc->source($tpl,$srcType);
            $this->content($src);
        }
        else {
            if ($src=='wml:page') { // set page source
                $src = self::pageCode('wml');
                $this->responseType = 'wml'; $srcType = 'xml';
            }
            elseif (!$src || $src=='html:page') {
                $src = self::pageCode('html');
                $this->responseType = $srcType = 'html';
            }
            else if ($srcType!='xml' && $srcType!='html')
                $srcType = ($src && substr($src,-4)=='.xml') ? 'xml' : 'html';

            $this->doc->source($src,$srcType);
        }

        return $this;

    }

    /**
     * Set or returns the data store name for the page
     * @return RaxanWebPage or String
     */
    public function storeName($n = null) {
        if ($n===null) return $this->_storeName;
        else $this->_storeName = $n;
        return $this;
    }

    /**
     * Change switchboard action and reloads the current page
     */
    public function switchTo($action){
        $url = Raxan::currentURL();
        if (strpos($url,'sba=')!==false) $url = trim(preg_replace('#sba=[^&]*#','',$url),"&?\n\r ");
        $url.= (strpos($url,'?') ? '&' : '?').'sba='.$action;
        $this->redirectTo($url);
    }

    /**
     * Transfer page control to the specified php file
     */
    public function transferTo($file){
        include_once($file);
        exit();
    }

    /**
     * Trigger events on the specified elements - used by RaxanElement
     * @return RaxanWebPage
     */
    public function triggerEvent(&$elms,$type,$args = null,$eOpt = null) {
        $events = & $this->events; $e = null;
        foreach($elms as $n) {
            $id = $n->getAttribute('id');
            $hnd = 'e:'.$id.'.'.$type; $lhnd = 'l:'.$id.'.'.$type;
            $hndlrs = isset($events[$hnd]) ? $events[$hnd] : array();
            $hndlrs = isset($events[$lhnd]) ? array_merge($hndlrs,$events[$lhnd]) : $hndlrs ; // merge local events handlers
            if ($hndlrs) {
                if (!$e) $e = new RaxanWebPageEvent($type,$this,$eOpt); // convert to object
                foreach ($hndlrs as $hnd) {
                    if (!$this->triggerHandle($hnd, $e,$args)) break;
                }
            }
        }
        return $this;
    }

    /**
     * Returns or sets the title for an HTML page
     * @return RaxanWebPage or String
     */
    public function title($str = null) {
        if (!$this->doc->isInit()) $this->doc->initDOMDocument();
        $title = $this->doc->getElementsByTagName('title');
        if ($str!==null && $title->length) $title->item(0)->nodeValue = (String)$str;
        else if ($str===null && $title->length) return $title->item(0)->nodeValue;
        return $this;
    }

    /**
     * Update form fields with postback values
     * @return RaxanWebPage
     */
    public function updateFields() {
        if ($this->isPostback)
            $this->find('form [name]')->val($_POST);
        return $this;
    }

    /**
     * Remove event handlers from elements - used by RaxanElement
     * @return RaxanWebPage
     */
    public function unbindElements(&$elms,$type,$selector) {
        if ($this->events) {
            $e = & $this->events; $ss = isset($e['selectors']);
            $type = str_replace('@local','',$type);
            $type = str_replace('#','',trim($type));
            if ($ss && $selector) unset($e['selectors'][$selector]);
            foreach($elms as $n) {
                $id = $n->getAttribute('id');
                if ($id) {
                    $id = $id.'.'.$type;
                    unset($e[$id]); unset($e['l.'.$id]); // remove local and remote
                    if ($ss) unset($e['selectors']['#'.$id]);
                }
            }
        }
        return $this;
    }


    // Array Access Methods - support for $page['selector']->html()
    // -----------------------
    
    /**
     * Check is element exists for the specified selector
     * @return Boolean
     */
    public function offsetExists($selector) {return $this->find($selector)->length>0; }
    /**
     * Sets HTML for selected elements
     * @return RaxanElement
     */
    public function offsetSet( $selector, $html) { return $this->find($selector)->html($html); }
    /**
     * Return selectd elements
     * @return RaxanElement
     */
    public function offsetGet( $selector ) { return $this->find($selector); }
    /**
     * Remove selectd elements
     * @return RaxanWebPage
     */
    public function offsetUnset( $selector ) {$this->find($selector)->remove(); return $this; }



    // Protected Functions
    // -----------------------

    /**
     * Initialize page data storage handler
     * @return RaxanWebPage
     */
    protected function initDataStorage() {
        $cls = Raxan::config('page.data.storage');
        $id = 'pdiWPage-'.(($id = $this->_storeName) ? $id : $this->objId); // setup store id
        $this->_dataStore = new $cls($id);
        $this->isDataStorageLoaded = true;
        return $this;
    }

     /**
     * Returns string containing action scripts and client-side event bindings
     * @return String
      */
    protected function buildActionScripts($includeEvents = true) {
        // build event scripts
        $actions = $binds = '';
        if ($includeEvents) {
            if (isset($this->events['selectors'])) {
                $sels = $this->events['selectors'];
                foreach($sels as $sel=>$opts) {
                    // no need to bind page registered events on client
                    if ($sel!='page') foreach ($opts as $opt) {
                        $de = $opt['delegate'];
                        $x = ($de ? ',true' : '');
                        // setup extended options
                        if ($opt['_extendedOpt'] || is_string($de)) {
                            $x = array();
                            if ($opt['delegate']) $x[] = 'dt:'.($de!==true  ? '"'.Raxan::escapeText($de).'"' : 'true');  // delegate
                            if ($opt['delay']) $x[] = 'dl:\''.$opt['delay'].'\'';
                            if ($opt['autoDisable']) $x[] = 'ad:\''.$opt['autoDisable'].'\'';
                            if ($opt['autoToggle']) $x[] = 'at:\''.$opt['autoToggle'].'\'';
                            if ($opt['inputCache']) $x[] = 'ic:\''.$opt['inputCache'].'\'';
                            if ($opt['switchTo']) $x[] = 'sba:\''.$opt['switchTo'].'\'';
                            if ($opt['repeat']) $x[] = 'rpt:'.($opt['repeat']===true ? 'true' : (int)$opt['repeat']).'';
                            $x = ',{'.implode(',',$x).'}';
                        }
                        $script = is_array($opt['script']) ? Raxan::JSON('encode',$opt['script']): '"'.Raxan::escapeText($opt['script']).'"';
                        $binds.='$bind("'.$sel.'","'.
                            $opt['type'].'","'.
                            Raxan::escapeText($opt['value']).'","'.
                            Raxan::escapeText($opt['serialize']).'","'.
                            $opt['ptarget'].'",'.
                            $script.
                            $x.');';
                    }
                }
            }
        }
        $vars = implode(',',RaxanWebPage::$vars);
        $varsGlobal = implode(',',RaxanWebPage::$varsGlobal);
        if ($vars) $vars = 'var '.$vars.';';
        if ($varsGlobal) $vars.= $varsGlobal.';';
        if (PHP_VERSION_ID < 50200) {   // fix for issue #4
            foreach(RaxanWebPage::$actions as $i=>$act)
                if ($act) RaxanWebPage::$actions[$i] = $act->__toString();
        }
        if ($binds) RaxanWebPage::$actions[] = $binds; // add bindings to the end of the actions script queue
        $actions = $vars.implode(';',RaxanWebPage::$actions);
        if (!$includeEvents) return $actions;
        else if ($actions){
            // check if we should load jquery
            if (!isset($this->_scripts['JSI:jquery'])) {
                $this->loadScript('jquery',false,1);
            }
            $actions = 'html.ready(function() {'.$actions.'});';
        }
        return $actions;
    }

    /**
     * Returns the selector for the master content block
     * @return string
     */
    protected function masterContentSelecor() {
        $s = $this->masterContentBlock;
        $t =  $this->responseType=='wml' ? 'card:first' : 'body';
        return ($s && $this->masterTemplate) ?  $s : $t;
    }

    /**
     * Load regsitered UI Elements
     * @return RaxanWebPage
     */
    protected function loadUIElements() {
        if (isset($this->_uiElms))
            foreach($this->_uiElms as $ui) $ui->loadUI();
         return $this;
    }

    /**
     * Render regsitered UI Elements
     * @return RaxanWebPage
     */
    protected function renderUIElements() {
        if (isset($this->_uiElms))
            foreach($this->_uiElms as $ui) $ui->renderUI();
         return $this;
    }

    /**
     * Triggers event callback handler
     * @return Boolean - Returns true if not $e->isStopPropagation
     */
    protected function triggerHandle($hnd,$e,$args = null) {
        $fn = $hnd[0]; $data = $hnd[1];
        $e->data = $data;
        if (!is_callable($fn)) Raxan::throwCallbackException($fn);
        elseif (is_array($fn)) $rt = $fn[0]->{$fn[1]}($e,$args);       // object callback
        else  $rt = $fn($e,$args);  // function callback (string or anonymous function)
        if ($rt!==null) $e->result = $rt;
        if (!$e->isStopPropagation) return true;
        else return false;
    }


    // Static Functions
    // -----------------------

    /**
     * Adds a custom method to the RaxanWebPage Class. Use addMethod($object) to add multiple methods from an object
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
     * Creates and return a DOMDocument
     * @return RaxanDOMDocument
     */
    public static function createDOM($html,$charset = 'UTF-8',$type = null) {
        $dom = new RaxanDOMDocument('1.0',$charset);
        $dom->formatOutput = false;
        if ($html!==null) {  // don't set source if html is null. See __construct
            if ($type!='xml' && $type!='html')
                $type = ($html && substr($html,-4)=='.xml') ? 'xml' : 'html';
            $html = $html ? $html : self::pageCode();
            $dom->source($html,$type);
        }
        return $dom;
    } 

    /**
     * Returns the Page controller object
     * @return RaxanWebPage
     */
    public static function controller($page = null) {
        if ($page && ($page instanceof RaxanWebPage)) self::$mPage = $page;
        else if (self::$mPage==null) self::$mPage = new RaxanWebPage();
        return self::$mPage;
    }

    /**
     * Creates and returns an instance of the web page class
     * @return RaxanWebPage
     */
    public static function init($pageClass) {
        if (class_exists($pageClass)) {
            $page = new $pageClass();
            return $page->reply();
        }
        else{
            trigger_error('Class \''.$pageClass.'\' not found',E_USER_WARNING);
        }
    }

    /**
     * Loads the RaxanClientExtension class
     */
    public static function loadClientExtension() {
        if (self::$cliExtLoaded) return;
        require_once(Raxan::config('base.path').'shared/raxan.clientextension.php');
        self::$cliExtLoaded = true;
    }

    /**
     * Localize nodes
     */
    public static function nodeL10n($nodes){
        if ($nodes) foreach ($nodes as $n) {
            $id = $n->getAttribute('langid');
            $v = Raxan::locale($id);
            if ($v) {
                $n->nodeValue = $v;
                $n->removeAttribute('langid');
            }
        }
    }

    /**
     * Returns blank html page template 
     * @return String
     */
    protected static function pageCode($type = 'html',$charset='UTF-8',$content = ''){
        $nl = "\n";
        if ($type=='wml') return '<?xml version="1.0" standalone="yes"?>'.$nl.
            '<!DOCTYPE wml PUBLIC "-//WAPFORUM//DTD WML 1.1//EN" "http://www.wapforum.org/DTD/wml_1.1.xml">'.$nl.
            '<wml><card>'.$content.'</card></wml>';
        else return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'.$nl.
            '<html>'.$nl.'<head>'.$nl.'<meta http-equiv="Content-Type" content="text/html; charset='.$charset.'" />'.$nl.
            '<title></title>'.$nl.'</head>'.$nl.'<body>'.$content.'</body>'.$nl.'</html>';
    }


}

/**
 * Raxan Web Page Storage
 */
class RaxanWebPageStorage extends RaxanDataStorage {

    protected function _init() {
        $this->store = & Raxan::data($this->id);
    }
    protected function _reset() {
        Raxan::removeData($this->id);
        $this->_init();
    }
}

/**
 *  Used internally by RaxanWebPage
 */
class RaxanWebPageEvent extends RaxanSysEvent{

    /**
     * @var $target DOMElement */
    public $target;
    public $uiDraggable,$uiHelper,$uiSender;

    public $value, $button;
    public $pageX, $pageY;
    public $targetX, $targetY;
    public $which, $ctrlKey, $metaKey;

    /**
     *  @var $target RaxanWebPage */
    protected $iPage;    

    public function __construct($type, &$page, $e = null) {
        parent::__construct($type);
        $this->iPage = $page;
        if ($e) {
            $props = explode(' ','data value target button pageX pageY targetX targetY which ctrlKey metaKey uiDraggable uiHelper uiSender');
            foreach($props as $prop) {
                if (isset($e[$prop])) $this->{$prop} = $e[$prop];
            }
        }
    }

    /**
     * Returns an instance of the RaxanClientExtension class
     * @return RaxanClientExtension
     */
    public function client($selector = null, $context = null) {
        RaxanWebPage::loadClientExtension();
        return new RaxanClientExtension($selector,$context);
    }

    /**
     * Returns an instance of the web page controller that's associated with this event
     * @return  RaxanWebPage
     */
    public function page($css = null) {
        if ($css) return  $this->iPage[$css];
        else return  $this->iPage;
    }

    /**
     * Returns the event callback value
     * @return mixed
     */
    public function val() {
        return $this->value;
    }

    /**
     * Returns the non-html text from the callback value
     * @return string
     */
    public function textval() {
        return strip_tags($this->value);
    }

    /**
     * Returns an integer from the callback value
     * @return string
     */
    public function intval() {
        return intval($this->value);
    }

    /**
     * Returns a float from the callback value 
     * @return string
     */
    public function floatval() {
        return floatval($this->value);
    }

}

/**
 * Used internally by RaxanWebPage */
class RaxanDOMDocument extends DOMDocument {

    public $charset;
    public $page;   // Reference to Raxan Web Page

    protected $css; // css: cache array for xpath queries
    protected $xPath, $source, $srcType, $init;
    protected $cssRegEx;

    public function __construct($v = '1.0',$charset='UTF-8'){
        $this->charset = $charset;
        $this->init = false;
        // $this->formatOutput = true;

        // setup css to xpath regex
        $rx = array();
        $rx['element']         = "/^([#.]?)([a-z0-9\\*_-]*)((\|)([a-z0-9\\*_-]*))?/i";
        $rx['attr1']           = "/^\[([^\]]*)\]/i";
        $rx['attr2']           = '/^\[\s*([^~\*\!\^\$=\s]+)\s*([~\*\^\!\$]?=)\s*"([^"]+)"\s*\]/i';
        $rx['attrN']           = "/^:not\((.*?)\)/i";
        $rx['psuedo']          = "/^:([a-z_-])+/i";    // empty, even, odd
        $rx['not']             = "/^:not\((.*?)\)/i";
        $rx['contains']        = "/^:contains\((.*?)\)/i";
        $rx['gtlt']            = "/^:([g|l])t\(([0-9])\)/i";
        $rx['last']            = "/^:(last\([-]([0-9]+)\)|last)/i";
        $rx['first']           = "/^:(first\([+]([0-9]+)\)|first)/i";
        $rx['psuedoN']         = "/^:nth-child\(([0-9])\)/i";
        $rx['combinator']      = "/^(\s*[>+\s])?/i";
        $rx['comma']           = "/^\s*,/i";
        $this->cssRegEx  = $rx;

        parent::__construct($v,$charset);
    }

    /**
     * Return DOMNodeList query dom based on XPath
     * @return DOMNodeList
     */
    public function xQuery($pth, $context = null) {
        if (!$this->init) $this->initDOMDocument(); // init dom on first query
        if (!$context) $dl = @$this->xPath->query($pth);
        else {
            if (substr($pth,0,1)=='/') $pth = '.'.$pth;
            $dl = @$this->xPath->query($pth, $context);
        }
        if ((Raxan::$isDebug)) {
            if (!$dl) Raxan::debug('xQuery Error: Invalid XPath Expression: '.$pth);
            else Raxan::debug("XPath: \t Found ".$dl->length." \t $pth ");
        }
        return $dl;
    }

    /**
     * Return DOMNodeList query dom based on CSS Selector
     * @return DOMNodeList
     */
    public function cssQuery($rule, $context = null, $includeSelf = false) {
        if(!$rule) return null;
        $cache = $rule.$includeSelf;
        $this->css = $this->css ? $this->css : array();
        if (isset($this->css[$cache])) $x = $this->css[$cache];
        else $x = $this->css[$cache] = $this->cssToXPath($rule,$includeSelf);
        return $this->xQuery($x,$context);
    }

    /**
     *  Sets HTML/XML source - this is loaded only when dom is first queried
     */
    public function source($src = null,$srcType = 'html') {
        if ($src!==null) {
            $this->source = $src;
            $this->srcType = $srcType;
            if ($this->init) $this->initDOMDocument(); //reload DOM if already init
            return $this;
        }
        else {
            if ($this->init) return ($srcType=='xml') ? $this->saveXML() : $this->saveHTML();
            else {
                $s = $this->source;
                if (substr(trim($s),0,1)=='<') return $s;
                else return @file_get_contents($s);
            }
        }
    }

    /**
     * Returns true if DOM source was loaded.
     * @return Boolean
     */
    public function isInit() {
        return $this->init;
    }

    /**
     * Initialize DOM source
     */
    public function initDOMDocument() {
        $isFile = false;
        $s = trim($this->source); $stype = $this->srcType;
        if (($p = substr($s,0,7))=='http://' || $p=='https:/') $s = file_get_contents($s);
        else $isFile = (substr($s,0,1)=='<') ? false: true;
        if ($stype=='xml') $s = (!$isFile) ? @$this->loadXML($s) : @$this->load($s);
        else $s = (!$isFile) ?  @$this->loadHTML($s) : @$this->loadHTMLFile($s);
        $this->xPath = new DOMXPath($this);
        $this->init = true;
    }

    // Protected Methods
    // --------------------------

    /**
     * CSS to Xpath - http://www.webdesignerforum.co.uk/index.php?showtopic=2325
     * Mod by rayond 10-dec-2008. fixed: last, first, added not */
    protected function cssToXPath($rule, $inludeSelf = false) {
        $index = 1;
        $start = $inludeSelf ? 'descendant-or-self::' : '//';
        $parts = array($start);
        $lastRule = NULL;
        $reg = $this->cssRegEx;

        while( strlen($rule) > 0 && $rule != $lastRule ) {
            $lastRule = $rule;
            $rule = trim($rule);
            if( strlen($rule) > 0) {
                // Match the Element identifier
                $a = preg_match( $reg['element'], $rule, $m );
                if ($a) {
                    if ( !isset($m[1]) ) {
                        if ( isset( $m[5] ) ) {
                            $parts[$index] = $m[5];
                        } else {
                            $parts[$index] = $m[2];
                        }  
                    }
                    else if( $m[1] == '#') {
                        $parts[] = "[@id='".$m[2]."'][1]";
                    } else if ( $m[1] == '.' ) {
                        $parts[] = "[contains(concat(' ',@class,' '), concat(' ','".$m[2]."',' '))]";
                    }else {
                        $parts[] = $m[0];
                    }
                    $rule = substr($rule, strlen($m[0]) );
                }

                // Match attribute selectors.
                $a = preg_match( $reg['attr2'], $rule, $m );
                if( $a ) {
                    if( $m[2] == "!=" ) $parts[] = "[@".$m[1]."!='".$m[3]."']";
                    else if( $m[2] == "^=" ) $parts[] = "[starts-with(@".$m[1].",'".$m[3]."')]";
                    else if( $m[2] == "$=" ) $parts[] = "[substring(@".$m[1].", string-length(@".$m[1].") - string-length('".$m[3]."') + 1) = '".$m[3]."']";
                    else if( $m[2] == "*=" ) $parts[] = "[contains(@".$m[1].", '".$m[3]."')]";
                    else if( $m[2] == "~=" ) {   // case insentive look up
                        $t = strtolower($m[3]);
                        $trans = "translate(@".$m[1].",'ABDCEFGHIJKLMNOPQRSTUVWXYZ','abdcefghijklmnopqrstuvwxyz')";
                        $parts[] =  "[contains(".$trans.",'".$t."')]" ;
                    }
                    else {
                        $parts[] = "[@".$m[1]."='".$m[3]."']";
                    }
                    $rule = substr($rule, strlen($m[0]) );
                } else {
                    $a = preg_match( $reg['attr1'], $rule, $m );
                    if( $a ) {
                        $parts[] = "[@".$m[1]."]";
                        $rule = substr($rule, strlen($m[0]) );
                    }
                }

                // register not
                $a = preg_match( $reg['not'], $rule, $m );
                if( $a ) {
                    $parts[] = "[not(".$this->cssToXPath($m[1],true).")]" ;
                    $rule = substr($rule, strlen($m[0]));
                }

                // register contains
                $a = preg_match( $reg['contains'], $rule, $m );
                if( $a ) {
                    $parts[] = "[contains(.,\"".str_replace('"','',$m[1])."\")]" ;
                    $rule = substr($rule, strlen($m[0]));
                }

                // register nth-child
                $a = preg_match( $reg['psuedoN'], $rule, $m );
                if( $a ) {
                    $parts[] = "[".$m[1]."]";
                    $rule = substr($rule, strlen($m[0]));
                }

                // gt and lt commands
                $a = preg_match( $reg['gtlt'], $rule, $m );
                if( $a ) {
                    if( $m[1] == "g" ) {
                        $c = ">";
                    } else {
                        $c = "<";
                    }
                    $parts[] = "[position()".$c.$m[2]."]";
                    $rule = substr($rule, strlen($m[0]));
                }

                // last and last(-n) command
                $a = preg_match( $reg['last'], $rule, $m );
                if( $a ) {
                    if( isset( $m[2] ) ) $m[2] = "-".$m[2]; // mod by raymond to fix last selector
                    else $m[2] = '';
                    $parts[] = "[last()".$m[2]."]";
                    $rule = substr($rule, strlen($m[0]));
                }

                // first and first(+n) command
                $a = preg_match( $reg['first'], $rule, $m );
                if( $a ) {
                    $n = 1;
                    if( isset( $m[2] ) ) {
                        $n+= (int)$m[2];
                    }
                    $parts[] = "[$n]";
                    $rule = substr($rule, strlen($m[0]));
                }


                // loop through and skip over unused psuedo classes and psuedo elements
                $a = preg_match( $reg['psuedo'], $rule, $m );
                while( $m ) { // loop???
                    if ($m[0]==':odd') $parts[] = '[position() mod 2 = 1]';
                    elseif ($m[0]==':even') $parts[] = '[position() mod 2 = 0]';
                    elseif ($m[0]==':empty') $parts[] = '[not(* or text())]';
                    $rule = substr( $rule, strlen( $m[0]) );
                    $a = preg_match( $reg['psuedo'], $rule, $m );
                }

                // Match combinators
                $a = preg_match( $reg['combinator'], $rule, $m );
                if( $a && strlen($m[0]) > 0 ) {
                    if( strpos($m[0], ">") ) {
                        $parts[] = "/";
                    } else if( strpos( $m[0], "+") ) {
                        $parts[] = "/following-sibling::";
                    } else {
                        $parts[] = "//";
                    }

                    $index = count($parts);
                    //$parts[] = "*";
                    $rule = substr( $rule, strlen( $m[0] ) );
                }

                $a = preg_match( $reg['comma'], $rule, $m );
                if( $a ) {
                    array_push( $parts, " | ", $start );
                    $index = count($parts) -1;
                    $rule = substr( $rule, strlen($m[0]) );
                }
            }
        }
        $xpath = implode("",$parts);
        $xpath = str_replace('::[','::*[',$xpath);
        // @todo: Optimize fix for #id bug that returns //[@id=value]
        return str_replace('//[','//*[',$xpath); // quick fix for  #id
    }

}

?>