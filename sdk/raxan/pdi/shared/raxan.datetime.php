<?php
/**
 *  RaxanDateTime Class for handling dates beyond the 1970 and 2038
 *  Requires ADODB_Date library file to be in the same path as this library
 *  Copyright (c) 2008-2010 Raymond Irving
 *
 *  This Class Library is distributed under the terms of the GNU GPL and MIT license with
 *  the exception of the ADOdb Date Library which is distributed under it's respective license(s). 
 *  See the adodb-time.inc.php file for furthor information.
 * @package Raxan
 *
 */
class RaxanDateTime {

    public static $months;  // replace with locale month names
    protected static $isDateTimeSupported;

    protected $_dtstr = null;
    
    /**
     * Date Class Constructor
     * @param $str (Optional) String containing a valid date in the formats: 
     * Date: dd mmm yyyy,<br />mmm dd yyyy,<br /> mm/dd/yyy,<br /> yyyy/mm/dd,<br /> dd/mm/yyyy.
     * Also supports the delimitors "." and "-". Example: mm-dd-yyyy or mm.dd.yyyy
     * Time: hh:mm:ss - Supports the time format that is supported by PHP     */
    public function __construct($str=''){

        if (!isset(self::$isDateTimeSupported)) {
            self::$isDateTimeSupported = function_exists('date_create');
        }
        
        if (!self::$isDateTimeSupported) {
            // make sure we have the adodb date libray loaded
            if (!function_exists('adodb_mktime')) {
                $datelib = dirname(__FILE__).'/adodb-time.inc.php';
                if (file_exists($datelib)) {
                    include_once ($datelib);
                }
                if (!function_exists('adodb_mktime'))
                    die ('Date Class: Unable to load the ADOdb Date Library.');
            }
        }

        $this->setDate($str);

    }

    /**
     * Sets the Date/Time for the Date object
     * @param $str String containing a valid date
     * @return void
     */
    public function setDate($strtime) {
        $this->_dtstr = $strtime;
    }   
    

    /**
     * Returns an ADODB Date timestamp
     * @return int
     */
    public function getTimestamp($strtime = '') {
        $strtime = $strtime!=='' ? $strtime : $this->_dtstr;
        if (self::$isDateTimeSupported) {
            $dt = new DateTime(is_numeric($strtime) ? "@$strtime" : $strtime);
            $v = $dt->format('U');
        }
        else {
            if (is_numeric($strtime)) return $strtime;
            else {
                $d = ($strtime && $strtime!='now') ?
                    $this->parse($strtime) : getdate();
                $v = $d ? adodb_mktime(
                    $d['hours'],
                    $d['minutes'],
                    $d['seconds'],
                    $d['mon'],$d['mday'],$d['year']) : false;                
            }
        }
        return !$v || ($v == $strtime) ? false: $v;
    }
    
    /**
     * Format and returns a date string. This function used the PHP date() format.
     * @return String
     * @param $fmt String
     * @param boolean $noTrans Disable translation of date string
     * @param $strtime Mixed [optional] DateTime String or ADODB Date TimeStamp
     */
    public function format($fmt, $strtime = '', $noTrans = false){
        $strtime = $strtime ? $strtime : $this->_dtstr;

        if (self::$isDateTimeSupported) {
            $strtime = is_numeric($strtime) ? "@$strtime" : $strtime;
            $dt = new DateTime($strtime);
            $dt = $dt->format($fmt);
        }
        else {
            $ts = ($strtime && is_numeric($strtime)) ? $strtime : $this->getTimestamp($strtime);
            if (!$ts) return false;
            else $dt = adodb_date($fmt,$ts);
        }

        if (!$noTrans && preg_match('/[a-z]/',$dt)) {
            // translate month and day names based on locale
            $a = Raxan::locale('dt._eng_names');
            $b = Raxan::locale('dt._locale_names');
            $keys = array('{1}','{2}','{3}','{4}','{5}','{6}','{7}','{8}','{9}','{10}','{11}',
                '{12}','{13}','{14}','{15}','{16}','{17}','{18}','{19}','{20}','{21}','{22}','{23}',
                '{24}','{25}','{26}','{27}','{28}','{29}','{30}','{31}','{32}','{33}','{34}','{35}',
                '{36}','{37}','{38}');
            if ($a && $b) {
                $dt = str_ireplace($a,$keys,$dt);   // use keys to avoid left to right replacement issues
                $dt = str_ireplace($keys,$b,$dt);   // with abbreviated names such as jan, may, oct, etc.
            }
        }
        
        return $dt;

    }
    
    /**
     * Parses a date string and returns an array containing the date parts otherwise false
     * It's works great with date values returned from MSSQL, MySQL and others.
     * @return Array Returns an array that contains the date parts: year, month, mday, minutes,hours and seconds
     * @param $str String Supported Date/Time string format
     */
    public function parse($str) {
        $delim = '';
        $dpart = array('minutes'=>'','hour'=>'','seconds'=>'');
        
        $dt = preg_replace('/(\s)+/',' ',$str); // remove extra white spaces
        
        if (strpos($dt,'-') > 0) $delim = '-';
        if (strpos($dt,'/') > 0) $delim = '/';
        if (!$delim && ($d = strpos($dt,'.'))>0) {
            $c = strpos($dt,':');
            if (!$c || ($c > $d)) $delim = '.';
        }
        
        if ($delim=='-' || $delim=='/' || $delim=='.') {
            @list($date,$time) = explode(' ',$dt);
            $date = explode($delim,$date);          
            $date[] = $time;
        }
        else {
            $date = explode(' ',$dt,4);
        }
        
        foreach ($date as $i => $v) $date[$i] = trim(trim($v,','));
        
        @list($d1,$d2,$d3,$time) = $date;

        if (!self::$months)
            self::$months = Raxan::locale('months.short');
        $months = self::$months;

        // get year
        if ($d1 > 1000) { $dpart['year'] = $d1; unset($date[0]); }
        if ($d3 > 1000) { $dpart['year'] = $d3; unset($date[2]); }
        if (!isset($dpart['year'])) $dpart['year'] =  date('Y');
        
        // get month - defaults to mm-dd-yyyy 
        if (!is_numeric($d1)) for ($i=0; $i<12; $i++) {                     // mmm dd yyyy
            if (stristr($d1,$months[$i])!=false) {
                $dpart['mon'] = $i+1;
                unset($date[0]);
                break;
            }
        }
        else if (!is_numeric($d2)) for($i=0; $i<12; $i++) {
            if (stristr($d2,$months[$i])!=false) {
                $dpart['mon'] = $i+1;
                unset($date[1]);
                break;
            }
        }
        else {
            if ($d2 <= 12 && $d1 >= 1500) { $dpart['mon'] = $d2; unset($date[1]); } // yyyy-mm-dd
            if ($d1 <= 12 && $d3 >= 1500) { $dpart['mon'] = $d1; unset($date[0]); } // mm-dd-yyyy
            else if ($d1 > 12 && $d3 >= 1500) { $dpart['mon'] = $d2; unset($date[1]); } // dd-mm-yyyy     
        }
        
        // get day
        unset($date[3]);
        $dpart['mday'] = implode('',$date);
        if (!is_numeric($dpart['mday'])||$dpart['mday']> 31) return false;
        
        // get time info. use 1 jan 2008 as a starting date
        $t = strtotime('1-jan-2008 '.$time);
        if($t) {
            $t = getdate($t);           
            $dpart['hours'] = $t['hours'];
            $dpart['minutes'] = $t['minutes'];
            $dpart['seconds'] = $t['seconds'];
        }

        return $dpart;
        
    }

    public function __toString() {
        return $this->format('Y-m-d');
    }
}
    
?>