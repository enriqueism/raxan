<?php
/**
 * Raxan PDI Gateway file
 * Coptyright Raymmond Irving 2008-2009.
 * License: GPL, MIT
 * @package Raxan
 */

// replace \ in path
$__raxanGTWPth = str_replace('\\','/', dirname(__FILE__)).'/';

// include main files
include_once($__raxanGTWPth.'shared/rich.api.php');
include_once($__raxanGTWPth.'shared/rich.webpage.php');

// set base path
RichAPI::setBasePath($__raxanGTWPth);


?>