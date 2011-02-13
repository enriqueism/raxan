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

// Session settings
$config['session.name']    = 'XPDI1000SE';  // change for each NEW application to prevent PHP sessions from being shared across applications. session name must be short and contains only alphanumeric characters
$config['session.timeout'] = '30';          // in minutes
$config['session.data.storage'] = 'RaxanSessionStorage'; // default session data storage class
// No id value will be to the above session storage class.  The class will have to generate unique ids for each user

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

// cache path. defaults to {raxan path}/cache/
$config['cache.path']   = '';

// plugins path. defaults to {raxan path}/plugins/
$config['plugins.path'] = '';

// widgts path. defaults to {raxan path}/ui/widgets/
$config['widgets.path'] = '';

// Path to error pages. eg. views/404.html
// To display a custom message, add the {message} placeholder inside the html file
$config['error.400'] = '';
$config['error.401'] = '';
$config['error.403'] = '';
$config['error.404'] = '';

// Raxan Web Page default settings
$config['page.localizeOnResponse'] = false;         // loacalize  web page content based on the langid attribute
$config['page.initStartupScript'] = false;          // loads the raxan startup.js script
$config['page.resetDataOnFirstLoad'] = true;        // reset page data on first load
$config['page.preserveFormContent'] = false;        // preserve form content during post back
$config['page.disableInlineEvents'] = false;        // disables the processing of inline events
$config['page.masterTemplate'] = '';                // page master template -  html source or file name
$config['page.serializeOnPostBack'] = '';           // default selector value for matched elements to serialize and postback. e.g. form
$config['page.degradable'] = false;                 // enable accessible mode for links, forms and submit buttons when binding to an event
$config['page.showRenderTime'] = false;
$config['page.data.storage'] = 'RaxanWebPageStorage';  // default page data storage class
// The page class will passed the shared store name or id to the staorage class.

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


// Auto startup setting
$config['autostart'] = '';  // name of page class to be initialize on startup

// Preload Plugins and UI Widgets
$config['preload.plugins'] = '';    // comma separated list of plugins to be loaded from plugins.path -  e.g. plugin1,plugin2,plugin3.php
$config['preload.widgets'] = '';    // comma separated list of UI widgets  to be loaded from widgets.path - e.g. widget1,widget2,widget3.php


?>