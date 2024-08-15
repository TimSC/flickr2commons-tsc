#!/usr/bin/env php
<?php

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');

require "vendor/autoload.php";
require_once ( '/data/project/magnustools/scripts/buggregator/Buggregator.php' ) ;
#require_once ( '/data/project/magnustools/public_html/php/oauth.php' ) ;

use Abraham\TwitterOAuth\TwitterOAuth;

$buggregator = new Buggregator ;

?>