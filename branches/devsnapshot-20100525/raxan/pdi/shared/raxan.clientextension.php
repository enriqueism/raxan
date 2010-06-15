<?php
/**
 * Raxan Client Extension
 * Copyright (c) 2008-2010 Raymond Irving (http://raxanpdi.com)
 * @package Raxan
 */

/**
 * Creates reference to client-side variables and callback functions
 */
class RaxanClientVariable {

    // Used internally during json encoding
    // See RaxanClientExtension::encodeVar
    public $_pointer;   

    protected static $vid = 0;
    protected $name,$value;

    public function __construct($val,$name = null, $isFunction = false, $registerGlobal = false) {
        $n = $this->name = $this->_pointer = ($name!==null) ? $name : '_v'.(self::$vid++);
        if (!$isFunction) $this->value = RaxanClientExtension::encodeVar($val);
        else {
            $fn  = trim($val);
            $this->value = (substr($fn,0,8)!='function')  ? 'function() {'.$val.'}' : $fn;
        }
        if (!$registerGlobal) RaxanWebPage::$vars[] = $n.'='.$this->value;
        else {
            $n = $this->name = 'window.'.$n;
            RaxanWebPage::$varsGlobal[] = $n.'='.$this->value;
        }
        
    }

    public  function __toString(){
        return $this->name;
    }
}


/***
 * Create a wrapper around client-side jQuery and native JavaScript function calls
 */
class RaxanClientExtension {

    protected static $scripts = array();

    protected $chain;

    // $ss - css selector
    public function __construct($ss,$context = null){
        RaxanWebPage::$actions[] = $this;
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
     * @return RaxanClientExtension
     */
    public function alert($msg) {
        $this->chain  = (($this->chain=='$()') ? '':$this->chain.';').
            'alert("'.$this->escapeString($msg).'")';
        return $this;
    }

    /**
     * Returns client usergabgent object
     * @return object
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
     * @return RaxanClientExtension
     */
    public function confirm($msg,$okFn = null,$cancelFn = null) {
        $confirm = 'confirm("'.$this->escapeString($msg).'")';
        if ($okFn||$cancelFn) {
            $confirm = 'if ('.$confirm.')'.
            ($okFn instanceof RaxanClientVariable ? ' '.$okFn.'()':'').';'.
            ($cancelFn instanceof RaxanClientVariable ? ' else '.$cancelFn.'();' : '');
        }
        $this->chain  = (($this->chain=='$()') ? '':$this->chain.';').$confirm;
        return $this;
    }

    /**
     * Writes the value of a server-side variable or object to the client-side console
     * @param mixed $var Variable to be displayed inside console
     * @param boolean $halt Halt servide operations
     * @return RaxanClientExtension
     */
    public function console($var, $halt = false) {
        $var = $var===null ? 'null' : print_r($var,true); 
        $this->chain  = (($this->chain=='$()') ? '':$this->chain.';').
            'Raxan.log("'.$this->escapeString($var).'")';
        if (!$halt) return $this;
        else {
            RaxanWebPage::controller()->endResponse()->reply();
            exit();
        }
    }

    /**
     * Evaluates javascript code and return a new RaxanClientExtension object
     * @return RaxanClientExtension
     */
    public function evaluate($s){
        $this->chain  = (($this->chain=='$()') ? '':$this->chain.';').
            '$('.$s.')';
        return $this;
    }

    /**
     * Toggle class on mouse over and out
     * @return RaxanClientExtension
     */
    public function hoverClass($cls){ // @todo: make this method use jquery.live() instead of hover
        $this->chain.= '.hover(function(){$(this).addClass("'.$cls.'")},function(){$(this).removeClass("'.$cls.'")})';
        return $this;
    }

    /**
     * Dynamically Load CSS files from client-side
     * @return RaxanClientExtension
     */
    public function loadCSS($src,$ext = false){
        $ext = $ext===true ? 'true' : 'false';
        if ($this->chain=='$()') $this->chain = '';
        $this->chain.= ';h.css("'.$this->escapeString($src).'",'.$ext.')';
        return $this;
    }
    
    /**
     * Dynamically Load script files from client-side
     * @return RaxanClientExtension
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
     * @return RaxanClientExtension
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
     * @return RaxanClientExtension
     */
    public function prompt($msg,$default = '',$fn = null) {
        $val = $default ? ',"'.$this->escapeString($default).'"' :'';
        $prompt = 'prompt("'.$this->escapeString($msg).'"'.$val.')';
        if ($fn) {
            $prompt = 'var _p='.$prompt.'; if (_p)'.
            ($fn instanceof RaxanClientVariable ? ' '.$fn.'(_p)':'').';';
        }
        $this->chain  = (($this->chain=='$()') ? '':$this->chain.';').$prompt;
        return $this;
    }

    /**
     * Redirect client to the sepecified url
     */
    public function redirectTo($url) {
        Raxan::redirectTo($url,true);
    }

    /**
     * Redirect to new page view
     * @param string $view View mode
     * @param string $url Optional page url
     */
    public function redirectToView($view,$url = null) {
        Raxan::redirectToView($view,$url,true);
    }

     // Protected methods
     // -----------------------------

    /**
     * Returned javascript escaped string
     * @return string
     */
    protected function escapeString($txt) {
        return Raxan::escapeText($txt);
    }

     // Static methods
     // -----------------------------

    /**
     * Returned encoded javascript value 
     */
    public static function encodeVar($v) {
        if (!is_numeric($v) && is_string($v)) {
            $v = '"'.Raxan::escapeText($v).'"';
        }
        else if ($v instanceof RaxanClientExtension ||
                 $v instanceof RaxanClientVariable) {
            // pass chain as value
            $v = $v.'';
        }
        else if ($v===true) $v = 'true';
        else if ($v===false) $v = 'false';
        else if (!is_scalar($v)) {
            // encode arrays and objects
            $v = Raxan::JSON('encode',$v);
            // replace _pointer hash array with variable name due to json encoding.
            // See RaxanClientVariable->_pointer
            if (strpos($v,':{"_pointer":"_v')) {
                $v = preg_replace('/:\{"_pointer"\:"(_v[0-9]+)"\}/', ':\1', $v);
            }
            if(!$v) $v = '{}';
        }
        return $v;
    }
    
}

?>