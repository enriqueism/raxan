<?php
/**
 * Raxan Gateway file
 * Copyright (c) 2011 Raymond Irving (http://raxanpdi.com)
 * License: GPL, MIT
 * @package Raxan
 */

// replace \ in path
$__raxanGTWPth = str_replace('\\','/', dirname(__FILE__)).'/';

// include main files
include_once($__raxanGTWPth.'shared/raxan.php');
include_once($__raxanGTWPth.'shared/raxan.element.php');       // element
include_once($__raxanGTWPth.'shared/raxan.webpage.php');    // web page
include_once($__raxanGTWPth.'shared/raxan.ui.php');

// set pdi base path
Raxan::setBasePath($__raxanGTWPth);


?>