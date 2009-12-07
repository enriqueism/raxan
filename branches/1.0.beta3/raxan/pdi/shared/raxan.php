<?php
/**
 * Raxan for PHP
 * This file includes Raxan, RaxanBase, RaxanDataStorage, RaxanSessionStorage, RaxanPlugin
 * @package Raxan
 */

// @todo: check other server settings to make sure that site path/url works.


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
        if (isset($this->events[$type])) $this->{$type}($event,$args);
    }

    public static function instance($class) {
        $cls = $class;
        if (!isset(self::$shared[$cls])) self::$shared[$cls] = new $cls();
        return self::$shared[$cls];
    }
}

/**
 * Raxan Main classs
 */
class Raxan {

    public static $version = '1.0'; // @todo: Update API version/revision
    public static $revision = '1.0.0.b3';

    public static $isInit = false;
    public static $isPDOLoaded = false;
    public static $isDataStorageLoaded = false;

    public static $isDebug = false;
    public static $isLogging = false;
    public static $postBackToken;  // used to identify legitimate Events and Post Back requests

    private static $nativeJSON;
    private static $isJSONLoaded;
    private static $isLocaleLoaded = false;
    private static $jsonStrict, $jsonLose;
    private static $dataStore;
    private static $isSanitizerLoaded = false;

    private static $debug, $logFile = 'PHP';
    private static $configFile;
    private static $sysEvents;
    private static $_timer;
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
        'site.url'      => '',
        'site.path'     => '',
        'raxan.url'     => '',
        'raxan.path'    => '',
        'views.path'    => '',
        'plugins.path'  => '',
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
        'page.data.storage' => 'RaxanWebPageStorage'    // default page data storage class
    );

    /**
     * Initialize the system and load config options
     * @return Boolean
     */
    public static function init() {
        $config = &self::$config;
        $file  = self::$configFile ? self::$configFile :
                 $config['base.path'].'gateway.config.php';
                 
	// load config file if available. 
        $rt = file_exists($file) ? include_once($file) : false;  // @todo: To be optimized. Maybe @include_once is faster?

        // setup defaults
        $base = $config['base.path'];
        if (empty($config['raxan.path'])||empty($config['raxan.url'])) {
            // auto detect raxan path & url
            $pth = implode('/',array_slice(explode('/',$base),0,-2));
            $config['raxan.path'] = $pth.'/';
            $config['raxan.url'] = './raxan/';
        }
        if (empty($config['site.path'])||empty($config['site.url'])) {
            // auto detect site path & url
            $sn = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
            $sf = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '';
            $ps = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
            if ($sn && $ps!=$sn) $su = $sn; else $su = $ps;
            $config['site.url'] = $su ? dirname($su).'/' : './';
            $config['site.path'] = $sf ? dirname(str_replace('\\','/',$sf)).'/' : './';
        }
        if (empty($config['cache.path'])) $config['cache.path'] = $base.'cache/';
        if (empty($config['locale.path'])) $config['locale.path'] = $base.'shared/locale/';
        if (empty($config['views.path'])) $config['views.path'] = $config['site.path'].'views/';
        if (empty($config['plugins.path'])) $config['plugins.path'] = $config['raxan.path'].'plugins/';

        self::$isDebug = $config['debug'];
        self::$isLogging = $config['log.enable'];

        // setup post back token
        if (isset($_COOKIE['_ptok'])) self::$postBackToken =  $_COOKIE['_ptok'];
        else {
            self::$postBackToken = chr(rand(65,90)).(rand(1000,9999999));
            setcookie('_ptok',self::$postBackToken);
        }

        // set timezone
        if ($config['site.timezone']) date_default_timezone_set($config['site.timezone']);

        // set error handler
        set_error_handler("raxan_error_handler",error_reporting());

        self::$isInit = true;
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
            include_once dirname(__FILE__)."/JSON.php";
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
     * @returns String
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

        $removeTags = false;
        $opt = $tplAlt = $tpl = $options; $truncStr = '...';
        $page = $size = $trunc = $tr1 = $tr2 = 0;
        $tplF = $tplL = $tplS = $tplE = $key = $edited = $selected = '';
        $fn = $delimiter = $callByVariable = $rtArr = $fmt = '';
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
                $key = isset($opt['key']) ? $opt['key'] : '';
                $edited = isset($opt['edited']) ? $opt['edited'] : '';
                $selected = isset($opt['selected']) ? $opt['selected'] : '';
                $page = isset($opt['page']) ? (int)$opt['page'] : 0;
                $size = isset($opt['pageSize']) ? (int)$opt['pageSize'] : 0;
                $delimiter = isset($opt['delimiter']) ? $opt['delimiter'] : '';
                $fn = isset($opt['callback']) ? $opt['callback'] : 0;
                $rtArr = isset($opt['returnArray']) ? true : false;
                $fmt = isset($opt['format']) ? $opt['format'] : '';
                $truncStr = isset($opt['truncString']) ? $opt['truncString'] : $truncStr;
                $trunc = isset($opt['truncate']) ? (float)$opt['truncate'] : 0;
                $removeTags = isset($opt['removeUnusedTags']) ? $opt['removeUnusedTags'] : false;
                $tr1 = intval($trunc); $tr2 = abs(str_replace('0.','',$trunc - $tr1));  // get truncate values
                if ($selected && !is_array($selected)) $selected = array($selected);
                if ($fmt) $fmtr = Raxan::dataSanitizer();
                if ($fn) {
                    if (!is_callable($fn)) Raxan::throwCallbackException($fn);
                    $callByVariable = is_string($fn);
                }
            }
        }

        // fix: using {tags} in <a> tags. E.g.  <a href="{name}">
        if (strpos($tpl.$tplAlt.$tplF.$tplL.$tplS,'%7B')!==false)  {
            $a1 = array('%7B','%7D'); $a2 = array('{','}');
            list($tpl,$tplAlt,$tplF,$tplL,$tplS) = str_replace($a1,$a2,array($tpl,$tplAlt,$tplF,$tplL,$tplS));
        }

        // get record size if not set
        if (!$size && ($tplL||$page||$trunc!=0)) $size = count($rows);

        // finalize row setup
        $rc = $page ? ($page-1)*$size : 0; // init row count
        $rt = array(); $isIndex = false; $startTrunc = '';
        if ($rowType!='raxanElement'){
            if (!isset($rows[0])) $rows = array($rows);
            elseif (!is_array($rows[0])) {
                $isIndex = true;
                $keys = array('{INDEX}','{VALUE}','{ROWCOUNT}');
            }
        }
        
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
            if ($tplF && $rc==1) $t = $tplF; // first
            else if ($tplL && $rc==$size) $t = $tplL; // last
            else $t = ($i%2) ? $tplAlt : $tpl;

            // check if row selected
            if ($tplS && ($key || $isIndex) && $selected) {
                $v = isset($row[$key]) ? $row[$key] : $row;
                $t = in_array($v,$selected) ? $tplS : $t;
            }

            // check if row should be edited
            if ($tplE && ($key || $isIndex) && $edited) {
                $v = isset($row[$key]) ? $row[$key] : $row;
                $t = ($v==$edited) ? $tplE : $t;
            }

            // setup index row
            if ($isIndex) $values = array($i,$row,$rc);
            else {

                // check if row is an element
                if($rowType=='raxanElement'){
                    $v = array('INDEX'=>$i,'VALUE'=>$row->nodeValue);
                    $row = $row->attributes;
                    foreach($row as $attr) $v[$attr->name] = $attr->value;
                    $row = $v; $v = null; $attr = null;
                }

                // format values
                if ($fmt) {
                    $fmtr->setDataArray($row);
                    foreach($fmt as $n=>$f) {
                        if(!isset($row[$n])) continue;
                        if (!isset($fmt[$n.'.param'])) {
                            if ($f=='longdate'||$f=='shortdate') $f = 'date:'.substr($f,0,-4);
                            $v = ($p=strpos($f,':')) ? substr($f,$p+1) : null;
                            $f = $fmt[$n] = $p ? substr($f,0,$p) : $f;
                            if ($f=='replace') {
                                $v = explode(',',$v,2);
                                if (!isset($v[1])) $v[1] = '';
                            }
                            $fmt[$n.'.param'] = $v; 
                        }
                        $p = $fmt[$n.'.param']; // get parameter
                        if ($f=='integer') $row[$n] = $fmtr->integer($n);
                        else if ($f=='float') $row[$n] = $fmtr->float($n);
                        else if ($f=='money') $row[$n] = $fmtr->money($n,$p);
                        else if ($f=='escape') $row[$n] = $fmtr->escape($n);
                        else if ($f=='date') $row[$n] = $fmtr->date($n,$p);
                        else if ($f=='number') $row[$n] = $fmtr->number($n,$p);
                        else if ($f=='capitalize') $row[$n] = ucwords($row[$n]);
                        else if ($f=='replace') $row[$n] = preg_replace('/'.$p[0].'/i',$p[1],$row[$n]);
                    }
                }
                $keys = !isset($keys) ? explode(',','{'.implode('},{',array_keys($row)).'},{ROWCOUNT}') : $keys;
                $values = array_values($row); $values[] = $rc;
            }

            // callback handler
            if (!$fn) $rt[] = str_replace($keys,$values,$t);
            else  {
                $rt[] = ($callByVariable) ? $fn($row,$i,$t) : $fn[0]->{$fn[1]}($row,$i,$t);
            }

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
     * @return Mixed
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
     * Usage:
     *  Raxan::connect($dsn,$uid,$pwd,$errMode) // enables exception error mode - set $errMode to true or set to PDO error mode constant
     *  Raxan::connect($dsn,$uid,$pwd,$attribs) // set attributes
     * 
     * @param Mixed $dsn String or Array
     * @param Mixed $attribs Boolean, PDO error mode or Array of attributes
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
            $pdo =  new RaxanPDO($dsn,$user,$password,$attribs);
            return $pdo;
        }
        catch(PDOException $e){
            $lbl = 'Raxan::connect';
            $msg = $e->getMessage()."\n".$e->getTraceAsString();            
            $msg = str_replace(array($dsn,$user,$password),'...',$msg); // remove sensative data
            if ($errmode!==null) throw new Exception($msg,$e->getCode());
            else {
                self::log($msg,'error',$lbl) || self::debug($lbl.' Error: '.$msg);
                return false;
            }            
        }
    }

    /**
     * Returns current web page URL
     * @return String
     */
    public static function currentURL() {
        // @todo: optimize currentURL ?
        $qs = isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? 
           '?'.str_replace(array('"','<','>'), array('%22','%3C','%3E'),$_SERVER['QUERY_STRING'])  : ''; // sanitize: encode speical chars
        return isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'].$qs : '';
    }

    /**
     * Returns or sets named data value based on the specified id and/or key
     * @return Mixed
     */
    public static function &data($id,$name = null,$value = null,$setValueIfNotIsSet = false){
        if (!self::$isDataStorageLoaded) self::initDataStorage();
        $s = self::$dataStore;
        if($s->exists($id)) $h = & $s->read($id);
        else $h = & $s->write($id,array()); // create data cache
        if ($name===null) return $h;    // return data array
        else if ($value!==null) {
            $sv = $setValueIfNotIsSet;
            if (!$sv || ($sv && !isset($h[$name]))) $h[$name] = $value; // set value on first use
        }
        return $h[$name];
    }

    /**
     * Sanitize the selected array and returns an instanace of the Data Sanitizer
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
        if ($store===null && !self::$isDataStorageLoaded) self::initDataStorage();
        else {
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
     * @return Boolean
     */
    public static function debug($txt){
        if (self::$isDebug) {
            if (!isset(self::$debug)) self::$debug = array();
            self::$debug[] = print_r($txt,true);
            return true;
        }
        return false;
    }

    /**
     * Returns debug output as text
     * @return String
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
     * @return String
     */
    public static function escapeText($txt) {
        if (!$txt) return '';
        else return str_replace(self::$jsStrng1,self::$jsStrng2,$txt);
    }

    /**
     * Timer Functions
     */
    public static function getTimer()  { return self::$_timer; }
    public static function startTimer(){ self::$_timer = microtime(true); }
    public static function stopTimer() { return self::$_timer = microtime(true) - self::$_timer; }


    /**
     * Resamples (convert/resize) an image file. You can specify a new width, height and type
     * @return Boolean
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
     * @return Array or NULL if error
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
     * @return Array
     */
    public static function importCSV($file, $delimiter = ',', $enclosure = '"', $escape = '\\', $terminator = "\n") {
        $csv = file_get_contents($file);
        if (!function_exists('raxan_csv_to_array')) include_once self::$config['base.path'].'shared/csvtoarray.php';
        return raxan_csv_to_array($csv);
    }
    
    /**
     * Encode/Decode JSON Strings
     * @return String
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
     * @return String
     */
    public static function locale($key = null,$param1=null,$param2=null) {
        if (!self::$isLocaleLoaded) self::setLocale(self::$config['site.locale']); // init on first use
        if ($key===null) return self::$config['site.locale'];
        $v = isset(self::$locale[$key]) ? self::$locale[$key] : '';
        return ($param1!==null) ? sprintf($v,$param1,$param2) : $v;
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
     * @return Boolean
     */
    public static function loadLangFile() {
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
     * Load plugin file.
     * @param $extrn Boolean Set to true if file will be loaded from path that's external to plugins.path
     * @return Boolean
     */
    public static function loadPlugin($file,$extrn = false) {
        if (!self::$isInit) self::init();
        if (!$extrn) $file = self::$config['plugins.path'].$file.'.php';
        return include_once($file);
    }

    /**
     * Adds an entry to the log file
     * @param String $str
     * @param String $level Optional tag to be assocciated with the log entry. E.g. ERROR, WARNING, INFO, etc
     * @param String $label Optional.
     * @return Boolean
     */
    public static function log($var, $level = null, $label = null){
        if (!self::$isInit) self::init();
        if (!self::$isLogging) return false;
        $level = $level ? strtoupper($level): 'INFO';
        $label = $label ? ' ['.$label.']' :  '';
        $var = $level." \t".date('Y-m-d H:i:s',time())." \t".$label. " \t".print_r($var,true);
        if (self::$isDebug && self::$config['debug.log']) Raxan::debug($var);
        if (self::$logFile=='PHP') return error_log($var);
        else {
            try {
                // @todo: add code to truncate log file
                return error_log($var."\n",3,self::$logFile);
            } catch(Exception $e){
                exit('PDI Logger: '.$e->getMessage()."<br />\n".$e->getTraceAsString());
            }
        }
    }
    /**
     * Generate page numbers based . The $option values are similar to that of bindTemplate
     * @returns String
     */
    public static function paginate($maxPage,$page,$options = null) {
        $o = is_array($options) ? $options : array();
        $ps = isset($o['pageSize']) ? (int)$o['pageSize'] : 5;
        if ($ps<3) $ps = 3; if ($page<1)$page = 1;
        if (!isset($o['tpl'])) $o['tpl'] = '<a href="#{VALUE}">{VALUE}</a>&nbsp;';
        if (!isset($o['tplSelected'])) $o['tplSelected'] = '<span>{VALUE}&nbsp;</span>';
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

        return str_replace(
            array('{FIRST}','{LAST}','{NEXT}','{PREV}'),
            array(1,$maxPage,$next,$prev),
            self::bindTemplate($pg, $o)
        );
    }

    /**
     * Remove named data
     */
    public static function removeData($id,$name = null) {
        if (!self::$isDataStorageLoaded) self::initDataStorage();
        $s = & self::$dataStore;
        if ($name===null) $s->remove($id);
        else { $h = & $s->read($id); unset($h[$name]); }
    }

    /**
     * Sends an error page to the web browser
     */
    public static function sendError($msg,$code = null) {
        $html = ''; $code = !$code ? $msg: $code;
        if ($code && !empty(self::$config['error.'.$code])) {
            $html = @file_get_contents(self::$config['error.'.$code]);
        }
        $html = $html ?  str_replace('{message}',$msg,$html) :$msg;
        switch ($code) {
            case 400: header("HTTP/1.0 400 Bad syntax"); break;
            case 401: header("HTTP/1.0 401 Unauthorized"); break;
            case 403: header("HTTP/1.0 403 Forbidden"); break;
            case 404: header("HTTP/1.0 404 Not Found"); break;
        }
        if ($msg!=$code) {
            if (!isset($_REQUEST['_ajax_call_'])) echo $html;
            else {                
                echo self::JSON('encode', array(
                    '_actions' => 'alert("'.self::escapeText($msg).'");'
                ));
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
     * @return Boolean     
     */
    public static function setLocale($code,$lang = null) {
        if (!self::$isInit) self::init();
        // load locale general settings
        $locale = &self::$locale;
        $config = &self::$config;
        $code = strtolower(trim($code));
        $config['site.locale'] = $code;
        $pth = $config['locale.path'] . str_replace('-','/',$code).'/'; // locales are stored as {lang}/{country}
        if (@include_once($pth.'general.php')) {
            $config['lang.path'] = $pth;
            if ($lang!==null) $config['site.lang'] = $lang;
            else $config['site.lang'] = substr($code,0,2);
            setlocale(LC_CTYPE,   $locale['php.locale']);
            setlocale(LC_COLLATE, $locale['php.locale']);
            // setup locale date name - used instead of LC_TIME as it's reported to fail on some systems
            $locale['dt._eng_names'] = array(
                'january','february','march','april','may','june','july',
                'august','september','october','november','december',
                'jan','feb','mar','apr','may','jun','jul','aug','sep','oct',
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
     * Triggers a System Event
     */
    public static function triggerSysEvent($name,$args = null) {
        if (isset(self::$sysEvents[$name])) {
            $e = new RaxanSysEvent($name);
            $hndls = self::$sysEvents[$name];
            if ($hndls) foreach ($hndls as $fn) {
                if (is_array($fn)) $rt = $fn[0]->{$fn[1]}($e,$args);
                else $rt = $fn($e,$args);
                if ($rt!==null) $e->result = $rt;
                if ($e->isStopPropagation) break;
            }
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
        // @todo: To be reviewed.
        if (!$this->events) $this->events = array();
        $cb = ($fn===null) ? $array($data,null) : array($fn,$data);
        $e = & $this->events; $id = $this->objId.$type;
        if (!isset($e[$id])) $e[$id] = array($cb);
        else $e[$id][] = $cb;
        return $this;
    }

    /**
     * Adds an entry to the log file
     * @return Boolean
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
     * @return RaxanBase
     */
    public function trigger($type,$args = null){
        $e = & $this->events; $id = $this->objId.$type;
        $hnds = isset($e[$id]) ? $e[$id] :  null;
        if ($hnds) {
            $e = new RaxanSysEvent($type);
            foreach($hnds as $hnd) {
                if (!is_callable($hnd)) Raxan::throwCallbackException($hnd);
                else {
                    $fn = $hnd;
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
     * @return  RaxanWebPageEvent
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
        if ($this->id) session_id($this->id);
        session_name($name = Raxan::config('session.name'));
        $timeout = intval(Raxan::config('session.timeout')) * 60;
        if ($timeout) session_set_cookie_params($timeout); //set timeout
        session_start();
        $this->store = & $_SESSION;
        if (!$this->id) $this->id = session_id();
        // reset cookie timeout on page load/refesh
        if (isset($_COOKIE[$name]))
            setcookie($name, $_COOKIE[$name], time() + $timeout, '/');
    }

    protected function _reset() {
        session_destroy();
        session_start();
        $this->store = & $_SESSION;
    }

}

?>