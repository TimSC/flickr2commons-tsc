#!/usr/bin/env php
<?php

require_once ( 'Buggregator.php' ) ;

$buggregator = new Buggregator ;

if ( $argv[1] == 'update' ) $buggregator->update() ;
else if ( $argv[1] == 'maintenance' ) $buggregator->maintenance() ;
else if ( $argv[1] == 'toolhub' ) $buggregator->toolhub_update() ;
else if ( $argv[1] == 'issue_status' ) {
	$issue = Issue::new_from_id($argv[2],$buggregator) ;
	$issue->set_status ( strtoupper($argv[3]) ) ;
	$issue->update_in_database ( $buggregator , ['status'] ) ;
	$buggregator->touch_issue($issue->id());
}
else if ( $argv[1] == 'webstatus' ) $buggregator->update_http_status();
#else if ( $argv[1] == 'test' ) $buggregator->check_wikidata_for_tool_items() ;
else print "Nothing to do!\n" ;

?>