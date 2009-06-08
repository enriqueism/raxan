<?php
/**
 * Rich Web Page 
 * Copyright Raymond Irving 2008
 * @date: 10-Dec-2008
 * @package Raxan
 */



// Load Rich Element
include_once(dirname(__FILE__).'/rich.element.php');

/**
 * Rch web Page query shortcut
 * @param $selector Mixed - css selector, html, DOMNode
 * @param $content DOMNode
 * @return RichElement  
 */
function P($selector = '', $context = null){
    return RichWebPage::Controller()->find($selector,$context);
}

/**
 * Rich Client Extention Wrapper
 * @param $selector Mixed - css selector, html
 * @return RichClientExtentions
 */
function C($selector = '', $context = null){
    return RichWebPage::Controller()->client($selector,$context);
}

/**
 * Creates a reference to a client-side function
 * @return RichClientVariable
 */
function _fn($str,$name = null,$registerGlobal = false){
     RichWebPage::LoadClientExtension();
    $v = new RichClientVariable($str,null,true);
    return $v;
}

/**
 * Creates a reference to the client-side $trigger function. Used to trigger an event callback to the server from the client.
 * @return RichClientVariable
 */
function _event($type,$value = null,$target = 'page'){
    // get value from first argument. If first argument is an event then pass event to trigger
    $code = 'var e,v=arguments[0],data=arguments[1]; if (v && v.stopPropagation) {e=v;v=null};';
    $code.= '$trigger("'.RichAPI::escapeText($target).'",'.
         ($type!==null ? '"'.RichAPI::escapeText($type).'"' : '""').','.
         ($value!==null ? '"'.RichAPI::escapeText($value).'"' : 'v').',null,{event:e,data:data}'.
    ')';
    return _fn($code);
}

/**
 * Creates a reference to a client-side javascript variable
 * @return RichClientVariable
 */
function _var($str, $name = null, $registerGlobal = false){
    RichWebPage::LoadClientExtension();
    $v = new RichClientVariable($str, $name, false,$registerGlobal);
    return $v;
}

/**
 * Use for traversing and manipulating HTML DOM elements
 */
class RichWebPage extends RichAPIBase implements ArrayAccess  {

    public static $vars = array();          // client-side local variables.
    public static $varsGlobal = array();    // client-side global variables.
    public static $actions = array();       // client-side actions

    protected static $eventId = 1;
    protected static $cliExtLoaded = false;
    protected static $mPage = null;      // page controller or master page

    public $clientPostbackUrl;
    public $isPostback, $isCache, $isCallback, $isAuthorized;
    public $responseType = 'html';      // html,xhtml,xhtml/html,xml,wml,

    protected $resetDataOnFirstLoad;    // reset page data on first load
    protected $updateFormOnPostback;    // set form values on post back
    protected $localizeOnResponse;      // Automatically insert language strings into element with the langid attribute set to valid locale key/value pair
    protected $showRenderTime = false;

    /**
     * @var RichDOMDocument $doc 
     */
    protected $doc = null;   // document
    protected $charset, $headers, $_contentType;
    protected $name, $output;
    protected $scripts = array();
    protected $scriptBehind = '';
    protected $request;
    protected $startTime;
    protected $flyDOM;

    // Page request handlers
    protected function _init() {}
    protected function _authorize() { return true; }
    protected function _load() {}
    protected function _prerender() {}
    protected function _postrender() {}
    protected function _reply() {}
    protected function _finalize() {}

    public function __construct($xhtml='', $charset = null, $type=null) {
        parent::__construct();

        // initailize the RichAPI
        if (!RichAPI::$isInit) RichAPI::init();

        // @todo: optimize clientPostbackUrl ?
        $qs = isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING']:'';
        $this->clientPostbackUrl = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'].$qs : '';
        
        // load default settings, charset, etc
        $this->charset = $charset ? $charset : RichAPI::config('site.charset');
        $this->doc = self::CreateDOM($xhtml, $this->charset, $type);
        $this->doc->page = $this;
        if (self::$mPage==null) self::Controller($this);

        // init value
        $this->isPostback = count($_POST) ? true : false;
        $this->isCallback = isset($_POST['_ajax_call_']) ? true : false;

        // call page  _init, _load 
        $this->_init();
        $this->isAuthorized = $this->_authorize();
        if ($this->isAuthorized) {
            if ($this->resetDataOnFirstLoad && !$this->isPostback) $this->removeData();    // reset page data
            $this->_load();
            if ($this->updateFormOnPostback && $this->isPostback) $this->updateFields(); // update form fields on postbacks
        }
        else {            
            // if authorization failed then return HTTP: 403
            RichAPI::sendError(RichAPI::locale('unauth_access'), 403);
        }

        // set start time
        if ($this->showRenderTime) $this->startTime = RichAPI::startTimer();

        return $this;
    }

    public function __destruct() {
        $this->_finalize();
        $this->doc = null; // discard dom object
    }

    /**
     * Adds an HTTP header to the page
     * @return RichWebPage
     */
    public function addHeader($hdr) {
        if (!$this->headers) $this->headers = array();
        $this->headers[] = $hdr;
        return $this;
    }

    /**
     * Adds a block of CSS to the webpage
     * @return RichWebPage
     */
    public function addCSS($style) {
        $this->loadCSS('<style type="text/css"><![CDATA[ '."\n".$style."\n".' ]]></style>');
        return $this;
    }

    /**
     * Adds a block of Javascript to the webpage
     * @return RichWebPage
     */
    public function addScript($script) {
        $this->loadScript('<script type="text/javascript"><![CDATA[ '."\n".$script."\n".' ]]></script>');
        return $this;
    }

    /**
     * Appends an html view to the page body element
     * @return RichWebPage
     */
    public function appendView($view) {
        $view = file_get_contents(RichAPI::config('views.path').$view);
        if ($view) {
            $this->find('body')->append($view);
        }
        return $this;
    }

    /**
     * Binds an event to the page body element
     * @return RichWebPage
     */
    public function bind($type,$data = null ,$fn = null) {
        $this->find('body')->bind($type, $data, $fn);
        return $this;
    }

    /**
     * Binds an array of elements to a client-side event - used by RichElement
     * @return RichWebPage
     */
    public function bindElements(&$elms,$type,$data,$fn = null,$selector = '',$delegate = false) {
        if (!$this->events) $this->events = array();
        if (!isset($this->events['selectors'])) $this->events['selectors'] = array();
        $e = & $this->events; $type = trim($type);
        $accessible = $extras = false;
        $selector = trim($selector);
        $local = $global = $ids = $prefTarget = $serialize = $value = $script =  '';
        $delay = $autoDisable = $autoToggle = $inputCache = $repeat = $extendedOpt = '';
        if (substr($type,-6)=='@local') {$type = substr($type,0,-6); $local = true; } // check if local access
        elseif (substr($type,-7)=='@global') {$type = substr($type,0,-7); $global = true; } // check if global access
        // setup event options
        $hndPrefix = 'e:'; // flag events handlers as external (client or global access)
        if ($local) $hndPrefix = 'l:'; // flag event as local (private)
        else {
            if ($fn===null && is_array($data)) {
                $fn = isset($data['callback']) ? $data['callback'] : $data;
                $value = isset($data['value']) ? $extras = $data['value'] : null;
                $prefTarget = isset($data['prefTarget']) ? $extras = $data['prefTarget'] : null;
                $serialize = isset($data['serialize']) ? $extras = $data['serialize'] : null;
                $script = isset($data['script']) ? $extras = $data['script'] : null;
                $delay = isset($data['delay']) ? $extras = $data['delay'] : null;
                $autoDisable = isset($data['autoDisable']) ? $extras = $data['autoDisable'] : null;
                $autoToggle = isset($data['autoToggle']) ? $extras = $data['autoToggle'] : null;
                $inputCache = isset($data['inputCache']) ? $extras = $data['inputCache'] : null;
                $accessible = isset($data['accessible']) && $data['accessible']==true ? true : false;
                $repeat = isset($data['repeat']) ? $data['repeat'] : null;
                $data = isset($data['data']) ? $data['data'] : null; // get data object
                $extendedOpt = ($delay||$autoDisable||$autoToggle||$inputCache||$repeat) ? true : false;
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
                '_extendedOpt' => $extendedOpt // this will cause the system to append options to last paramater of $bind
            );
        } 
        // bind elements to event
        if (substr($type,0,1)=='#') $type = substr($type,1); // remove ajax event callback symbol (#)
        $callback = ($fn===null) ? array($data,null,$global) : array($fn,$data,$global);
        // suport for .function_name - will callabck a method on the current page
        if (is_string($callback[0]) && substr($callback[0],0,1)=='.') {
            $callback[0] = array($this, substr($callback[0],1));
        }
        foreach($elms as $n) {
            $id = $n->getAttribute('id');
            if (!$id) $n->setAttribute('id', $id = 'e0'.self::$eventId++); // auto assign id
            if (!$local && !$selector) {
                if(!$ids) $ids = array();
                $ids[]='#'.$id;
            }
            $hkey = $hndPrefix.$id.'.'.$type;
            if (!isset($e[$hkey])) $e[$hkey] = array();
            $e[$hkey][] = & $callback;
            // make forms and links accessible
            if ($accessible) {  
                $name = $n->nodeName;
                if ($name=='a') {           // links
                    $href = explode('#',$n->getAttribute('href'),2);
                    $val = !$value && isset($href[1]) ? trim($href[1]) : $value;  // use anchor (hash) as value
                    $query = explode('?',$href[0],2);
                    $href[0] = $query[0].'?'.(isset($query[1]) ? $query[1].'&' : '').
                    '_e[type]=click&'.'_e[target]='.$id.($val ? '&_e[value]='.urlencode($val) : '').
                    '&_e[tok]='.RichAPI::$postBackToken;
                    $url = implode('#',$href);
                    $n->setAttribute('href',$url);
                }
                elseif ($name=='form') {    // form
                    $htm = '<input type="hidden" name="_e[type]" value="submit" />'."\n".
                           '<input type="hidden" name="_e[target]" value="'.$id.'" />'."\n".
                           '<input type="hidden" name="_e[tok]" value="'.RichAPI::$postBackToken.'" />'."\n".
                           ($value ? '<input type="hidden" name="_e[value]" value="'.htmlspecialchars($value).'" />' :'');
                    $f = $this->doc->createDocumentFragment();
                    $f->appendXML($htm);
                    $n->appendChild($f);
                }
            }
        }
        $selector = $ids ? implode(',',$ids): $selector;
        if (!$local && $selector) {
            $skey = 's:'.$selector.':'.$type;
            if (!isset($e['selectors'][$selector])) $e['selectors'][$selector] = array();
            // check if a selector options already exist for this event
            if (!isset($e[$skey]) || ($extras && !in_array($opts,$e['selectors'][$selector]))) {
                $e['selectors'][$selector][] =  $opts; // attach event options
                $e[$skey] = true;
            }
            // bind event through a delegate
            if ($delegate) {
                $hkey = $hndPrefix.$selector.'.'.$type;
                if (!isset($e[$hkey])) $e[$hkey] = array();
                $e[$hkey][] = & $callback;
            }
        }
        return $this;
    }

    /**
     * Returns an instance of the RichClientExtension class
     * @return RichClientExtension
     */
    public function client($selector = null, $context = null) {
        self::LoadClientExtension();
        return new RichClientExtension($selector,$context);
    }

    /**
     * Returns data sanitizer object with get and/or post values. Defaults to post values
     * @return RichDataSanitizer
     */
    public function clientRequest($incGetRequest = false) {
        $array = $incGetRequest ? array_merge($_GET,$_POST): null;
        if (!isset($this->request)) $this->request = new RichDataSanitizer($array,$this->charset);
        return $this->request;
    }


    /**
     * Returns or sets the main (body or card) content for an HTML or WML document
     * @return RichWebPage or HTML String
     */
    public function content($html = null) {
        $tag =  $this->responseType=='wml' ? 'card:first' : 'body';
        $c = $this->find($tag)->html($html);
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
     * @return RichWebPage
     */
    public function contentType($type,$charset = null) {
        $charset =  ($charset!==null) ? ($this->charset = $charset) :$this->charset;
        $this->_contentType = 'Content-Type: '.$type.'; charset='.$charset .'';
        return $this;
    }

    /**
     * Makes the page content downloadable
     * @return RichWebPage
     */
    public function contentDisposition($fileName,$transEncode = null, $fileSize = null) {
        $this->addHeader('Content-Disposition: attachment; filename="'.$fileName.'"');
        if ($transEncode) $this->addHeader('Content-Transfer-Encoding: '.$transEncode);
        if ($fileSize) $this->addHeader('Content-Length: '.$fileSize);
    }

    /**
     * Sets or returns names data value
     * @return RichWebPage or Mixed
     */
    public function &data($key,$value = null){
        $id = 'pdiWPage-'.($this->name ? $this->name : $this->objId);
        return RichAPI::data($id,$key,$value);
    }

    /**
     * Returns RichDOMDocument instance
     * @return RichDOMDocument
     */
    public function document() {
        return $this->doc;
    }

    /**
     * Search the page for matched elements
     * @return RichElement
     */
    public function find($css,$context = null){
        $context = $context ? $context : $this->doc;
        return new RichElement($css,$context);
    }

    /**
     * Find and returns a RichElement by Id
     * @return RichElement
     */
    public function findById($id){
        $elm = $this->getElementById($id);
        if (!$elm) $elm = ' ';
        return new RichElement($elm);
    }

    /**
     * Find and return matched elements based on specified xpath
     * @return RichElement
     */
    public function findByXPath($pth){
        $dl = $this->doc->xQuery($pth);
        if (!$dl) $dl = ' ';
        return new RichElement($dl);
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
        if ($this->flyDOM) return $this->flyDOM;
        else {
            $dom = new DOMDocument('1.0',$this->charset);
            $dom->loadXML(self::PageCode('html',$this->charset));
            return $this->flyDOM = $dom;
        }
    }

    /**
     * Handle client Postback/Callback events
     * @return RichWebPage
     */
    protected function handleClientEventRequest(&$eventResult){
        $events = & $this->events;
        $e = isset($_POST['_e']) ? $_POST['_e'] : null;
        if (!$e && isset($_GET['_e'])) $e = $_GET['_e'];
        if ($e && $events) {
            $rt = '';
            // get handlers
            $id = isset($e['target']) ? trim($e['target']) : '';    // defaults to nothing. Dont raise an event if a target was not specified
            $type = isset($e['type']) ? $e['type'] : 'click';
            $tokenMatch = isset($e['tok']) && $e['tok']==RichAPI::$postBackToken ? true : false ;
            $hnd = 'e:'.$id.'.'.$type;
            $hndlrs = isset($events[$hnd]) ? $events[$hnd] : null;
            if ($hndlrs) {
                // check if delegate
                $delegate = isset($events['selectors'][$id]) ? true : false;
                if($delegate) $e['target'] = null;
                else $e['target'] = $this->getElementById($id); // lookup target element
                if (isset($e['uiSender'])) $e['uiSender'] = $this->getElementById($e['uiSender']);
                if (isset($e['uiHelper'])) $e['uiHelper'] = $this->getElementById($e['uiHelper']);
                if (isset($e['uiDraggable'])) $e['uiDraggable'] = $this->getElementById($e['uiDraggable']);
                $e = new RichWebPageEvent($type,$this,$e); // convert data from client to event object
                foreach ($hndlrs as $hnd) {
                    if ($hnd[2] || $tokenMatch) { // only invoke if global access or token matched
                        if (!$this->triggerHandle($hnd, $e)) break;
                    }
                }
                $eventResult = $e->result;
            }
        }
        return $this;
    }

    /**
     * Localize nodes with the langid attribute set to a valid lcoale key/value pair
     * @return RichWebPage
     */
    protected function handleNodeL10n(){
        if (!$this->localizeOnResponse) return true;
        $nl = $this->doc->xQuery('//*[@langid]');
        self::NodeL10n($nl);
        return $this;
    }

    /**
     * Send debugging information to client
     * @return RichWebPage
     */
    protected function handleDebugResponse(){
        $d = RichAPI::debugOutut();
        $o = RichAPI::config('debug.output');
        if ($o=='popup'||$o=='embedded')
            $d = '<pre><code><strong>Debug Output</strong><hr />'.$d.'</code></pre>';
        if ($o=='alert') C()->alert($d);
        else if ($o=='popup') C()->popup('','rxPDI_Debug','width=500, scrollbars=yes')->html($d);
        else if ($o=='console') C()->evaluate('(window.console)? window.console.log("'.RichAPI::escapeText($d).'"):""');
        else if ($o=='embedded') {
            if ($this->isCallback) C('#rxPDI_Debug')->remove(); // remove if in callback
            C('body')->append('<div id="rxPDI_Debug" style="padding:5px;border:3px solid #ccc;background:#fff"></div>');
            C('#rxPDI_Debug')->html($d);
        }
        return $this;
    }


     /**
     * Inserts JavaScript into the html document
     * @return RichWebPage
      */
    protected function handleScripts() {
        $inc = $raxan = $css = $js = '';
        $actions = $this->buildActionScripts();
        // build scripts
        $url = RichAPI::config('raxan.url');
        foreach($this->scripts as $s=>$i) {
            $tag = substr($s,0,4); $s = substr($s,4);
            if ($tag=='JSI:'){
                if ($i===1) $js.=$s;
                else $inc.= 'h.include("'.$s.'"'.($i ? ',true':'').');';
            }
            elseif ($tag=='CSS:') {
                if ($i===1) $css.=$s;
                else $css.= '<link href="'.$url.'styles/'.$s.'.css" type="text/css" rel="stylesheet" />'."\n";
                if ($s=='master') $css.='<!--[if IE]><link href="'.$url.'styles/master.ie.css" type="text/css" rel="stylesheet" /><![endif]-->'."\n";
            }
        }
        $sb = $this->scriptBehind;
        if ($sb || $inc || $actions) {
            $raxan = '<script src="'.$url.'startup.js">'.$sb.'</script>'."\n";
            if ($inc || $actions) $inc =  '<script type="text/javascript"><![CDATA[ var _PDI_URL ="'.$this->clientPostbackUrl.'",h=Raxan;'.$inc.$actions." ]]></script>\n";
        }
        $inc = $css.$raxan.$inc.$js;
        if ($inc) $this->find('head:first')->append($inc);

        return $this;
    }

    /**
     *  Halt and exit page processing while displaying a message
     */
    public function halt($msg = null) {
        exit($msg);
    }

    /**
     *  Add CSS stylesheet to document
     * @return RichWebPage
     */
    public function loadCSS($css,$extrn = false) {
        $css = trim($css);
        $embed = (stripos($css,'<')!==false);
        if (!isset($this->scripts['CSS:'.$css])) {
            $this->scripts['CSS:'.$css] = $embed ? 1 : $extrn;
        }
        return $this;
    }

    /**
     *  Add Javascript to document
     * @return RichWebPage
     */
    public function loadScript($js,$extrn = false) {
        $js = trim($js);
        $embed = (stripos($js,'<script')!==false);
        if (!isset($this->scripts['JSI:'.$js])) {
            $this->scripts['JSI:'.$js] = $embed ? 1 : $extrn;
        }
        return $this;
    }

    /**
     * Loads JavaScript Code behind file
     * @return RichWebPage
     */
    public function loadScriptBehind($pth = null) {
        if ($pth===null) $pth = '-';
        else if ($pth && substr($pth,-1)=='/') $pth.='-';
        $this->scriptBehind  =  '/'.$pth.'/';
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
     * @return RichWebPage
     */
    public function registerEvent($type,$data,$fn = null){
        $elms = array();
        $this->bindElements($elms, $type, $data, $fn, 'page',true);
        return $this;
    }

    /**
     * Render and return html content
     * @return String     
     */
    public function render($type = 'html') {
        $this->output = '';
        $this->handleClientEventRequest($result); // handle client events request

        // respond to ajax callback
        if ($this->isCallback) {
            $charset = $this->charset;
            if (RichAPI::$isDebug) $this->handleDebugResponse();
            $this->handleNodeL10n();    // proccess tags with langid
            $a = $this->buildActionScripts(false); // exclude events from actions
            $rt = isset($result) ? $result : '';
            $json =  RichAPI::JSON('encode',array(
                '_result' => $rt,
                '_actions' =>  iconv($charset,$charset.'//IGNORE',$a)  // clean up action scripts charset encoding
            ));
            if ($_POST['_ajax_call_']=='iframe') {
                $html = str_replace('UTF-8',$this->charset,self::PageCode());
                $json = '<form><textarea>'.htmlspecialchars($json,null,$this->charset).'</textarea></form>';
                $json = str_replace('</body>',$json.'</body>',$html);
            }
            $this->output = $json;
        }
        // response to standard postback
        elseif (!$this->isCallback) {
            $this->_prerender();         // call _prerender event
            if (RichAPI::$isDebug) $this->handleDebugResponse();
            $this->handleScripts();      // build scripts
            $this->handleNodeL10n();    // proccess tags with langid
            $this->output = $this->doc->source(null,$type);
            $this->_postrender();        // call _postrender event
        }
        
        // return html or json string
        return $this->output; 
    }
    
    /**
     * Sends data to client
     * @return RichWebPage
     */
    public function reply($responseType = null) {

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
                if (!$this->_contentType) $this->contentType('text/html');
                if ($rt=='html') { // change existing doctype to HTML5 Doctype
                    $content = preg_replace('/<!DOCTYPE[^>]*>/si',"<!DOCTYPE html >",$content,1);
                }
                break;
        }
        
        // send headers
        header($this->_contentType); 
        if ($this->headers) foreach($this->headers as $s) @header($s);
        
        // send content to client (eg. html, xml, json, etc)
        echo $content;
        $this->_reply(); // call _reply event
        if ($this->showRenderTime && !$this->isCallback)
            echo 'Render Time: '.RichAPI::stopTimer($this->startTime);

        return $this;
    }

    /**
     * Remove Page data
     * @return RichWebPage     
     */
    public function removeData($name = null){
        $id = 'pdiWPage-'.($this->name ? $this->name : $this->objId);
        RichAPI::removeData($id,$name);
        return $this;
    }

    /**
     * Set or returns the data store name for the page
     * @return RichWebPage or String
     */
    public function storeName($n = null) {
        if ($n===null) return $this->name;
        else $his->name = $n;
        return $this;
    }

    /**
     * Set or returns the page html/xml source. Source can be a file, url or <html> tags
     * @return RichWebPage or String
     */
    public function source($src = null,$srcType = 'html') {
        if ($src===null) return $this->doc->source(null,$srcType);
        else {
            if ($src=='wml:page') {
                $src = self::PageCode('wml');
                $this->responseType = 'wml';$srcType = 'xml';
            }
            elseif ($src=='html:page') {
                $src = self::PageCode('html');
                $this->responseType = $srcType = 'html';
            }
            $this->doc->source($src,$srcType);
            return $this;
        }
    }

    /**
     * Transfer page control to the specified php file
     */
    public function transferTo($file){
        include_once($file);
        exit();
    }

    /**
     * Trigger events on the specified elements - used by RichElement
     * @return RichWebPage
     */
    public function triggerEvent(&$elms,$type,$args = null) {
        $events = & $this->events;
        $e = new RichEvent($type); // convert to object
        foreach($elms as $n) {
            $id = $n->getAttribute('id');
            $hnd = 'e:'.$id.'.'.$type; $lhnd = 'l:'.$id.'.'.$type;
            $hndlrs = isset($events[$hnd]) ? $events[$hnd] : array();
            $hndlrs = isset($events[$lhnd]) ? array_merge($hndlrs,$events[$lhnd]) : $hndlrs ; // merge local events handlers
            if ($hndlrs) {
                foreach ($hndlrs as $hnd) {
                    if (!$this->triggerHandle($hnd, $e,$args)) break;
                }
            }
        }
        return $this;
    }

    /**
     * Returns or sets the title for an HTML page
     * @return RichWebPage or String
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
     * @return RichWebPage
     */
    public function updateFields() {
        if ($this->isPostback)
            $this->find('form [name]')->val($_POST);
        return $this;
    }

    /**
     * Remove event handlers from elements - used by RichElement
     * @return RichWebPage 
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
     * @return RichElement
     */
    public function offsetSet( $selector, $html) { return $this->find($selector)->html($html); }
    /**
     * Return selectd elements
     * @return RichElement
     */
    public function offsetGet( $selector ) { return $this->find($selector); }
    /**
     * Remove selectd elements
     * @return RichWebPage
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
        $actions = '';
        if ($includeEvents) {
            if (isset($this->events['selectors'])) {
                $sels = $this->events['selectors'];
                foreach($sels as $sel=>$opts) {
                    // no need to bind page registered events on client
                    if ($sel!='page') foreach ($opts as $opt) {
                        $x = ($opt['delegate'] ? ',true' : '');
                        // setup extended options
                        if ($opt['_extendedOpt']) {
                            $x = array();
                             if ($opt['delegate']) $x[] = 'dt:true';  // delegate
                             if ($opt['delay']) $x[] = 'dl:\''.$opt['delay'].'\'';
                             if ($opt['autoDisable']) $x[] = 'ad:\''.$opt['autoDisable'].'\'';
                             if ($opt['autoToggle']) $x[] = 'at:\''.$opt['autoToggle'].'\'';
                             if ($opt['inputCache']) $x[] = 'ic:\''.$opt['inputCache'].'\'';
                             if ($opt['repeat']) $x[] = 'rpt:'.($opt['repeat']===true ? 'true' : (int)$opt['repeat']).'';
                             $x = ',{'.implode(',',$x).'}';
                        }
                        $script = is_array($opt['script']) ? RichAPI::JSON('encode',$opt['script']): '"'.RichAPI::escapeText($opt['script']).'"';
                        $actions.='$bind("'.$sel.'","'.
                            $opt['type'].'","'.
                            RichAPI::escapeText($opt['value']).'","'.
                            RichAPI::escapeText($opt['serialize']).'","'.
                            $opt['ptarget'].'",'.
                            $script.
                            $x.');';
                    }
                }
            }
        }
        $vars = implode(',',RichWebPage::$vars);
        $varsGlobal = implode(',',RichWebPage::$varsGlobal);
        if ($vars) $vars = 'var '.$vars.';';
        if ($varsGlobal) $vars.= $varsGlobal.';';
        if (PHP_VERSION_ID < 50200) {   // fix for issue #4
            foreach(RichWebPage::$actions as $i=>$act)
                if ($act) RichWebPage::$actions[$i] = $act->__toString();
        }
        $actions.= $vars.implode(';',RichWebPage::$actions);
        if (!$includeEvents) return $actions;
        else if ($actions){
            $this->loadScript('jquery');  // we need jQuery to handle client-side bindings
            $actions = 'html.ready(function() {'.$actions.'});';
        }
        return $actions;
    }

    /**
     * Triggers event callback handler
     * @return Boolean - Returns true if not $e->isStopPropagation
     */
    protected function triggerHandle($hnd,$e,$args = null) {
        $fn = $hnd[0]; $data = $hnd[1];
        $e->data = $data;
        if (!is_callable($fn)) {
            $rt = null;
            throw new Exception('Unable to execute callback function or method: '.print_r($fn,true));
        }
        elseif (is_string($fn)) $rt = $fn($e,$args);  // function callback
        else  $rt = $fn[0]->{$fn[1]}($e,$args);       // object callback
        if ($rt!==null) $e->result = $rt;
        if (!$e->isStopPropagation) return true;
        else return false;
    }


    // Static Functions
    // -----------------------


    /**
     * Creates and return a DOMDocument
     * @return RichDOMDocument
     */
    public static function CreateDOM($html,$charset = 'UTF-8',$type = null) {
        $dom = new RichDOMDocument('1.0',$charset);
        $dom->formatOutput = false;
        if ($type!='xml' && $type!='html')
            $type = ($html && substr($html,-4)=='.xml') ? 'xml' : 'html';
        $html = $html ? $html : self::PageCode();
        $dom->source($html,$type);
        return $dom;
    } 

    /**
     * Returns the Page controller object
     * @return RichDOMDocument
     */
    public static function Controller($page = null) {
        if ($page && ($page instanceof RichWebPage)) self::$mPage = $page;
        else if (self::$mPage==null) self::$mPage = new RichWebPage();
        return self::$mPage;
    }


    /**
     * Creates and returns an instance of the web page class
     * @return RichWebPage
     */
    public static function Init($pageClass) {
        if (class_exists($pageClass)) {
            $page = new $pageClass();
            return $page->reply();
        }
        else{
            trigger_error('Class \''.$pageClass.'\' not found',E_USER_WARNING);
        }
    }

    /**
     * Loads the RichClientExtension class
     */
    public static function LoadClientExtension() {
        if (self::$cliExtLoaded) return;
        require_once(RichAPI::config('base.path').'shared/rich.clientextension.php');
        self::$cliExtLoaded = true;
    }

    /**
     * Localize nodes
     */
    public static function NodeL10n($nodes){
        if ($nodes) foreach ($nodes as $n) {
            $id = $n->getAttribute('langid');
            $v = RichAPI::locale($id);
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
    protected static function PageCode($type = 'html',$charset='UTF-8'){
        $nl = "\n";
        if ($type=='wml') return '<?xml version="1.0" standalone="yes"?>'.$nl.
            '<!DOCTYPE wml PUBLIC "-//WAPFORUM//DTD WML 1.1//EN" "http://www.wapforum.org/DTD/wml_1.1.xml">'.$nl.
            '<wml><card>'.$nl.'</card></wml>';
        else return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'.$nl.
            '<html>'.$nl.'<head>'.$nl.'<meta http-equiv="Content-Type" content="text/html; charset='.$charset.'" />'.
            '    <title></title>'.$nl.'</head>'.
            '<body></body>'.$nl.'</html>';
    }


}


/**
 *  Used internally by RichWebPage
 */
class RichWebPageEvent {

    /**
     * @var $target DOMElement */
    public $target;
    public $uiDraggable,$uiHelper,$uiSender;

    public $type;
    public $result = null;     // returned value from previous handler
    public $data;
    public $value;
    public $button;
    public $pageX;
    public $pageY;
    public $targetX;
    public $targetY;
    public $which;
    public $ctrlKey;
    public $metaKey;
    public $isStopPropagation = false;

    /**
     *  @var $target RichWebPage */
    protected $iPage;    

    public function __construct($type, &$page, $e = null) {
        $this->type = $type;
        $this->iPage = $page;
        if ($e) {
            $props = explode(' ','data value target button pageX pageY targetX targetY which ctrlKey metaKey uiDraggable uiHelper uiSender');
            foreach($props as $prop) {
                if (isset($e[$prop])) $this->{$prop} = $e[$prop];
            }
        }
    }

    /**
     * Returns an instance of the RichClientExtension class
     * @return RichClientExtension
     */
    public function client($selector = null, $context = null) {
        RichWebPage::LoadClientExtension();
        return new RichClientExtension($selector,$context);
    }

    /**
     * Returns an instance of the rich web page controller that's associated with this event
     * @return  RichWebPage
     */
    function page($css = null) {
        if ($css) return  $this->iPage[$css];
        else return  $this->iPage;
    }

    /**
     * Stops event propagation
     * @return  RichEvent
     */
    public function stopPropagation() {
        $this->isStopPropagation = true;
        return this;
    }
}

/**
 * Used internally by RichWebPage */
class RichDOMDocument extends DOMDocument {

    public $charset;
    public $page;   // Reference to Rich Web Document

    protected $css; // css: cache array for xpath queries
    protected $xPath, $source, $srcType, $init;

    public function __construct($v = '1.0',$charset='UTF-8'){
        $this->charset = $charset;
        $this->init = false;
        // $this->formatOutput = true;
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
        if ((RichAPI::$isDebug)) {
            if (!$dl) RichAPI::debug('xQuery Error: Invalid XPath Expression: '.$pth);
            else RichAPI::debug("XPath: \t Found ".$dl->length." \t $pth ");
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
        if (substr($s,0,7)=='http://') $s = file_get_contents($s);
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
        $reg['element']         = "/^([#.]?)([a-z0-9\\*_-]*)((\|)([a-z0-9\\*_-]*))?/i";
        $reg['attr1']           = "/^\[([^\]]*)\]/i";
        $reg['attr2']           = '/^\[\s*([^~\*\!\^\$=\s]+)\s*([~\*\^\!\$]?=)\s*"([^"]+)"\s*\]/i';
        $reg['attrN']           = "/^:not\((.*?)\)/i";
        $reg['psuedo']          = "/^:([a-z_-])+/i";    // empty, even, odd
        $reg['not']             = "/^:not\((.*?)\)/i";
        $reg['contains']        = "/^:contains\((.*?)\)/i";
        $reg['gtlt']            = "/^:([g|l])t\(([0-9])\)/i";
        $reg['last']            = "/^:(last\([-]([0-9]+)\)|last)/i";
        $reg['first']           = "/^:(first\([+]([0-9]+)\)|first)/i";
        $reg['psuedoN']         = "/^:nth-child\(([0-9])\)/i";
        $reg['combinator']      = "/^(\s*[>+\s])?/i";
        $reg['comma']           = "/^\s*,/i";

        $index = 1;
        $start = $inludeSelf ? 'descendant-or-self::' : '//';
        $parts = array($start);
        $lastRule = NULL;

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
                        $parts[] = "[contains(@class, '".$m[2]."')]";
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