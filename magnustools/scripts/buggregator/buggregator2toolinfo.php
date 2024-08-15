#!/usr/bin/env php
<?php

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');

require_once ( '/data/project/magnustools/scripts/buggregator/Buggregator.php' ) ;
require_once ( '/data/project/magnustools/public_html/php/oauth.php' ) ;

$outfile = "/data/project/magnustools/public_html/toolinfo_buggregator.json" ;

$buggregator = new Buggregator ;
$buggregator->toolhub_update() ;

$bad_tags = [
	"deactivated",
	"replaced",
	"no results",
	"inactive",
	"broken",
	"planned",
	"error",
	"fixme",
	"replaced",
];

$tool_repo = [] ;
$sql = "SELECT * FROM `git_repo`" ;
$result = $buggregator->getSQL ( $sql ) ;
while($o = $result->fetch_object()) $tool_repo[$o->tool_id] = $o->html_url ;

$keywords = [] ;
$sql = "SELECT * FROM `keywords`" ;
$result = $buggregator->getSQL ( $sql ) ;
while($o = $result->fetch_object()) {
	if ( !isset($keywords[$o->tool_id]) ) $keywords[$o->tool_id] = [] ;
	$keywords[$o->tool_id][] = $o->keyword ;
}


$log = [ "whole_tool"=>0 , "index"=>0 , "no_url"=>0 , "has_toolhub"=>0 , "has_bad_tag"=>0 , "no_desc"=>0 , "added2json"=>0 ] ;
$names = [] ;
$tools = [] ;
$sql = "SELECT * FROM `tool` WHERE NOT EXISTS (SELECT * FROM tool_kv WHERE tool_id=tool.id AND `key`='status')" ;
$result = $buggregator->getSQL ( $sql ) ;
while($o = $result->fetch_object()) {
	if ( $o->name == "WHOLE TOOL" ) { $log['whole_tool']++; continue ; }
	if ( $o->name == "index" ) { $log['index']++; continue ; }
	if ( $o->url == '' ) { $log['no_url']++; continue ; } # Nothing to point to
	if ( $o->toolhub!='' ) { $log['has_toolhub']++; continue ; } # Handled elsewhere
	$skip = false ;
	foreach ( $bad_tags AS $tag ) {
		if ( !stristr($o->note, $tag) ) continue ;
		$skip = true ;
		break ;
	}
	if ( $skip ) { $log['has_bad_tag']++; print "NEEDS tool_kv STATUS: {$o->name}\n" ; continue ; }
	$name = strtolower("mm_{$o->name}") ;
	$name = preg_replace("|[^a-zA-Z0-9_-]|","",$name) ;
	if ( in_array($name, $names) ) $name .= "_{$o->subdomain}" ;
	if ( in_array($name, $names) ) print "DUPLICATE NAME: {$name}\n" ;
	$names[] = $name ;
	$tool = (object) [
		"name"=>$name,
		"title"=>str_replace('_',' ',$o->name),
		"description"=>$o->description,
		"url"=>$o->url,
		"keywords"=>[],
		"author"=>"Magnus Manske",
		"repository"=>"",
	] ;
	if ( $tool->description=='' ) $tool->description = $o->note ;
	if ( $tool->description=='' ) { $log['no_desc']++; continue ; } ; # Do not add if description is empty
	if ( isset($keywords[$o->id]) ) {
		foreach ( $keywords[$o->id] AS $keyword ) $tool->keywords[] = $keyword ;
	}
	if ( stristr($o->note, "javascript") ) $tool->keywords[] = "javascript" ;
	foreach ( ["wikipedia","wikisource","wikibooks","wikidata","commons"] as $key ) {
		if ( stristr($o->note, $key) ) $tool->keywords[] = $key ;
		if ( stristr($o->name, $key) ) $tool->keywords[] = $key ;
		if ( stristr($o->subdomain, $key) ) $tool->keywords[] = $key ;
	}
	$tool->keywords = array_unique($tool->keywords) ;
	$tool->keywords = implode(", ",$tool->keywords) ;
	if ( isset($tool_repo[$o->id]) ) $tool->repository = $tool_repo[$o->id] ;
	if ( $o->toolhub!='' ) $tool->name = $o->toolhub ;
	$tools[] = $tool ;
	$log['added2json']++;
}

$j = json_encode($tools,JSON_PRETTY_PRINT);
file_put_contents($outfile, $j);
print_r($log);
