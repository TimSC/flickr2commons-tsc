#!/usr/bin/php
<?php

require __DIR__ . '/vendor/autoload.php';

$classname_replacements = [
	'ToolforgeCommon' => 'Common',
	'WikidataItemList' => 'Wikidata' ,
	'WDI' => 'WikidataItem' ,
	'MW_OAuth' => 'OAuth'
] ;

$filename_replacements = [
	'oauth.php' => 'OAuth.php' ,
	'ToolforgeCommon.php' => 'Common.php'
] ;

$basedir = '/data/project/magnustools' ;
$dir_classes = "{$basedir}/classes" ;
$dir_inc = "{$basedir}/public_html/php" ;
$files = scandir ( $dir_inc ) ;

$file2class = [] ;
foreach ( $files AS $filename_inc ) {
	if ( in_array ( $filename_inc , ['common.php','itemdiff.php','wikiquery.php'] ) ) continue ;
	if ( !preg_match ( '|.php$|' , $filename_inc ) ) continue ;
	$filename_inc = "{$dir_inc}/{$filename_inc}" ;
	$php = file_get_contents($filename_inc) ;
	if ( !preg_match('|\bclass (\S+)|', $php , $m ) ) continue ;
	$class_name = $m[1] ;
	$file2class[$filename_inc] = $class_name ;
}

foreach ( $files AS $filename_inc ) {
	$filename_class = $filename_inc ;
	if ( isset($filename_replacements[$filename_inc]) ) $filename_class = $filename_replacements[$filename_inc] ;
	$filename_class = ucfirst ( $filename_class ) ;
	$filename_inc = "{$dir_inc}/{$filename_inc}" ;
	if ( !isset($file2class[$filename_inc]) ) continue ;
	$filename_class = "{$dir_classes}/{$filename_class}" ;
	print "{$filename_inc} => {$filename_class}\n" ;
	$php = file_get_contents($filename_inc) ;
	$php = preg_replace ( "|^<\?PHP\n|i" , "<?PHP\n\nnamespace Toolforge ;\n" , $php ) ; # \nrequire_once 'vendor/autoload.php';\n
	$php = preg_replace ( "|\bclass ToolforgeCommon\b|" , "class Common" , $php ) ;
	foreach ( $file2class AS $filename => $classname ) {
		#$php = preg_replace ( '|\bnew '.$classname.'\b|' , "new Toolforge\\{$classname}" , $php ) ;
	}
	foreach ( $classname_replacements as $from => $to ) {
		$php = preg_replace ( '~\b(new|class) *'.$from.'\b~' , "$1 {$to}" , $php ) ;
	}
	$php = preg_replace ( '|\bnew +Exception\b|' , 'new \\Exception' , $php ) ;
	$php = preg_replace ( '~\b(require|include)(_once){0,1}[ \(]*[\"\']'.$dir_inc.".*?;\s~" , '' , $php ) ;
	$php = preg_replace ( '~^(require|include)(_once){0,1}.*?WikidataItem\.php.*$~' , '' , $php ) ;
	file_put_contents($filename_class, $php) ;
	$check = `php -l '{$filename_class}'` ;
	if ( !preg_match ( '|^No syntax errors detected in |' , $check ) ) {
		die ( "Syntax error in {$filename_class}:\n{$check}\n" ) ;
	}
}

# Test new classes
error_reporting(E_ERROR|E_CORE_ERROR|E_COMPILE_ERROR);
$tfc = new Toolforge\Common ( 'magnustools' ) ;
$wd = new Toolforge\Wikidata ;
#$wdi = new Toolforge\WikidataItem ( 'https://www.wikidata.org/w/api.php' ) ;

# Silence session/header warnings
error_reporting(E_ERROR|E_CORE_ERROR|E_COMPILE_ERROR);
$widar = new Toolforge\Widar ( 'magnustools' ) ;

# Quick tests
$wd->loadItems ( ['Q12345'] ) ;
$i = $wd->getItem ( 'Q12345' ) ;
print 'Testing: ' . $i->getLabel() . "\n" ;

?>