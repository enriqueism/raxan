<?php
/**
 * Raxan Data Sanitizer/Filter 
 * Copyright (c) 2011 Raymond Irving (http://raxanpdi.com)
 * @package Raxan
 */


/**
 * Provides APIs to filter, sanitizer and format user inputs and file uploads
 * @property RaxanDateTime $_date
 */
class RaxanDataSanitizer {

    protected static $validators = array();
    protected static $badCharacters = array("\r","\n","\t","\x00","\x1a");

    protected $_data;
    protected $_date;
    protected $_directInp;
    protected $_charset;


    public function __construct($array=null,$charset = null) {
        $this->_charset = $charset ? $charset : Raxan::config('site.charset');
        $this->setDataArray($array);
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
     * Returns the sanitized text for the specified key. All html charcters will be removed.
     * @return String
     */
    public function __get($name) {
        return $this->textVal($name);
    }

    /**
     * Sets the value for a key to be sanitized or formatted.
     * Example:
     * $format->keyName = 'Mary Jane<br />';
     * echo $format->textVal('keyName');
     * @return String
     */
    public function __set($name,$value) {
        $this->_data[$name] = $value;
    }

    /**
     * Adds a custom data validator using regex patterns or callback function
     * Used as a wrapper to the RaxanDataSanitizer::addDataValidator() static method 
     */
    public function addValidator($name,$pattern){
        self::addDataValidator($name,$pattern);
    }

    /**
     * Returns an alphanumeric value for the specified field name
     * @return string If input is an array then an array of alphanumeric values will be returned
     */
    public function alphanumericVal($key) {
        $v = $this->value($key);
        if (!is_array($v)) $v = trim(((preg_match('/^[a-zA-Z0-9]+$/',$t = trim($v))) ? $t : ''));
        else foreach ($v as $k=>$b){
            $v[$k] = trim(((preg_match('/^[a-zA-Z0-9]+$/',$t = trim($b))) ? $t : ''));
        }
        return $v;
    }

    /**
     * Returns a date value for the specified field name
     * @param string $format
     * @return mixed Returns a date/time string value based on the $format parameter or null if value is not a valid date.  If input is an array then an array of date values is returned
     */
    public function dateVal($key, $format = null) {
        return ($v = $this->formatDate($key,$format)) ? $v : null;
    }

    /**
     * Enables direct data input. This will allow values to be passed directly to validator functions.
     * @example
     *  <p>$sanitize->enableDirectInput();</p>
     *  <p>echo $sanitize->textVal('This is a &lt;strong&gt;text&lt;/strong&gt; message.');</p>
     * @param boolean $state Defaults to true
     */
    public function enableDirectInput($state = true) {
        $this->_directInp = $state;
    }

    /**
     * Returns sanitized email address for the selected field
     * @return string Returns sanitized email address or an empty string if input value is not a valid email address. If input is an array then an array of email values is returned
     */
    public function emailVal($key) {
        $v = $this->value($key);
        if (!is_array($v)) $v = trim(str_replace(self::$badCharacters,'',strip_tags($v)));
        else foreach ($v as $k=>$b){
            $v[$k] = trim(str_replace(self::$badCharacters,'',strip_tags($b)));
        }
        return $v;
    }

    /**
     * Returns text with special xml/html characters encoded for the selected field
     * @return string  If input is an array then an array of escaped values is returned
     */
    public function escapeVal($key) {
        $v = $this->value($key);
        if (!is_array($v)) $v = htmlspecialchars($v);
        else foreach ($v as $k=>$b){
            $v[$k] = htmlspecialchars($b);
        }
        return $v;
    }

    /**
     * Returns the content of an uploaded file based on the selected field name
     * @param string $fld Form element field name
     * @return string
     */
    public function fileContent($fld) {
        $fl = isset($_FILES[$fld]) ? $_FILES[$fld]['tmp_name'] : '';
        return (is_file($fl)) ? file_get_contents($fl) : '';
    }

    /**
     * Copies an uploaded files (based on the selected field name) to the specified destination.
     * @param string $fld Form element field name
     * @param string $dest Destination path and file name
     * @return boolean
     */
    public function fileCopy($fld,$dest) {
        $fl = isset($_FILES[$fld]) ? $_FILES[$fld] : null;
        if($fl) return copy($fl['tmp_name'],$dest);
        else return false;
    }

    /**
     * Returns a total number of file uploaded
     * @return int
     */
    public function fileCount() {
         return isset($_FILES) ? count($_FILES) : 0;
    }

    /**
     * Returns an array containing the width, height and type for the uploaded image file
     * @param string $fld Form element field name
     * @return mixed Array or null on error
     */
    public function fileImageSize($fld) {
        $fl = isset($_FILES[$fld]) ? $_FILES[$fld] : null;
        $fl = $fl ? $fl['tmp_name'] : null;
        return Raxan::imageSize($fl);
    }

    /**
     * Resamples (convert/resize) the uploaded image. You can specify a new width, height and type
     * @param string $fld Form element field name
     * @see Raxan::imageResample()
     * @return boolean
     */
    public function fileImageResample($fld,$w,$h,$type=null) {
        $fl = isset($_FILES[$fld]) ? $_FILES[$fld] : null;
        $fl  = $fl ? $fl['tmp_name'] : null;
        return Raxan::imageResample($fl,$w,$h,$type);
    }

    /**
     * Moves an uploaded files (based on the selected field name) to the specified destination.
     * @param string $fld Form element field name
     * @param string $dest Destination path and file name
     * @return boolean
     */
    public function fileMove($fld, $dest) {
        $fl = isset($_FILES[$fld]) ? $_FILES[$fld] : null;
        if($fl) return move_uploaded_file($fl['tmp_name'], $dest);
        else  return false;

    }

    /**
     * Returns the original name of the uploaded file based on the selected field name
     * @param string $fld Form element field name
     * @return string
     */
    public function fileOrigName($fld) {
        return isset($_FILES[$fld]) ?
            str_replace(array('/','\\'),'',strip_tags($_FILES[$fld]['name'])) : '';
    }

    /**
     * Returns the size of the uploaded file based on the selected field name
     * @param string $fld Form element field name
     * @return integer
     */
    public function fileSize($fld) {
        return isset($_FILES[$fld]) ? $_FILES[$fld]['size'] : '';
    }

    /**
     * Returns the file type (as reported by browser) of an uploaded file based on the selected field name
     * @param string $fld Form element field name
     * @return string
     */
    public function fileType($fld) {
        return isset($_FILES[$fld]) ? $_FILES[$fld]['type'] : '';
    }

    /**
     * Returns the file upload error code for uploaded file
     * @param string $fld Form element field name
     * @return string
     */
    public function fileUploadError($fld) {
        return isset($_FILES[$fld]) ? $_FILES[$fld]['error'] : '';
    }

    /**
     * Returns the temporary file name and path of the uploaded file based on the selected field name
     * @param string $fld Form element field name
     * @return string
     */
    public function fileTmpName($fld) {
        return isset($_FILES[$fld]) ? $_FILES[$fld]['tmp_name'] : '';
    }

    /**
     * Returns associative array of filtered/sanitized values
     * @param mixed $keyFields Optional. Comma (,) delimitted key/field names or associative array of field names and filter type. Default to all keys/fields with textVal filter applied
     * @return array
     */
    public function filterValues($keyFields = null) {
        $data = $fields = array();
        if (($kIsArray = is_array($keyFields))) $fields = array_keys($keyFields);
        else $fields = ($keyFields===null) ? array_keys($this->_data) : explode(',',(string)$keyFields) ;
        $oldDirectInp = $this->_directInp;
        $this->_directInp = true; // activate direct input mode
        foreach($fields as $k) {
            $k = trim($k);
            $v = isset($this->_data[$k]) ?  $this->_data[$k] : null;
            $p1 = $p2 = $p3 = null;
            $filter = ($kIsArray && isset($keyFields[$k])) ? $keyFields[$k] : 'text';
            if (is_array($filter)) {
                $p1 = isset($filter[1]) ? $filter[1] : null;
                $p2 = isset($filter[2]) ? $filter[2] : null;
                $p3 = isset($filter[3]) ? $filter[3] : null;
                $filter = $filter[0];
            }
            if (strpos('alphanumeric,date,email,escape,float,html,int,match,number,text,timestamp,url',$filter)===false) $filter = '';
            if (is_scalar($v)) $data[$k] = $filter ? $this->{$filter.'Val'}($v,$p1,$p2,$p3) : null; // directly pass value to filter function
            else if (is_array($v)) {
                $data[$k] = array();
                foreach ($v as $a=>$b) $data[$k][$a] = $filter ? $this->{$filter.'Val'}($b,$p1,$p2,$p3) : null;
            }
        }
        $this->_directInp = $oldDirectInp;  //restore input mode
        return $data;
    }
   
    /**
     * Converts input value/key to a float value
     * @return float Returns float if value is numeric or null if there was an error. If input is an array then an array of float values is returned
     */
    public function floatVal($key,$decimal = null) {
        $v = $this->value($key);
        if (!is_array($v))  {
            $v = is_numeric($v) ? (float)$v : null;
            $v = $v && is_numeric($decimal) ? number_format($v,$decimal) : $v;
        }
        else foreach ($v as $k=>$b){
            $b = is_numeric($b) ? (float)$b : null;
            $v[$k] = $b && is_numeric($decimal) ? number_format($b,$decimal) : $b;
        }
        return $v;
    }

    /**
     * Returns formated date value
     * @param string $key Key name or input value (direct input must be enabled)
     * @param string $format Date format. Default to ISO date format
     * @return string If input is an array then an array of formated date values is returned
     */
    public function formatDate($key,$format = null) {
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
                $format = Raxan::locale('date.short');
                break;
            case 'long':
                $format = Raxan::locale('date.long');
                break;
        }

        if (!isset($this->_date)) $this->_date = Raxan::cDate();
        $v = $this->value($key);
        if (!($isa = is_array($v))) {
            try {
                $v = $v ? $this->_date->format($format,$v,$noTrans) : '';
            } catch (Exception $e) { $v = ''; }
        }
        else foreach ($v as $k=>$b) {
            try {
                $b = $b ? $this->_date->format($format,$b,$noTrans) : '';
            } catch (Exception $e) { $b = ''; }
            $v[$k] = $b;
        }

        return $v;
    }

    /**
     * Returns formatted money value based on locale settings
     * @param string $key Key name or input value (direct input must be enabled)
     * @param int $decimal Optional. Total number of decimal places to return
     * @param string $symbol Optional. Currency symbol
     * @return string If input is an array then an array of formated values is returned
     */
    public function formatMoney($key,$decimal = null,$symbol = null) {
        $mf = Raxan::locale('money.format',$symbol);
        $ds = Raxan::locale('decimal.separator');
        $ts = Raxan::locale('thousand.separator');
        $cl = Raxan::locale('currency.location');
        $cs = $symbol ? $symbol : Raxan::locale('currency.symbol');
        $value = $this->value($key);
        $isa = is_array($value);
        if(!$isa) $value = array($value);
        foreach($value as $k=>$v) {
            if (!is_numeric($v)) $v = '';
            else {
                $v = number_format($v,$decimal,$ds,$ts);
                if ($mf) $v = money_format($mf, $v);   // @todo: Test money_format;
                else $v = $cl=='rt' ? $v.$cs : $cs.$v;
            }
            $value[$k] = $v;
        }
        return $isa ? $value : $value[0];
    }

    /**
     * Returns formatted number value based on locale settings
     * @param string $key Key name or input value (direct input must be enabled)
     * @param int $decimal Optional. Total number of decimal places to return
     * @return string If input is an array then an array of formatted numeric values is returned
     */
    public function formatNumber($key,$decimal = null) {
        $ds = Raxan::locale('decimal.separator');
        $ts = Raxan::locale('thousand.separator');
        $v = $this->value($key);
        if (!is_array($v)) $v = is_numeric($v) ? number_format($v,$decimal,$ds,$ts) : '';
        else foreach ($v as $k=>$b){
            $v[$k] = is_numeric($b) ? number_format($b,$decimal,$ds,$ts) : '';
        }
        return $v;
    }

    /**
     * Sanitized html by removing javascript tags and inline events
     * @param string $key Key name or input value (direct input must be enabled)
     * @param string $allowable Optional. Allowable html tags. Example <p><a>
     * @param string $allowStyle Optional. Allow css styles inside html.  Defaults to true
     * @return string If input is an array then an array of html values is returned
     */
    public function htmlVal($key,$allowable = null,$allowStyle = true) {
        $value = $this->value($key);
        $isa = is_array($value);
        if (!$isa) $value = array($value);
        foreach($value as $k => $v) {       // support for array input
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
            $rx2 = '/on\w+\s*=\s*'.(!$allowStyle ? '|style\s*=\s*' : '').'/is';
            $rx3 = '/nXtra=(["\']).*?\1|javascript\:|\s*expression\s*.*\(.*[^\)]?\)/is';
            if (preg_match_all($rx1,$v,$m)) {
                $tags = preg_replace($rx2,'nXtra=',$m[0]); // nutralize inline scripts/styles
                $tags = preg_replace($rx3,'',$tags);
                $v = str_replace($m[0],$tags,$v);
            }
            $value[$k] = $v;
        }
        return $isa ? $value : $value[0];

    }

    /**
     * Converts input value/key to an integer value
     * @return int Returns interger if value is numeric or null if there was an error
     */
    public function intVal($key) {
        $v = $this->value($key);
        if (!is_array($v)) $v = is_numeric($v) ? (int)$v : null;
        else foreach ($v as $k=>$b){
            $v[$k] = is_numeric($b) ? (int)$b : null;
        }
        return $v;
    }

    /**
     * Returns true if the selected field contains a valid date
     * @param string $key Key name or input value (direct input must be enabled)
     * @param string $format Optional date input format. Accepts the same format as formatDate()
     * @return boolean
     */
    public function isDate($key,$format = null) {
        try {
            if ($format===null) return $this->timestampVal($key) > 0 ? true : false;
            else {
                $dt = trim($this->value($key));
                return $dt && strtolower($dt) === strtolower($this->formatDate($key,$format));
            }
        }
        catch(Exception $e) { return false; }
    }

    /**
     * Returns true if the selected field contains a valid email address
     * @return boolean
     */
    public function isEmail($key) {
        // Based on Regex by Geert De Deckere. http://pastie.textmate.org/159503
        $regex = '/^[-_a-z0-9\'+^~]++(?:\.[-_a-z0-9\'+^~]+)*+@'.
                 '(?:(?![-.])[-a-z0-9.]+(?<![-.])\.[a-z]{2,6}|\d{1,3}(?:\.\d{1,3}){3})(?::\d++)?$/iD';
        return preg_match($regex, $this->value($key));
    }

    /**
     * Returns true if the selected field is numeric
     * @param mixed $key Key field name or value
     * @param float $min Optional. Minimum value
     * @param float $max Optional. Maximum value
     * @return boolean
     */
    public function isNumeric($key, $min = null, $max = null) {
        $v = $this->value($key);
        $ok = is_numeric($v) && !(($min && $v<$min) || ($max && $v>$max));
        return $ok;
    }

    /**
     * Returns true if the selected field contains a valid url
     * @return boolean
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
     * @return int
     */
    public function length($key) {
        return strlen($this->value($key));
    }

    /**
     * Returns matched characters
     * @param string $key Key name or input value (direct input must be enabled)
     * @param string $pattern Optional Regex pattern. Defaults to /[a-zA-Z0-9-.]/
     * @return string
     */
    public function matchVal($key,$pattern = '/[a-zA-Z0-9-.]/') {
        $v = $this->value($key);
        if (!is_array($v)) $v = (preg_match($pattern, $v, $m)) ? $m[0] : '';
        else foreach ($v as $k=>$b){
            $v[$k] = (preg_match($pattern, $b, $m)) ? $m[0] : '';
        }
        return $v;
    }
    
    /**
     * Sets the array source for the sanitizer
     * @return RaxanDataSanitizer
     */
    public function setDataArray($array) {
        $this->_data = is_array($array) ? $array : $_POST;
        return $this;
    }

    /**
     * Remove html tags from input values
     * @param string $key Key name or input value (direct input must be enabled)
     * @param int $maxlength Optional. Length of text value to be returned
     * @return string
     */
    public function textVal($key,$maxlength = null) {
        $v = $this->value($key);
        if (!is_array($v)) {
            $v = strip_tags($v);
            $v = ($maxlength!==null && is_numeric($maxlength)) ? substr($v,0,$maxlength) : $v;
        }
        else foreach ($v as $k=>$b){
            $b = strip_tags($b);
            $v[$k] = ($maxlength!==null && is_numeric($maxlength)) ? substr($b,0,$maxlength) : $b;
        }
        return $v;
    }

    /**
     * Converts input value/key to a valid timestamp
     * @return int Returns timestamp if value is a valid datetime string or null if there was an error
     */
    public function timestampVal($key) {
        if (!isset($this->_date)) $this->_date = Raxan::CDate();
        $v = $this->value($key);
        if (!is_array($v)) {
            try { $v = $v ? $this->_date->getTimestamp($v) : null; }
            catch( Exception $e ) { $v = null; }
        }
        else foreach ($v as $k=>$b){
            try { $b = $b ? $this->_date->getTimestamp($b) : null; }
            catch( Exception $e ) { $b = null; }
            $v[$k] = $b;
        }
        return $v;
    }
    
    /**
     * Returns sanitized url for the selected field
     * @param string $key Key name or input value (direct input must be enabled)
     * @param boolean $encoded Optional. Encode url string. Defaults to false
     * @return string
     */
    public function urlVal($key, $encoded = false) {
        $v = $this->value($key);
        if (!is_array($v)) {
            $v = trim(str_replace(self::$badCharacters,'',strip_tags($v)));
            $v = $encoded ?  url_encode($v) : $v;
        }
        else foreach ($v as $k=>$b){
            $b = trim(str_replace(self::$badCharacters,'',strip_tags($b)));
            $v[$k] = $encoded ?  url_encode($b) : $b;
        }
        return $v;
    }

    /**
     * Returns a value  based on the specified key
     * @return mixed
     */
    public function value($key) {
        if ($this->_directInp) return $key;
        else return isset($this->_data[$key]) ? $this->_data[$key] : null;
    }


    // Static Functions
    // -----------------------

    /**
     * Adds a custom data validator using regex patterns or callback function
     * @param string $key Key name or input value (direct input must be enabled)
     * @param mixed $pattern Optional. Regex pattern or a callback function
     * @return null
     */
    public static function addDataValidator($name,$pattern){
        $isRegEx = is_string($pattern) && preg_match('/^\W/',trim($pattern));
        self::$validators['is'.ucfirst($name)] = ($isRegEx ? '#':'').$pattern;
    }

}

?>