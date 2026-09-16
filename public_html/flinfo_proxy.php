<?php

$id = $_GET['id'];
$raw = $_GET['raw'];
$repo = $_GET['repo'];
$categories = $_GET['categories'];
$format = $_GET['format'];

$url = 'https://wikipedia.ramselehof.de/flinfo.php?' . http_build_query([
        'id' => $id,
        'raw' => $raw,
        'repo' => $repo,
        'categories' => $categories,
        'format' => $format
    ]);

$retries = [] ;
$output = false ;
$attempts_left = 3 ;
while ( $attempts_left > 0 ) {
	$attempts_left-- ;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$output = curl_exec($ch);
	$http_code = curl_getinfo ( $ch , CURLINFO_HTTP_CODE ) ;
	$curl_error = curl_error ( $ch ) ;
	curl_close($ch) ;
	if ( $output !== false && $output !== '' && $http_code == 200 ) break ;
	$reason = $curl_error != '' ? $curl_error : "HTTP {$http_code}" ;
	if ( $attempts_left > 0 ) {
		$retries[] = "Flinfo request failed ({$reason}), retrying in 10s" ;
		sleep ( 10 ) ;
	} else {
		$retries[] = "Flinfo request failed ({$reason}), giving up" ;
	}
	$output = false ;
}

header('Content-type: application/json; charset=UTF-8');

if ( $output === false ) {
	echo json_encode ( [ 'wiki' => [ 'status' => 1 ] , 'retries' => $retries ] ) ;
	exit ;
}

if ( count ( $retries ) > 0 ) {
	$decoded = json_decode ( $output , true ) ;
	if ( is_array ( $decoded ) ) {
		$decoded['retries'] = $retries ;
		$output = json_encode ( $decoded ) ;
	}
}

echo $output;
