<?php
/**
 * Sample Configuration file
 * @package Raxan
 * 
 * Note: Paths and URLs must have trailing /
 *       For example: myviews/
 */
 

/** Required settings ********************/

// site locale and encoding
$config['site.charset'] = 'UTF-8';
$config['site.locale']  = 'en-us';  // determines regional & local settings
$config['site.lang']    = 'en';     // language used by labels
$config['site.timezone']= '';       // sets the timezone to use by the framework. e.g. America/Toronto
// Note: Setting the timezone will affect all date/time functions.
//       For a list of supported timeszones visit http://www.php.net/timezones

/** Optional settings ********************/

// site contact
$config['site.email']   = '';
$config['site.phone']   = '';
// note: site title can be found in locale settings

// site or application path and url
$config['site.url']     = '';
$config['site.path']    = '';

// raxan folder path and url. Defaults to {base path}/../ 
$config['raxan.url']    = '';
$config['raxan.path']   = '';   

// views path
// folder were html views are stored
$config['views.path']   = '';

// locale path. defaults to {base path}/shared/locale/
$config['locale.path']  = '';

// cache path. defaults to {base path}/cache/
$config['cache.path']   = '';

// Path to error pages. eg. views/404.html
// To display a custom message, add the {message} placeholder to the html file
$config['error.400'] = '';
$config['error.401'] = '';
$config['error.403'] = '';
$config['error.404'] = '';

// Session settings
$config['session.name']    = 'XPDI1000SE';
$config['session.timeout'] = '30';      // in minutes
$config['session.handler'] = 'default'; // values: default, database
// When using the database option make sure that database settings are configured
// @todo: Add session database handler and timeout

// logging & debugging settings
$config['debug']        = false;
$config['debug.log']    = false;   // include log entries in debug ouput when logging is enabled
$config['debug.output'] = 'alert'; // embedded, alert, popup, console (for use with firebug,etc)
$config['log.enable']   = false;
$config['log.file']     = 'PHP'; // if set to PHP the system log entries using php's error logging
// Note: Check the PHP manual for more information on how to activare PHP error logging fetaures

// PDO Database connectors
$config['db.default'] = array(
    'dsn'       => 'mysql: host=localhost; dbname=mysql',
    'user'      => '',
    'password'  => '',
    'attribs'   => ''
);
// For more PDO DSN information visit http://www.php.net/manual/en/pdo.drivers.php


?>