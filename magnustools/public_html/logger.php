<?PHP

/*
This script logs user events for tools.

PHP:
$logger->tfc->logToolUse ( 'toolname' , 'method' ) ;

or:
require_once ( "php/ToolforgeCommon.php" ) ;
$logger->tfc = new ToolforgeCommon('toolname') ;
$logger->tfc->logToolUse('') ;


JavaScript:
// Logging
$.getJSON ( 'https://tools.wmflabs.org/magnustools/logger.php?tool=flickr2commons&method=upload to commons&callback=?' , function(j){} ) ;

*/

set_time_limit ( 60 * 2 ) ; // Seconds
error_reporting(E_ERROR|E_CORE_ERROR|E_COMPILE_ERROR); // E_ALL|
ini_set('display_errors', 'On');

require_once ( '/data/project/magnustools/scripts/logger/Logger.php' ) ;

$logger = new Logger ;

$toolname = $logger->tfc->getRequest ( 'tool' , '' ) ;
$method = $logger->tfc->getRequest ( 'method' , '' ) ;

$tool_id = $logger->getOrCreateToolByNameAndMethod ( $toolname , $method ) ;
$logger->addToLog ( $tool_id ) ;
$logger->micDrop();

?>