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
$locale['view_not_found']       = 'Page View or File Not Found';

// client-side error message
$locale['pdi-ajax-err-msg']     = 'Error while connecting to server. Please try again or report the matter to the administrator. See the Error Console for more information.';

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
$locale['first']                = 'First';
$locale['last']                 = 'Last';

// validation messages
$locale['valueMissing'] = 'Value missing';
$locale['patternMismatch'] = 'Pattern mismatched. The value does not match the required syntax';
$locale['tooLong'] = 'The value entered exceeds the allowed input length';
$locale['typeMismatch'] = 'Type mismatched. The value entered is not in the correct syntax';
$locale['rangeUnderflow'] = 'The value entered is less than the minmum value allowed';
$locale['rangeOverflow'] = 'The value entered is greater than the maximum value allowed';
$locale['customError'] = 'The value entered is not valid'; // default custom message

?>