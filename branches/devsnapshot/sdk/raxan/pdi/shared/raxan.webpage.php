<?php
/**
 * Raxan Web Page Classes
 * Copyright (c) 2008-2010 Raymond Irving (http://raxanpdi.com)
 * @package Raxan
 */


/**
 * Raxan Web Page query shortcut
 * @param mixed $selector CSS selector, html, DOMNode
 * @param DOMNode $content
 * @return RaxanElement
 */
function p($selector = '', $context = null){
    return RaxanWebPage::controller()->find($selector,$context);
}

/**
 * Raxan Client Extention Wrapper
 * @param mixed $selector CSS selector, html
 * @return RaxanClientExtension
 */
function c($selector = '', $context = null){
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
 * Creates a reference to the client-side event callback wrapper function. Used to trigger an event callback to the server from the client.
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
 * @see RaxanWebPage::registerVar()
 * @return RaxanClientVariable
 */
function _var($str, $name = null, $registerGlobal = false){
    RaxanWebPage::loadClientExtension();
    $v = new RaxanClientVariable($str, $name, false,$registerGlobal);
    return $v;
}

/**
 * Raxan Web Page Controller Base Class
 * This class is used to traverse and manipulate HTML DOM elements
 * @property Raxan $Raxan Instance of Raxan main class
 * @property RaxanDOMDocument $doc
 * @property RaxanDataSanitizer $post Sanitized Post Request
 * @property RaxanDataSanitizer $get Sanitized Get Request
 * @property mixed $autoAppendView Automatically appends a view to the document. Set to true to append files using the default file name pattern {basename}.{view}.php or enter a customized pattern. For example: 'myapp-{view}.php'
 * @property-read string $activeView Active view
 * @property string $masterTemplate Master template file name or html string
 * @property string $masterContentBlock Master template content css selector. Defaults to .master-content
 * @property string $pageOutputBuffer output buffer
 */
class RaxanWebPage extends RaxanBase implements ArrayAccess  {


    public static $vars = array();          // client-side local variables.
    public static $varsGlobal = array();    // client-side global variables.
    public static $actions = array();       // client-side actions
    public static $badIdChars = array('\\','"',"\r","\n","\x00","\x1a",'@',':','/',' ');

    public $Raxan;
    public $clientPostbackUrl;
    public $isLoaded = false, $isInitialized = false;
    public $isEmbedded = false,$embedOptions;
    public $isPostBack = false, $isAjaxRequest = false;
    public $isCallback = false;     // deprecated. use isAjaxRequest
    public $isAuthorized = false;
    public $isCache = false, $isDataStorageLoaded = false;
    public $isThemeLoaded = false;
    public $isRendering = false, $isReplying = false;
    public $responseType = 'html';          // html,xhtml,xhtml/html,xml,wml
    public $defaultBindOptions = array();   // default bind options

    public $autoAppendView;
    public $localizeOnResponse;      // Automatically insert language strings into element with the langid attribute set to valid locale key/value pair
    public $initStartupScript;       // loads the raxan startup.js script
    public $resetDataOnFirstLoad;    // reset page data on first load
    public $preserveFormContent;     // preserve form values on post back
    public $disableInlineEvents;     // disables the processing on inline events
    public $showRenderTime;          // shows the render time of the page
    public $serializeOnPostBack;     // default selector value for matched elements to be serialize on postback
    public $degradable;              // enable accessible mode for links, forms and submit buttons when binding to an event
    public $preventBrowserCache;     // send headers to prevent client-side browser or proxy caching
    public $masterTemplate;
    public $masterContentBlock = '.master-content';

    public $pageOutputBuffer;         // page output buffer

    // auto id properties
    protected static $autoIdPrefix = 'e0x'; // auto id prefix for elements.
    protected static $autoId;               // auto id for elements.

    protected static $eventId = 1;
    protected static $cliExtLoaded = false;
    protected static $mPageId = null;   // page controller or master page Id
    protected static $pages = array();  // collection of pages
    protected static $customMethods;    // stores custom (extended) methods
    protected static $customProps;      // stores custom (extended) propeties
    protected static $regScripts = array();       // stores registered scripts


    protected $doc = null;   // document
    protected $activeView;
    protected $_startTime, $_endResponse;
    protected $_charset, $_headers, $_contentType;
    protected $_storeName, $_postRqst, $_post, $_get;
    protected $_flyDOM, $_scripts = array();
    protected $_eCache = array();    // element cache
    protected $_uiElms;     //registered ui elements
    protected $_clientElms; // array of client elements to be updated/modified
    protected $_dataStore, $_dataReset = false;
    protected $_tmpStateData;  //temporarily stores state data
    protected $_preserveElms; // array of elements to be preserved
    protected $_hiddenElms; // array of elements to hide from client's browser
    protected $_hasRegisteredEvents;

    // Page request handlers
    protected function _config() {}
    protected function _init() {}
    protected function _authorize() { return true; }
    protected function _indexView() {}
    protected function _load() {}
    protected function _prerender() {}
    protected function _postrender() {}
    protected function _reply() {}
    protected function _destroy() {}

    // Data reset handler - called when resetDataOnFirstLoad is true
    protected function _reset ()  { return true; }

    public function __construct($xhtml='', $charset = null, $type=null) {
        parent::__construct();

        $this->_endResponse = false;

        // get instance of Raxan main class
        $this->Raxan = Raxan::singleton();

        // set start time must be set before page_config
        if ($this->showRenderTime) $this->_startTime = Raxan::startTimer();

        // initialize Raxan
        if (!Raxan::$isInit) Raxan::init();

        // script dependencies
        $dep = array('jquery');
        $this->registerScript('jquery-ui','jquery-ui',$dep);
        $this->registerScript('jquery-tools','jquery-tools',$dep);
        $this->registerScript('jquery-ui-utils','jquery-ui-utils',$dep);
        $this->registerScript('jquery-ui-effects','jquery-ui-effects',$dep);
        $this->registerScript('jquery-ui-interactions','jquery-ui-interactions',$dep);

        // call page setup
        if (Raxan::$isDebug) Raxan::debug('Page _config()');
        $this->_config();
        Raxan::triggerSysEvent('page_config',$this);

        // apply default page settings to the specified page object
        $c = Raxan::config();
        if (!isset($this->localizeOnResponse)) $this->localizeOnResponse = $c['page.localizeOnResponse'];
        if (!isset($this->showRenderTime)) $this->showRenderTime = $c['page.showRenderTime'];
        if (!isset($this->initStartupScript)) $this->initStartupScript = $c['page.initStartupScript'];
        if (!isset($this->resetDataOnFirstLoad)) $this->resetDataOnFirstLoad = $c['page.resetDataOnFirstLoad'];
        if (!isset($this->preserveFormContent)) $this->preserveFormContent = $c['page.preserveFormContent'];
        if (!isset($this->disableInlineEvents)) $this->disableInlineEvents = $c['page.disableInlineEvents'];
        if (!isset($this->masterTemplate)) $this->masterTemplate = $c['page.masterTemplate'];
        if (!isset($this->serializeOnPostBack)) $this->serializeOnPostBack = $c['page.serializeOnPostBack'];
        if (!isset($this->degradable)) $this->degradable = $c['page.degradable'];

        // set clientPostbackUrl
        if (!isset($this->clientPostbackUrl)) $this->clientPostbackUrl = Raxan::currentURL();

        // load default settings, charset, etc
        $this->_charset = $charset ? $charset : $c['site.charset'];
        $this->doc = self::createDOM(null, $this->_charset);
        $this->doc->initPageController($this->objId);

        self::$pages[$this->objId] = $this;
        if (self::$mPageId==null) self::$mPageId = $this->objId;
        $this->source($xhtml,$type); // set document source

        // init postback variables
        $this->isPostBack = $this->isPostback = count($_POST) ? true : false; // @todo: deprecate $t->isPostback
        $this->isAjaxRequest = $this->isCallback = isset($_POST['_ajax_call_']) ? true : false;
        if (isset($_GET['embed'])) {
            $em = $_GET['embed'];
            $opt = ($this->isEmbedded = isset($em['js'])) ? $_GET['embed']['js'] : '';
            $this->embedOptions = $opt;
        }

        // get active view. defaults to index view
        $hasVuHandler = false;
        if (isset($_GET['vu'])) {
            preg_match('/[a-z0-9_]+/i',$_GET['vu'],$matches);  // get view name from url (vu)
            $this->activeView = $matches ? trim($matches[0]) : '';   
        }
        if (!$this->activeView) $this->activeView = 'index';

        // call page  _init, _*Views and _load event handlers
        if (Raxan::$isDebug) Raxan::debug('Page _init()');
        $this->_init(); $this->isInitialized = true;
        Raxan::triggerSysEvent('page_init',$this);


        // check authorization
        if (!$this->_endResponse) {
            if (Raxan::$isDebug) Raxan::debug('Page _authorize()');
            $this->isAuthorized = $this->_authorize();
            $pluginAuth = Raxan::triggerSysEvent('page_authorize',$this);
            if ($this->isAuthorized && $pluginAuth!==null) $this->isAuthorized = $pluginAuth;
        }
        
        if ($this->isAuthorized || $this->_endResponse) { // continue if authized or _endResponse = true
            // initialize view
            if (!$this->_endResponse) {
                $hasVuFile = false;
                $vu = $this->activeView;
                $vuHandler = '_'.$vu.'View';
                $autov = $this->autoAppendView;
                if ($autov) {
                    if ($autov!==true && is_string($autov)) $vuFile = str_replace('{view}',$vu,$autov); // apply custom view file name
                    else {  // default view file name
                        $filePrefix = basename(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : $_SERVER['SCRIPT_NAME']);
                        $filePrefix = substr($filePrefix, 0, strrpos($filePrefix,'.'));
                        $filePrefix = str_replace(array('/','\\',' '),'',$filePrefix); // remove special chars from script name
                        $vuFile = $filePrefix.'.'.$vu.'.php';
                    }
                    $hasVuFile = file_exists($c['views.path'].$vuFile);
                    if ($hasVuFile) {
                        if (Raxan::$isDebug) Raxan::debug('Page  - Load view '.$vuFile);
                        $this->appendView($vuFile);
                    }
                }
                if ($vu != 'index') $hasVuHandler = method_exists($this,$vuHandler);
                if ($hasVuHandler || $vu == 'index') {
                    if (Raxan::$isDebug) Raxan::debug('Page '.$vuHandler.'()');
                    $this->{$vuHandler}();  // call view handler
                }
                else if(!$hasVuFile) {      // view handler or file not found
                    Raxan::sendError(Raxan::locale('view_not_found'));
                }
            }

            // initialize UI Widgets
            $this->initUIWidgets();

            // initialize inline events
            $this->initInlineEvents();

            // initialize preserved state
            $this->initPreservedElements();

            // initialize hidden elments
            $dl = $this->doc->xQuery('//*[@xt-hidefromclient]');
            if ($dl->length) {
                foreach ($dl as $node) {
                    $node->removeAttribute('xt-hidefromclient');
                    $this->hideElementFromClient($node,true);
                }
            }

            // load page and widgets
            if (Raxan::$isDebug) Raxan::debug('Page _load()');
            if (!$this->_endResponse) {
                $this->_load(); $this->isLoaded = true;
                $this->loadUIWidgets();
                Raxan::triggerSysEvent('page_load',$this);
            }

            // update form elements
            if ($this->preserveFormContent && $this->isPostBack) {
                if (Raxan::$isDebug) Raxan::debug('Page - Restore Form State');
                $this->updateFormFields(); // update form fields on postbacks
            }
        }
        else {
            // if authorization failed then return HTTP: 403
            Raxan::sendError(Raxan::locale('unauth_access'), 403);
        }

    }

    public function __destruct() {
        $this->_destroy();
        $this->doc = null; // discard dom document object
        unset($this->_eCache);
        unset($this->_uiElms);
        unset($this->_hiddenElms);
        unset($this->_clientElms);
        unset($this->events);
        unset(self::$pages[$this->objId]);
    }

    // call
    public function __call($name,$args){
        if (isset(self::$customMethods[$name])) {
            $fn = self::$customMethods[$name];
            if (is_array($fn)) return $fn[0]->{$fn[1]}($this,$args);
            else return $fn($this,$args);
        }
        else throw new Exception('Undefined Method \''.$name.'\'');
    }

    /**
     * Returns a matched element by id using the variable name
     * @param string $id
     * @return RaxanElement
     */
    public function __get($name) {
        if ($name=='post') return $this->_post ? $this->_post: $this->_post = Raxan::dataSanitizer($_POST);
        else if ($name=='get') return $this->_get ? $this->_get: $this->_get = Raxan::dataSanitizer($_GET);
        else if ($name=='activeView') return $this->activeView; // return protected value
        else {            
            if (isset(self::$customProps[$name])) return self::$customProps[$name];   // custom property lookup
            else if (($e = $this->findById($name)) && $e->length) return $e;        // element lookup
            $msg = 'Page element \''.$name.'\' or property not found';
            throw new Exception($msg);
        }
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
     * @param string $style
     * @return RaxanWebPage
     */
    public function addCSS($style) {
        $this->loadCSS('<style type="text/css">'.$style.'</style>');
        return $this;
    }

    /**
     * Adds a block of Javascript to the webpage
     * @param string $script
     * @param string $startupEvent Set to either ready, load or unload
     * @return RaxanWebPage
     */
    public function addScript($script,$startupEvent = null) {
        if ($startupEvent && strpos('ready,load,unload,',$startupEvent.',')!==false) {
            $script = "Raxan.".$startupEvent."(function(){\n".$script."\n})";
            $this->initStartupScript = true;
        }
        $nl = "\n"; //newline
        $script = str_replace(']]>',']]\>',$script);
        $this->loadScript('<script type="text/javascript">//<![CDATA['.$nl.$script.'//]]></script>');
        return $this;
    }

    /**
     * Appends the specified content to the page master content block element
     * @param string $html HTML content to append to page body
     * @return RaxanWebPage
     */
    public function append($html) {
        $s = $this->masterContentSelector();
        $this->find($s)->append($html);
        return $this;
    }

    /**
     * Appends an html view to the page master content block element
     * @param string $view Name of file found inside the views folder. Example: "content.php" or "sidebar.html"
     * @param string $selector Optional CSS selector. Used to filter the contebt of the view
     * @param mixed $data Optional. Data to be used by dynamic views (.php files)
     * @return RaxanWebPage
     */
    public function appendView($view, $selector = null, $data = null) {
        $view = $this->getView($view, $selector, $data);
        if ($view) {
            $s = $this->masterContentSelector();
            $this->find($s)->append($view);
        }
        return $this;
    }

    /**
     * Binds an event to the page body element
     * @return RaxanWebPage
     */
    public function bind($type,$data = null ,$fn = null) {
        $this->findByXPath('//body[1]')->bind($type, $data, $fn);
        return $this;
    }

    /**
     * Binds an event to the selector of the page body element using event delegation
     * @param string $selector CSS selector
     * @param string $type Event type. Example click
     * @param mixed $data Optional. Event data to be passed to the callback function
     * @param mixed $fn Callback function
     * @return RaxanWebPage
     */
    public function delegate($selector, $type,$data = null ,$fn = null) {
        $this->findByXPath('//body[1]')->delegate($selector,$type, $data, $fn);
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
        $local = $global = $ids = $prefTarget = $serialize = $confirmText = $value = $script =  $targetWindow = '';
        $delay = $autoDisable = $autoToggle = $inputCache = $repeat = $view = $extendedOpt = '';
        if (substr($type,-6)=='@local') {$type = substr($type,0,-6); $local = true; } // check if local access
        elseif (substr($type,-7)=='@global') {$type = substr($type,0,-7); $global = true; } // check if global access

        // setup default options
        $opts = $this->defaultBindOptions ? $this->defaultBindOptions : array();
        if ($this->serializeOnPostBack) $opts['serialize'] = $this->serializeOnPostBack;
        if ($this->degradable) $opts['accessible'] = true;
        if ($fn===null) {
            $isOpt = (is_array($data) && !isset($data[1])) ; // check if data is a callback function
            if (!$isOpt) {
                $fn = $data; $data = null;
            }
        }
        if ($isOpt) $opts = ($opts) ? array_merge($opts,$data) : $data;
        else {
            $isOpt = true;
            $opts['data'] = $data;
            $opts['callback'] = $fn;
        }

        // handle ui event binding
        $id = $elms ? $elms[0]->getAttribute('id') : null;
        if ($id && !$delegate && isset($this->_eCache[$id]->isUIWidget)) {
            $rt = $this->_eCache[$id]->handleEventBinding($type,$opts,$local);
            if ($rt===true) return $this; // return if event was handled by ui
        }

        // setup event options
        $hndPrefix = 'e:'; // flag events handlers as external (client or global access)
        if ($local) $hndPrefix = 'l:'; // flag event as local (private)
        else {
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
                $view = isset($opts['view']) ? $opts['view'] : null;
                $confirmText = isset($opts['confirm']) ? $opts['confirm'] : null;
                $targetWindow = isset($opts['targetWindow']) ? $opts['targetWindow'] : null;
                $data = isset($opts['data']) ? $opts['data'] : null; // get data object
                $extendedOpt = ($delay||$autoDisable||$autoToggle||$inputCache||$repeat||$view||$confirmText||$targetWindow) ? true : false;
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
                'view' => $view,
                'confirm' => $confirmText,
                'targetWindow' => $targetWindow,
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
        if ($delegate && $delegate!==true && $delegate!='page') { // check if delegate is a string
            $dlgSelector = str_replace(',',' '.$delegate.',',$selector).' '.$delegate;
            if ($accessible) { // locate child elements for delagates if any
                $elms = $this->find($dlgSelector)->get();
                $delegate = true;
            }
        }
        $dlgPTarget = $accessible && $delegate ? urlencode(($prefTarget) ? $prefTarget : $dlgSelector) : '';

        if ($elms && (!$delegate || $accessible)) foreach($elms as $n) {
            $id = $n->getAttribute('id'); // get id
            // setup non-delegate callback and element id
            if (!$delegate) {
                if (!$id) $n->setAttribute('id', $id = self::$autoIdPrefix.'v'.self::$eventId++); // auto assign id
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
                    '&_e[tok]='.Raxan::$postBackToken.($view ? '&vu='.$view : '');
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
                            if (!$id) $n->setAttribute('id', $id = self::$autoIdPrefix.'v'.self::$eventId++); // auto assign id
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
     * Execute a JavaScript function on th client
     * @example Usage:<br />
     *  <p>$this->callScriptFn('myFunction' $param);</p>
     *  <p>// call function and pass multiple parameters<br />
     *     $this->callScriptFn('myNextFunction' $param1, $param2, $param3,...);</p>
     * @param string $name Name of JavaScript function
     * @param mixed $param Parameter to be passed to function
     * @return RaxanWebPage
     */
    public function callScriptFn($name,$param = null) {
        $params = ''; $p = array();
        self::loadClientExtension();
        if (($cnt=func_num_args())>=2) {
            $params = func_get_args();
            for($i=1;$i<$cnt;$i++) $p[] = RaxanClientExtension::encodeVar($params[$i]);
            $params = implode(',',$p);
        }
        self::$actions[] = $name.'('.$params.')';
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
     * Returns or sets the main (body or card) content for an HTML or WML document
     * @return RaxanWebPage Returns RaxanWebPage or HTML (string) content of the web page
     */
    public function content($html = null) {
        $s = $this->masterContentSelector();
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
     * Create and return a DOM fragment node
     * @param string $html
     * @return DOMDocumentFragment
     */
    public function createFragment($html) {
        $frag = $this->doc->createDocumentFragment();
        if (trim($html)!=='') {
            $ok = $frag->appendXML($html); // I really wish there was a appendHTML() method :(
            if(!$ok) {
                // if unknown entities or xhtml errors then decode entities and try again
                $charset = $this->doc->charset;
                $ok = $frag->appendXML(html_entity_decode($html.'',null,$charset));
                if(!$ok) {  // final attempt - the long and hard way :(
                    $dom = new DOMDocument();
                    $dom->loadHTML('<!DOCTYPE html>'.
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
                libxml_clear_errors(); // clear libxml errors
            }
        }

        // check if fragment is empty
        if ($frag->firstChild==null) {
            $tn = $this->doc->createTextNode('');   // create empty text node
            $frag->appendChild($tn);                // and add it to the fragment
        }
        
        return $frag;
        
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
     * Get and/or set a named data value for the current page. The data will be preserved during post back requests.
     * @param string $name
     * @param mixed $value
     * @param boolean $setValueIfNotIsSet
     * @return Mixed
     */
    public function &data($name,$value = null,$setValueIfNotIsSet = false){
        if (!$this->isDataStorageLoaded) $this->initDataStorage();
        $s = & $this->_dataStore;
        if (!$this->_dataReset && $this->resetDataOnFirstLoad) {
            $this->_dataReset = true; $tokenMatch = false;
            if ($this->degradable && !isset($_POST['_e']) && isset($_GET['_e'])) {
                $g = $_GET['_e'];
                $tokenMatch = isset($g['tok']) ? $g['tok']==Raxan::$postBackToken : false;
            }
            $pb = $this->isPostBack|$tokenMatch; $lp = $s->read('_rxLastPage_');
            if (!$pb || !$lp || ($pb && $lp != $_SERVER['PHP_SELF'])) {
                if (Raxan::$isDebug) Raxan::debug('Page _reset()');
                if ($this->_reset()) $s->resetStore(); // reset page data store
            }
            $s->write('_rxLastPage_', $_SERVER['PHP_SELF']); // save last page
        }
        if ($value!==null) {
            $sv = $setValueIfNotIsSet;
            if (!$sv || ($sv && !$s->exists($name))) return $s->write($name,$value);
        }
        return $s->read($name);
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
     * Downloads the content to the browser and stop page execution
     * @param string $content
     * @param string $fileName Optional File name
     * @param string $contentType Optional content type. If not set the sytem will auto detect content-type based on extension otherwise application/octet-stream is used
     */
    public function download($content, $fileName = null, $contentType = null) {
        $fileName = $fileName  ? $fileName : basename(__FILE__);
        if ($contentType===null) {
            switch (substr(strtolower($fileName),-3)) {
                case 'csv': $type = 'text/csv'; break;
                case 'gif': $type = 'image/gif'; break;
                case 'jpg': $type = 'image/jpeg'; break;
                case 'png': $type = 'image/png'; break;
                case 'svg': $type = 'image/svg+xml'; break;
                case 'zip': $type = 'application/zip'; break;
                case 'pdf': $type = 'application/pdf'; break;
                case 'doc': $type = 'application/msword'; break;
                case 'xls': $type = 'application/vnd.ms-excel'; break;
                case 'txt': $type = 'text/plain'; break;
                default: $type = 'application/octet-stream'; break;
            }
            $contentType  = $type;
        }
        header('Content-Type:'.$contentType);
        header('Content-Disposition: attachment; filename="'.$fileName.'"');
        echo $content;
        exit();
    }

    /**
     * Dumps the content directly to the browser and stop page execution
     * @param string $content
     * @param string $contentType Defaults to text/html
     */
    public function dump($content,$contentType = 'text/html') {
        header('Content-Type:'.$contentType);
        echo $content;
        exit();
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
        $ec = & $this->_eCache;
        if (isset($ec[$id]) && ($e = $ec[$id])) // check element cache
            if (($n = $e->get(0)) && $n->parentNode)
                return $e->end(true); // reset stack if exist
            else
                unset($ec[$id]);


        $elm = $this->getElementById($id);
        $ui = $elm ? $elm->getAttribute('xt-ui') : '';
        $e = $this->wrapElement($elm, $ui);
        if ($e->length) $ec[$id] = $e;
        return $e;
    }

    /**
     * Find and return matched elements based on specified xpath
     * @return RaxanElement
     */
    public function findByXPath($pth, DOMNode $context = null){
        $dl = $this->doc->xQuery($pth,$context);
        if (!$dl) $dl = ' ';
        return new RaxanElement($dl,$this->doc);
    }

    /**
     * Flash a message unto the page. Message will be displayed inside an element with the "flashmsg" class
     * To allow the user to close the message, add a clickable element with a "close" class inside the message.
     * Here's an example using a close button: &lt;span&gt;Record successfully saved. &lt;a class="close" href="#"&gt;Ok&lt;/a&gt;&lt;/span&gt;
     * @param string $msg Message to be displayed
     * @param string $effect Optional jQuery UI effect
     * @param string $class Optional CSS class name to added to the message
     * @param string $id Optional. DOM element id where the message is to be displayed
     * @return RaxanWebPage Returns an array or instance of RaxanWebPage
     */
    public function flashmsg($msg = null,$effect = null,$class = null,$id = null,$expose = null) {
        $key = 'Page.FlashMsg';
        if ($msg===null) return $this->Raxan->flash($key);
        else if (!$this->_endResponse) {
            $class = $class ? ' '.str_replace('"','',$class) : '';    // sanitize attributes
            $rel = $effect ? ' data-flash-fx="'.str_replace('"','',$effect).'"' : '';
            $msg = '<div class="rax-flash-msg'.$class.'"'.$rel.'>'.$msg.'</div>';
            $this->Raxan->flash($key,array($msg,$effect,$id,$expose));
        }
        return $this;
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
     * Returns a DOMElement based on the Id value
     * @returns DOMElement
     */
    public function getElementById($id){
        $elm = PHP_VERSION_ID >= 50211 ? $this->doc->getElementById($id) : null;
        // @todo: it appears that getElementById is failing in php 5.2 after tring to retrieve a form element by it's id
        // the error occurred after setting the checked attribute on two or more input elements of type checkbox or radio.
        // getElementById appears to be working in php 5.2.11+ and 5.3+
        if ($elm) return $elm;
        else {
            $id = str_replace(self::$badIdChars,'',$id); // clean target id - prevent xpath injection
            $dl = $this->doc->xQuery('//*[@id="'.$id.'"][1]'); // find DOMElement
            return ($dl && $dl->length) ? $dl->item(0) : null;
        }
    }

    /**
     * Returns the content of a view file from within the views folder
     * @param string $view Name of file found inside the views folder. Example: "content.php" or "sidebar.html"
     * @param string $selector Optional CSS selector. Used to filter the content of the view
     * @param mixed $data Optional. Data input to be used by dynamic views (.php files)
     * @return string
     */
    public function getView($view,$selector = null,$data = null){
        $pth = Raxan::config('views.path');
        $view = trim($view);
        if (substr($view,-4)=='.php' && strpos($view,'://')===false) {
            $page = $this; // make page available to view
            ob_start(); include $pth.$view; $view = ob_get_clean();  // handle php file
        }
        else {
            $view = file_get_contents($pth.$view);
        }
        if ($selector && $view) // apply selector filter
            $view = $this['<div>'.$view.'</div>']->find($selector)->outerHtml();
        return $view;
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
            $type = isset($e['type']) ? trim($e['type']) : 'click';
            $tokenMatch = isset($e['tok']) && $e['tok']==Raxan::$postBackToken ? true : false ;
            $hnd = 'e:'.$id.'.'.$type;
            $hndlrs = isset($events[$hnd]) ? $events[$hnd] : null;
            if ($hndlrs) {
                if (Raxan::$isDebug) Raxan::debug('Raise '.htmlspecialchars($type).' Event For '.htmlspecialchars($id));
                $conduit = substr($type,0,12)=='rax-conduit-';
                // check if delegate
                $delegate = isset($events['selectors'][$id]) ? true : false;
                if($delegate||$conduit) $e['target'] = null;
                else $e['target'] = $this->getElementById($id); // lookup target element
                if (isset($e['uiSender'])) $e['uiSender'] = $this->getElementById($e['uiSender']);
                if (isset($e['uiHelper'])) $e['uiHelper'] = $this->getElementById($e['uiHelper']);
                if (isset($e['uiDraggable'])) $e['uiDraggable'] = $this->getElementById($e['uiDraggable']);
                if ($conduit) {
                    $p = array('offset'=>0,'limit'=>1,'params'=>null,'rowcound'=>null);
                    $e['value'] = is_array($e['value']) ? array_merge($p,$e['value']) : $p;
                }
                $e = new RaxanWebPageEvent($type,$this,$e); // convert data from client to event object
                foreach ($hndlrs as $hnd) {
                    if ($hnd[2] || $tokenMatch) { // only invoke if global access or token matched
                        if ($hnd[0]!==null && !$this->triggerHandle($hnd, $e, null, $conduit)) break;
                    }
                }
                $eventResult = $e->result;
            }
        }
        return $this;
    }

    /**
     * Handle automatic client-side updates via the CLX
     */
    public function handleClientUpdates() {
        $h = ''; $delim = '<<'.time().'>>';
        if ($this->_clientElms) foreach($this->_clientElms as $sel=>$content) {
            $p = strrpos($sel,'|'); $h = '';
            $mode = substr($sel,$p + 1);
            $selector = substr($sel,0,$p);
            if ($content instanceof DOMNode) $h = trim($this->nodeContent($content, true));
            else if (is_array($content)) foreach($content as $elm) {
                $h.= trim($this->nodeContent($elm, true));
                if ($mode=='update') $h.= $delim;
            }
            else $h = (string)$content;

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
            if ($this->isAjaxRequest) C('#rxPDI_Debug')->remove(); // remove if in callback
            C('body')->append('<div id="rxPDI_Debug" style="padding:5px;border:3px solid #ccc;background:#fff"></div>');
            C('#rxPDI_Debug')->html($d);
        }
        return $this;
    }


     /**
     * Inserts JavaScript into the html document
     * @return RaxanWebPage
      */
    protected function handleScripts($includeEvents = true, $returnActionScripts = false) {
        $charset = $this->_charset;
        $inc = $raxan = $css = $js = $pdiVars = '';

        // build action script & clean up encoding and remove ascii ctrl chars
        $actions = $this->buildActionScripts($includeEvents);
        if ($actions) $actions = iconv($charset,$charset.'//IGNORE',preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S','',$actions));

        // build scripts
        $url = Raxan::config('raxan.url');
        foreach($this->_scripts as $s=>$x) {
            $tag = substr($s,0,4); $s = substr($s,4);
            if ($tag=='JSI:'){
                if ($x===1 && !$returnActionScripts) $js.= $s."\n";
                else if ($x!==1) {
                    // check for jquery ui related scripts
                    if ($s=='jquery-ui-interactions' && isset($this->_scripts['JSI:jquery-ui'])) continue;
                    else if ($s=='jquery-ui-effects' && (isset($this->_scripts['JSI:jquery-ui'])||isset($this->_scripts['JSI:jquery-ui-interactions']))) continue;
                    else if ($s=='jquery-ui-utils' && (isset($this->_scripts['JSI:jquery-ui'])||isset($this->_scripts['JSI:jquery-ui-interactions'])||isset($this->_scripts['JSI:jquery-ui-effects']))) continue;
                    $inc.= 'Raxan.include("'.Raxan::escapeText($s).'"'.($x ? ',true':'').');';
                }
            }
            elseif ($tag=='CSS:') {
                if ($x===1 && !$returnActionScripts) $css.=$s;
                else if ($x!==1) {
                    if ($returnActionScripts) $css.= 'Raxan.css("'.Raxan::escapeText($s).'"'.($x ? ',true':'').');';
                    else {
                        $href = ($x ? $s : $url.'ui/css/'.$s.'.css');
                        $css.= '<link href="'.htmlspecialchars($href).'" type="text/css" rel="stylesheet" />'."\n";
                        if ($s=='master') $css.='<!--[if lt IE 8]><link href="'.$url.'ui/css/master.ie.css" type="text/css" rel="stylesheet" /><![endif]-->'."\n";
                        $inc = 'Raxan.inc["css:'.Raxan::escapeText($s).'"]=true;'.$inc; // add stylesheet url to the included array
                    }
                }
            }
        }

        if ($returnActionScripts) return $css.$inc.$actions;
        else {
            if ($inc || $actions || $this->_hasRegisteredEvents) {
                $pdiVars = 'var _PDI_URL="'.Raxan::escapeText($this->clientPostbackUrl).'";';
                if (Raxan::$isLocaleLoaded) $pdiVars.='var _PDI_AJAX_ERR_MSG="'.Raxan::locale('pdi-ajax-err-msg').'";';
                $pdiVars = '<script type="text/javascript"><![CDATA[ '.$pdiVars." ]]></script>\n";
            }
            if ($inc || $actions || $this->initStartupScript || $this->isEmbedded) {
                $src = isset(self::$regScripts['JSI:startup']) ? self::$regScripts['JSI:startup'] : $url.'startup.js';
                if ($src===true) $raxan == '';
                else {
                    if ($src && is_array($src)) $src = $src['src'];
                    $raxan = '<script type="text/javascript" src="'.$src.'"></script>'."\n";
                }
                if ($inc) $inc = '<script type="text/javascript"><![CDATA[ '.$inc." ]]></script>\n";
                if ($actions) $actions = '<script type="text/javascript"><![CDATA[ '.str_replace(']]>',']]\>',$actions)." ]]></script>\n"; // prevent nested CDATA tag
            }

            $inc = $css.$raxan.$pdiVars.$inc.$actions.$js;
            if ($inc) {
                $hd = $this->findByXPath('/html/head[1]'); // find first head tag
                if ($hd->length) { // check for <head> tag.
                    $found = false;
                    $head = $hd->get(0);
                    foreach($head->childNodes as $child) {
                        $nn = strtolower($child->nodeName);
                        if ($nn=='link'||$nn=='style'||$nn=='script') {
                            $f = $this->createFragment($inc);
                            $head->insertBefore($f,$child); // add scripts before existing links, styles or scripts
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) $hd->append($inc);
                }
                else { // add <head> tag with charset
                    $meta = '<meta http-equiv="Content-Type" content="text/html; charset='.$this->_charset.'" />';
                    $this->findByXPath('/html[1]')->prepend('<head>'.$meta.$inc.'</head>');
                }
            }

            return $this;
        }
    }

    /**
     *  Halt and exit page processing while displaying a message
     */
    public function halt($msg = null) {
        echo $msg; exit();
    }

    /**
     * Load CSS stylesheet
     * @param string $css The name of css file to be loaded or inline <style> tag to be embedded inside the document
     * @param boolean $extrn Set to true if stylesheet will be loaded from a folder that's not relative to ui/css
     * @return RaxanWebPage
     */
    public function loadCSS($css,$extrn = false) {
        $css = trim($css);
        if (isset(self::$regScripts['CSS:'.$css])) { // check if stylesheet was registered
            $src = self::$regScripts['CSS:'.$css];
            if ($src===true) return; // stylesheet manually loaded by user
            elseif (is_array($src)) {
                $deps = $src['deps'];
                $src = $src['src'];
                foreach($deps as $i=>$d) $this->loadCSS($d);
            }
            if ($css!=$src) { $extrn = true; $css = $src; }
        }
        $embed = (stripos($css,'<')!==false);
        if (!isset($this->_scripts['CSS:'.$css])) {
            $this->_scripts['CSS:'.$css] = $embed ? 1 : $extrn;
        }
        return $this;
    }

    /**
     * Loads a plugin from the plugins folder.
     * Usage: <p>Raxan::loadPlugin($name,$alias)</p>
     *        <p>Raxan::loadPlugin($name,$extrn,$alias)</p>
     * @param string $name Name of plugin file without the .php extension
     * @param boolean $extrn Set to true if plugin will be loaded from a folder that's not relative to {plugins.path}
     * @param string $alias Page propery instance name
     * @return mixed Returns an instance of the plugin
     */
    public function loadPlugin($name,$extrn = false, $alias = null) {
        if ($extrn && $extrn!==true && $alias===null) { $alias = $extrn; $extrn = false; }
        $ins = Raxan::loadPlugin($name,$extrn,$alias);
        self::$customProps[$alias] = $ins;
        return $ins;
    }

    /**
     * Load Javascript library
     * @param string $js The name of script file to be loaded or inline <script> tag to be embedded inside the document
     * @param boolean $extrn Set to true if JavaScript library file will be loaded from a folder that's not relative to ui/javascripts
     * @return RaxanWebPage
     */
    public function loadScript($js,$extrn = false, $_priority = 0) {
        $js = trim($js);
        $s = & $this->_scripts;
        if (isset(self::$regScripts['JSI:'.$js])) { // check if script was registered
            $src = self::$regScripts['JSI:'.$js];
            if ($src===true) return; // script manually loaded by user
            elseif (is_array($src)) {
                $deps = $src['deps'];
                $src = $src['src'];
                foreach($deps as $i=>$d) $this->loadScript($d);
            }
            if ($js!=$src) { $extrn = true; $js = $src; }
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
     * Loads a CSS theme for the current page
     * @param string $name The name of theme (folder) to be loaded
     * @param boolean $extrn Set to true if theme will be loaded from a folder that's not relative to {raxan.url}/ui/css
     * @return RaxanWebPage
     */
    public function loadTheme($name,$extrn = false) {
        $this->isThemeLoaded = true;
        $url = (!$extrn) ? $name.'/theme' : $name;
        $this->loadCSS($url,$extrn);
        return $this;
    }

    /**
     * Loads a widget from the widgets folder
     * @param string $name Name of widget file without the .php extension
     * @param boolean $extrn Set to true if theme will be loaded from a folder that's not relative to {widgets.path}
     * @see Raxan::loadWidtget()
     * @return boolean
     */
    public function loadWidget($name,$extrn = false) {
        return Raxan::loadWidget($name,$extrn);
    }

    /**
     * Returns the html content of an element
     * @param DOMElement $n
     * @param boolean $outer
     * @return string
     */
    public function nodeContent(DOMElement $n, $outer=false) {
        $d = $this->flyDOM(); // DOM with xhtml doctype
        //$n = $d->importNode($n->cloneNode(true),true);
        $n = $d->importNode($n,true);   // @todo: test to see if clone is faster
        $h = str_replace('&#13;','',$d->saveXML($n)); // save and cleanup xhtml code
        $n = $this->doc->importNode($n,true);
        // remove outer tags
        if (!$outer) $h = substr($h,strpos($h,'>')+1,-(strlen($n->nodeName)+3));
        return $h;
    }

    /**
     * Prepends the specified html content to the page master content block element
     * @return RaxanWebPage
     */
    public function prepend($html) {
        $s = $this->masterContentSelector();
        $this->find($s)->prepend($html);
        return $this;
    }

    /**
     * Redirect client to the specified url
     * @param string $url
     * @param boolean $ajaxEnabled Optional. Force page redirection during an Ajax request
     */
    public function redirectTo($url, $forceAjax = true){
        $useJavaScript = $forceAjax && $this->isAjaxRequest;
        Raxan::redirectTo($url, $useJavaScript);
    }

    /**
     * Redirect to new page view
     * @param string $view View mode
     * @param string $url Optional page url
     * @param boolean $ajaxEnabled Optional. Force page redirection during an Ajax request
     */
    public function redirectToView($view,$url = null,$forceAjax = true){
        $useJavaScript = $forceAjax && $this->isAjaxRequest;
        Raxan::redirectToView($view, $url, $useJavaScript);
    }

    /**
     * Registers a data conduit
     * @param string $name Name of conduit
     * @param mixed $fn Conduit callback handler. Parameters passed to callback handler: $offset,$limit,$params,$rowcount
     * @return RaxanWebPage
     */
    protected function registerConduit($name, $fn) {
        $this->registerEvent('rax-conduit-'.$name,$fn);
        return $this;
    }

    /**
     * Registers an event to be triggered from the client using page as the target id
     * Client-side Example: Raxan.dispatchEvent('customevent',function(result,state){ some code here });
     * @return RaxanWebPage
     */
    public function registerEvent($type,$data = null,$fn = null){
        $elms = array();
        $this->_hasRegisteredEvents = true;
        if (!is_array($type)) $this->bindElements($elms, $type, $data, $fn, 'page', true);
        else {
            $types = $type; $type = '';
            foreach ($types as $type=>$fn)
                $this->bindElements($elms, $type, $data, $fn, 'page', true);
        }
        return $this;
    }

    /**
     * Used internally to register a UI widget with the current web page.
     * @param RaxanUIWidget $ui
     * @return RaxanWebPage
     */
    public function registerUIWidget(RaxanUIWidget $ui) {
        if (!isset($this->_uiElms)) $this->_uiElms = array();
        $this->_uiElms[$ui->objectId()] = $ui;
        if ($this->isLoaded) $ui->loadInterface();
        return $this;
    }

    /**
     * Registers a CSS stylesheet file by name
     * Use the loadCSS() method to load the stylesheet by name.
     * @param string $name - Name of script
     * @param string $src - Url for the stylesheer. Set to True if the stylesheet was loaded manually.
     * @param array $dependencies
     */
    public static function registerCSS($name,$src, $dependencies = null) {
        self::$regScripts['CSS:'.$name] = !$dependencies ?
            $src : array ('src'=> $src, 'deps'=> $dependencies);
    }

    /**
     * Registers a javascript file by name
     * Use the loadScript() method to load the script by name.
     * @param string $name - Name of script
     * @param string $src - Url for the script. Set to True if the script was loaded manually.
     * @param array $dependencies
     */
    public static function registerScript($name,$src, $dependencies = null) {
        self::$regScripts['JSI:'.$name] = !$dependencies ?
            $src : array ('src'=> $src, 'deps'=> $dependencies);
    }

    /**
     * Registers a value or variable to be accessed from the client-side Raxan JavaScript library
     * Use registerVar() to send PHP arrays, objects and other datatypes to the client.
     * To retrieve the value from JavaScript use Raxan.getVar('name');
     * @param string $name
     * @param mixed $value
     * @return RaxanWebPage
     */
    public function registerVar($name,$value) {
        self::loadClientExtension();
        $name = Raxan::escapeText($name);
        self::$actions[] = 'Raxan.regvar[\''.$name.'\']='.RaxanClientExtension::encodeVar($value);
        return $this;
    }

    /**
     * Render and return html content
     * @param string $type Accepted values: html, xml
     * @return string
     */
    public function render($type = 'html') {

        $this->pageOutputBuffer = $result = '';

        // handle client events request
        if (!$this->_endResponse) {
            $this->handleClientEventRequest($result);
        }

        // set rendering flag after client events
        $this->isRendering = true;

        // render page and widgets
        if (!$this->_endResponse) {
            // call _prerender event and render registered ui elements
            if (Raxan::$isDebug) Raxan::debug('Page _prerender()');
            $this->_prerender();
            $this->renderUIWidgets();
            Raxan::triggerSysEvent('page_prerender',$this);
        }

        // handle hidden elements
        if ($this->_hiddenElms) foreach($this->_hiddenElms as $id=>$node) {
            $p = $node ? $node->parentNode : null;
            if($p) $p->removeChild($node);
        }

        // handle flash message - endResponse check was added to flashmsg()
        $flElm = $this->findByXPath(".//*[contains(concat(' ',@class,' '), concat(' ','flashmsg',' '))][1] ");
        if ($flElm->length > 0) {
            $flash = $this->flashmsg();
            if (!$flash) $msg = $effect = $id ='';
            else {
                $msg = $flash[0]; $effect = $flash[1];
                $id = $flash[2]; $expose = $flash[3];
                $params = array();
                $params[] = "'".Raxan::escapeText($effect)."'";
                $params[] = "'".Raxan::escapeText($id)."'";
                if ($expose) $params[] = Raxan::JSON('encode',$expose);
                $params = $id||$effect||$expose ? implode(',',$params) : '';
                self::$actions[]='Raxan.iFlashEffect('.$params.')';
                if ($effect) $this->loadScript('jquery-ui-effects');
                if ($expose) $this->loadScript('jquery-tools');
            }
            if (!$id) $elm = $flElm->autoId()->html($msg);
            else $elm = $this->findById($id)->html($msg);
            if ($msg) $elm->updateClient(); // update client if in ajax mode and $msg contains a valid message
        }

        // handle language tags - proccess tags with langid
        if (!$this->_endResponse) $this->handleNodeL10n();

        // handle automatic client-side updates
        if (!$this->_endResponse) $this->handleClientUpdates();

        // handle debug script
        if (Raxan::$isDebug) {
            $dmsg = $this->isAjaxRequest ? 'Ajax Response' : 'Full Page';
            Raxan::debug('Handle '.$dmsg.' Response');
            $this->handleDebugResponse();
        }

        // handle action & css scripts
        if (!$this->isAjaxRequest) $this->handleScripts();         // build full page action/css scripts
        else $actionScripts = $this->handleScripts(false,true);     // exclude events from actions

        // process page output
        if ($this->isAjaxRequest) {        // respond to ajax callback
            $charset = $this->_charset; $rt = '';
            $a = iconv($charset,$charset.'//IGNORE',$actionScripts);  // clean up action scripts charset encoding
            if (isset($result)) $rt = is_string($result) ? iconv($charset,$charset.'//IGNORE',$result) : $result;
            $json =  Raxan::JSON('encode',array( '_result' => $rt, '_actions' => $a ));
            if ($_POST['_ajax_call_']!='iframe') $this->pageOutputBuffer = $json;
            else {
                $html = '<form><textarea>'.htmlspecialchars($json).'</textarea></form>';
                $html = self::pageCode('html',$this->_charset,$html);
                $this->pageOutputBuffer = $html;
            }
            
        }
        elseif (!$this->isAjaxRequest) {   // response to standard postback
            // check if is embedded
            if (!$this->isEmbedded) {
                $this->pageOutputBuffer = $this->doc->source(null,$type);
            }
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
                $this->pageOutputBuffer = $h;
            }
        }

        if (!$this->_endResponse) {
            $this->_postrender();        // call _postrender event
            Raxan::triggerSysEvent('page_postrender',$this);
        }
        
        // save element state
        if ($this->_preserveElms) foreach($this->_preserveElms as $id=>$node) {
            $p = strrpos($id,'|');
            $mode = substr($id,$p + 1);
            $id = substr($id,0,$p);
            $this->saveElementState($node, $id, $mode);
        }

        $this->isRendering = false;

        // return html or json string
        return $this->pageOutputBuffer;
    }

    /**
     * Sends data to client
     * @param string $responseType Accepted values: html, xhtml, xhtml/html, xml, wml
     * @return RaxanWebPage
     */
    public function reply($responseType = null) {

        $this->isReplying = true;

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
        if ($this->preventBrowserCache) {
            header( "Expires: Mon, 1 Jan 1990 11:59:00 GMT" );
            header( "Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT" );
            header( "Cache-Control: no-cache, must-revalidate" );
            header( "Cache-Control: post-check=0, pre-check=0", false);
            header( "Pragma: no-cache" );
        }
        if ($this->_headers) foreach($this->_headers as $s) @header($s);

        // send content to client (eg. html, xml, json, etc)
        echo $content;
        if (Raxan::$isDebug) Raxan::debug('Page _reply()');
        if (!$this->_endResponse) {
            $this->_reply(); // call _reply event
            Raxan::triggerSysEvent('page_reply', $this);
        }

        // show render time and memory usage
        if ($this->showRenderTime && !$this->isAjaxRequest) {
            $time = Raxan::stopTimer($this->_startTime);
            $mem = function_exists('memory_get_usage') ?  memory_get_usage() / 1048576 : null;
            echo '<div style="position:fixed; left:0; bottom:0;width:100%; margin-top:5px; padding:5px 10px;'.
                'border-top:1px solid #eee; background:#fff; color:#000; opacity:0.85;z-index:1000;" align="left">'.
                'Render time: '.number_format($time, 5).' secs.'.
                ($mem ? ' Memory usage: '.number_format($mem, 2).' MB.' : '').
                '</div>';
        }


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
     * Used internally to hide element from client's browser
     * @param DOMElement $elm
     * @param boolen $state
     * @return RaxanWebPage
     */
    public function hideElementFromClient($elm,$state){
        if ($state == true) $this->_hiddenElms[] = $elm;
        else foreach($this->_hiddenElms as $i=>$n) {
            if ($n===$elm) unset($this->_hiddenElms[$i]);
        }
    }

    /**
     * Used internally - Remove element state
     * @param DOMElement $elm
     * @param string $id
     * @param string $mode
     * @return RaxanWebPage
     */
    public function removeElementState(DOMElement $elm, $id, $mode = 'local') {
        if (!$mode) $mode = 'local';
        if (!isset($this->_tmpStateData[$mode])) $this->_tmpStateData[$mode] = & $this->getStateData($mode);
        $data = & $this->_tmpStateData[$mode];
        unset($data[$id]);
        unset($this->_preserveElms[$id.'|'.$mode]);
    }

    /**
     * Restore element state. Used internally
     * @param DOMElement $elm
     * @param string $id
     * @param string $mode
     * @return RaxanWebPage
     */
    public function restoreElementState(DOMElement $elm, $id, $mode) {
        if (!$mode) $mode = 'local';
        if (!$id) $elm->setAttribute('id',$id = self::uniqueElmId());
        $this->_preserveElms[$id.'|'.$mode] = $elm; // add to array to be save
        if (!isset($this->_tmpStateData[$mode])) $this->_tmpStateData[$mode] = & $this->getStateData($mode);
        $data = & $this->_tmpStateData[$mode];
        if (isset($data[$id])) {
            $ec = isset($this->_eCache[$id]) ? $this->_eCache[$id] : null;
            $rt = ($ec && isset($ec->isUIWidget)) ?
                $ec->handleStateData($mode,$data[$id]) : false;
            if ($rt===false) {
                $htm =  isset($data[$id]['_html']) ? $data[$id]['_html'] : '';
                foreach($data[$id] as $n=>$v)
                    if ($n!='_html') $elm->setAttribute($n,$v);
                $elm->nodeValue = '' ; // clear node
                $f = $this->createFragment($htm);
                if ($f) $elm->appendChild($f);  // insert html
            }
        }
        return $this;
    }

    /**
     * Save element state. Used internally
     * @param DOMElement $elm
     * @param string $id
     * @param string $mode
     * @param boolean $reset Set to true to reset state data
     * @return RaxanWebPage
     */
    public function saveElementState(DOMElement $elm, $id, $mode = 'local') {
        if (!$mode) $mode = 'local';
        if (!$this->isRendering) $this->_preserveElms[$id.'|'.$mode] = $elm; // add to array to be save
        else {
            if (!isset($this->_tmpStateData[$mode])) $this->_tmpStateData[$mode] = & $this->getStateData($mode);
            $data = & $this->_tmpStateData[$mode];
            $ec = isset($this->_eCache[$id]) ? $this->_eCache[$id] : null;
            $rt = ($ec && isset($ec->isUIWidget)) ?
                $ec->handleStateData($mode,$data[$id],true) : false;
            if ($rt===false) {
                $attribs = $elm->attributes;
                foreach ($attribs as $a) $data[$id][$a->name] = $a->value;
                $data[$id]['_html'] = $this->nodeContent($elm);
            }
        }
        return $this;
    }

    /**
     * Returns data sanitizer object with get and/or post values. Defaults to post values
     * @deprecated deprecated since 1.0 rc1. Use post and get properties
     * @see RaxanWebPage->post and RaxanWebPage->get
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
        $mTpl = trim($this->masterTemplate);
        if ($src===null) return $this->doc->source(null,$srcType);
        else if ($mTpl) { // set master template
            if (substr($mTpl,-4)=='.php' && strpos($mTpl,'://')===false) { // handle php files
                ob_start(); include $mTpl; $mTpl = ob_get_clean();
            }
            $this->doc->source($mTpl,$srcType);
            $src = trim($src);
            if ($src) { // fix for issue #6
                $isFile = (substr($src,0,1)!='<') && is_file($src) ? true : false;
                if ($isFile || ($p = substr($src,0,7))=='http://' || $p=='https:/') {
                    $src = file_get_contents($src);
                }
            }
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
     * Transfer page control to the specified php file
     * @param string $file Physical path and file name of the page to be loaded
     * @param string $autoStartClass Name of page class to be initialized
     */
    public function transferTo($file,$autoStartClass = null){
        if ($autoStartClass) {
            self::$mPageId = null;
            ob_start();
        }
        include_once($file);
        if ($autoStartClass) {
            $src = trim(ob_get_clean());
            raxan_auto_create($autoStartClass,$src);
        }
        exit();
    }

    /**
     * Trigger events on the specified elements - used by RaxanElement
     * @param array $elms Array or DOMElements
     * @param string $type Event Type. Use the @local suffix to only trigger local events
     * @param mixed $args Optional arugments to to be passed to event handlers
     * @param object $eObject Original event object to passed to handlers
     * @return RaxanWebPage
     */
    public function triggerEvent(&$elms,$type,$args = null,$eObject = null) {
        $events = & $this->events; $e = null;
        $local = strpos($type,'@local'); // only trigger local event
        if ($local) $type = substr($type,0,$local);
        foreach($elms as $n) {
            $id = $n->getAttribute('id');
            $hnd = 'e:'.$id.'.'.$type; $lhnd = 'l:'.$id.'.'.$type;
            $hndlrs = !$local && isset($events[$hnd]) ? $events[$hnd] : array();
            $hndlrs = isset($events[$lhnd]) ? array_merge($hndlrs,$events[$lhnd]) : $hndlrs ; // merge local events handlers
            if ($hndlrs) {
                if (!$e) $e = new RaxanWebPageEvent($type,$this,$eObject); // convert to object
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
     * Update or replace client elements with the content or elements specified
     * @param $selector String client-side css selector
     * @param $mode String Used internally. possible values 'insert (default), replace, append, prepend, before, after'
     * @param mixed $content String, Array of DOMElements or a single DOMelement
     * @see RaxanWebPage::handleClientUpdates()
     * @return RaxanWebPage
     */
    public function updateClient($selector, $mode, $content) {
        $this->_clientElms[$selector.'|'.$mode] = $content;
        return $this;
    }

    /**
     * Update form fields with postback values
     * @return RaxanWebPage
     */
    public function updateFormFields() {
        if ($this->isPostBack) {
            // find form elements that are not readonly or disabled
            $xpath = '//form//*[@name][not(descendant-or-self::*[@disabled]|descendant-or-self::*[@readonly])]';
            $this->findByXPath($xpath)->val($_POST);
        }
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
                    unset($e['e:'.$id]); unset($e['l:'.$id]); // remove local and remote
                    if ($ss) unset($e['selectors']['#'.$id]);
                }
            }
        }
        return $this;
    }

    /**
     * Creates and returns a new RaxanElement from the specified DOM element
     * @param DOMElement $elm
     * @return RaxanElment
     */
    public function wrapElement($elm,$ui = null) {
        if (!$elm) $elm = ' ';
        if ($ui && (!class_exists($ui) || !is_subclass_of($ui, 'RaxanUIWidget'))) {
            throw new Exception('UI Widget Class '.$ui.' not found or is not a valid RaxanUIWidget sub-class');
        }
        $e = ($ui) ? new $ui($elm,$this->doc) : new RaxanElement($elm,$this->doc);
        return $e;
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
                            if ($opt['view']) $x[] = 'vu:\''.$opt['view'].'\'';
                            if ($opt['confirm']) $x[] = 'ct:\''.Raxan::escapeText($opt['confirm']).'\'';
                            if ($opt['targetWindow']) $x[] = 'tw:\''.Raxan::escapeText($opt['targetWindow']).'\'';
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
        $actions = str_ireplace('</script>','<\\/script>',$vars.implode(';',RaxanWebPage::$actions));

        if ($actions){
            // check if we should load jquery
            if (!isset($this->_scripts['JSI:jquery'])) {
                $this->loadScript('jquery',false,1);
            }
            $actions = 'Raxan.ready(function() {'.$actions.'});';
        }
        return $actions;
    }

    /**
     * Initialize page data storage handler
     * @return RaxanWebPage
     */
    protected function initDataStorage() {
        $cls = Raxan::config('page.data.storage');
        $id = 'pdiWPage-'.(($id = $this->_storeName) ? $id : $this->objId); // setup data store id
        $this->_dataStore = new $cls($id);
        $this->isDataStorageLoaded = true;
        return $this;
    }

    /**
     * Initialize inline events and bind event handlers on the current page
     * @return RaxanWebPage
     */
    protected function initInlineEvents() {
        if (Raxan::$isDebug) Raxan::debug('Page - Bind Inline Events');
        if ($this->isLoaded || $this->disableInlineEvents) return $this;
        $elms = $this->doc->xQuery('//*[@xt-bind]|//*[@xt-delegate]|//*[@xt-autoupdate]');
        $elmIds = ''; $elmsToUpdate = array();
        if ($elms->length > 0) {
            $l = $elms->length;
            for($i=0;$i<$l;$i++) {
                $elm = $elms->item($i);
                $attr = ($a = $elm->getAttribute('xt-bind')) ? 'B:'.str_replace(';',';B:',$a) : ''; // bind to multiple events
                $attr.= ($a = $elm->getAttribute('xt-delegate')) ? ($attr ? ';':'').'D:'.str_replace(';',';D:',$a) : ''; // bind to multiple events
                $attr.= (($auto = $elm->getAttribute('xt-autoupdate'))!='true' && $auto) ? ($attr ? ';':'').'A:'.$auto : '';
                $events = explode(';',$attr);
                // create id if not assigned
                $id = $elm->getAttribute('id');
                if (!$id) $elm->setAttribute('id',$id = self::$autoIdPrefix.'v'.self::$eventId++);
                // package elements for auto update
                if ($this->isAjaxRequest && $elm->hasAttribute('xt-autoupdate')) {
                    $elmIds.= '#'.$id.',';
                    $elmsToUpdate[] = $elm;
                }
                $elm->removeAttribute('xt-bind');
                $elm->removeAttribute('xt-delegate');
                $elm->removeAttribute('xt-autoupdate');
                foreach($events as $e) {
                    $mode = $e ? $e[0] : '';
                    $params = $mode ? trim(substr($e,2)) : '';
                    if (!$mode || !$params) continue;
                    $sel = ''; $belms = null;
                    $type = explode(',',$params,5); $de = false;
                    $o = array('callback'=>(isset($type[1]) && ($t = trim($type[1])) ? '.'.$t : null));
                    if (isset($type[2]) && ($t = trim($type[2])))
                        $o[($mode=='A' ? 'repeat' : 'serialize')] =  ($mode=='A' && ($t=='true'||$t=='repeat')) ? true : $t;
                    if (isset($type[3]) && ($t = trim($type[3])))
                        $o[($mode=='A' ? 'serialize' : 'autoDisable')] =  ($t=='true') ? true : $t;
                    if (isset($type[4]) && ($t = trim($type[4]))) $o['autoToggle'] = ($t=='true') ? true : $t;
                    if ($mode=='D') {   // delegate event
                        $t = trim($type[0]);
                        if (($p = strrpos($t,' '))===false) $de = true; // separate selector from event type
                        else {
                            $de = substr($t,0,$p);
                            $type[0] = substr($t,$p+1-strlen($t));
                        }
                        $sel = '#'.$id;
                    }
                    $belms = array($elm);
                    $this->bindElements($belms, $type[0], $o, null, $sel, $de);
                }
            }
            // register auto-update elements
            if ($elmIds) {
                $this->updateClient(trim($elmIds,','),'update',$elmsToUpdate);
            }
        }
        return $this;
    }

    /**
     * Initialize preserved elements with xt-preservestate attribute
     * @return RaxanWebPage
     */
    protected function  initPreservedElements() {
        // restore element state
        $dl = $this->doc->xQuery('//*[@xt-preservestate]');
        if ($dl->length) {
            if (Raxan::$isDebug) Raxan::debug('Page - Initialize Preserved Elements');
            foreach ($dl as $node) {
                $id = $node->getAttribute('id');
                $mode = trim($node->getAttribute('xt-preservestate'));
                $node->removeAttribute('xt-preservestate');
                if ($mode != 'false') $this->restoreElementState($node,$id,$mode);
            }
        }
        return $this;
    }

    /**
     * Initialize inline UI widgets
     * @return RaxanWebPage
     */
    protected function initUIWidgets() {
        $dl = $this->doc->xQuery('//*[@xt-ui]');
        if ($dl->length) {
            if (Raxan::$isDebug) Raxan::debug('Page - Initialize UI elements');
            foreach($dl as $node) {
                $id = $node->getAttribute('id');
                if (!$id) $node->setAttribute('id',$id = self::uniqueElmId());
                if (!isset($this->_eCache[$id])) {
                    $ui = $node->getAttribute('xt-ui');
                    $e = $this->wrapElement($node, $ui);    // create an instance of the element
                    $this->_eCache[$id] = $e;               // cache instance
                }
                $node->removeAttribute('xt-ui');
            }
        }
        return $this;
    }

    /**
     * Returns state data
     * @return mixed
     */
    protected function & getStateData($type = 'local') {
        $dtName  = '_raxanState_';
        if ($type == 'session') return Raxan::data($dtName,array(),true); // session state
        else return $this->data($dtName,array(),true); // local state
    }

    /**
     * Returns the selector for the master content block
     * @return string
     */
    protected function masterContentSelector() {
        $s = $this->masterContentBlock;
        $t =  $this->responseType=='wml' ? 'card:first' : 'body';
        return ($s && $this->masterTemplate) ?  $s : $t;
    }

    /**
     * Used internally to load regsitered UI Widgets
     * @return RaxanWebPage
     */
    protected function loadUIWidgets() {
        if (isset($this->_uiElms))
            foreach($this->_uiElms as $ui) $ui->loadInterface();
        return $this;
    }

    /**
     * Used internally to render regsitered UI Elements
     * @return RaxanWebPage
     */
    protected function renderUIWidgets() {
        if (isset($this->_uiElms))
            foreach($this->_uiElms as $ui) $ui->renderInterface();
        return $this;
    }

    /**
     * Used internaly to triggers event/conduit callback handler
     * @param array $hnd
     * @param RaxanWebPageEvent $e
     * @param mixed $args Event arguments
     * @param boolean $conduit Set to true to trigger conduit callback
     * @return Boolean - Returns true if not $e->isStopPropagation
     */
    protected function triggerHandle($hnd,$e,$args = null,$conduit = null) {
        $fn = $hnd[0]; $data = $hnd[1];
        $e->data = $data;
        if (!is_callable($fn)) Raxan::throwCallbackException($fn);
        if ($conduit) {
            $p = $e->value;
            $offset = (int)$p['offset']; $limit = (int)$p['limit'];
            $param = $p['param']; $rowcount = (int)$p['rowcount'];
            $rt = (is_array($fn)) ?     // handle conduit callback
                $fn[0]->{$fn[1]}($offset,$limit,$param,$rowcount) : $fn($offset,$limit,$param,$rowcount);
            $rt = array('dataset' => $rt, 'rowcount' => $rowcount);
        }
        else {
            $rt = (is_array($fn)) ?     // handle event callback (object, string or anonymous function)
                $fn[0]->{$fn[1]}($e,$args) : $fn($e,$args);  
        }
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
        if(!self::$customMethods) self::$customMethods = array();
        if ($callback===null && is_object($name)) { // add methods from an object
            $obj = $name; $names = get_class_methods($obj);
            foreach($names as $name)
                if($name[0]!='_') self::$customMethods[$name] = array($obj,$name); // don't add names that begins with '_'
        }
        else {
            if (!is_callable($callback)) Raxan::throwCallbackException($callback);
            self::$customMethods[$name] = $callback;
        }
    }

    /**
     * Adds a custom property to the RaxanWebPage Class.
     */
    public static function addProperty($name,$value = null) {
        self::$customProps[$name] = $value;
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
     * Returns web page controller object based on the pageId.
     * @return RaxanWebPage
     */
    public static function controller($pageId = null) {
        $pageId = $pageId!==null ? $pageId : self::$mPageId;
        if (isset(self::$pages[$pageId])&& self::$pages[$pageId])  {
            return self::$pages[$pageId];
        }
        else {
            $page = new RaxanWebPage();
            return self::$pages[$page->objectId()] = $page;
        }
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
                if (!is_array($v)) {
                    if ($n->nodeName=='input') $n->setAttribute("value",$v);
                    else $n->nodeValue = $v;
                }
                else foreach($v as $name => $attr)
                    if ($name=='html') $n->nodeValue = $v;
                    else $n->setAttribute($name,$attr);
                if ($id) $n->removeAttribute('langid');
            }
        }
    }

    /**
     * Returns blank html page template
     * @return string
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

    /**
     * Returns a unique id for an elment
     * @return String
     */
    public static function uniqueElmId($prefix = null) {
        if ($prefix===null) $prefix = self::$autoIdPrefix;
        return $prefix.(++self::$autoId);
    }

}

/**
 * Raxan Web Page Storage
 */
class RaxanWebPageStorage extends RaxanDataStorage {

    protected function _init() {
        $this->store = & Raxan::dataBank($this->id);
    }
    protected function _reset() {
        Raxan::removeDataBank($this->id);
        $this->_init();
    }
}

/**
 *  Used internally by RaxanWebPage
 * @property DOMElement $target
 * @property string $uiDraggable Id value for ui draggable element
 * @property string $uiHelper Id value for ui helper element
 * @property string $uiSender Id value for ui sender element
 * @property string $uiItem Id value for ui item element
 * @property string $uiSortedItemIds Comma delimited list of sorted item ids
 * @property string $which Number representing the ASCII code of the key that was pressed
 * @property string $button Number representing the mouse button that was pressed (1=left, 2=middle, 3=right)
 * @property string $value Event value submited from client 
 * @property mixed $result Stores the returned value from a callback method.
 */
class RaxanWebPageEvent extends RaxanSysEvent{

    public $target;
    public $uiDraggable,$uiHelper,$uiSender,$uiItem,$uiSortedItemIds;

    public $value, $button;
    public $pageX, $pageY;
    public $targetX, $targetY;
    public $which, $ctrlKey, $metaKey;

    public $result, $data;

    protected $iPage;

    public function __construct($type, &$page, $e = null) {
        parent::__construct($type);
        $this->iPage = $page;
        if ($e) {
            if (is_object($e)) $e = (array)$e;
            $props = explode(' ','result data value target button pageX pageY targetX targetY which ctrlKey metaKey uiDraggable uiHelper uiSender uiItem uiSortedItemIds');
            foreach($props as $prop) {
                if (isset($e[$prop])) $this->{$prop} = $e[$prop];
            }
        }
    }

    /**
     * Returns alphanumeric characters from the event value
     * @return string
     */
    public function alphanumericVal() {
        $s = Raxan::getSharedSanitizer();
        return $s->alphanumericVal($this->value);
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
     * Returns formatted a date value from the selected form element
     * @return string
     */
    public function dateVal($format = null) {
        $s = Raxan::getSharedSanitizer();
        return $s->dateVal($this->value,$format);
    }

    /**
     * Returns an integer from the callback value
     * @return string
     */
    public function intVal() {
        $s = Raxan::getSharedSanitizer();
        return $s->intVal($this->value);
    }

    /**
     * Returns a float from the callback value
     * @return string
     */
    public function floatVal() {
        $s = Raxan::getSharedSanitizer();
        return $s->floatVal($this->value);
    }

    /**
     * Returns matched characters from the event value
     * @param string $pattern Optional Regex pattern. Defaults to /[a-zA-Z0-9-.]/
     * @return string
     */
    public function matchVal($pattern = '/[a-zA-Z0-9-.]/') {
        $s = Raxan::getSharedSanitizer();
        return $s->matchVal($this->value,$pattern);
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
     * Returns the non-html text from the callback value
     * @return string
     */
    public function textVal() {
        $s = Raxan::getSharedSanitizer();
        return $s->textVal($this->value);
    }

    /**
     * Returns the event callback value
     * @return mixed
     */
    public function val() {
        return $this->value;
    }

}

/**
 * Used internally by RaxanWebPage
 * @property-read RaxanWebPage $page // Reference to Raxan Web Page
 */
class RaxanDOMDocument extends DOMDocument {

    public $charset;

    protected $css; // css: cache array for xpath queries
    protected $xPath, $source, $srcType, $init;
    protected $cssRegEx;
    protected $pageId;

    public function __construct($v = '1.0',$charset='UTF-8'){
        $this->charset = $charset;
        $this->init = false;
        //$this->formatOutput = false;
        //$this->preserveWhiteSpace = true;

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

    public function __get($name) {
        if ($name=='page')
            return RaxanWebPage::controller($this->pageId);
    }

    /**
     * Sets the page controller id
     * @param int $id
     */
    public function initPageController($id) {
        $this->pageId = $id;
    }

    /**
     * Return DOMNodeList query dom based on XPath
     * @param string $pth CSS selector
     * @param DOMNode $context Optional context node
     * @return DOMNodeList
     */
    public function xQuery($pth, DOMNode $context = null) {
        if (!$this->init) $this->initDOMDocument(); // init dom on first query
        if (!$context) $dl = $this->xPath->query($pth);
        else {
            if (substr($pth,0,1)=='/') $pth = '.'.$pth;
            $dl = $this->xPath->query($pth, $context);
        }
        if ((Raxan::$isDebug)) {
            if (!$dl) Raxan::debug('xQuery Error: Invalid XPath Expression: '.$pth);
            else Raxan::debug("XPath: \t Found ".$dl->length." \t $pth ");
        }
        if (!$dl) libxml_clear_errors(); // clear libxml error
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
     *  Sets or returns HTML/XML source - this is loaded only when the DOM is first queried
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
                else return is_file($s) ? file_get_contents($s) : '';
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
        libxml_use_internal_errors(true); // use internal xml error
        $s = trim($this->source); $stype = $this->srcType;
        if (($p = substr($s,0,7))=='http://' || $p=='https:/') $s = file_get_contents($s);
        else $isFile = (substr($s,0,1)=='<') ? false: true;
        if ($s) {   // empty string or file will generate an exception. See php manual DOMDocument::loadHTML()
            if ($stype=='xml') $s = (!$isFile) ? $this->loadXML($s) : @$this->load($s);
            else $s = (!$isFile) ?  $this->loadHTML($s) : @$this->loadHTMLFile($s);
        }
        $this->xPath = new DOMXPath($this);
        $this->init = true;
        libxml_clear_errors(); // clear libxml errors if any. The libxml_get_last_error() check is a little slower
    }

    // Protected Methods
    // --------------------------

    /**
     * CSS to Xpath - http://www.webdesignerforum.co.uk/index.php?showtopic=2325
     * Mod by rayond 10-dec-2008. fixed: last, first, added not
     * @return string
     */
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
                    else if( $m[1] == '#') {    // #id
                        $parts[] = "[@id='".$m[2]."'][1]";
                    } else if ( $m[1] == '.' ) {    // .class
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