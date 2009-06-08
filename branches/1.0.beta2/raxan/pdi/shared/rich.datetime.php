<?php
/**
 *  RichDateTime Class for handling dates beyond the 1970 and 2038
 *  Requires ADODB_Date library file to be in the same path as this library
 *  Written by Raymond Irving 2008
 *
 *  This Class Library is distributed under the terms of the GNU GPL and MIT license with
 *  the exception of the ADOdb Date Library which is distributed under it's respective license(s). 
 *  See the adodb-time.inc.php file for furthor information.
 * @package Raxan
 *
 */
class RichDateTime {
    
    public $_timestamp = null;

    // replace with locale month names
    public static $months;
    
    /**
     * Date Class Constructor
     * @param $str (Optional) String containing a valid date in the formats: 
     * Date: dd mmm yyyy,<br />mmm dd yyyy,<br /> mm/dd/yyy,<br /> yyyy/mm/dd,<br /> dd/mm/yyyy.
     * Also supports the delimitors "." and "-". Example: mm-dd-yyyy or mm.dd.yyyy
     * Time: hh:mm:ss - Supports the time format that is supported by PHP     */
    public function __construct($str=''){
        // make sure we have the adodb date libray loaded
        if (!function_exists('adodb_mktime')) {
            $datelib = dirname(__FILE__).'/adodb-time.inc.php';
            if (file_exists($datelib)) {
                include_once ($datelib);
            }           
            if (!function_exists('adodb_mktime')) 
                die ('Date Class: Unable to load the ADOdb Date Library.');
        }

        $this->setDate($str);               
    }

    /**
     * Sets the Date/Time for the Date object
     * @return void
     * @param $str String containing a valid date */
    public function setDate($str) {
        if (is_numeric($str)) $this->_timestamp = $str;
        else {
            $this->_timestamp = $this->_makeTimestamp($str);        
        }
    }   
    
    // internal function
    protected function _makeTimestamp($str){
        $d = ($str && $str!='now') ? $this->parse($str) : getdate();
        return $d ? adodb_mktime(
            $d['hours'],
            $d['minutes'],
            $d['seconds'],
            $d['mon'],$d['mday'],$d['year']) : false;
    }
    
    /**
     * Returns an ADODB Date timestamp
     * @return int
     */
    public function getTimestamp($date = '') {
        // @todo: optimize getTimeStamp
        return ($date) ? $this->_makeTimestamp($date) : $this->_timestamp;
    }
    
    /**
     * Format and returns a date string. This function used the PHP date() format.
     * @return String
     * @param $fmt String
     * @param $dtTime Mixed [optional] DateTime String or ADODB Date TimeStamp  
     */
    public function format($fmt, $dtTime = '', $noTrans = false){
        $ts = ($dtTime && is_numeric($dtTime)) ? $dtTime : $this->getTimestamp($dtTime);
        if (!$ts) return false;
        else {
            $dt = adodb_date($fmt,$ts);
            if (!$noTrans && preg_match('/[a-z]/',$dt)) {
                // translate month and day names based on locale
                $a = RichAPI::locale('dt._eng_names');
                $b = RichAPI::locale('dt._locale_names');
                if ($a && $b) $dt = str_ireplace($a,$b,$dt);
            }
            return $dt;
        }
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
            self::$months = RichAPI::locale('months.short');
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