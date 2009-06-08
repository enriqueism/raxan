<?php
/**
 * Raxan PDI Gateway file
 * Coptyright Raymmond Irving 2008-2009.
 * License: GPL, MIT
 * @package Raxan
 */

// replace \ in path
$pth = str_replace('\\','/', dirname(__FILE__)).'/';

// include main files
include_once($pth.'shared/rich.api.php');
include_once($pth.'shared/rich.webpage.php');

// set base path
RichAPI::setBasePath($pth);



?>