#!/usr/bin/php
<?PHP

require_once ( '/data/project/magnustools/scripts/logger/Logger.php' ) ;

$logger = new Logger ;

if ( false ) {
	# Merge methods with funny names
	$sql = "SELECT * FROM tools" ;
	$result = $logger->tfc->getSQL ( $logger->db , $sql ) ;
	while($o = $result->fetch_object()) {
		$name = "{$o->name}" ;
		$method = "{$o->method}" ;
		$logger->sanitizeToolAndMethodName ( $name , $method ) ;
		if ( $name==$o->name and $method==$o->method ) continue ;
		$logger->mergeMethods ( $o->name , $o->method , $method ) ;
	}

} else {

	$tool_id = $logger->getOrCreateToolByNameAndMethod('testing','misc requests') ;
	print "Tool {$tool_id}\n" ;
	$logger->addToLog ( $tool_id ) ;
	$logger->addToLog ( $tool_id ) ;
	$uses = $logger->getAllUses ( $tool_id ) ;
	print "Uses {$uses}, expecting 2\n" ;
	$logger->deleteToolById($tool_id) ;
	$logger->micDrop();
}

?>