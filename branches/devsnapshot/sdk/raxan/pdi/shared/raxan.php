<?php
/**
 * Raxan Framework
 * This file includes Raxan, RaxanBase, RaxanDataStorage, RaxanSessionStorage, RaxanPlugin
 * Copyright (c) 2008-2010 Raymond Irving 
 * @license GPL/MIT
 * @date 10-Dec-2008
 * @package Raxan
 */

// @todo: check other server settings to make sure that site path/url works.

define('RAXANPDI',TRUE);

// Set PHP Version ID
if(!defined('PHP_VERSION_ID')) {
    $version = PHP_VERSION;
    define('PHP_VERSION_ID', ($version{0} * 10000 + $version{2} * 100 + $version{4}));
}

/**
 * Raxan Main Error Handler
 */
function raxan_error_handler($errno, $errstr, $errfile, $errline ) {
    if (error_reporting()===0) return;
    if (error_reporting() & $errno){    // repect error reporting level
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
}

/**
 * Abstract Data Storage Class
 * Extend the RaxanDataStorage class to create custom data storage classes for web pages and session data.
 */
abstract class RaxanDataStorage {
    protected $store,$id;
    public function __construct($id = null) {
        $this->id = $id; $this->_init();
    }
    public function __destruct() { $this->_save(); }
    protected function _init() { /* initailize storage and handle garbage collection  */ }
    protected function _save() { /* save storage */ }
    protected function _reset() { /* reset storage */ }
    public function exists($key) { return isset($this->store[$key]); }
    public function & read($key) { return $this->store[$key]; }
    public function & write($key, $value) { $s = $this->store[$key] = $value; return $this->store[$key];}
    public function remove($key) { unset($this->store[$key]); }
    public function resetStore() { $this->_reset(); }
    public function storageId() { return $this->id; }

 }

/**
 * Abstract Class for creating plugins
 */
abstract class RaxanPlugin {

    // copy these properties to new plugin
    public static $name = 'Plugin Name';
    public static $description = "Plugin description";
    public static $author = "Author's name";

    protected static $lastClassName;
    protected static $shared = array();

    protected $events;

    public function __construct() {
        $call =  array($this,'raiseEvent');
        $a = $this->methods();
        foreach ($a as $n)
            if ($n[0]!='_' && strpos($n,'_')){
                $this->events[$n] = true;
                Raxan::bindSysEvent($n, $call);
            }
    }

    public function raiseEvent($event,$args) {
        $type = $event->type;
        if (isset($this->events[$type])) return $this->{$type}($event,$args);
    }

    public static function instance($class) {
        $cls = $class;
        if (!isset(self::$shared[$cls])) self::$shared[$cls] = new $cls();
        self::$lastClassName = $cls;
        return self::$shared[$cls];
    }

    /**
     * Used internally - Returns the last registered plugin class name
     * @return string
     */
    public static function getLastClassName() {
        return self::$lastClassName;
    }

    /**
     * Used internally - Returns the last registered plugin instance
     * @return mixed
     */
    public static function getLastInstance() {
        return (self::$lastClassName) ? self::$shared[self::$lastClassName] : null;
    }

}

/**
 * Raxan Main Class
 */
class Raxan {

    // set raxan version
    // @todo: Update API version/revision
    const VERSION = 1.0;
    const REVISION = 'rc1';

    // template binder constants
    const TPL_FIRST     = 1;
    const TPL_LAST      = 2;
    const TPL_ITEM      = 4;
    const TPL_ALT       = 8;
    const TPL_SELECT    = 16;
    const TPL_EDIT      = 24;

    public static $isInit = false;
    public static $isLocaleLoaded = false;
    public static $isPDOLoaded = false;
    public static $isDataStorageLoaded = false;

    public static $isDebug = false;
    public static $isLogging = false;
    public static $postBackToken;  // used to identify legitimate Events and Post Back requests

    private static $instance;
    
    private static $nativeJSON;
    private static $isJSONLoaded;
    private static $jsonStrict, $jsonLose;
    private static $dataStore;
    private static $isSanitizerLoaded = false;
    private static $sharedSanitizer;

    private static $debug, $logFile = 'PHP';
    private static $configFile;
    private static $sysEvents;
    private static $_timer;
    private static $pluginFileMap = array();    // stores plugin file name and class
    private static $jsStrng1= array('\\','"',"\r","\n","\x00","\x1a");
    private static $jsStrng2= array('\\\\','\\"','','\n','\x00','\x1a');
    private static $locale = array();
    private static $config = array(
        'autostart'     => '',
        'base.path'     => '',
        'site.locale'   => 'en',    // e.g. en-us
        'site.lang'     => 'en',    // languae used by labels
        'site.charset'  => 'UTF-8',
        'site.timezone' => '',
        'site.email'    => '',
        'site.phone'    => '',
        'site.host'     => '',
        'site.url'      => '',
        'site.path'     => '',
        'raxan.url'     => '',
        'raxan.path'    => '',
        'views.path'    => '',
        'plugins.path'  => '',
        'widgets.path'  => '',
        'cache.path'    => '',
        'locale.path'   => '',
        'session.name'  => 'XPDI1000SE',
        'session.timeout'=> '30',   // in minutes
        'session.data.storage' => 'RaxanSessionStorage',    // default session data storage class
        'db.default'    => '',
        'debug'         => false,
        'debug.log'     => false,
        'debug.output'  => 'embedded',
        'log.enable'    => false,
        'log.file'      => 'PHP',
        'error.400' => '', 'error.401' => '',
        'error.403' => '', 'error.404' => '',
        'page.localizeOnResponse' => false,         // default page settings
        'page.initStartupScript' => false,
        'page.resetDataOnFirstLoad' => true,
        'page.preserveFormContent' => false,
        'page.disableInlineEvents' => false,
        'page.masterTemplate' => '',
        'page.serializeOnPostBack' => '',
        'page.degradable' => false,
        'page.showRenderTime' => false,
        'page.data.storage' => 'RaxanWebPageStorage',    // default page data storage class
        'preload.plugins' => '',        
        'preload.widgets' => ''
    );

    /**
     * Initialize the system and load config options
     * @return boolean
     */
    public static function init() {
        $config = &self::$config;
        $file  = self::$configFile ? self::$configFile :
                 $config['base.path'].'gateway.config.php';
                 
        // load config file if available.
        $rt = is_file($file) ? include_once($file) : false;

        // setup defaults
        $base = $config['base.path'];
        if (empty($config['site.host'])) {
            $port = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : '';
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
            if ($host) {
                $host = explode(':',$host); $host = trim($host[0]);
                $schema = (isset($_SERVER['HTTP_HOST']) && strtolower($_SERVER['HTTP_HOST'])=='on') ||
                      $port==443 ? 'https://' : 'http://' ;
                $config['site.host'] = $schema.$host.(($port==80||$port==443) ? '' : ':'.$port);
            }
        }
        if (empty($config['site.path'])||empty($config['site.url'])) {
            // auto detect site path & url
            $sn = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
            $sf = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '';
            $ps = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
            if ($sn && $ps!=$sn) $su = $sn; else $su = $ps;
            $su = str_replace('\\','/',dirname($su));   // fix issue #10 - bug with "\" path on windows PCs
            $sf = str_replace('\\','/',dirname($sf));
            $config['site.url'] = ($su!='/') ? $su.'/': './';
            $config['site.path'] = ($sf!='/') ? $sf.'/': './';
        }
        if (empty($config['raxan.path'])||empty($config['raxan.url'])) {
            // auto detect raxan path & url
            $pth = implode('/',array_slice(explode('/',$base),0,-2));
            $config['raxan.path'] = $pth.'/';
            $url = Raxan::mapSitePathToUrl($pth);
            $config['raxan.url'] = $url ? $url.'/' : './raxan/';
        }
        if (empty($config['cache.path'])) $config['cache.path'] = $config['raxan.path'].'cache/';
        if (empty($config['locale.path'])) $config['locale.path'] = $base.'shared/locale/';
        if (empty($config['views.path'])) $config['views.path'] = $config['site.path'].'views/';
        if (empty($config['plugins.path'])) $config['plugins.path'] = $config['raxan.path'].'plugins/';
        if (empty($config['widgets.path'])) $config['widgets.path'] = $config['raxan.path'].'ui/widgets/';

        self::$isDebug = $config['debug'];
        self::$isLogging = $config['log.enable'];

        // setup post back token
        if (isset($_COOKIE['_ptok'])) self::$postBackToken =  $_COOKIE['_ptok'];
        else {
            // generate random token
            $tok = substr(str_shuffle('$*!:;'),-2).rand(1000000,999999999);
            for($i=0;$i<7;$i++) $tok.= chr($i % 2 ? rand(64,90) : rand(97,122));
            self::$postBackToken = str_shuffle($tok);
            if (!defined('STDIN')) setcookie('_ptok', self::$postBackToken); // send cookie if not in CLI mode
        }

        // set timezone
        if ($config['site.timezone']) date_default_timezone_set($config['site.timezone']);

        // set error handler
        set_error_handler("raxan_error_handler",error_reporting());

        self::$isInit = true;
        // preload widgets from $config
        if ($config['preload.widgets']) {
            $ui = explode(',',$config['preload.widgets']);
            foreach($ui as $f) {    
                $f = trim($f);      // fix issue #9
                $extrn = substr($f,-4)=='.php';
                self::loadWidget($f,$extrn);
            }
        }
        // preload plugins from $config
        if ($config['preload.plugins']) {
            $pl = explode(',',$config['preload.plugins']);
            foreach($pl as $f) {
                $f = trim($f);      // fix issue #9
                $extrn = substr($f,-4)=='.php';
                self::loadPlugin($f,$extrn);
            }
        }

        // trigger system_init
        self::triggerSysEvent('system_init');

        return $rt;
    }

    /**
     * Initialize session data storage handler
     */
    public static function initDataStorage() {
        if (!self::$isInit) self::init();
        $cls = self::$config['session.data.storage'];
        self::$dataStore = new $cls();
        self::$isDataStorageLoaded = true;
        self::triggerSysEvent('session_init');
    }

    /**
     *  Initialize JSON support
     */
    public static function initJSON() {
        self::$nativeJSON = function_exists('json_encode');
        if (!self::$nativeJSON) {
            include_once(self::$config['base.path']."shared/JSON.php");
            self::$jsonStrict   = new Services_JSON();
            self::$jsonLose     = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
        }        
        self::$isJSONLoaded = true;
    }

    /**
     * Binds a callback functio to a System Event
     */
    public static function bindSysEvent($name,$callback) {
        if (!isset(self::$sysEvents)) self::$sysEvents = array();
        if (!isset(self::$sysEvents[$name])) self::$sysEvents[$name] = array();
        if (is_callable($callback)) self::$sysEvents[$name][] = $callback;
    }

    /**
     * Binds an Array or a PDO result set to a template
     * @returns string
     */
    public static function bindTemplate($rows, $options) {

        $rowType = '';
        if ($rows instanceof PDOStatement)
            $rows = $rows->fetchAll(PDO::FETCH_ASSOC); // get rows from PDO results
        else if ($rows instanceof RaxanElement) {
            $rows = $rows->get();   // get all matched elements
            $rowType='raxanElement';
        }

        if (empty($rows) || !is_array($rows)) return '';

        $initRowCount = 1;
        $removeTags = true; // Remove unused tags by default
        $opt = $tplAlt = $tpl = $options; $truncStr = '...';
        $page = $size = $trunc = $tr1 = $tr2 = 0;
        $tplF = $tplL = $tplS = $tplE = $key = $edited = $selected = '';
        $fn = $delimiter = $callByVariable = $rtArr = '';
        $itemClass = $altClass = $editClass = $selectClass = '';
        $firstClass = $lastClass = '';
        $fmt = $format = $fmtr = '';
        if (is_array($opt)) {
            if (isset($opt[0])) {
                // fetch templates from index based array
                $tplAlt = isset($opt[1]) ? $opt[1] : $opt[0];
                $tpl = $opt[0];
            }
            else {
                // fetch options from assoc array
                $tpl = isset($opt['tpl']) ? $opt['tpl'] : '';
                $tplAlt = isset($opt['tplAlt']) ? $opt['tplAlt'] : $tpl;
                $tplF = isset($opt['tplFirst']) ? $opt['tplFirst'] : '';
                $tplL = isset($opt['tplLast']) ? $opt['tplLast'] : '';
                $tplS = isset($opt['tplSelected']) ? $opt['tplSelected'] : '';
                $tplE = isset($opt['tplEdit']) ? $opt['tplEdit'] : '';
                $itemClass = isset($opt['itemClass']) ? $opt['itemClass'] : '';
                $altClass = isset($opt['altClass']) ? $opt['altClass'] : '';
                $selectClass = isset($opt['selectClass']) ? $opt['selectClass'] : '';
                $editClass = isset($opt['editClass']) ? $opt['editClass'] : '';
                $firstClass = isset($opt['firstClass']) ? $opt['firstClass'] : '';
                $lastClass = isset($opt['lastClass']) ? $opt['lastClass'] : '';
                $key = isset($opt['key']) ? $opt['key'] : '';
                $edited = isset($opt['edited']) ? $opt['edited'] : '';
                $selected = isset($opt['selected']) ? $opt['selected'] : '';
                $page = isset($opt['page']) ? (int)$opt['page'] : 0;
                $size = isset($opt['pageSize']) ? (int)$opt['pageSize'] : 0;
                $delimiter = isset($opt['delimiter']) ? $opt['delimiter'] : '';
                $fn = isset($opt['callback']) ? $opt['callback'] : 0;
                $rtArr = isset($opt['returnArray']) ? true : false;
                $format = isset($opt['format']) ? $opt['format'] : '';
                $truncStr = isset($opt['truncString']) ? $opt['truncString'] : $truncStr;
                $trunc = isset($opt['truncate']) ? (float)$opt['truncate'] : 0;
                $removeTags = isset($opt['removeUnusedTags']) ? $opt['removeUnusedTags'] : $removeTags;
                $initRowCount = isset($opt['initRowCount']) ? $opt['initRowCount'] : $initRowCount;
                $tr1 = intval($trunc); $tr2 = abs(str_replace('0.','',$trunc - $tr1));  // get truncate values
                if ($selected && !is_array($selected)) $selected = array($selected);
                if ($fn) {
                    if (!is_callable($fn)) Raxan::throwCallbackException($fn);
                    $callByVariable = is_string($fn);
                }
            }
        }

        // setup css classes
        $cssClasses = array(
            self::TPL_FIRST => $firstClass,
            self::TPL_LAST => $lastClass,
            self::TPL_ALT => $altClass,
            self::TPL_ITEM => $itemClass,
            self::TPL_SELECT => $selectClass,
            self::TPL_EDIT => $editClass
        );

        // fix: using {tags} in <a> tags. E.g.  <a href="{name}">
        if (strpos($tpl.$tplAlt.$tplF.$tplL.$tplS,'%7B')!==false)  {
            $a1 = array('%7B','%7D'); $a2 = array('{','}');
            list($tpl,$tplAlt,$tplF,$tplL,$tplS) = str_replace($a1,$a2,array($tpl,$tplAlt,$tplF,$tplL,$tplS));
        }

        // get record size if not set
        if (!$size && ($tplL||$lastClass||$page||$trunc!=0)) $size = count($rows);

        // finalize row setup
        $rc = $page ? ($page-1)*$size : $initRowCount - 1; // initail row count
        $rt = array(); $isIndex = false; $startTrunc = '';
        if ($rowType!='raxanElement'){
            if (!isset($rows[0])) $rows = array($rows);
            else if (is_object($rows[0])) { // check for an array of objects
                $rowType = 'object';
            }
            else if (!is_array($rows[0])) {
                $isIndex = true;
            }
        }

        // create empty format array when using callback
        if ($fn) $format = $format ? $format : array();

        // bind rows to template
        foreach($rows as $i=>$row) {
            if ($page && $i < (($page-1)*$size)) continue;
            else if ($page && $i>($page*$size-1)) break;
            
            if ($trunc!=0){ // truncate rows
                if (($tr1 > 0 && $i+1 > (($page-1)*$size)+$tr2 && $i+1 <= (($page-1)*$size)+$tr1+$tr2) ||
                    ($tr1 < 0 && $i+1 > ($page*$size+$tr1-$tr2) && $i+1 <= ($page*$size-$tr2))) {
                        if (!$startTrunc) $rt[] = $truncStr;
                        $startTrunc = true; continue;
                }
                else $startTrunc = false;
            }

            $rc++; // increment row count
            
            // set template
            if ($rc==1) {           // first
                $t = $tplF ? $tplF : $tpl;
                $tplType = self::TPL_FIRST;
            }
            else if ($rc==$size) {  // last
                $t = $tplL ? $tplL : $tpl;
                $tplType = self::TPL_LAST;
            }
            else if ($i%2) {       // alt
                $t = $tplAlt ? $tplAlt : $tpl;
                $tplType = self::TPL_ALT;
            }
            else {                  // item
                $t = $tpl;
                $tplType = self::TPL_ITEM;
            }

            // check if row selected
            if ($selected && ($key || $isIndex)) {
                $v = isset($row[$key]) ? $row[$key] : $row;
                if (in_array($v,$selected)) {
                    $t = $tplS ? $tplS : $t;
                    $tplType = self::TPL_SELECT;
                }
            }

            // check if row should be edited
            if ($edited && ($key || $isIndex)) {
                $v = isset($row[$key]) ? $row[$key] : $row;
                if ($v==$edited) {
                    $t = $tplE ? $tplE : $t;
                    $tplType = self::TPL_EDIT;
                }
            }

            // check if row is an element
            if ($rowType=='object')
                $row = (array)$row;
            else if ($rowType=='raxanElement'){
                $v = array('INDEX'=>$i,'VALUE'=>$row->nodeValue);
                $row = $row->attributes;
                foreach($row as $attr) $v[$attr->name] = $attr->value;
                $row = $v; $v = null; $attr = null;
            }
            else if ($isIndex) {    // setup index row
                $row = array('INDEX'=>$i,'VALUE'=>$row);
            }

            $fmt = $format; // reset format before callback
            
            $cssClass = $cssClasses[$tplType] ? $cssClasses[$tplType] : $cssClasses[self::TPL_ITEM];

            // callback handler
            if ($fn) {
                 $rtn = ($callByVariable) ? 
                    $fn($row,$i,$t,$tplType,$fmt,$cssClass) : $fn[0]->{$fn[1]}($row,$i,$t,$tplType,$fmt,$cssClass);
                 if ($rtn===false) continue;         // skip row and continue
                 else if ($rtn!==null) { // check if a value was returned. if not, let the binder handle the insert
                     $rt[] = $rtn;
                     continue;
                 }
            }

            // sanitizer
            if ($fmt && !$fmtr) {
                $fmtr = Raxan::getSharedSanitizer(); // sanitizer with direct input
                $fmtrParam = array();
            }

            // format & sanitize row values
            foreach($row as $n=>$v) {

                if (!isset($row[$n])) continue;
                else if(!is_scalar($row[$n])) {
                    $row[$n] = ''; continue; // skip nested arays and objects
                }

                // format row value
                $fv = isset($fmt[$n]) ? $fmt[$n] : '';
                if (!$fv) $row[$n] = htmlspecialchars($v); // escape special chars by default
                else {

                    // format value
                    if (!isset($fmtrParam[$fv])) {
                        $f = $fv;
                        if ($f==='longdate'||$f==='shortdate') $f = 'date:'.substr($f,0,-4);
                        $v = ($p=strpos($f,':')) ? substr($f,$p+1) : null;
                        $f = $fmt[$n] = $p ? substr($f,0,$p) : $f;
                        if ($f=='replace') {
                            $v = explode(',',$v,2);
                            if (!isset($v[1])) $v[1] = '';
                        }
                        $fmtrParam[$fv] = $v;
                        $fmtrParam[$fv.'.type'] = $f;
                    }
                    $p = $fmtrParam[$fv]; // get parameter
                    $f = $fmtrParam[$fv.'.type']; // get type
                    if ($f==='int' || $f==='integer') $row[$n] = $fmtr->intVal($row[$n]);
                    else if ($f==='float') $row[$n] = $fmtr->floatVal($row[$n]);
                    else if ($f==='text') $row[$n] = $fmtr->textVal($row[$n]);
                    else if ($f==='escape') $row[$n] = $fmtr->escapeVal($row[$n]);
                    else if ($f==='money') $row[$n] = $fmtr->formatMoney($row[$n],$p);
                    else if ($f==='date') $row[$n] = $fmtr->formatDate($row[$n],$p);
                    else if ($f==='number') $row[$n] = $fmtr->formatNumber($row[$n],$p);
                    else if ($f==='percentage') $row[$n] = ($row[$n]*100).'%';
                    else if ($f==='capitalize') $row[$n] = ucwords($row[$n]);
                    else if ($f==='replace') $row[$n] = preg_replace('/'.$p[0].'/i',$p[1],$row[$n]);
                    else if ($f==='lower') $row[$n] = strtolower($row[$n]);
                    else if ($f==='upper') $row[$n] = strtoupper ($row[$n]);
                    else if ($f=='html') $row[$n] = $fmtr->htmlVal($row[$n]); 
                    else if ($f!='raw') $row[$n] = ''; // check if we should allow raw values
                }

                // set format style. Remove illegal quotes (") from color an style
                if (isset($fmt[$n.' bold'])) $row[$n] = '<strong>'.$row[$n].'</strong>';
                if (isset($fmt[$n.' color'])) $row[$n] = '<span style="color:'.str_replace('"','',$fmt[$n.' color']).'">'.$row[$n].'</span>';;
                if (isset($fmt[$n.' style'])) $row[$n] = '<span style="'.str_replace('"','',$fmt[$n.' style']).'">'.$row[$n].'</span>';;

            }

            $keys = !isset($keys) ? explode(',','{'.implode('},{',array_keys($row)).'},{ROWCOUNT},{ROWCLASS}') : $keys;
            $values = array_values($row);
            $values[] = $rc; // assign row count
            $values[] = $cssClass; // assign row css class

            $rt[] = str_replace($keys,$values,$t); // replace template fields {name:integer}
        }
        // return array or string - remove {tags}
        if ($rtArr) return  $rt;
        else {
            $rt = implode($delimiter,$rt);
            return $removeTags ?  preg_replace('/(\{[a-zA-Z0-9._-]+\})/','',$rt) : $rt;
        }
    }
    
    /**
     * Converts the given date to a RaxanDateTime object
     * @returns RaxanDateTime
     */
    public static function cDate($dt = null) {
        require_once(Raxan::config('base.path').'shared/raxan.datetime.php');
        $dt = new RaxanDateTime($dt);
        return $dt;
    }

    /**
     * Returns or sets configuration values
     * @return mixed
     */
    public static function config($key = null,$value = null) {
        if ($key!=='base.path' &&  !self::$isInit) self::init();
        if ($key===null) return self::$config;
        else if($value===null) return isset(self::$config[$key]) ? self::$config[$key] : '';
        else {
            $c = & self::$config;
            $c[$key] = $value;
            if ($key=='site.timezone' && $c['site.timezone']) {
                date_default_timezone_set($c['site.timezone']);
            }
            else if ($key=='debug'||$key=='log.enable'||$key=='log.file'){
                self::$isDebug = $c['debug'] ;
                self::$isLogging = $c['log.enable'];
                self::$logFile = $c['log.file'];
            }
        }
    }

    /**
     * Creates and returns a PDO connection to a database.
     * If connection failed then error is logged to the log file or debug screen. Sensitive data will be removed.
     *
     * @example:
     *  <p>Raxan::connect($dsn,$uid,$pwd,$errMode) // enables exception error mode - set $errMode to true or set to PDO error mode constant</p>
     *  <p>Raxan::connect($dsn,$uid,$pwd,$attribs) // set attributes</p>
     * 
     * @param mixed $dsn Acceptts string or array
     * @param string $user Optional user name
     * @param string $password Optional password
     * @param mixed $attribs Optional PDO error mode or array of attributes. Set to TRUE to enable PDO error mode
     * @return RaxanPDO  False if connection failed
     */
    public static function connect($dsn,$user=null,$password=null,$attribs=null){
        $dsn = (is_string($dsn) && $d=Raxan::config('db.'.$dsn)) ? $d :$dsn;
        if (!self::$isPDOLoaded) {
            self::$isPDOLoaded = true;
            include_once(self::$config['base.path'].'shared/raxan.pdo.php');
        }
        if (is_array($dsn)){
            // build pdo dsn
            $dsn = array_merge(array('user'=>'','password'=>'','attribs'=>''), $dsn); // set default keys
            $user = $user ? $user : $dsn['user'];
            $password = $password ? $password : $dsn['password'];
            $attribs = $attribs ? $attribs : ($dsn['attribs']? $dsn['attribs'] : null);
            $dsn = $dsn['dsn'];
        }

        // check for error mode
        if ($attribs===true) $attribs  = PDO::ERRMODE_EXCEPTION;
        if ($attribs===PDO::ERRMODE_EXCEPTION||$attribs===PDO::ERRMODE_WARNING) {
            $attribs = array(PDO::ATTR_ERRMODE => $attribs);
        }
        $errmode = ($attribs && is_array($attribs) && isset($attribs[PDO::ATTR_ERRMODE])) ?
                   $attribs[PDO::ATTR_ERRMODE] : null;
        
        try {
            $args = array($dsn,$user,$password,$attribs);
            $rt = self::triggerSysEvent('data_connection',$args);
            if ($rt!==null) return $rt;
            else $pdo = new RaxanPDO($dsn,$user,$password,$attribs);
            return $pdo;
        }
        catch(PDOException $e){
            $lbl = 'Raxan::connect';
            $msg = $e->getMessage();            
            $msg = str_replace(array($dsn,$user,$password),'...',$msg); // remove sensitive data
            if ($errmode!==null) throw new PDOException($msg,$e->getCode());
            else {
                self::log($msg,'error',$lbl) || self::debug($lbl.' Error: '.$msg);
                return false;
            }            
        }
    }

    /**
     * Returns current web page url relative to the document root 
     * @return string 
     */
    public static function currentURL() {
        // @todo: optimize currentURL 
        $qs = isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? 
           '?'.str_replace(array('"',"'",'<','>'), array('%22','%27','%3C','%3E'),$_SERVER['QUERY_STRING'])  : ''; // sanitize: encode special chars
        return isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'].$qs : '';
    }


    /**
     * Get and/or set a named data value for the current Session
     * @param string $name
     * @param mixed $value
     * @param boolean $setValueIfNotIsSet
     * @return mixed
     */
    public static function &data($name = null,$value = null,$setValueIfNotIsSet = false){
        if (!self::$isDataStorageLoaded) self::initDataStorage();
        $s = self::$dataStore; $name = '_RaxDT.'.$name;
        if ($value!==null) {
            $sv = $setValueIfNotIsSet;
            if (!$sv || ($sv && !$s->exists($name))) return $s->write($name,$value);
        }
        return $s->read($name);
    }

    /**
     * Sets or returns a named data value for the current Session based on the specified data bank id
     * @return mixed
     */
    public static function &dataBank($bankId,$name = null,$value = null,$setValueIfNotIsSet = false){
        if (!self::$isDataStorageLoaded) self::initDataStorage();
        $s = self::$dataStore; $bankId = '_RaxDB.'.$bankId;
        if($s->exists($bankId)) $h = & $s->read($bankId);
        else $h = & $s->write($bankId,array()); // create data bank
        if ($name===null) return $h;    // return data array
        else if ($value!==null) {
            $sv = $setValueIfNotIsSet;
            if (!$sv || ($sv && !isset($h[$name]))) $h[$name] = $value; // set value on first use
        }
        return $h[$name];
    }

    /**
     * Returns a new instanace of RaxanDataSanitizer. Use the sanitizer to sanitize array or input values
     * @param array $array Array containing values to be sanitized
     * @param string $charset
     * @return RaxanDataSanitizer
     */
    public static function dataSanitizer($array = null, $charset = null) {
        if (!self::$isSanitizerLoaded) {
            require_once(Raxan::config('base.path').'shared/raxan.datasanitizer.php');
            self::$isSanitizerLoaded = true;
        }
        return new RaxanDataSanitizer($array,$charset);
    }


    /**
     * Returns or sets the session data storage handler
     * @return RaxanDataStorage
     */
    public static function dataStorage(RaxanDataStorage $store = null) {
        if ($store===null && !self::$isDataStorageLoaded)
            self::initDataStorage();
        else if ($store) {
            self::$isDataStorageLoaded = true;
            self::$dataStore = $store;
        }
        return self::$dataStore;
    }

    /**
     * Returns the session data storage id
     * @return string
     */
    public static function dataStorageId() {
        if (!self::$isDataStorageLoaded) self::initDataStorage();
        return self::$dataStore->storageId();
    }


    /**
     * Sends debugging information to client
     * @param string $txt Debug value. Set to TRUE or FALSE to enable or disable debugging
     * @return boolean
     */
    public static function debug($txt){
        if ($txt===true||$txt===false) {
            self::$isDebug = self::$config['debug'] = $txt;
            return true;
        }
        if (self::$isDebug) {
            if (!isset(self::$debug)) self::$debug = array();
            self::$debug[] = print_r($txt,true);
            return true;
        }
        return false;
    }

    /**
     * Returns debug output as text
     * @return string
     */
    public static function debugOutut(){
        if (!self::$isDebug||!is_array(self::$debug)) return '';
        else {
            $o = self::$config['debug.output'];
            if ($o=='embedded'||$o=='popup') $dm = '<hr />';
            else $dm = "\n";
            return implode($dm,self::$debug);
        }
    }

    /**
     * Converts multi-line text into a single-line JS string
     * @return string
     */
    public static function escapeText($txt) {
        if ($txt===null) return '';
        else return str_replace(self::$jsStrng1,self::$jsStrng2,$txt);
    }

    /**
     * Set or get flash value. Flash provides a way to pass temporary objects/values
     * between pages and views. The flash value is removed after it has been retrieved
     * @param string $name
     * @param mixed $value
     * @return string
     */
    public static function flash($name,$value = null) {
        $name = 'FlashMsg.'.$name;
        if ($value !== null) self::data($name,$value);
        else {
            $value = self::data($name);
            self::removeData($name);
        }
        return $value;
    }

    /**
     * Returns a shared instanace of RaxanDataSanitizer with direct input enabled. Used internally
     * @return RaxanDataSanitizer
     */
    public static function getSharedSanitizer() {
        if (self::$sharedSanitizer) return self::$sharedSanitizer;
        else {
            self::$sharedSanitizer = self::dataSanitizer();
            self::$sharedSanitizer->enableDirectInput();
            return self::$sharedSanitizer;
        }
    }

    /**
     * Resamples (convert/resize) an image file. You can specify a new width, height and type
     * @param string $file Image path and file name
     * @param int $w Width
     * @param int $h Height
     * @param string $type Supported image types: gif,png,jpg,bmp,xbmp,wbmp. Defaults to jpg
     * @return boolean
     */
     public static function imageResample($file,$w,$h, $type = null) {
        if (!function_exists('imagecreatefromstring')) {
            Raxan::log('Function imagecreatefromstring does not exists - The GD image processing library is required.','warn','Raxan::imageResample');
            return false;
        }
        $info = @getImageSize($file);
        if ($info) {
            // maintain aspect ratio
            if ($h==0) $h = $info[1] * ($w/$info[0]);
            if ($w==0) $w = $info[0] * ($h/$info[1]);
            if ($w==0 && $h==0) {$w = $info[0]; $h = $info[1];}
            // resize/resample image
            $img = @imageCreateFromString(file_get_contents($file));
            if (!$img) return false;
            $newImg = function_exists('imagecreatetruecolor') ? imageCreateTrueColor($w,$h) : imageCreate($w,$h);
            if(function_exists('imagecopyresampled'))
                imageCopyResampled($newImg, $img, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
            else
                imageCopyResized($newImg, $img, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
            imagedestroy($img);
            $type = !$type ? $info[2] : strtolower(trim($type));
            if ($type==1||$type=='gif') $f = 'imagegif';
            else if ($type==3 || $type=='png') $f = 'imagepng';
            else if ($type==6 || $type==16 || $type=='bmp' || $type=='xbmp') $f = 'imagexbm';
            else if ($type==15 || $type=='wbmp') $f = 'image2wbmp';
            else $f = 'imagejpeg';
            if (function_exists($f)) $f($newImg,$file);
            imagedestroy($newImg);
            return true;
        }
        return false;
    }

    /**
     * Returns an array containing the width, height and type for the image file
     * @param string $file Image path and file name
     * @return array Returns array or NULL if error
     */
    public static function imageSize($file) {
        if (!function_exists('getImageSize')) {
            Raxan::log('Function getImageSize does not exists - The GD image processing library is required.','warn','Raxan::imageSize');
            return null;
        }

        $info = @getImageSize($file);
        if (!$info) return null;
        else {
            return array(
                'width' => $info[0],
                'height'=> $info[1],
                'type'  => $info[2]
            );
        }
    }

    /**
     * Converts a CSV file into an 2D array. The first row of the CSV file must contain the column names
     * @return array
     */
    public static function importCSV($file, $delimiter = ',', $enclosure = '"', $escape = '\\', $terminator = "\n") {
        $csv = file_get_contents($file);
        if (!function_exists('raxan_csv_to_array')) include_once(self::$config['base.path'].'shared/csvtoarray.php');
        return raxan_csv_to_array($csv,$delimiter,$enclosure,$escape,$terminator);
    }
    
    /**
     * Encode/Decode JSON Strings
     * @param string $mode Set to encode or decode
     * @param mixed $value Value to be encoded or decoded
     * @param boolean $assoc Set to true to return objects as associative arrays
     * @return string
     */
    public static function JSON($mode,$value,$assoc = false) {
        if (!self::$isJSONLoaded) self::initJSON();
        $rt = null;
        switch ($mode) {
            case 'encode':
                if (self::$nativeJSON) $rt = json_encode($value);
                else {
                    $rt = self::$jsonStrict->encode($value);
                    if (self::$jsonStrict->isError($rt)) $rt = null;
                }
                break;
            case 'decode':
                if (self::$nativeJSON) $rt = json_decode($value,$assoc);
                else {
                    $rt = ($assoc) ? self::$jsonLose->decode($value) : self::$jsonStrict->decode($value) ;
                    if (self::$jsonLose->isError($rt)||self::$jsonStrict->isError($rt)) $rt = null;
                }
                break;
        }
        return $rt;
    }

    /**
     * Returns locale settings based on the the site.locale config option
     * @return string
     */
    public static function locale($key = null,$arg1=null,$arg2=null,$arg3=null,$arg4=null,$arg5=null,$arg6=null) {
        if (!self::$isLocaleLoaded) self::setLocale(self::$config['site.locale']); // init on first use
        if ($key===null) return self::$config['site.locale'];
        $v = isset(self::$locale[$key]) ? self::$locale[$key] : '';
        return ($v && $arg1!==null) ? sprintf($v,$arg1,$arg2,$arg3,$arg4,$arg5,$arg6) : $v;
    }

    /**
     * Loads a config file
     */
    public static function loadConfig($file) {
        $reload = ($file && self::$configFile!=$file);
        self::$configFile = $file;
        return ($reload || !self::$isInit) ? self::init() : true;
    }

    /**
     * Loads a language file based on locale settings
     * usage: loadLangFile($fl1,$fl2,$fl3,...)
     * @return boolean
     */
    public static function loadLangFile($fl) {
        if (!self::$isLocaleLoaded) self::setLocale(self::$config['site.locale']); // init on first use
        $pth = self::$config['lang.path'];
        $args = func_get_args(); $rt = false;
        foreach ($args as $f) {
            try {
                $locale = & self::$locale;
                $rt = include_once($pth.$f.'.php');
            } catch(Exception $e) {
                if (self::$isDebug)
                    Raxan::debug('Error while loading Language File \''.$f.'\' - '.$e->getMessage());
            }
            
        }
        return $rt;
    }

    /**
     * Loads a plugin from the plugins folder.
     * Usage: <p>Raxan::loadPlugin($name,$extrn)</p>
     * @param string $name Name of plugin file without the .php extension
     * @param boolean $extrn Set to true if plugin will be loaded from a folder that's not relative to {plugins.path}
     * @return mixed Returns an instance of the plugin
     */
    public static function loadPlugin($name,$extrn = false) {
        if (!self::$isInit) self::init();
        if (!$extrn) $name = self::$config['plugins.path'].$name.'.php';
        $class = isset(self::$pluginFileMap[$name]) ? self::$pluginFileMap[$name] : '';
        if ($class) $ins = RaxanPlugin::instance($class);
        else {
            require_once($name);
            $class = RaxanPlugin::getLastClassName();
            $ins = RaxanPlugin::getLastInstance();
            self::$pluginFileMap[$name] = $class;
        }
        return $ins;
    }

    /**
     * Loads a widget from the widgets folder.
     * @param string $name Name of widget file without the .php extension
     * @param boolean $extrn Set to true if widget will be loaded from a folder that's not relative to {widgets.path}
     * @return boolean
     */
    public static function loadWidget($name, $extrn = false) {
        if (!self::$isInit) self::init();
        if (!$extrn) $name = self::$config['widgets.path'].$name.'.php';
        return require_once($name);
    }

    /**
     * Adds an entry to the log file
     * @param string $str
     * @param string $level Optional tag to be assocciated with the log entry. E.g. ERROR, WARNING, INFO, etc
     * @param string $label Optional.
     * @return boolean
     */
    public static function log($var, $level = null, $label = null){
        if (!self::$isInit) self::init();
        if (!self::$isLogging) return false;
        $level = $level ? strtoupper($level): 'INFO';
        $label = $label ? ' ['.$label.']' :  '';
        $var = $level." \t".date('Y-m-d H:i:s',time())." \t".$label. " \t".print_r($var,true);
        if (self::$isDebug && self::$config['debug.log']) self::debug($var);
        if (self::$logFile=='PHP') return error_log($var);
        else {
            try {
                // @todo: add code to truncate log file
                return error_log($var."\n",3,self::$logFile);
            } catch(Exception $e){
                exit('Error while writing to Log: '.$e->getMessage());
            }
        }
    }

    /**
     * Returns the URL for a file or folder path within the site
     * @param string $pth The path to the file or folder
     * @return string Returns null if file or folder is not within the site
     */
    public static function mapSitePathToUrl($pth) {
        $spth = self::$config['site.path'];
        $surl = self::$config['site.url'];
        $fl = str_replace('\\', '/', realpath($pth));
        $match = false;
        while (!$match){
            $flu = str_ireplace($spth, '',$fl); // case-insensitive replace
            if ($fl!=$flu) $match = true;
            else {
                $spth = dirname($spth).'/'; $surl = dirname($surl).'/';
                if (strlen($surl)<=3||strlen($spth)<=3) break;
            }
       }
        return $match ? $surl.$flu : null;
    }

    /**
     * Generate page numbers based . The $option values are similar to that of bindTemplate
     * @returns string
     */
    public static function paginate($maxPage,$page,$options = null) {
        $o = is_array($options) ? $options : array();
        $ps = isset($o['pageSize']) ? (int)$o['pageSize'] : 5;
        if ($ps<3) $ps = 3; if ($page<1)$page = 1;
        if (!isset($o['delimiter'])) $o['delimiter'] = '&nbsp;';
        if (!isset($o['tpl'])) {
            $o['tpl'] = '<a href="#{VALUE}">{VALUE}</a>';
            if (!isset($o['tplSelected'])) $o['tplSelected'] = '<span>{VALUE}</span>';
        }
        $o['selected'] = $page; $o['page'] = 1; $o['pageSize'] = $ps;

        $start = 0; $end = $maxPage > 1 ? $maxPage : 1;
        $prev = $page>1 ? $page - 1 : 1; $next = $page<$maxPage ? $page + 1 : $maxPage;
        if ($end > $ps) {
            $start = ($page>1) ? intval($page/($ps-2))*($ps-2) : 0;
            if ($start>0) $start-=2;
            $end = $start + $ps;
            if ($end>=$maxPage) {$end=$maxPage; $start=$end-$ps;}
        }
        $pg = range($start+1,$end);

        // check if we should remove unsed tags
        $removeTags = isset($o['removeUnusedTags']) ? $o['removeUnusedTags'] : false;
        $o['removeUnusedTags'] = false;

        $nav1 = array('{FIRST}','{LAST}','{NEXT}','{PREV}');
        $nav2 = array(1,$maxPage,$next,$prev);
        $pager = self::bindTemplate($pg, $o);
        $pager = str_replace($nav1,$nav2,$pager);
        $pager = $removeTags ? preg_replace('/(\{[a-zA-Z0-9._-]+\})/','',$pager) : $pager; // remove unused tags
        return $pager;
    }

    /**
     * Redirect client to the specified url
     * @param string $url page url
     * @param boolean $useJavaScript Optional. Enable page redirection using client-side JavaScript
     */
    public static function redirectTo($url,$useJavaScript = false) {
        if (!$useJavaScript) header('Location: '.$url);
        else {
            $redirect = 'window.location = "'.self::escapeText($url).'"';
            RaxanWebPage::$actions = array($redirect);
            RaxanWebPage::controller()->endResponse()->reply();
        }
        exit();
    }

    /**
     * Redirect to new page view
     * @param string $view View mode
     * @param string $url Optional page url
     * @param boolean $useJavaScript Optional. Enable page redirection using client-side JavaScript
     */
    public static function redirectToView($view,$url = null, $useJavaScript = false){
        $url = $url ? $url : self::$config['site.host'].self::currentURL();
        if (strpos($url,'vu=')!==false) $url = trim(preg_replace('#vu=[^&]*#','',$url),"&?\n\r ");
        if ($view && $view!='index') $url.= (strpos($url,'?') ? '&' : '?').'vu='.$view;
        $url = str_replace("\n",'',$url);
        self::redirectTo($url,$useJavaScript);
    }

    /**
     * Remove session data
     */
    public static function removeData($name) {
        if (!self::$isDataStorageLoaded) self::initDataStorage();
        $s = self::$dataStore; $name = '_RaxDT.'.$name;
        $s->remove($name);
    }

    /**
     * Remove named data from a data bank within the current session
     */
    public static function removeDataBank($bankId,$name = null) {
        if (!self::$isDataStorageLoaded) self::initDataStorage();
        $s = & self::$dataStore; $bankId = '_RaxDB.'.$bankId;
        if ($name===null) $s->remove($bankId);
        else { $h = & $s->read($bankId); unset($h[$name]); }
    }

    /**
     * Sends an error page to the web browser
     */
    public static function sendError($msg,$code = null) {
        $html = ''; $code = !$code ? $msg: $code;
        switch ($code) {
            case 400: header("HTTP/1.0 400 Bad syntax"); break;
            case 401: header("HTTP/1.0 401 Unauthorized"); break;
            case 403: header("HTTP/1.0 403 Forbidden"); break;
            case 404: header("HTTP/1.0 404 Not Found"); break;
        }

        if ($msg && !is_numeric($msg)) {
            if (isset($_REQUEST['_ajax_call_'])) echo $msg;
            else {
                if ($code && !empty(self::$config['error.'.$code])) {
                    $html = is_file(self::$config['error.'.$code]) ?
                        file_get_contents(self::$config['error.'.$code]) : '';
                }
                $html = $html ?  str_replace('{message}',$msg,$html) :$msg;
                echo $html;
            }
        }
        exit();
    }

    /**
     * Sets the base path for the framework
     */
    public static function setBasePath($pth) {
        $pth = $pth && substr($pth,-1)!='/' ? $pth.'/' : $pth;
        self::$config['base.path'] = $pth;
    }

    /**
     * Sets the locale and/or lang code
     * @return boolean
     */
    public static function setLocale($code,$lang = null) {
        if (!self::$isInit) self::init();
        // load locale general settings
        $locale = &self::$locale;
        $config = &self::$config;
        $code = str_replace(array('/','\\','.'),'',strtolower(trim($code))); //sanitize code
        $config['site.locale'] = $code;
        $pth = $config['locale.path'] . str_replace('-','/',$code).'/'; // locales are stored as {lang}/{country}
        if (is_file($pth.'general.php') && include_once($pth.'general.php')) {
            $config['lang.path'] = $pth;
            if ($lang!==null) $config['site.lang'] = $lang;
            else $config['site.lang'] = substr($code,0,2);
            setlocale(LC_CTYPE,   $locale['php.locale']);
            setlocale(LC_COLLATE, $locale['php.locale']);
            // setup locale date name - used instead of LC_TIME as it's reported to fail on some systems
            $locale['dt._eng_names'] = array(
                'january','february','march','april','may','june','july',
                'august','september','october','november','december',
                'jan','feb','mar','apr','may','june','july','aug','sept','oct',
                'nov','dec','sunday','monday','tuesday','wednesday','thursday',
                'friday','saturday','sun','mon','tue','wed','thu','fri','sat'
            );
            $locale['dt._locale_names'] = array_merge($locale['months.full'],$locale['months.short'],$locale['days.full'],$locale['days.short']);
            $locale = array_merge($locale, array_combine($locale['dt._eng_names'],$locale['dt._locale_names'])); // combine names
            return self::$isLocaleLoaded = true;
        }
        return false;
    }

    /**
     * Returns an instance of Raxan
     * @return Raxan
     */
    public static function singleton() {
        return isset(self::$instance) ?
            self::$instance : self::$instance = new Raxan();
    }

    /**
     * Start/Stop Timer Functions
     */
    public static function startTimer(){ return self::$_timer = microtime(true); }
    public static function stopTimer($time = null) {
        return microtime(true) - ($time!==null ? $time : self::$_timer);
    }

    /**
     * Triggers a System Event
     * @param string $name Event type/name
     * @param mixed $args Optional event argument
     * @return mixed
     */
    public static function triggerSysEvent($name,$args = null) {
        if (!isset(self::$sysEvents[$name])) return null;
        else {
            $e = new RaxanSysEvent($name);
            $hndls = self::$sysEvents[$name];
            if ($hndls) foreach ($hndls as $fn) {
                if (is_array($fn)) $rt = $fn[0]->{$fn[1]}($e,$args);
                else $rt = $fn($e,$args);
                if ($rt!==null) $e->result = $rt;
                if ($e->isStopPropagation) break;
            }
            return $e->result; // return event results
        }
    }
 
    /**
     * Throws an exception for missing or invalid callback
     */
    public static function throwCallbackException($fn) {
        if (is_array($fn) && is_object($fn[0])) $fn[0] = get_class($fn[0]);
        throw new Exception('Unable to execute callback function or method: '.print_r($fn,true));
    }

}

// Raxan Base Class
abstract class RaxanBase {

    protected static $mObjId = 0;   // Event Object counter
    protected $objId, $events;

    public function __construct() {
        $this->objId = self::$mObjId++;
    }

    /**
     * Bind the selected event to a callback function
     * @return RaxanBase
     */
    public function bind($type,$data = null, $fn = null) {
        if (!$this->events) $this->events = array();
        $cb = ($fn===null) ? array($data,null) : array($fn,$data);
        $e = & $this->events; $id = $this->objId.$type;
        if (!isset($e[$id])) $e[$id] = array($cb);
        else $e[$id][] = $cb;
        return $this;
    }

    /**
     * Adds an entry to the log file
     * @return boolean
     */
    public function log($var,$level=null,$label=null){
        return Raxan::log($var,$level,$label);
    }

    /**
     * Returns Object ID
     * @return int
     */
    public function objectId() {
        return $this->objId;
    }

    /**
     * Triggers an event on the object
     * @param string $type Event type
     * @param mixed $args Optional event argument
     * @return RaxanBase
     */
    public function trigger($type,$args = null){
        $events = & $this->events; $id = $this->objId.$type;
        $hnds = isset($events[$id]) ? $events[$id] :  null;
        if ($hnds) {
            $e = new RaxanSysEvent($type);
            foreach($hnds as $hnd) {
                $fn = $hnd[0]; $data = $hnd[1];
                if (!is_callable($fn)) Raxan::throwCallbackException($hnd);
                else {
                    $e->data = $data;
                    if (is_string($fn)) $rt = $fn($e,$args);  // function callback
                    else  $rt = $fn[0]->{$fn[1]}($e,$args);   // object callback
                    if ($rt!==null) $e->result = $rt;
                    if (!$e->isStopPropagation) break;
                }
            }
        }
        return $this;
    }

    /**
     * Removes all event handlers for the specified event type
     * @return RaxanBase
     */
    public function unbind($type){
        $id = $this->objId.$type;
        unset($this->events[$id]);
        return $this;
    }


}

/**
 * Raxan System Event
 */
class RaxanSysEvent {
    public $type;
    public $result = null;     // returned value from previous handler
    public $data;
    public $isStopPropagation = false;

    public function __construct($type) {
        $this->type = $type;
    }

    /**
     * Stops event propagation
     * @return RaxanWebPageEvent
     */
    public function stopPropagation() {
        $this->isStopPropagation = true;
        return this;
    }    
}

/**
 * Raxan Session Data Storage
 */
class RaxanSessionStorage extends RaxanDataStorage {

    protected function _init() {
        if (!session_id()) {    // no current session exists so let's create one
            if ($this->id) session_id($this->id);
            session_name($name = Raxan::config('session.name'));
            $timeout = intval(Raxan::config('session.timeout')) * 60;
            if ($timeout) session_set_cookie_params($timeout); //set timeout
            session_start();
            if (!$this->id) $this->id = session_id();
            // reset cookie timeout on page load/refesh
            if (isset($_COOKIE[$name]))
                setcookie($name, $_COOKIE[$name], time() + $timeout, '/');
        }
        $this->store = & $_SESSION; // use current session object
    }

    protected function _reset() {
        session_destroy();
        session_start();
        $this->store = & $_SESSION;
    }

}

?>