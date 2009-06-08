<?php
/**
 * @package Raxan
 */

// @todo: check other server settings to make sure that site path/url works.

// Main Error Handler
function richAPI_error_handler($errno, $errstr, $errfile, $errline ) {
    if (error_reporting()===0) return;
    if (error_reporting() & $errno){    // repect error reporting level
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
}

/**
 * Raxan Core Classes - Includes RichAPI & RichAPIEvent Classes
 */
class RichAPI {


    public static $version = '1.0'; // @todo: Update API version/revision
    public static $revision = '1.0.0.291';

    public static $isInit = false;
    public static $isSessionLoaded = false;
    
    public static $isDebug = false;
    public static $isLogging = false;
    public static $postBackToken;  // used to identify legitimate Events and Post Back requests

    private static $nativeJSON;
    private static $isJSONLoaded;
    private static $isLocaleLoaded = false;
    private static $jsonStrict, $jsonLose;

    private static $debug, $logFile = 'PHP';
    private static $configFile;
    private static $_timer;
    private static $jsStrng1= array('\\','"',"\r","\n","\x00","\x1a");
    private static $jsStrng2= array('\\\\','\\"','','\n','\x00','\x1a');
    private static $locale = array();
    private static $config = array(
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
        'cache.path'    => '',
        'locale.path'   => '',
        'session.name'  => 'XPDI1000SE',
        'session.timeout'=> '30',
        'session.handler'=> 'default',
        'db.default'    => '',
        'debug'         => false,
        'debug.log'     => false,
        'debug.output'  => 'embedded',
        'log.enable'    => false,
        'log.file'      => 'PHP',
        'error.400' => '', 'error.401' => '',
        'error.403' => '', 'error.404' => '',
    );

    /**
     * Initialize the system and load config options
     * @return Boolean
     */
    public static function init() {
        $config = &self::$config;
        $file  = self::$configFile ? self::$configFile :
                 $config['base.path'].'gateway.config.php';
        $rt = @include_once($file); // load config file if available. dont use file_exists as it adds overhead

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

        self::$isInit = true;

        // set error handler
        set_error_handler("richAPI_error_handler",error_reporting());
        
        return $rt;
    }

    /**
     * Start php user session
     */
    public static function initSession() {
        if (!self::$isInit) self::init();
        $hnd = self::$config['session.handler'];        
        if ($hnd=='database') {
            // load database session handler
            include_once(self::$config['base.path'].'shared/session.database.php');
        }
        session_name(self::$config['session.name']);
        session_start();
        self::$isSessionLoaded = true;
    }
    
    /**
     *  Init JSON support
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
     * Binds an Array or a PDO result set to a template
     * @returns String
     */
    public static function bindTemplate($rows, $options) {

        $rowType = '';
        if ($rows instanceof PDOStatement)
            $rows = $rows->fetchAll(PDO::FETCH_ASSOC); // get rows from PDO results
        else if ($rows instanceof RichElement) {
            $rows = $rows->get();   // get all matched elements
            $rowType='richElement';
        }

        if (empty($rows) || !is_array($rows)) return '';

        $removeTags = false;
        $opt = $tplAlt = $tpl = $options; $truncStr = '...';
        $page = $size = $trunc = $tr1 = $tr2 = 0;
        $tplF = $tplL = $tplS = $key = $selected = '';
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
                $key = isset($opt['key']) ? $opt['key'] : '';
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
                if ($fmt) $fmtr = new RichDataSanitizer();
                if ($fn) {
                    if (!is_callable($fn))
                        throw new Exception('Unable to execute callback function or method: '.print_r($fn,true));
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
        if ($rowType!='richElement'){
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

            // setup index row
            if ($isIndex) $values = array($i,$row,$rc);
            else {

                // check if row is an element
                if($rowType=='richElement'){
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
     * Converts the given date to a RichDateTime object
     * @returns RichDateTime
     */
    public static function CDate($dt = null) {
        require_once(RichAPI::config('base.path').'shared/rich.datetime.php');
        $dt = new RichDateTime($dt);
        return $dt;
    }

    /**
     * Returns or sets configuration values
     * @return Mixed
     */
    public static function config($key,$value = null) {
        if ($key!=='base.path' &&  !self::$isInit) self::init();
        if($value===null) return isset(self::$config[$key]) ? self::$config[$key] : '';
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
     * @param Mixed $dsn String or Array
     * @return PDO  False is connection failed
     */
    public static function Connect($dsn,$user=null,$password=null,$attribs=null){
        $dsn = (is_string($dsn) && $d=RichAPI::config('db.'.$dsn)) ? $d :$dsn;
        if (is_array($dsn)){
            // build pdo dsn
            $user = $user ? $user : $dsn['user'];
            $password = $password ? $password : $dsn['password'];
            $attribs = $attribs ? $attribs : ($dsn['attribs']? $dsn['attribs'] : null);
            $dsn = $dsn['dsn'];
        }
        try {
            return new PDO($dsn,$user,$password,$attribs);
        }
        catch(PDOException $e){
            $lbl = 'RichAPI::Connect';
            $msg = $e->getMessage()."\n".$e->getTraceAsString();
            // remove sensative data
            $msg = str_replace(array($dsn,$user,$password),'...',$msg);
            RichAPI::log($msg,'error',$lbl) || RichAPI::debug($lbl.' Error: '.$msg);
            return false;
        }
    }

    /**
     * Returns or sets named data value based on the specified id and/or key
     * @return Mixed
     */
    public static function &data($id,$name = null,$value = null){
        if (!self::$isSessionLoaded) self::initSession();
        if(!isset($_SESSION[$id])) $_SESSION[$id] = array(); // create data cache
        if ($value!==null) $_SESSION[$id][$name] = $value;
        else if ($name===null) return $_SESSION[$id];    // return data array
        return $_SESSION[$id][$name];
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
     * Converts a multi-line text to a single-line js string
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
                if (self::$nativeJSON) $rt = json_decode($json,$assoc);
                else {
                    $rt = ($assoc) ? self::$jsonLose->decode($json) : self::$jsonStrict->decode($json) ;
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
        if ($key===null) return $config['site.locale'];
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
                    RichAPI::debug('Error while loading Language File \''.$f.'\' - '.$e->getMessage());
            }
            
        }
        return $rt;
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
        if (self::$isDebug && self::$config['debug.log']) RichAPI::debug($var);
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
        if (!self::$isSessionLoaded) self::initSession();
        if ($name===null) unset($_SESSION[$id]);
       else unset($_SESSION[$id][$name]);
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
        if ($msg!=$code) echo $html;
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

}

// RichAPI Base Class
abstract class RichAPIBase {

    protected static $mObjId = 0;   // Event Object counter
    protected $objId, $events;

    public function __construct() {
        $this->objId = self::$mObjId++;
    }

    /**
     * Bind the selected event to a callback function
     * @return RichAPIBase
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
     * Triggers an event on the object
     * @return RichAPIBase
     */
    public function trigger($type,$args = null){
        $e = & $this->events;
        $id = $this->objId.$type;
        $hnds = isset($e[$id]) ? $e[$id] :  null;
        if ($hnds) foreach($hnds as $hnd) {
            if (is_callable($validator)) {
                $fn = $validator;
                if (is_string($fn)) $rt = $fn($value);  // function callback
                else  $rt = $fn[0]->{$fn[1]}($value);   // object callback
            }
            else {
                throw new Exception('Unable to execute callback function or method: '.print_r($hnd,true));
            }             
        }
        return $this;
    }

    /**
     * Removes all event handlers for the specified event type
     * @return RichAPIBase
     */
    public function unbind($type){
        $id = $this->objId.$type;
        unset($this->events[$id]);
        return $this;
    }

    /**
     * Adds an entry to the log file
     * @return Boolean     
     */
    public function log($var,$level=null,$label=null){
        return RichAPI::log($var,$level,$label);
    }
}

/**
 * Provides APIs to filter and sanitizer user inputs and file uploads
 */
class RichDataSanitizer {
    /**
     *  @var $iDate RichDateTime */

    protected static $validators = array();
    protected static $badCharacters = array("\r","\n","\t","\x00","\x1a");

    protected $iData;
    protected $iDate;
    protected $charset;

    
    public function __construct($array=null,$charset = null) {
        $this->charset = $charset ? $charset : RichAPI::config('site.charset');
        $this->setDataArray($array);
    }

    /**
     * Sets the array source for the sanitizer
     */
    public function setDataArray($array) {
        $this->iData = is_array($array) ? $array : $_POST;
    }

    // handle calls for custom validators
    public function __call($name,$args){
        $validator = isset(self::$validators[$name]) ? self::$validators[$name] : '';
        if (!$validator) {
            throw new Exception('Undefined Method \''.$name.'\'');
        }
        $isPattern = substr($validator,0,1)=='#';
        $value = $this->value($args[0]);
        if ($isPattern) return preg_match(substr($validator,1),$value);
        elseif (is_callable($validator)) {
            $fn = $validator;
            if (is_string($fn)) $rt = $fn($value);  // function callback
            else  $rt = $fn[0]->{$fn[1]}($value);   // object callback
            return $rt ? $rt : false;
        }
        else {
            throw new Exception('Unable to execute validator callback function or method: '.print_r($validator,true));
        }
    }

    /**
     * Adds a custom data validator using regex patterns or callback function
     * Used as a wrapper to addDataValidator
     */
    public function addValidator($name,$pattern){
        self::addDataValidator($name,$pattern);
    }

    /**
     * Returns formated date value
     * @return String
     */
    public function date($key,$format = null) {
        if ($format===null) $format = 'iso';
        $noTrans  = false;
        switch ($format) {
            case 'iso':
            case 'mysql':
                $format = 'Y-m-d'; $noTrans = true;
                break;
            case 'mssql':
                $format = 'm/d/Y'; $noTrans = true;
                break;
            case 'short':
                $format = RichAPI::locale('date.short');
                break;
            case 'long':
                $format = RichAPI::locale('date.long');
                break;
        }
        
        if (!isset($this->iDate)) $this->iDate = RichAPI::CDate();
        $v = $this->iDate->format($format,$this->value($key),$noTrans);
        return $v;
    }

    /**
     * Returns sanitized email address for the selected field
     * @return String
     */
    public function email($key) {
        return str_replace(self::$badCharacters,'',$this->value($key));
    }

    /**
     * Returns html escaped value for the selected field
     * @return String
     */
    public function escape($key) {
        return htmlspecialchars($this->value($key), ENT_COMPAT, $this->charset);
    }

    /**
     * Returns float value
     * @return Float
     */
    public function float($key,$decimal = null) {
        $v = $this->value($key);
        return $decimal ? number_format($v,$decimal) :(float)$v;
    }

    /**
     * Returns sanitized html by removing javascript tags and inline events
     * @return String
     */
    public function html($key,$allowable = null,$allowStyle = true) {
        $v = $this->value($key);
        if ($allowable==null) {
            // remove script & style tags
            $rx1 = '#<script[^>]*?>.*?</script>'.(!$allowStyle ? '|<style[^>]*?>.*?</style>' :'').'#is';
            $v = preg_replace($rx1,'',$v);
        }
        else {
            // allow specified html tags
            $v = strip_tags($v,$allowable);
        }
        // nutralize inline styles and events
        $rx1 = '/<\w+\s*.*(on\w+\s*=|style\s*=)[^>]*?>/is';
        $rx2 = '/on\w+.s*=\s*'.(!$allowStyle ? '|style\s*=\s*' : '').'/is';
        $rx3 = '/nXtra=(["\']).*?\1|javascript\:|\s*expression\s*.*\(.*[^\)]?\)/is';
        if (preg_match_all($rx1,$v,$m)) {
            $tags = preg_replace($rx2,'nXtra=',$m[0]); // nutralize inline scripts/styles
            $tags = preg_replace($rx3,'',$tags);
            $v = str_replace($m[0],$tags,$v);
        }
        return $v;

    }

    /**
     * Returns the content of an uploaded file based on the selected field name
     * @return String
     */
    public function fileContent($fld) {
        $fl = isset($_FILES[$fld]) ? $_FILES[$fld]['tmp_name'] : '';
        return (file_exists($fl)) ? file_get_contents($fl) : '';
    }

    /**
     * Copies an uploaded files (based on the selected field name) to the specified destination.
     * @return Boolean
     */
    public function fileCopy($fld,$dest) {
        $fl = isset($_FILES[$fld]) ? $_FILES[$fld] : null;
        if($fl) return copy($fl['tmp_name'],$dest);
        else return false;
    }

    /**
     * Returns a total number of file uploaded
     * @return Integer
     */
    public function fileCount() {
         return isset($_FILES) ? count($_FILES) : 0;
    }

    /**
     * Returns an array containing the width, height and type for the uploaded image file
     * @return Array
     */
    public function fileImageSize($key) {
        if (!function_exists('getImageSize')) {
            RichAPI::log('Function getImageSize does not exists - The GD image processing library is required.','warn','RichDataSanitizer::fileImageSize');
            return null;
        }

        $fl = isset($_FILES[$key]) ? $_FILES[$key] : null;
        $info = $fl ? @getImageSize($fl['tmp_name']) : null;
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
     * Resamples (convert/resize) the selected image. You can specify a new width, height and type
     * @return Boolean
     */
    public function fileImageResample($key,$w,$h,$type=null) {
        $fl = isset($_FILES[$key]) ? $_FILES[$key] : null;
        if (!function_exists('imagecreatefromstring')) {
            RichAPI::log('Function imagecreatefromstring does not exists - The GD image processing library is required.','warn','RichDataSanitizer::fileImageResample');
            return false;
        }
        $info = $fl ? @getImageSize($fl['tmp_name']) : null;
        if ($info) {
            // maintain aspect ratio
            if ($h==0) $h = $info[1] * ($w/$info[0]);
            if ($w==0) $w = $info[0] * ($h/$info[1]);
            if ($w==0 && $h==0) {$w = $info[0]; $h = $info[1];}
            // resize/resample image
            $img = @imageCreateFromString(file_get_contents($fl['tmp_name']));
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
            if (function_exists($f)) $f($newImg,$fl['tmp_name']);
            imagedestroy($newImg);
            return true;
        }
        return false;
    }

    /**
     * Moves an uploaded files (based on the selected field name) to the specified destination.
     * @return Boolean
     */
    public function fileMove($fld, $dest) {
        $fl = isset($_FILES[$fld]) ? $_FILES[$fld] : null;
        if($fl) return move_uploaded_file($fl['tmp_name'], $dest);
        else  return false;

    }

    /**
     * Returns the original name of the uploaded file based on the selected field name
     * @return String
     */
    public function fileOrigName($fld) {
        return  isset($_FILES[$fld]) ? $_FILES[$fld]['name'] : '';
    }


    /**
     * Returns the size of the uploaded file based on the selected field name
     * @return Integer
     */
    public function fileSize($fld) {
        return  isset($_FILES[$fld]) ? $_FILES[$fld]['size'] : '';
    }

    /**
     * Returns the file type (as reported by browser) of an uploaded file based on the selected field name
     * @return String
     */
    public function fileType($fld) {
        return  isset($_FILES[$fld]) ? $_FILES[$fld]['type'] : '';
    }

    /**
     * Returns integer value
     * @return Integer
     */
    public function integer($key) {
        return (int)$this->value($key);
    }

    /**
     * Returns true if the selected field is a valid date entry
     * @return Boolean
     */
    public function isDate($key,$format = null) {
        if ($format===null && $this->timestamp($key)>0) return true;
        else {
            $dt = trim($this->value($key));
            return strtolower($dt) === strtolower($this->date($key,$format));
        }
    }

    /**
     * Returns true if the selected field is a valid email address
     * @return Boolean
     */
    public function isEmail($key) {
        // Based on Regex by Geert De Deckere. http://pastie.textmate.org/159503
        $regex = '/^[-_a-z0-9\'+^~]++(?:\.[-_a-z0-9\'+^~]+)*+@'.
                 '(?:(?![-.])[-a-z0-9.]+(?<![-.])\.[a-z]{2,6}|\d{1,3}(?:\.\d{1,3}){3})(?::\d++)?$/iD';
        return preg_match($regex, $this->value($key));
    }

    /**
     * Returns true if the selected field is numeric
     * @return Boolean
     */
    public function isNumeric($key) {
        return is_numeric($this->value($key));
    }

    /**
     * Returns true if the selected field is numeric
     * @return Boolean
     */
    public function isUrl($key) {
        // @todo: Optimize isUrl() - replace rexgex if necessary
        // regex based on http://geekswithblogs.net/casualjim/archive/2005/12/01/61722.aspx
        $regex = '>^(?#Protocol)(?:(?:ht|f)tp(?:s?)\:\/\/|~/|/)?(?#Username:Password)(?:\w+:\w+@)'.
                 '?(?#Subdomains)(?:(?:[-\w]+\.)+(?#TopLevel Domains)(?:com|org|net|gov|mil|biz|info|mobi|name|aero|jobs|museum|travel|[a-z]{2}))'.
                 '(?#Port)(?::[\d]{1,5})?(?#Directories)(?:(?:(?:/(?:[-\w~!$+|.,=]|%[a-f\d]{2})+)+|/)+|\?|#)?(?#Query)(?:(?:\?(?:[-\w~!$+|.,*:]|'.
                 '%[a-f\d{2}])+=(?:[-\w~!$+|.,*:=]|%[a-f\d]{2})*)(?:&(?:[-\w~!$+|.,*:]|%[a-f\d{2}])+=(?:[-\w~!$+|.,*:=]|%[a-f\d]{2})*)*)'.
                 '*(?#Anchor)(?:#(?:[-\w~!$+|.,*:=]|%[a-f\d]{2})*)?$>i ';
        $ok = preg_match($regex, $this->value($key),$m);
        return $ok;
    }

    /**
     * Returns the length of the speicifed field value
     * @return Integer
     */
    public function length($key) {
        return strlen($this->value($key));
    }

    /**
     * Returns formatted money value based on locale settings
     * @return String
     */
    public function money($key,$decimal = null) {
        $v = $this->number($key,$decimal);
        $mf = RichAPI::locale('money.format');
        if ($mf) return money_format($mf, $v);   // @todo: Test money_format;
        else {
            $cs = RichAPI::locale('currency.symbol');
            $cl = RichAPI::locale('currency.location');
            return $cl=='rt' ? $v.$cs : $cs.$v;
        }
    }

    /**
     * Returns formatted number value based on locale settings
     * @return String
     */
    public function number($key,$decimal = null) {
        $ds = RichAPI::locale('decimal.separator');
        $ts = RichAPI::locale('thousand.separator');
        return number_format($this->value($key),$decimal,$ds,$ts);
    }

    /**
     * Remove html tags
     * @return String
     */
    public function text($key,$length = null) {
        $v = strip_tags($this->value($key));
        $v = ($length!==null && is_numeric($length)) ? substr($v,0,$length) : $v;
        return $v;
    }

    /**
     * Returns timestamp
     * @return Integer
     */
    public function timestamp($key) {
        if (!isset($this->iDate)) $this->iDate = RichAPI::CDate();
        return $this->iDate->getTimestamp($this->value($key));
    }

    /**
     * Returns sanitized url for the selected field
     * @return String
     */
    public function url($key, $encoded = false) {
        $v = str_replace(self::$badCharacters,'',$this->value($key));
        return $encode ?  url_encode($v) : $v;
    }
    
    /**
     * Returns a value  based on the specified key
     * @return Mixed
     */
    public function value($key) {
        return isset($this->iData[$key]) ? $this->iData[$key] : null;
    }


    // Static Functions
    // -----------------------

    /**
     * Adds a custom data validator using regex patterns or callback function
     * A callback function can be used as in place of a $pattern
     * @return null 
     */
    public static function addDataValidator($name,$pattern){
        $isRegEx = is_string($pattern) && preg_match('/^\W/',trim($pattern));
        self::$validators['is'.ucfirst($name)] = ($isRegEx ? '#':'').$pattern;
    }

}

?>