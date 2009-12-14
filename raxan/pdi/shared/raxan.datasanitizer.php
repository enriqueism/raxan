<?php
/**
 * Provides APIs to filter and sanitizer user inputs and file uploads
 * @package Raxan
 */
class RaxanDataSanitizer {
    /**
     *  @var $iDate RaxanDateTime */

    protected static $validators = array();
    protected static $badCharacters = array("\r","\n","\t","\x00","\x1a");

    protected $iData;
    protected $iDate;
    protected $charset;


    public function __construct($array=null,$charset = null) {
        $this->charset = $charset ? $charset : Raxan::config('site.charset');
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
     * Returns the sanitized text for the specified key. All html charcters will be removed.
     * @return String
     */
    public function __get($name) {
        return $this->text($name);
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
                $format = Raxan::locale('date.short');
                break;
            case 'long':
                $format = Raxan::locale('date.long');
                break;
        }

        if (!isset($this->iDate)) $this->iDate = Raxan::cDate();
        $v = $this->iDate->format($format,$this->value($key),$noTrans);
        return $v;
    }

    /**
     * Returns sanitized email address for the selected field
     * @return String
     */
    public function email($key) {
        return str_replace(self::$badCharacters,'',$this->text($key));
    }

    /**
     * Returns html escaped value for the selected field
     * @return String
     */
    public function escape($key) {
        return htmlspecialchars($this->value($key));
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
     * @return Array or NULL if error
     */
    public function fileImageSize($key) {
        $fl = isset($_FILES[$key]) ? $_FILES[$key] : null;
        $fl = $fl ? $fl['tmp_name'] : null;
        return Raxan::imageSize($fl);
    }

    /**
     * Resamples (convert/resize) the uploaded image. You can specify a new width, height and type
     * @return Boolean
     */
    public function fileImageResample($key,$w,$h,$type=null) {
        $fl = isset($_FILES[$key]) ? $_FILES[$key] : null;
        $fl  = $fl ? $fl['tmp_name'] : null;
        return Raxan::imageResample($fl, $w, $h,$type);
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
        $mf = Raxan::locale('money.format');
        if ($mf) return money_format($mf, $v);   // @todo: Test money_format;
        else {
            $cs = Raxan::locale('currency.symbol');
            $cl = Raxan::locale('currency.location');
            return $cl=='rt' ? $v.$cs : $cs.$v;
        }
    }

    /**
     * Returns formatted number value based on locale settings
     * @return String
     */
    public function number($key,$decimal = null) {
        $ds = Raxan::locale('decimal.separator');
        $ts = Raxan::locale('thousand.separator');
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
        if (!isset($this->iDate)) $this->iDate = Raxan::CDate();
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