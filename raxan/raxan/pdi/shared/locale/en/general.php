<?php
/**
 * General locale settings - English
 * @package Raxan
 */

// site & language info
$locale['php.locale']           = 'en_EN';  // see setlocale()
$locale['lang.dir']             = 'ltr';
$locale['site.title']           = 'My Website';

// date & time (strtime format)
$locale['date.short']           = 'Y-m-d';
$locale['date.long']            = 'l, F d, Y';
$locale['date.time']            = 'h:n AM';

// numbers & currency
$locale['decimal.separator']    = '.';
$locale['thousand.separator']   = ',';
$locale['currency.symbol']      = '$';
$locale['currency.location']    = 'lt';     // lt - left, rt - right
$locale['money.format']         = '';       // overrides above currency settings. See money_format()

$locale['days.short']           = array('Sun','Mon','Tue','Wed','Thu','Fri','Sat');
$locale['days.full']            = array('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday');
$locale['months.short']         = array('Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');
$locale['months.full']          = array('January','February','March','April','May','June','July','August','September','October','November','December');

// error messages
$locale['unauth_access']        = 'Unauthorized Access';
$locale['file_notfound']        = 'File Not Found';

// commonly used words
$locale['error']                = 'Error';
$locale['yes']                  = 'Yes';
$locale['no']                   = 'No';
$locale['cancel']               = 'Cancel';
$locale['save']                 = 'Save';
$locale['send']                 = 'Send';
$locale['submit']               = 'Submit';
$locale['delete']               = 'Delete';
$locale['close']                = 'Close';
$locale['next']                 = 'Next';
$locale['prev']                 = 'Previous';
$locale['page']                 = 'Page';
$locale['click']                = 'Click';
$locale['sort']                 = 'Sort';
$locale['drag']                 = 'Drag';
$locale['help']                 = 'Help';

?>