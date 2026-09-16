<?php

require_once ( 'php/common.php' ) ;

$action = get_request ( 'action' , '' ) ;

$out = ['status'=>'OK'] ;

function duplicate_checks_enabled () {
	$value = getenv ( 'F2C_ENABLE_DUPLICATE_CHECKS' ) ;
	if ( $value === false || trim ( $value ) == '' ) return true ;
	return !preg_match ( '/^(0|false|no|off)$/i' , trim ( $value ) ) ;
}

function upload_logging_enabled () {
	$value = getenv ( 'F2C_ENABLE_UPLOAD_LOGGING' ) ;
	if ( $value === false || trim ( $value ) == '' ) return false ;
	return (bool) preg_match ( '/^(1|true|yes|on)$/i' , trim ( $value ) ) ;
}

function urlPathBatchGenerator ( $data , $batch_size = 5000 ) {
	global $db ;
	$paths = [] ;
	$user_names = [] ;
	if ( isset($data) and isset($data->owners) ) {
		foreach ( $data->owners AS $nsid => $owner_data ) {
			if ( $nsid!=$owner_data[0] ) $user_names = $owner_data ;
			else $user_names = [$nsid] ;
		}
	}
	foreach ( $data->files AS $nsid => $file_ids ) {
		foreach ( $file_ids AS $file_id ) {

			foreach ( $user_names AS $user ) {
				foreach ( ['photo','photos'] AS $p1 ) {
					foreach ( ['','/'] AS $p2 ) {
						$paths[] = $db->real_escape_string ( "/$p1/$user/$file_id$p2" ) ;
						if ( count($paths) < $batch_size ) continue ;
						yield $paths ;
						$paths = [] ;
					}
				}
			}
		}
	}
	if ( count($paths)>0 ) yield $paths ;
	else yield from [] ;
}

if ( $action == 'check_flickr_files_in_commons' ) {

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

	$data = json_decode ( get_request ( 'data' , '{}' ) ) ;
	$out['data']['files'] = [] ;
	if ( !duplicate_checks_enabled() ) {
		$out['warning'] = 'Commons duplicate checks disabled by F2C_ENABLE_DUPLICATE_CHECKS' ;
	} else {
		$db = openDB ( 'commons' , 'wikimedia' ) ;

		$domains = [];
		foreach ( ['http','https'] AS $protocol ) {
			foreach ( ['','www.'] AS $subdomain ) {
				$domains[] = $db->real_escape_string ( "$protocol://com.flickr.$subdomain" ) ;
			}
		}

		foreach ( urlPathBatchGenerator($data) AS $paths ) {
			$sql = "SELECT DISTINCT page_title,el_to_path FROM page,externallinks WHERE page_id=el_from AND page_namespace=6 AND el_to_domain_index IN ('" . implode("','", $domains) . "') AND el_to_path IN ('" . implode("','", $paths) . "')" ;

			$result = getSQL ( $db , $sql ) ;
			while($o = $result->fetch_object()) {
				if ( !preg_match ( '/\/(\d+)\/{0,1}$/' , $o->el_to_path , $m ) ) continue ; // Huh?
				$out['data']['files'][$m[1]] = $o->page_title ;
			}
			#$out['sql'][] = $sql ; # Debugging output
		}
	}

} else if ( $action == 'get_flickr_key' ) {

	$max_photos = getenv('F2C_MAX_PHOTOS') ;
	if ( $max_photos === false || trim($max_photos) == '' ) $max_photos = 500 ;
	$out['data'] = [
		'flickr_key' => getenv('FLICKR_ACCESS_KEY') ,
		'max_photos' => $max_photos * 1 ,
		'enable_upload_logging' => upload_logging_enabled()
	] ;

} else if ( $action == 'check_existing_commons_filenames' ) {

	$filenames = json_decode ( get_request ( 'filenames' , '[]' ) ) ; // No "File:" prefix!
	if ( !is_array ( $filenames ) ) $filenames = [] ;
	$out['data']['files'] = [] ;
	$to_check = [] ;
	foreach ( $filenames AS $fn ) {
		$fn = str_replace ( ' ' , '_' , ucfirst ( trim ( $fn ) ) ) ;
		$to_check[] = $fn ;
		$out['data']['files'][$fn] = 0 ;
	}
	if ( !duplicate_checks_enabled() ) {
		$out['warning'] = 'Commons duplicate checks disabled by F2C_ENABLE_DUPLICATE_CHECKS' ;
	} else {
		$db = openDB ( 'commons' , 'wikimedia' ) ;
		$to_check = array_map ( function ( $fn ) use ( $db ) { return $db->real_escape_string ( $fn ) ; } , $to_check ) ;
		$sql = "SELECT DISTINCT page_title FROM page WHERE page_namespace=6 AND page_title IN ('" . implode("','",$to_check) . "')" ;
		$out['sql'] = $sql ;
		$result = getSQL ( $db , $sql ) ;
		while($o = $result->fetch_object()) {
			$out['data']['files'][$o->page_title] = 1 ;
		}
	}

} else {

	require_once 'php/Widar.php' ;
	$widar = new \Widar ( 'flickr2commons-ng' ) ;
	$host = $_SERVER['HTTP_HOST'] ?? 'flickr2commons-ng.toolforge.org' ;
	$is_localhost = preg_match ( '/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/' , $host ) ;
	$base_url = $is_localhost ? "http://$host/" : 'https://flickr2commons-ng.toolforge.org/' ;
	$widar->attempt_verification_auto_forward ( $base_url ) ;
	if ( !$is_localhost ) $widar->authorization_callback = $base_url . 'api.php' ;
	if ( $widar->render_reponse ( true ) ) exit ( 0 ) ;
	$out['status'] = "Unknown action '{$action}'" ;
}

header('Content-type: application/json; charset=UTF-8');
print json_encode ( $out ) ;
myflush();
ob_end_flush() ;
