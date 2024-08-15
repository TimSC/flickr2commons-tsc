<?php

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');

require_once ( 'php/ToolforgeCommon.php' );
$tfc = new ToolforgeCommon ( 'spacer' );

print $tfc->getCommonHeader ( "Spacer" ) ;


$id = preg_replace ( '|[<>]|' , '' , $tfc->getRequest ( 'id' , '' ) ) ;

if ( $id == '' ) {
?>

<form method='get' class='form-inline' style='display:inline'>
	ID: 
	<input type='text' name='id' />
	<input type='submit' class='btn btn-outline-primary' value='Get links' />
</form>

<?php
} else {

	$rows = explode ( "\n" , file_get_contents("https://de.wikipedia.org/wiki/Benutzer:Magnus_Manske/Spacer?action=raw") ) ;
	$name2url = [] ;
	foreach ( $rows AS $row ) {
		if ( preg_match('|^\*\s*\[(.+?) (.+)\]$|',$row,$m) ) {
			$name2url[$m[2]] = $m[1] ;
		}
	}



	print "<h1>{$id}</h1><ul style='font-size:14pt;'>" ;
	foreach ( $name2url AS $name => $url ) {
		$url = str_replace('%ID%',$id,$url) ;
		print "<li><a class='external' href='{$url}'>{$name}</a></li>" ;
	}
	print "</ul></div>" ;

	print "<p><small>You can change the configuration for this page <a href='https://de.wikipedia.org/wiki/Benutzer:Magnus_Manske/Spacer' class='wikipedia'>here</a>.</small></div>" ;

}

print $tfc->getCommonFooter() ;

?>