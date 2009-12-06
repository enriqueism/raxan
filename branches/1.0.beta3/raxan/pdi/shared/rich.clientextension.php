<?php
/**
 * @package Raxan
 */

/**
 * Creates reference to client-side variables and callback functions
 */
class RichClientVariable {

    // Used internally during json encoding
    // See RichClientExtension::encodeVar
    public $_pointer;   

    protected static $vid = 0;
    protected $name,$value;

    public function __construct($val,$name = null, $isFunction = false, $registerGlobal = false) {
        $n = $this->name = $this->_pointer = ($name!==null) ? $name : '_v'.(self::$vid++);
        if (!$isFunction) $this->value = RichClientExtension::encodeVar($val);
        else {
            $fn  = trim($val);
            $this->value = (substr($fn,0,8)!='function')  ? 'function() {'.$val.'}' : $fn;
        }
        if (!$registerGlobal) RichWebPage::$vars[] = $n.'='.$this->value;
        else {
            $n = $this->name = 'window.'.$n;
            RichWebPage::$varsGlobal[] = $n.'='.$this->value;
        }
        
    }

    public  function __toString(){
        return $this->name;
    }
}


/***
 * Create a wrapper around client-side jQuery and native JavaScript function calls
 */
class RichClientExtension {

    protected static $scripts = array();

    protected $chain;

    // $ss - css selector
    public function __construct($ss,$context = null){
        RichWebPage::$actions[] = $this;
        if ($ss===''||$ss===null) $ss = '';
        else if ($ss=='this') $ss='_ctarget_';
        else if ($ss=='target') $ss='_target_';
        else $ss = ($ss=='document'||$ss=='window') ? $ss :
             self::encodeVar($ss);
        if ($context!==null) {
            if ($context=='this') $context='_ctarget_';
            else if ($context=='target') $context='_target_';
            else $context = ($context=='this'||$context=='document'||$context=='window') ? $context :
                self::encodeVar($context);
            if($context) $context = ','.$context;
        }
        $this->chain = '$('.$ss.$context.')';
    }

    public function __toString() {
        $str = $this->chain;
        $this->chain = '';  // reset chain
        return $str;
    }

    public function __call($name,$args) {
        $l = count($args);
        for($i=0;$i<$l;$i++) {
            $args[$i] = self::encodeVar($args[$i]);
        }
        $args = implode(',',$args);
        $this->chain.= '.'.$name.'('.$args.')';
        return $this;
    }

    /**
     * Displays a client-side alert message
     * @return null
     */
    public function alert($msg) {
        $this->chain  = (($this->chain=='$()') ? '':$this->chain.';').
            'alert("'.$this->escapeString($msg).'")';
        return $this;
    }

    /**
     * Returns client usergabgent object
     * @return Object
     */
    public function browser() {
        if ($this->chain=='$()') $this->chain = '';
        $ua = $_SERVER['HTTP_USER_AGENT'];
        $accept = $_SERVER['HTTP_ACCEPT'];
        $o = (object)array(
            "isSafari"  => $webkit = stripos($ua,'webkit')!==false,
            "isOpera"   => $opera = stripos($ua,'opera')!==false,
            "isMSIE"    => stripos($ua,'msie')!==false && !$opera,
            "isMozilla" => stripos($ua,'mozilla')!==false && !($webkit || stripos($ua,'compatible')!==false),
            "acceptHTML"=> stripos($accept,'text/html')!==false || stripos($accept,'xhtml')!==false,
            "acceptWap" => stripos($accept,'wml')!==false || stripos($accept,'vnd.wap')!==false
            // @todo: add version detection
        );
        return $o;
    }

    /**
     * Displays a client-side confirmation message with calback
     * @return null
     */
    public function confirm($msg,$okFn = null,$cancelFn = null) {
        $confirm = 'confirm("'.$this->escapeString($msg).'")';
        if ($okFn||$cancelFn) {
            $confirm = 'if ('.$confirm.')'.
            ($okFn instanceof RichClientVariable ? ' '.$okFn.'()':'').';'.
            ($cancelFn instanceof RichClientVariable ? ' else '.$cancelFn.'();' : '');
        }
        $this->chain  = (($this->chain=='$()') ? '':$this->chain.';').$confirm;
        return $this;
    }

    /**
     * Evaluates javascript code and return a new RichClientExtension object
     * @return RichClientExtension
     */
    public function evaluate($s){
        $this->chain  = (($this->chain=='$()') ? '':$this->chain.';').
            '$('.$s.')';
        return $this;
    }

    /**
     * Toggle class on mouse over and out
     * @return RichClientExtension
     */
    public function hoverClass($cls){
        $this->chain.= '.hover(function(){$(this).addClass("'.$cls.'")},function(){$(this).removeClass("'.$cls.'")})';
        return $this;
    }

    /**
     * Dynamically Load CSS files from client-side
     * @return null
     */
    public function loadCSS($src,$ext = false){
        $ext = $ext===true ? 'true' : 'false';
        if ($this->chain=='$()') $this->chain = '';
        $this->chain.= ';h.css("'.$this->escapeString($src).'",'.$ext.')';
        return $this;
    }
    
    /**
     * Dynamically Load script files from client-side
     * @return null
     */
    public function loadScript($src,$ext = false, $fn = null){
        $ext = $ext===true ? 'true' : 'false';
        if ($this->chain=='$()') $this->chain = '';
        $fn = $fn!==null ? ','.self::encodeVar($fn) : '';
        $this->chain.= ';h.include("'.$this->escapeString($src).'",'.$ext.$fn.');';
        return $this;
    }

    /**
     * Opens a popup window and returns reference to window document
     * @return RichClientExtension
     */
    public function popup($url,$name = '',$attributes = '',$errorMsg = ''){
        if ($this->chain=='$()') $this->chain = '';
        $blank = empty($url) ? 1: 0;
        $err = ($errorMsg) ? 'else alert("'.$this->escapeString($errorMsg).'")' : '';
        $this->chain.= ';var _d = "",_w = window.open("'.$this->escapeString($url).'","'.$this->escapeString($name).'"'.
            ($attributes ? ',"'.$this->escapeString($attributes).'"' : '').');'.
            'if (_w) {_d =_w.document;if(!_d.isLoaded && '.$blank.'){_d.open();_d.close();_d.isLoaded=1}}'.
            $err.';$(( _d ?_d.body : "empty"))';
        return $this;
    }

    /**
     * Displays a client-side prompt (input box) with callback
     * @return null
     */
    public function prompt($msg,$default = '',$fn = null) {
        $val = $default ? ',"'.$this->escapeString($default).'"' :'';
        $prompt = 'prompt("'.$this->escapeString($msg).'"'.$val.')';
        if ($fn) {
            $prompt = 'var _p='.$prompt.'; if (_p)'.
            ($fn instanceof RichClientVariable ? ' '.$fn.'(_p)':'').';';
        }
        $this->chain  = (($this->chain=='$()') ? '':$this->chain.';').$prompt;
        return $this;
    }

    /**
     * Redirect Client to the sepecified url
     * @return null
     */
    public function redirectTo($url) {
        $redirect = 'window.location = "'.$this->escapeString($url).'"';
        $this->chain  = (($this->chain=='$()') ? '':$this->chain.';').$redirect;
        return $this;
    }

    /**
     * Change switchboard action and reloads the current page
     * @return null
     */
    public function switchTo($action) {
        $url = RichAPI::currentURL();
        if (strpos($url,'sba=')!==false) $url = trim(preg_replace('#sba=[^&]*#','',$url),"&?\n\r ");
        $url.= (strpos($url,'?') ? '&' : '?').'sba='.$action;
        return $this->redirectTo($url);
    }

     // Protected methods
     // -----------------------------

    /**
     * Returned javascript escaped string
     */
 	protected function escapeString($txt) {
        return RichAPI::escapeText($txt);
 	}

     // Static methods
     // -----------------------------

    /**
     * Returned enccoded javascript value 
     */
    public static function encodeVar($v) {
        if (!is_numeric($v) && is_string($v)) {
            $v = '"'.RichAPI::escapeText($v).'"';
        }
        else if ($v instanceof RichClientExtension ||
                 $v instanceof RichClientVariable) {
            // pass chain as value
            $v = $v.'';
        }
        else if ($v===true) $v = 'true';
        else if ($v===false) $v = 'false';
        else if (!is_scalar($v)) {
            // encode arrays and objects
            $v = RichAPI::JSON('encode',$v);
            // replace _pointer hash array with variable name due to json encoding.
            // See RichClientVariable->_pointer
            if (strpos($v,':{"_pointer":"_v')) {
                $v = preg_replace('/:\{"_pointer"\:"(_v[0-9]+)"\}/', ':\1', $v);
            }
            if(!$v) $v = '{}';
        }
        return $v;
    }
    
}

?>